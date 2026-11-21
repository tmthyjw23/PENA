<?php
declare(strict_types=1);
namespace Pena\Service;

use Pena\Support\Db;
use RuntimeException;
use Throwable;

final class ExportService
{
    public function __construct(private readonly Db $db, private readonly string $directory,
        private readonly ?PdfConverter $converter = null) {}

    /** Returns an immutable export; access is checked before every cached or new download. */
    public function create(int $letterId, int $workspaceId, int $userId, string $format): array
    {
        if (!in_array($format, ['docx', 'pdf'], true)) throw new PenaException('Format unduhan tidak didukung.');
        if (!$this->db->one('SELECT user_id FROM organization_members WHERE organization_id=? AND user_id=?', [$workspaceId,$userId])) {
            throw new PenaException('Anda bukan anggota ruang kerja ini.');
        }
        $page=(new LetterQuery($this->db))->detail($letterId,$workspaceId);
        if (!$page || $page['letter']['status'] !== 'approved' || empty($page['version']['snapshot'])) {
            throw new PenaException('Surat harus disetujui sebelum diunduh.');
        }
        if (!is_dir($this->directory) && !mkdir($this->directory,0700,true) && !is_dir($this->directory)) {
            throw new RuntimeException('Penyimpanan ekspor tidak tersedia.');
        }
        $versionId=(int)$page['version']['id'];
        $preview=$page['preview'];
        $renderPrefix='v'.$versionId.'-'.SignatureService::previewHash($preview).'-';
        // Same-version exports serialize even across PHP workers; never serve a partial file.
        $lock=fopen($this->directory.'/version-'.$versionId.'.lock','c');
        if (!$lock || !flock($lock,LOCK_EX)) throw new RuntimeException('Ekspor sedang tidak tersedia.');
        $rowId=null; $temporary=null;
        try {
            $cached=$this->db->one("SELECT * FROM letter_exports WHERE version_id=? AND format=? AND purpose='final' AND status='done' ORDER BY id DESC LIMIT 1",[$versionId,$format]);
            if ($cached && str_starts_with($cached['file_path'],$renderPrefix) && is_file($this->directory.'/'.$cached['file_path'])
                && hash_equals($cached['sha256'],hash_file('sha256',$this->directory.'/'.$cached['file_path']))) {
                return ['path'=>$this->directory.'/'.$cached['file_path'],'name'=>'surat-'.$letterId.'-v'.$page['version']['version_no'].'.'.$format];
            }
            $rowId=$this->db->insert("INSERT INTO letter_exports (version_id,format,purpose,status) VALUES (?,?,'final','pending')",[$versionId,$format]);
            $filename=$renderPrefix.bin2hex(random_bytes(12)).'.'.$format;
            $target=$this->directory.'/'.$filename;
            $temporary=$this->directory.'/'.bin2hex(random_bytes(12)).'.docx';
            $preview=$page['preview'];
            $metadata=$preview['metadata'];
            if($preview['date']!=='')$metadata['Tanggal']=$preview['date'];
            $blocks=$preview['blocks'];
            if ($preview['recipient']) array_unshift($blocks,['type'=>'paragraph','text'=>implode("\n",$preview['recipient'])]);
            (new WordDocumentService())->save($temporary,$preview['letterhead'],$metadata,$blocks);
            if ($format==='pdf') {
                $converter=$this->converter ?? (PHP_OS_FAMILY==='Windows' ? new WordPdfConverter() : new LibreOfficePdfConverter());
                $converter->convert($temporary,$target);
            } elseif (!rename($temporary,$target)) throw new RuntimeException('Gagal menyimpan dokumen Word.');
            if (!is_file($target) || filesize($target)===0) throw new RuntimeException('Ekspor tidak menghasilkan dokumen.');
            $this->db->run("UPDATE letter_exports SET status='done',file_path=?,sha256=?,error=NULL WHERE id=?",[$filename,hash_file('sha256',$target),$rowId]);
            return ['path'=>$target,'name'=>'surat-'.$letterId.'-v'.$page['version']['version_no'].'.'.$format];
        } catch (Throwable $error) {
            if ($rowId!==null) $this->db->run("UPDATE letter_exports SET status='failed',error=? WHERE id=?",['Ekspor gagal. Hubungi pengelola jika berulang.',$rowId]);
            error_log('PENA export failed: '.$error->getMessage());
            throw new PenaException('Dokumen belum berhasil dibuat. Persetujuan tetap tersimpan; silakan coba unduh kembali.');
        } finally {
            if ($temporary && is_file($temporary)) unlink($temporary);
            flock($lock,LOCK_UN); fclose($lock);
        }
    }
}
