<?php
declare(strict_types=1);

namespace Pena\Service;

use Pena\Support\Db;

/**
 * Letterhead templates of an organization workspace. Any member manages them (there is no admin role).
 *
 * A kop is either kind=text (name, extra lines, address, contact, optional logo) or kind=image (one full kop image).
 * Content is never edited in place: saving changes inserts a new row and retires the old one (replaced_by),
 * so a submitted version, whose snapshot names the row id and image hashes, keeps showing what was approved.
 * Unsubmitted drafts follow the new revision. Images are re-encoded to PNG and kept in the database (no public URL);
 * pages receive them as data: URIs after the caller has authorized access to the letter.
 */
final class LetterheadService
{
    private const UPLOAD_LIMIT = 2 * 1024 * 1024; // PHP default upload_max_filesize

    public function __construct(private readonly Db $db) {}

    /** Active kop of an organization, default first. No image bytes. */
    public function list(int $orgId): array
    {
        return $this->db->all('SELECT id, kind, name, subtitle, address, contact, is_default, logo_sha256 IS NOT NULL AS has_logo,
                                      image_sha256 IS NOT NULL AS has_image, updated_at, created_at
                                 FROM letterheads WHERE organization_id = ? AND is_active = 1 ORDER BY is_default DESC, id', [$orgId]);
    }

    /** One active kop with its images as data: URIs (for the edit form and previews). */
    public function find(int $orgId, int $id): ?array
    {
        $row = $this->db->one('SELECT * FROM letterheads WHERE id = ? AND organization_id = ? AND is_active = 1', [$id, $orgId]);
        return $row ? self::withImages($row) : null;
    }

    /**
     * @param array $input name, subtitle, address, contact, kind, default ('1'), remove_logo ('1')
     * @param array $files ['logo' => upload|null, 'image' => upload|null] from $_FILES
     */
    public function create(int $orgId, int $userId, array $input, array $files): int
    {
        $this->requireOrganizationMember($orgId, $userId);
        $data = $this->validate($input, $files, null);
        return $this->db->transaction(function (Db $db) use ($orgId, $userId, $data, $input) {
            $hasDefault = (bool)$db->value('SELECT COUNT(*) FROM letterheads WHERE organization_id = ? AND is_active = 1 AND is_default = 1 FOR UPDATE', [$orgId]);
            $default = !$hasDefault || ($input['default'] ?? '') === '1';
            if ($default) $db->run('UPDATE letterheads SET is_default = 0 WHERE organization_id = ?', [$orgId]);
            return $this->insert($orgId, $userId, $data, $default);
        });
    }

    /** Saves changes as a new revision. Unsubmitted drafts move to it; submitted versions keep the old one. */
    public function revise(int $orgId, int $userId, int $id, array $input, array $files): int
    {
        $this->requireOrganizationMember($orgId, $userId);
        return $this->db->transaction(function (Db $db) use ($orgId, $userId, $id, $input, $files) {
            $old = $db->one('SELECT * FROM letterheads WHERE id = ? AND organization_id = ? AND is_active = 1 FOR UPDATE', [$id, $orgId]);
            if (!$old) throw new PenaException('Kop surat tidak ditemukan atau sudah tidak aktif.');
            $data = $this->validate($input, $files, $old);
            $newId = $this->insert($orgId, $userId, $data, (bool)$old['is_default']);
            $db->run('UPDATE letterheads SET is_active = 0, is_default = 0, replaced_by = ? WHERE id = ?', [$newId, $id]);
            $db->run('UPDATE letters l JOIN letter_versions v ON v.id = l.current_version_id
                         SET l.letterhead_id = ?
                       WHERE l.letterhead_id = ? AND l.organization_id = ? AND v.submitted_at IS NULL', [$newId, $id, $orgId]);
            return $newId;
        });
    }

    public function setDefault(int $orgId, int $userId, int $id): void
    {
        $this->requireOrganizationMember($orgId, $userId);
        $this->db->transaction(function (Db $db) use ($orgId, $id) {
            if (!$db->one('SELECT id FROM letterheads WHERE id = ? AND organization_id = ? AND is_active = 1 FOR UPDATE', [$id, $orgId])) {
                throw new PenaException('Kop surat tidak ditemukan atau sudah tidak aktif.');
            }
            $db->run('UPDATE letterheads SET is_default = (id = ?) WHERE organization_id = ?', [$id, $orgId]);
        });
    }

    /** Retires a kop. Letters that used it keep it; the oldest remaining kop becomes the default if needed. */
    public function deactivate(int $orgId, int $userId, int $id): void
    {
        $this->requireOrganizationMember($orgId, $userId);
        $this->db->transaction(function (Db $db) use ($orgId, $id) {
            $row = $db->one('SELECT id, is_default FROM letterheads WHERE id = ? AND organization_id = ? AND is_active = 1 FOR UPDATE', [$id, $orgId]);
            if (!$row) throw new PenaException('Kop surat tidak ditemukan atau sudah tidak aktif.');
            $db->run('UPDATE letterheads SET is_active = 0, is_default = 0 WHERE id = ?', [$id]);
            if ($row['is_default']) {
                $db->run('UPDATE letterheads SET is_default = 1 WHERE organization_id = ? AND is_active = 1 ORDER BY id LIMIT 1', [$orgId]);
            }
        });
    }

    /** Follows replaced_by to the current revision (used when a draft is submitted). */
    public function latest(int $id): int
    {
        for ($i = 0; $i < 50; $i++) {
            $next = $this->db->value('SELECT replaced_by FROM letterheads WHERE id = ?', [$id]);
            if ($next === null) return $id;
            $id = (int)$next;
        }
        return $id;
    }

    /** What a submitted version freezes: text and image hashes, never the bytes. */
    public function snapshot(int $id): ?array
    {
        $row = $this->db->one('SELECT id, kind, name, subtitle, address, contact, logo_sha256, image_sha256 FROM letterheads WHERE id = ?', [$id]);
        if ($row) $row['id'] = (int)$row['id'];
        return $row;
    }

    /**
     * Adds logo_src / image_src (data: URIs) to a letterhead array from a snapshot or a live row.
     * Snapshot images are loaded by id and must still match the frozen hash. Caller authorizes access first.
     */
    public function decorate(array $letterhead): array
    {
        if (empty($letterhead['id']) || (empty($letterhead['logo_sha256']) && empty($letterhead['image_sha256']))) return $letterhead;
        $row = $this->db->one('SELECT logo_png, logo_sha256, image_png, image_sha256 FROM letterheads WHERE id = ?', [(int)$letterhead['id']]);
        if (!$row) return $letterhead;
        foreach (['logo', 'image'] as $slot) {
            $want = $letterhead[$slot . '_sha256'] ?? null;
            $bytes = $row[$slot . '_png'];
            if ($want && is_string($bytes) && hash_equals($want, hash('sha256', $bytes))) {
                $letterhead[$slot . '_src'] = 'data:image/png;base64,' . base64_encode($bytes);
            }
        }
        return $letterhead;
    }

    /** PNG/JPEG in, metadata-free PNG out, scaled to fit. */
    public static function normalize(string $bytes, string $slot): string
    {
        $label = $slot === 'logo' ? 'Logo' : 'Gambar kop';
        if ($bytes === '' || strlen($bytes) > self::UPLOAD_LIMIT) throw new PenaException("$label harus PNG atau JPG, maksimal 2 MB.");
        $size = @getimagesizefromstring($bytes);
        if (!$size || !in_array($size[2], [IMAGETYPE_PNG, IMAGETYPE_JPEG], true)) throw new PenaException("$label harus PNG atau JPG.");
        [$w, $h] = $size;
        if ($w < 16 || $h < 16 || $w > 6000 || $h > 6000) throw new PenaException("$label harus berukuran 16 sampai 6000 piksel.");
        if ($slot === 'image' && $w < $h * 2) throw new PenaException('Gambar kop harus melebar (lebar minimal dua kali tinggi), seperti kop di bagian atas surat.');
        $image = @imagecreatefromstring($bytes);
        if (!$image) throw new PenaException("$label rusak atau tidak dapat dibaca.");
        try {
            [$maxW, $maxH] = $slot === 'logo' ? [600, 600] : [2000, 800];
            $scale = min(1, $maxW / $w, $maxH / $h);
            $out = imagecreatetruecolor(max(1, (int)round($w * $scale)), max(1, (int)round($h * $scale)));
            imagealphablending($out, false); imagesavealpha($out, true);
            imagefill($out, 0, 0, imagecolorallocatealpha($out, 255, 255, 255, 127));
            imagecopyresampled($out, $image, 0, 0, 0, 0, imagesx($out), imagesy($out), $w, $h);
            ob_start(); imagepng($out, null, 6); $png = (string)ob_get_clean(); imagedestroy($out);
            return $png;
        } finally {
            imagedestroy($image);
        }
    }

    public static function withImages(array $row): array
    {
        foreach (['logo', 'image'] as $slot) {
            if (!empty($row[$slot . '_png'])) $row[$slot . '_src'] = 'data:image/png;base64,' . base64_encode($row[$slot . '_png']);
            unset($row[$slot . '_png']);
        }
        return $row;
    }

    // ------------------------------------------------------------------ helpers

    private function validate(array $input, array $files, ?array $old): array
    {
        $kind = ($input['kind'] ?? 'text') === 'image' ? 'image' : 'text';
        $clean = static fn($v, int $max) => mb_substr(trim(preg_replace('/[ \t]+/', ' ', str_replace("\r", '', (string)$v))), 0, $max + 1);
        $data = ['kind' => $kind, 'name' => $clean($input['name'] ?? '', 150), 'subtitle' => $clean($input['subtitle'] ?? '', 255),
                 'address' => $clean($input['address'] ?? '', 255), 'contact' => $clean($input['contact'] ?? '', 255)];
        $errors = [];
        if ($data['name'] === '' || mb_strlen($data['name']) > 150) {
            $errors['name'] = $kind === 'text' ? 'Nama di kop wajib diisi (maksimal 150 karakter).' : 'Beri nama kop ini untuk daftar (maksimal 150 karakter).';
        }
        if (mb_strlen($data['subtitle']) > 255 || substr_count($data['subtitle'], "\n") > 2) $errors['subtitle'] = 'Baris tambahan maksimal tiga baris dan 255 karakter.';
        if ($kind === 'text' && ($data['address'] === '' || mb_strlen($data['address']) > 255)) $errors['address'] = 'Alamat wajib diisi (maksimal 255 karakter).';
        if (mb_strlen($data['contact']) > 255) $errors['contact'] = 'Kontak maksimal 255 karakter.';
        foreach (['logo', 'image'] as $slot) {
            $data[$slot] = null;
            $upload = $files[$slot] ?? null;
            if (is_array($upload) && ($upload['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
                try {
                    if ($upload['error'] !== UPLOAD_ERR_OK || !is_string($upload['tmp_name'] ?? null) || !self::isUpload($upload['tmp_name'])) {
                        throw new PenaException(($slot === 'logo' ? 'Logo' : 'Gambar kop') . ' gagal diunggah. Pilih berkas kembali (PNG atau JPG, maksimal 2 MB).');
                    }
                    $data[$slot] = self::normalize((string)file_get_contents($upload['tmp_name']), $slot);
                } catch (PenaException $e) {
                    $errors[$slot] = $e->getMessage();
                }
            } elseif ($old !== null && !($slot === 'logo' && ($input['remove_logo'] ?? '') === '1')) {
                $data[$slot] = $old[$slot . '_png']; // keep the current image in the new revision
            }
        }
        if ($kind === 'image' && $data['image'] === null && !isset($errors['image'])) $errors['image'] = 'Unggah gambar kop utuh (PNG atau JPG).';
        if ($kind === 'image') { $data['logo'] = null; } else { $data['image'] = null; }
        if ($errors !== []) throw new PenaException('Periksa kembali isian kop surat.', $errors);
        return $data;
    }

    /** is_uploaded_file in a request; tests pass real temporary files through the same validation. */
    private static function isUpload(string $path): bool
    {
        return is_uploaded_file($path) || (PHP_SAPI === 'cli' && is_file($path));
    }

    private function insert(int $orgId, int $userId, array $d, bool $default): int
    {
        return $this->db->insert('INSERT INTO letterheads (organization_id, kind, name, subtitle, address, contact, logo_png, logo_sha256,
                                       image_png, image_sha256, is_default, created_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [$orgId, $d['kind'], $d['name'], $d['subtitle'] === '' ? null : $d['subtitle'], $d['address'] === '' ? null : $d['address'],
             $d['contact'] === '' ? null : $d['contact'], $d['logo'], $d['logo'] === null ? null : hash('sha256', $d['logo']),
             $d['image'], $d['image'] === null ? null : hash('sha256', $d['image']), $default ? 1 : 0, $userId]);
    }

    private function requireOrganizationMember(int $orgId, int $userId): void
    {
        $workspace = (new WorkspaceService($this->db))->requireMember($orgId, $userId);
        if ($workspace['kind'] !== 'organization') throw new PenaException('Kop surat hanya untuk ruang organisasi.');
    }
}
