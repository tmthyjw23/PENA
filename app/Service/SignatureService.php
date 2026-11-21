<?php
declare(strict_types=1);
namespace Pena\Service;
use Pena\Support\Db;

/** Per-version image evidence; never a reusable signature library or proof of a guest's identity. */
final class SignatureService
{
    public function __construct(private readonly Db $db) {}

    public static function fromRequest(array $post,array $files): ?array
    {
        $data=$post['signature_data']??'';
        if(!is_string($data))throw new PenaException('Gambar tanda tangan tidak valid.');
        $file=$files['signature_file']??null;
        $hasFile=is_array($file)&&($file['error']??UPLOAD_ERR_NO_FILE)!==UPLOAD_ERR_NO_FILE;
        if($data===''&&!$hasFile){
            if(!empty($post['signature_consent']))throw new PenaException('Gambar atau unggah tanda tangan sebelum mengonfirmasi pemakaiannya.');
            return null;
        }
        if(($post['signature_consent']??'')!=='1')throw new PenaException('Konfirmasikan pemakaian tanda tangan untuk versi surat ini.');
        if($data!==''&&$hasFile)throw new PenaException('Pilih satu sumber tanda tangan: gambar langsung atau PNG.');
        if($hasFile){
            if(($file['error']??null)!==UPLOAD_ERR_OK || !is_string($file['tmp_name']??null) || !is_uploaded_file($file['tmp_name']))throw new PenaException('Unggah PNG gagal. Pilih berkas kembali.');
            if(filesize($file['tmp_name'])>524288)throw new PenaException('PNG tanda tangan maksimal 512 KB.');
            $png=(string)file_get_contents($file['tmp_name']);
        }else{
            if(strlen($data)>700000||!str_starts_with($data,'data:image/png;base64,'))throw new PenaException('Tanda tangan harus berupa PNG, maksimal 512 KB.');
            $png=base64_decode(substr($data,22),true);
            if($png===false)throw new PenaException('Gambar tanda tangan tidak dapat dibaca.');
        }
        if(!is_string($post['signature_preview_hash']??'')||!is_scalar($post['signature_version_id']??0))throw new PenaException('Versi tanda tangan tidak valid. Muat ulang halaman.');
        return ['png'=>self::normalize($png),'version_id'=>(int)($post['signature_version_id']??0),'preview_hash'=>(string)($post['signature_preview_hash']??'')];
    }

    /** Decode only small PNGs, strip metadata, make white paper transparent and trim to the ink. */
    public static function normalize(string $png): string
    {
        if(strlen($png)>524288||!str_starts_with($png,"\x89PNG\r\n\x1a\n"))throw new PenaException('Tanda tangan harus berupa PNG, maksimal 512 KB.');
        $size=@getimagesizefromstring($png);
        if(!$size||$size[2]!==IMAGETYPE_PNG||$size[0]>1600||$size[1]>800)throw new PenaException('PNG tanda tangan maksimal 1600 × 800 piksel.');
        $image=@imagecreatefromstring($png);
        if(!$image)throw new PenaException('PNG tanda tangan rusak.');
        try{
            imagepalettetotruecolor($image);imagealphablending($image,false);imagesavealpha($image,true);
            $white=imagecolorallocatealpha($image,255,255,255,127);
            $left=$size[0];$top=$size[1];$right=-1;$bottom=-1;$ink=0;
            for($y=0;$y<$size[1];$y++)for($x=0;$x<$size[0];$x++){
                $c=imagecolorat($image,$x,$y);$alpha=($c>>24)&127;
                if($alpha>115||((($c>>16)&255)>245&&(($c>>8)&255)>245&&($c&255)>245)){imagesetpixel($image,$x,$y,$white);continue;}
                $ink++;$left=min($left,$x);$right=max($right,$x);$top=min($top,$y);$bottom=max($bottom,$y);
            }
            if($ink<12||$right-$left<6||$bottom-$top<2)throw new PenaException('Gambar tanda tangan masih kosong atau terlalu kecil.');
            $w=$right-$left+1;$h=$bottom-$top+1;$scale=min(1,600/$w,200/$h);
            $out=imagecreatetruecolor(max(1,(int)round($w*$scale)),max(1,(int)round($h*$scale)));
            imagealphablending($out,false);imagesavealpha($out,true);imagefill($out,0,0,imagecolorallocatealpha($out,255,255,255,127));
            imagecopyresampled($out,$image,0,0,$left,$top,imagesx($out),imagesy($out),$w,$h);
            ob_start();imagepng($out,null,6);$bytes=(string)ob_get_clean();imagedestroy($out);
            return $bytes;
        }finally{imagedestroy($image);}
    }

    public static function previewHash(array $preview): string {return hash('sha256',LetterService::json($preview));}

    public function record(int $versionId,string $slot,string $contentHash,array $image,?int $userId,?int $requestId): void
    {
        $png=self::normalize($image['png']);
        $this->db->insert('INSERT INTO letter_signatures(version_id,slot_key,content_hash,png,image_sha256,user_id,request_id,auth_method) VALUES(?,?,?,?,?,?,?,?)',
            [$versionId,$slot,$contentHash,$png,hash('sha256',$png),$userId,$requestId,$requestId===null?'account':'link']);
    }

    /** Only call after the caller has authorized access to this version. No public image URLs. */
    public function decorate(int $versionId,array $preview,?array $author=null): array
    {
        $images=[];
        foreach($this->db->all('SELECT slot_key,png FROM letter_signatures WHERE version_id=?',[$versionId])as$row)$images[$row['slot_key']]='data:image/png;base64,'.base64_encode($row['png']);
        // Older frozen snapshots predate signature keys. Requests retain the original slot order.
        $keys=[];
        if($images!==[])foreach($this->db->all('SELECT r.stage_member_id FROM approval_requests r JOIN approval_stages s ON s.id=r.stage_id WHERE r.version_id=? ORDER BY s.stage_order,r.stage_member_id',[$versionId])as$request)$keys[]='reviewer:'.$request['stage_member_id'];
        if($author)$keys[]='author';
        $remaining=$images;
        foreach($preview['blocks'] as &$block){
            if($block['type']!=='signatures')continue;
            foreach($block['signers'] as $index=>&$signer){
                $key=($signer['signature_key']??'')?:($keys[$index]??'');
                if(isset($images[$key])){ $signer['signature_png']=$images[$key]; unset($remaining[$key]); }
            }unset($signer);
        }unset($block);
        if($remaining!==[]){
            $snapshot=json_decode((string)$this->db->value('SELECT snapshot FROM letter_versions WHERE id=?',[$versionId]),true);
            $slots=array_merge($snapshot['approval']??[],$author?[$author]:[]);$extra=[];
            foreach($slots as $index=>$slot){
                $key=($slot['signature_key']??'')?:($keys[$index]??'');
                if(isset($remaining[$key]))$extra[]=['name'=>$slot['name'],'role'=>$slot['position'],'signature_key'=>$key,'signature_png'=>$remaining[$key]];
            }
            // Reviewers may be assigned to an AI letter that has no signature block.
            if($extra!==[])$preview['blocks'][]=['id'=>'confirmed_signatures','type'=>'signatures','signers'=>$extra];
        }
        return $preview;
    }
}
