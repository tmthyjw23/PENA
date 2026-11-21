<?php
declare(strict_types=1);

// Workspaces (18 Nov 2026): migration from the 17 Nov schema, sign-up, personal and organization
// workspaces, letters without number or reviewers, letterhead with the first letter, reviewers added
// while drafting, and access between workspaces. Throwaway databases only; DB_NAME is never touched.
require __DIR__ . '/support/throwaway_db.php';

use Pena\Service\LetterQuery;
use Pena\Service\LetterService;
use Pena\Service\PenaException;
use Pena\Service\WorkspaceService;
use Pena\Support\Db;
use Pena\Support\Migrator;

function check(bool $condition, string $message): void {
    if (!$condition) throw new RuntimeException($message);
}
function fails(callable $action, string $why, ?string $errorKey = null): void {
    try { $action(); } catch (PenaException $e) {
        if ($errorKey !== null) check(isset($e->errors[$errorKey]), "$why: expected error $errorKey, got " . json_encode($e->errors));
        return;
    }
    throw new RuntimeException('Expected rejection: ' . $why);
}

// ---------------------------------------------------------------- migration from the 17 Nov schema
$server = new mysqli(HOST_DB, USER_DB, PASS_DB, '', PORT_DB);
$old = 'pena_test_migrate_' . bin2hex(random_bytes(4));
$server->query("CREATE DATABASE `$old` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
try {
    $c = new mysqli(HOST_DB, USER_DB, PASS_DB, $old, PORT_DB);
    $c->set_charset('utf8mb4');
    Database::applyStrictMode($c);
    pena_run_sql($c, (string)file_get_contents(__DIR__ . '/fixtures/pena_schema_2026-11-17.sql'));
    $c->query("INSERT INTO organization_members (organization_id, user_id, role) VALUES (1, 3, 'member')");
    $applied = Migrator::forProject($c)->run();
    check($applied === ['001_ruang_kerja', '002_request_limits', '003_account_email', '004_personal_ai_templates', '005_letter_signatures', '006_platform_operators', '007_user_ai_settings', '008_user_guide', '009_version_titles', '010_letterhead_templates'], 'Migration 001 not applied: ' . json_encode($applied));
    pena_run_sql($c, (string)file_get_contents(dirname(__DIR__) . '/database/pena_schema.sql'));
    check(Migrator::forProject($c)->run() === [], 'Migration applied twice');
    pena_run_sql($c, (string)file_get_contents(dirname(__DIR__) . '/database/pena_schema.sql')); // re-run stays safe
    check(Migrator::columnType($c, 'organization_members', 'role') === "enum('secretary')", 'admin/member roles still in the schema');
    $row = $c->query('SELECT kind, owner_user_id FROM organizations WHERE id = 1')->fetch_assoc();
    check($row['kind'] === 'organization' && (int)$row['owner_user_id'] === 1, 'Former admin is not the owner of the organization');
    check((int)$c->query("SELECT COUNT(*) c FROM organization_members WHERE organization_id = 1 AND user_id = 3")->fetch_assoc()['c'] === 0, 'Member without access kept access');
    check((int)$c->query("SELECT COUNT(*) c FROM organizations WHERE kind = 'personal'")->fetch_assoc()['c'] === 3, 'Personal workspaces missing after migration');
    check($c->query("SELECT GROUP_CONCAT(code ORDER BY id) c FROM letter_templates WHERE audience = 'personal'")->fetch_assoc()['c'] === 'IZN,DSP', 'Personal templates missing');
    echo "PASS migration: 17 Nov schema to workspaces, admin to owner, personal workspaces and templates, re-run safe\n";
} finally {
    $server->query("DROP DATABASE IF EXISTS `$old`");
}

// ---------------------------------------------------------------- workspaces on a fresh database
[$conn, $drop] = pena_throwaway_db('pena_test_workspace');
try {
    $db = new Db($conn);
    $spaces = new WorkspaceService($db);
    $letters = new LetterService($db, static fn() => new DateTimeImmutable('2026-11-18 10:00:00'));
    $query = new LetterQuery($db);

    fails(fn() => $spaces->register('', 'bukan-email', 'pendek'), 'invalid sign-up', 'email');
    fails(fn() => $spaces->register('Rina', 'sekretaris@pena.local', 'rahasia123'), 'duplicate email', 'email');
    $rina = $spaces->register('  Rina   Kartika ', 'Rina@Contoh.ID', 'rahasia123');
    $user = $db->one('SELECT username, email, nama_lengkap, password FROM users WHERE id = ?', [$rina]);
    check($user['email'] === 'rina@contoh.id' && $user['username'] === 'rina' && $user['nama_lengkap'] === 'Rina Kartika', 'Sign-up stored the wrong account');
    check(password_verify('rahasia123', $user['password']), 'Password not hashed with password_hash');
    $other = $spaces->register('Rina Lain', 'rina@kampus.ac.id', 'rahasia123');
    check($db->value('SELECT username FROM users WHERE id = ?', [$other]) === 'rina2', 'Username collision not resolved');
    $mine = $spaces->forUser($rina);
    check(count($mine) === 1 && $mine[0]['kind'] === 'personal' && $mine[0]['profile']['nama_mahasiswa'] === 'Rina Kartika', 'Personal workspace not created with sign-up');
    $personalId = $mine[0]['id'];
    echo "PASS sign-up: validation, unique email, lowercase email, username collision, hashed password, personal workspace\n";

    fails(fn() => $spaces->createOrganization($rina, 'HIMA', 'pribadi-9'), 'reserved short name', 'singkatan');
    fails(fn() => $spaces->createOrganization($rina, 'Himpunan', 'BEM-FILKOM'), 'short name taken', 'singkatan');
    $hima = $spaces->createOrganization($rina, 'Himpunan Mahasiswa Informatika', 'hima-if');
    $list = $spaces->forUser($rina);
    check($list[0]['id'] === $hima && $list[0]['short_name'] === 'HIMA-IF' && $list[0]['owner_user_id'] === $rina, 'Organization not created with owner');
    check($spaces->current($rina, null)['id'] === $hima && $spaces->current($rina, $personalId)['id'] === $personalId, 'Workspace choice not honoured');
    check($spaces->current($rina, 1)['id'] === $hima, 'Chose a workspace the user does not belong to');
    fails(fn() => $spaces->requireMember(1, $rina), 'outsider entering an organization');
    echo "PASS organization: created by anyone, owner marker, unique short name, switching limited to members\n";

    // Personal letter: personal templates only, identity from profile, no number, final without reviewers.
    $codes = array_column($query->templates($personalId), 'code');
    sort($codes);
    check($codes === ['DSP', 'IZN'] && in_array('PMJ', array_column($query->templates($hima), 'code'), true), 'Templates not separated by workspace kind');
    $izn = (int)$db->value("SELECT id FROM letter_templates WHERE code = 'IZN'");
    $definition = json_decode((string)$db->value('SELECT definition FROM letter_templates WHERE id = ?', [$izn]), true);
    $spaces->rememberProfile($personalId, $rina, ['nim' => '235150200111001', 'program_studi' => 'Teknik Informatika', 'alasan' => 'tidak disimpan']);
    $prefilled = WorkspaceService::prefill($spaces->requireMember($personalId, $rina), $definition, ['nim' => null]);
    check($prefilled['nim'] === '235150200111001' && $prefilled['nama_mahasiswa'] === 'Rina Kartika' && !isset($prefilled['alasan']), 'Profile prefill wrong');
    $fields = $prefilled + ['penerima_jabatan' => 'Dosen Pengampu Basis Data', 'mata_kuliah' => 'Basis Data',
                            'tanggal_mulai' => '2026-11-20', 'alasan' => 'sakit demam'];
    $blocks = [['id' => 'pembuka'], ['id' => 'identitas'],
               ['id' => 'izin', 'text' => 'Dengan ini saya memohon izin tidak mengikuti perkuliahan {{mata_kuliah}} pada {{tanggal_mulai}} karena {{alasan}}.'],
               ['id' => 'penutup'], ['id' => 'tanda_tangan']];
    fails(fn() => $letters->createDraft($rina, 1, $izn, 'Izin', $fields, $blocks, []), 'personal template in an organization without reviewers');
    fails(fn() => $letters->createDraft($other, $personalId, $izn, 'Izin', $fields, $blocks, []), 'someone else writing in a personal workspace');
    $letterId = $letters->createDraft($rina, $personalId, $izn, 'Izin tidak hadir Basis Data', $fields, $blocks, []);
    $sent = $letters->submit($letterId, $rina);
    $letter = $db->one('SELECT status, letter_number, letterhead_id, approved_at FROM letters WHERE id = ?', [$letterId]);
    check($sent['approved'] === true && $sent['links'] === [] && $letter['status'] === 'approved' && $letter['letter_number'] === null
          && $letter['approved_at'] !== null, 'Personal letter without reviewers not final on submit');
    $page = $query->detail($letterId, $personalId);
    check($page['version']['snapshot']['author']['name'] === 'Rina Kartika' && $page['version']['content_hash'] !== null, 'Author missing from the snapshot');
    check(!isset($page['preview']['metadata']['Nomor']) && $page['preview']['kop'] === false, 'Personal letter rendered with number or letterhead');
    $sign = array_values(array_filter($page['preview']['blocks'], static fn($b) => $b['type'] === 'signatures'))[0]['signers'] ?? [];
    check(count($sign) === 1 && $sign[0]['name'] === 'Rina Kartika', 'Personal letter not signed by its author');
    check(str_contains(implode(' ', array_column($page['preview']['blocks'], 'text')), 'NIM 235150200111001'), 'Identity not written into the letter');
    check((int)$db->value("SELECT COUNT(*) FROM number_counters WHERE organization_id = ?", [$personalId]) === 0, 'Personal letter used a number counter');
    echo "PASS personal letter: own templates, profile identity, no number or letterhead, final without reviewers, signed by author\n";

    // Personal letter acknowledged by a lecturer added while drafting.
    $wali = $spaces->addSigner($personalId, $rina, 'Dr. Contoh Dosen Wali', 'Dosen Wali');
    check($spaces->addSigner($personalId, $rina, 'Dr. Contoh Dosen Wali', 'Dosen Wali') === $wali, 'Same reviewer added twice');
    fails(fn() => $spaces->addSigner($personalId, $rina, '', 'Dosen Wali'), 'reviewer without a name');
    fails(fn() => $spaces->addSigner($personalId, $other, 'X', 'Y'), 'adding a reviewer to someone else\'s workspace');
    $second = $letters->createDraft($rina, $personalId, $izn, 'Izin diketahui dosen wali', $fields, $blocks,
                                    [['name' => 'Mengetahui', 'signer_ids' => [$wali]]]);
    $s2 = $letters->submit($second, $rina);
    check($s2['approved'] === false && count($s2['links']) === 1 && $s2['letter_number'] === null, 'Acknowledged personal letter did not open review without a number');
    $sign = array_values(array_filter($query->detail($second, $personalId)['preview']['blocks'], static fn($b) => $b['type'] === 'signatures'))[0]['signers'];
    check(array_column($sign, 'role') === ['Dosen Wali', 'Hormat saya'], 'Reviewer and author not both on the letter');
    echo "PASS reviewers added while drafting: reused when identical, validated, members only, review without number\n";

    // Organization: no letterhead until the first letter; number uses the organization's short name.
    $pmj = (int)$db->value("SELECT id FROM letter_templates WHERE code = 'PMJ'");
    $ketua = $spaces->addSigner($hima, $rina, 'Contoh Ketua HIMA', 'Ketua Himpunan');
    $orgFields = ['penerima_jabatan' => 'Kepala Laboratorium', 'penerima_instansi' => 'Fakultas Ilmu Komputer',
                  'nama_kegiatan' => 'Kelas Git', 'tanggal_mulai' => '2026-11-28', 'tempat_kegiatan' => 'Lab Dasar',
                  'barang' => [['nama' => 'Proyektor', 'jumlah' => '1', 'keterangan' => '']]];
    $orgBlocks = [['id' => 'pembuka'], ['id' => 'maksud', 'text' => 'Untuk {{nama_kegiatan}} pada {{tanggal_mulai}} di {{tempat_kegiatan}}, kami meminjam barang berikut.'],
                  ['id' => 'daftar_barang'], ['id' => 'penutup'], ['id' => 'tanda_tangan']];
    fails(fn() => $letters->createDraft($rina, $hima, $pmj, 'Pinjam', $orgFields, $orgBlocks, []), 'organization letter without reviewers');
    fails(fn() => $letters->createDraft($rina, $hima, $izn, 'Izin', $fields, $blocks, [['name' => 'Ketua', 'signer_ids' => [$ketua]]]), 'personal template in an organization');
    $noKop = $letters->createDraft($rina, $hima, $pmj, 'Pinjam proyektor', $orgFields, $orgBlocks, [['name' => 'Ketua', 'signer_ids' => [$ketua]]]);
    fails(fn() => $letters->submit($noKop, $rina), 'organization letter without letterhead');
    fails(fn() => $spaces->addLetterhead($hima, $rina, 'HIMA', '', ''), 'letterhead without address', 'kop.address');
    fails(fn() => $spaces->addLetterhead($personalId, $rina, 'X', 'Y', ''), 'letterhead in a personal workspace');
    $kop = $spaces->addLetterhead($hima, $rina, 'HIMPUNAN MAHASISWA INFORMATIKA', 'Gedung F, Jl. Veteran', '');
    $withKop = $letters->createDraft($rina, $hima, $pmj, 'Pinjam proyektor', $orgFields, $orgBlocks, [['name' => 'Ketua', 'signer_ids' => [$ketua]]], $kop);
    $s3 = $letters->submit($withKop, $rina);
    check($s3['letter_number'] === '001/HIMA-IF/PMJ/XI/2026', 'Organization number wrong: ' . $s3['letter_number']);
    echo "PASS organization letter: letterhead entered with the first letter, serial number with the workspace short name\n";
    fails(fn() => $letters->createDraft(2, $hima, $pmj, 'X', $orgFields, $orgBlocks, [['name' => 'Ketua', 'signer_ids' => [$ketua]]]), 'secretary of another organization');
    check($query->detail($withKop, 1) === null, 'Letter visible from another workspace');
    echo "PASS isolation: no access to another workspace's letters\n";
    $limiter = new \Pena\Service\RateLimiter($db);
    check($limiter->allow('test-user-a', 2, 3600), 'First budget request rejected');
    check($limiter->allow('test-user-a', 2, 3600), 'Second budget request rejected');
    check(!$limiter->allow('test-user-a', 2, 3600), 'Budget exceeded');
    check($limiter->allow('test-user-b', 2, 3600), 'Independent budget rejected');
    $db->run("UPDATE organizations SET name='Identitas pengguna' WHERE id=1");
    pena_run_sql($conn, (string)file_get_contents(dirname(__DIR__) . '/database/pena_schema.sql'));
    check($db->value('SELECT name FROM organizations WHERE id=1') === 'Identitas pengguna', 'Production schema overwrote user identity');
    echo "PASS budgets and migration: shared request limits, independent accounts, user identity preserved\n";
} finally {
    $drop();
}
