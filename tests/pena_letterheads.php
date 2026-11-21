<?php
declare(strict_types=1);

// Letterhead templates (21 Nov 2026): text + logo or full image, default kop, revisions that never change
// submitted versions, drafts following the new revision, image validation, access, Word export, migration 010.
// Throwaway databases only; DB_NAME is never touched.
require __DIR__ . '/support/throwaway_db.php';

use Pena\Service\LetterheadService;
use Pena\Service\LetterQuery;
use Pena\Service\LetterService;
use Pena\Service\PenaException;
use Pena\Service\WordDocumentService;
use Pena\Support\Db;
use Pena\Support\Migrator;

function check(bool $ok, string $why): void { if (!$ok) throw new RuntimeException($why); }
function fails(callable $action, string $why, ?string $key = null): void {
    try { $action(); } catch (PenaException $e) {
        if ($key !== null) check(isset($e->errors[$key]), "$why: expected error $key, got " . json_encode($e->errors));
        return;
    }
    throw new RuntimeException('Expected rejection: ' . $why);
}
/** A temporary image file shaped like a $_FILES entry. */
function upload(int $w, int $h, string $type = 'png'): array {
    $img = imagecreatetruecolor($w, $h);
    imagefill($img, 0, 0, imagecolorallocate($img, 20, 60, 160));
    imagestring($img, 5, 4, 4, 'KOP', imagecolorallocate($img, 255, 255, 255));
    $path = tempnam(sys_get_temp_dir(), 'kop');
    $type === 'png' ? imagepng($img, $path) : imagejpeg($img, $path, 90);
    imagedestroy($img);
    return ['tmp_name' => $path, 'error' => UPLOAD_ERR_OK, 'size' => filesize($path), 'name' => "kop.$type"];
}

// ---------------------------------------------------------------- migration 010 on the 17 Nov schema
$server = new mysqli(HOST_DB, USER_DB, PASS_DB, '', PORT_DB);
$old = 'pena_test_kopmig_' . bin2hex(random_bytes(4));
$server->query("CREATE DATABASE `$old` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
try {
    $c = new mysqli(HOST_DB, USER_DB, PASS_DB, $old, PORT_DB);
    $c->set_charset('utf8mb4');
    Database::applyStrictMode($c);
    pena_run_sql($c, (string)file_get_contents(__DIR__ . '/fixtures/pena_schema_2026-11-17.sql'));
    Migrator::forProject($c)->run();
    check(Migrator::columnType($c, 'letterheads', 'image_png') === 'mediumblob', 'Migration 010 did not add image columns');
    check((int)$c->query('SELECT is_default FROM letterheads WHERE id = 1')->fetch_assoc()['is_default'] === 1, 'Existing kop not made default');
    echo "PASS migration 010: image columns added, existing kop becomes the default\n";
} finally {
    $server->query("DROP DATABASE IF EXISTS `$old`");
}

[$conn, $drop] = pena_throwaway_db('pena_test_letterheads');
$tmp = [];
try {
    $db = new Db($conn);
    $kop = new LetterheadService($db);
    $letters = new LetterService($db);
    $query = new LetterQuery($db);
    $secretary = 2; $outsider = 3; $org = 1;

    // Validation and image handling.
    $text = ['kind' => 'text', 'name' => 'PANITIA FILKOMDAY 2026', 'subtitle' => "BADAN EKSEKUTIF MAHASISWA\nFAKULTAS ILMU KOMPUTER",
             'address' => 'Gedung F, Jl. Veteran', 'contact' => 'filkomday@example.invalid'];
    fails(fn() => $kop->create($org, $secretary, ['kind' => 'text', 'name' => 'X'], []), 'text kop without address', 'address');
    fails(fn() => $kop->create($org, $secretary, ['kind' => 'image', 'name' => 'Kop gambar'], []), 'image kop without image', 'image');
    $notImage = tempnam(sys_get_temp_dir(), 'kop'); file_put_contents($notImage, '<?php echo 1; ?>'); $tmp[] = $notImage;
    fails(fn() => $kop->create($org, $secretary, $text, ['logo' => ['tmp_name' => $notImage, 'error' => UPLOAD_ERR_OK]]), 'script uploaded as logo', 'logo');
    $tall = upload(200, 400); $tmp[] = $tall['tmp_name'];
    fails(fn() => $kop->create($org, $secretary, ['kind' => 'image', 'name' => 'Kop'], ['image' => $tall]), 'kop image that is not wide', 'image');
    fails(fn() => $kop->create($outsider, $secretary, $text, []), 'kop in an organization the user is not in');
    fails(fn() => $kop->create($org, $outsider, $text, []), 'non-member adding a kop');
    $personal = (int)$db->value("SELECT id FROM organizations WHERE kind = 'personal' AND owner_user_id = ?", [$secretary]);
    fails(fn() => $kop->create($personal, $secretary, $text, []), 'kop in a personal workspace');

    $logo = upload(1200, 1200, 'jpg'); $tmp[] = $logo['tmp_name'];
    $panitia = $kop->create($org, $secretary, $text + ['default' => '1'], ['logo' => $logo]);
    $row = $db->one('SELECT logo_png, logo_sha256, is_default FROM letterheads WHERE id = ?', [$panitia]);
    $size = getimagesizefromstring($row['logo_png']);
    check(str_starts_with($row['logo_png'], "\x89PNG") && $size[0] <= 600 && $row['logo_sha256'] === hash('sha256', $row['logo_png']), 'Logo not re-encoded to a bounded PNG');
    check((int)$row['is_default'] === 1 && (int)$db->value('SELECT COUNT(*) FROM letterheads WHERE organization_id = ? AND is_default = 1', [$org]) === 1, 'Default kop not unique');
    check((int)$kop->list($org)[0]['id'] === $panitia, 'Default kop not listed first');
    $banner = upload(1800, 300); $tmp[] = $banner['tmp_name'];
    $imageKop = $kop->create($org, $secretary, ['kind' => 'image', 'name' => 'Kop gambar BEM'], ['image' => $banner, 'logo' => $logo]);
    check($db->value('SELECT logo_png FROM letterheads WHERE id = ?', [$imageKop]) === null, 'Image kop kept a logo');
    echo "PASS kop create: validation, PNG/JPG re-encoded and bounded, script rejected, wide image only, members of organizations only, one default\n";

    // Letters: a submitted version freezes the kop; a later edit only moves unsubmitted drafts.
    $fields = ['penerima_jabatan' => 'Kepala Bagian Umum', 'penerima_instansi' => 'Fakultas Ilmu Komputer', 'nama_kegiatan' => 'FilkomDay',
               'tanggal_mulai' => '2026-11-30', 'tempat_kegiatan' => 'Aula', 'barang' => [['nama' => 'Kursi', 'jumlah' => '50', 'keterangan' => '']]];
    $blocks = [['id' => 'pembuka'], ['id' => 'maksud', 'text' => 'Untuk {{nama_kegiatan}} pada {{tanggal_mulai}} di {{tempat_kegiatan}}, kami meminjam barang berikut.'],
               ['id' => 'daftar_barang'], ['id' => 'penutup'], ['id' => 'tanda_tangan']];
    $stages = [['name' => 'Ketua', 'signer_ids' => [1]]];
    $sent = $letters->createDraft($secretary, $org, 1, 'Surat terkirim', $fields, $blocks, $stages, $panitia);
    $letters->submit($sent, $secretary);
    $draft = $letters->createDraft($secretary, $org, 1, 'Masih draf', $fields, $blocks, $stages, $panitia);
    $before = $query->detail($sent, $org);
    check(($before['version']['snapshot']['letterhead']['logo_sha256'] ?? null) === $row['logo_sha256'], 'Snapshot lacks the logo hash');
    check(!str_contains(json_encode($before['version']['snapshot']), 'base64'), 'Image bytes copied into the snapshot');
    check(str_starts_with($before['preview']['letterhead']['logo_src'] ?? '', 'data:image/png;base64,'), 'Submitted preview has no logo');

    $revised = $kop->revise($org, $secretary, $panitia, ['name' => 'PANITIA FILKOMDAY 2026 (BARU)', 'remove_logo' => '1'] + $text, []);
    $oldRow = $db->one('SELECT is_active, is_default, replaced_by FROM letterheads WHERE id = ?', [$panitia]);
    check((int)$oldRow['is_active'] === 0 && (int)$oldRow['replaced_by'] === $revised && (int)$db->value('SELECT is_default FROM letterheads WHERE id = ?', [$revised]) === 1,
          'Revision did not replace the old kop and keep the default');
    check((int)$db->value('SELECT letterhead_id FROM letters WHERE id = ?', [$draft]) === $revised, 'Draft did not follow the revised kop');
    check((int)$db->value('SELECT letterhead_id FROM letters WHERE id = ?', [$sent]) === $panitia, 'Submitted letter moved to the revised kop');
    $after = $query->detail($sent, $org);
    check($after['version']['content_hash'] === $before['version']['content_hash'] && $after['preview']['letterhead']['name'] === 'PANITIA FILKOMDAY 2026'
          && isset($after['preview']['letterhead']['logo_src']), 'Submitted version changed after the kop was edited');
    check(!isset($query->detail($draft, $org)['preview']['letterhead']['logo_src']), 'Draft preview still shows the removed logo');

    // A letter that was under review when the kop changed gets the current kop on its next version.
    $letters->startRevision($sent, $secretary, 'Ganti tanggal');
    $letters->submit($sent, $secretary);
    check((int)$db->value('SELECT letterhead_id FROM letters WHERE id = ?', [$sent]) === $revised
          && $query->detail($sent, $org)['version']['snapshot']['letterhead']['name'] === 'PANITIA FILKOMDAY 2026 (BARU)', 'Resubmission kept the retired kop');
    echo "PASS kop revisions: snapshot keeps hashes not bytes, submitted versions unchanged, drafts and resubmissions use the current revision\n";

    // Default and deactivation.
    $kop->setDefault($org, $secretary, $imageKop);
    check((int)$kop->list($org)[0]['id'] === $imageKop, 'Default not switched');
    $kop->deactivate($org, $secretary, $imageKop);
    check((int)$db->value('SELECT COUNT(*) FROM letterheads WHERE organization_id = ? AND is_active = 1 AND is_default = 1', [$org]) === 1, 'No default after deactivating it');
    fails(fn() => $kop->setDefault($org, $secretary, $imageKop), 'default on an inactive kop');
    fails(fn() => $kop->revise($org, $outsider, $revised, $text, []), 'outsider editing a kop');
    echo "PASS kop default: switch, deactivate promotes another, inactive and outsiders refused\n";

    // Word output carries the images.
    foreach (['text' => $kop->find($org, $revised) + ['logo_src' => 'data:image/png;base64,' . base64_encode($row['logo_png'])],
              'image' => LetterheadService::withImages($db->one('SELECT * FROM letterheads WHERE id = ?', [$imageKop]))] as $label => $lh) {
        $file = tempnam(sys_get_temp_dir(), 'kopdocx'); $tmp[] = $file;
        (new WordDocumentService())->save($file, $lh, ['Perihal' => 'Uji'], [['type' => 'paragraph', 'text' => 'Isi']]);
        $zip = new ZipArchive(); $zip->open($file);
        $media = 0; $header = '';
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $name = $zip->getNameIndex($i);
            if (str_starts_with($name, 'word/media/')) $media++;
            if (str_starts_with($name, 'word/header')) $header .= $zip->getFromIndex($i);
        }
        $zip->close();
        check($media === 1, "Word $label kop has $media images");
        if ($label === 'text') check(str_contains($header, 'PANITIA FILKOMDAY') && str_contains($header, 'FAKULTAS ILMU KOMPUTER'), 'Word text kop lacks its lines');
    }
    echo "PASS kop Word export: text kop with logo and full image kop embed one image in the header\n";
} finally {
    foreach ($tmp as $f) @unlink($f);
    $conn->close();
    $drop();
}
