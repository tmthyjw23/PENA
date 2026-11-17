<?php
declare(strict_types=1);

// End-to-end letter workflow against a throwaway database:
// draft → validation → submit/number → staged approval → revision request →
// revision with parallel reconfirm → final approval, plus cancel, signer change and link expiry.
require __DIR__ . '/support/throwaway_db.php';

use Pena\Service\ApprovalService;
use Pena\Service\LetterService;
use Pena\Service\PenaException;
use Pena\Support\Db;

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

/** What the secretary does after a stage opens: create the link for the reviewer still without one. */
function nextLink(Db $db, LetterService $letters, int $letterId, int $userId): string {
    $requestId = (int)$db->value("SELECT r.id FROM approval_requests r JOIN letters l ON l.current_version_id = r.version_id
                                  WHERE l.id = ? AND r.status = 'pending' AND r.token_hash IS NULL ORDER BY r.id LIMIT 1", [$letterId]);
    check($requestId > 0, 'Expected a pending reviewer waiting for a link');
    return $letters->reissueLink($letterId, $userId, $requestId);
}

[$conn, $drop] = pena_throwaway_db('pena_test_flow');
try {
    $db = new Db($conn);
    $now = new DateTimeImmutable('2026-11-17 09:00:00');
    $clock = static function () use (&$now) { return $now; };
    $letters = new LetterService($db, $clock);
    $reviews = new ApprovalService($db, $clock);
    $secretary = 2; $outsider = 3;
    $stages = [['name' => 'Ketua Panitia', 'signer_ids' => [1]],
               ['name' => 'Advisor', 'signer_ids' => [3]],
               ['name' => 'Dekan', 'signer_ids' => [4]]];
    $fields = ['penerima_jabatan' => 'Kepala Bagian Umum', 'penerima_instansi' => 'Fakultas Ilmu Komputer',
               'nama_kegiatan' => 'FilkomDay 2026', 'tanggal_mulai' => '2026-11-30', 'tempat_kegiatan' => 'Aula Fakultas',
               'barang' => [['nama' => 'Kursi lipat', 'jumlah' => '50', 'keterangan' => 'Baik']]];
    $blocks = [['id' => 'pembuka'],
               ['id' => 'maksud', 'text' => 'Sehubungan dengan {{nama_kegiatan}} pada {{tanggal_mulai}} di {{tempat_kegiatan}}, kami bermaksud meminjam barang berikut.'],
               ['id' => 'daftar_barang'], ['id' => 'penutup'], ['id' => 'tanda_tangan']];

    // --- drafts and validation
    fails(fn() => $letters->createDraft($outsider, 1, 1, 'X', [], [], $stages, 1), 'non-member creating a letter');
    fails(fn() => $letters->createDraft($secretary, 1, 1, 'X', [], [['id' => 'maksud', 'text' => 'Pada {{tanggal_palsu}}']], $stages, 1),
          'unknown placeholder', 'block.maksud');
    fails(fn() => $letters->createDraft($secretary, 1, 1, 'X', ['tanggal_mulai' => '30/11/2026'], [], $stages, 1),
          'bad date format', 'field.tanggal_mulai');
    fails(fn() => $letters->createDraft($secretary, 1, 1, 'X', [], [['id' => 'pembuka', 'text' => 'Halo']], $stages, 1),
          'editing fixed template text', 'block.pembuka');
    fails(fn() => $letters->createDraft($secretary, 1, 1, 'X', [], [], [['name' => 'A', 'signer_ids' => [1]], ['name' => 'B', 'signer_ids' => [1]]], 1),
          'same reviewer twice');
    $letterId = $letters->createDraft($secretary, 1, 1, 'Peminjaman kursi FilkomDay', ['nama_kegiatan' => 'FilkomDay 2026'], $blocks, $stages, 1);
    fails(fn() => $letters->submit($letterId, $secretary), 'submitting with required fields missing', 'field.tempat_kegiatan');
    $letters->updateDraft($letterId, $secretary, $fields, $blocks);
    echo "PASS drafts: membership, placeholders, field formats, fixed text, reviewer uniqueness, submission completeness\n";

    // --- submit: number + only stage 1 opens
    $sent = $letters->submit($letterId, $secretary);
    check($sent['letter_number'] === '001/BEM-FILKOM/PMJ/XI/2026', 'Unexpected number ' . $sent['letter_number']);
    check(count($sent['links']) === 1, 'Only the first stage should get a link');
    $ketuaToken = array_values($sent['links'])[0];
    fails(fn() => $letters->updateDraft($letterId, $secretary, $fields, $blocks), 'editing a submitted version');
    fails(fn() => $letters->submit($letterId, $secretary), 'submitting twice');
    $page = $reviews->open($ketuaToken);
    check($page['version']['snapshot']['letter_number'] === $sent['letter_number'], 'Snapshot carries the number');
    check($page['version']['snapshot']['letterhead']['name'] !== '', 'Snapshot carries the letterhead');
    echo "PASS submit: number 001 assigned, version frozen, snapshot complete, only stage 1 opened\n";

    // --- stage 1 approves
    fails(fn() => $reviews->approve($ketuaToken, str_repeat('0', 64), null, '127.0.0.1', 'test'), 'approving a different version than shown');
    $r = $reviews->approve($ketuaToken, $page['version']['content_hash'], 'Setuju', '127.0.0.1', 'test');
    check($r['letter_status'] === 'in_review' && $r['opened'] === 1, 'Approval should open exactly the advisor stage');
    check((int)$db->value("SELECT COUNT(*) FROM approval_requests WHERE letter_id = ? AND status = 'pending' AND token_hash IS NOT NULL", [$letterId]) === 0,
          'Opening a stage must not create a link nobody receives');
    fails(fn() => $reviews->approve($ketuaToken, $page['version']['content_hash'], null, '127.0.0.1', 'test'), 'reusing a spent link');
    $advisorToken = nextLink($db, $letters, $letterId, $secretary);
    echo "PASS approval: hash guard, stage advance without orphan links, single-use links\n";

    // --- advisor asks for revision → process stops
    fails(fn() => $reviews->requestRevision($advisorToken, $page['version']['content_hash'], '  ', null, '127.0.0.1', 'test'), 'empty revision note');
    $reviews->comment($advisorToken, 'Apakah aula sudah dicek ketersediaannya?', 'maksud');
    $reviews->requestRevision($advisorToken, $page['version']['content_hash'], 'Tanggal kegiatan mundur ke 7 Desember.', 'maksud', '127.0.0.1', 'test');
    check($db->value('SELECT status FROM letters WHERE id = ?', [$letterId]) === 'revision_requested', 'Letter should wait for revision');
    check((int)$db->value("SELECT COUNT(*) FROM letter_comments WHERE letter_id = ? AND kind = 'comment'", [$letterId]) === 1, 'Plain comment recorded');
    echo "PASS revision request: comment does not block, revision request stops the letter\n";

    // --- revision → v2, same number, ketua reconfirms in parallel before advisor
    fails(fn() => $letters->startRevision($letterId, $secretary, ''), 'revision without summary');
    $v2 = $letters->startRevision($letterId, $secretary, 'Tanggal diganti ke 7 Desember sesuai arahan advisor.');
    $fields['tanggal_mulai'] = '2026-12-07';
    $letters->updateDraft($letterId, $secretary, $fields, $blocks);
    $sent2 = $letters->submit($letterId, $secretary);
    check($sent2['letter_number'] === $sent['letter_number'], 'Number must stay the same across revisions');
    check(count($sent2['links']) === 1, 'Only the reconfirm should open before stage 1 is confirmed again');
    $reconfirmToken = array_values($sent2['links'])[0];
    $page2 = $reviews->open($reconfirmToken);
    check($page2['request']['kind'] === 'reconfirm' && $page2['previous']['fields']['tanggal_mulai'] === '2026-11-30', 'Reconfirm shows previous version');
    check($page2['version']['content_hash'] !== $page['version']['content_hash'], 'New version has a new hash');
    $v1Approval = $db->value("SELECT status FROM approval_requests r JOIN letter_versions v ON v.id = r.version_id
                              WHERE v.letter_id = ? AND v.version_no = 1 AND r.signer_id = 1", [$letterId]);
    check($v1Approval === 'approved', 'Approval on version 1 stays recorded');
    fails(fn() => $reviews->open($advisorToken), 'old-version link after revision');
    echo "PASS revision: v2 keeps number, previous approver reconfirms with diff, old approvals kept, old links dead\n";

    // --- chain to completion
    $reviews->approve($reconfirmToken, $page2['version']['content_hash'], null, '127.0.0.1', 'test');
    $reviews->approve(nextLink($db, $letters, $letterId, $secretary), $page2['version']['content_hash'], null, '127.0.0.1', 'test');
    $r = $reviews->approve(nextLink($db, $letters, $letterId, $secretary), $page2['version']['content_hash'], null, '127.0.0.1', 'test');
    check($r['letter_status'] === 'approved', 'Letter should be approved after the last stage');
    check($db->value("SELECT auth_method FROM approval_requests WHERE version_id = ? AND signer_id = 4", [$v2]) === 'link', 'Link-based identity recorded');
    fails(fn() => $letters->startRevision($letterId, $secretary, 'x'), 'revising an approved letter');
    echo "PASS completion: ketua → advisor → dekan approved v2, decisions marked as link-based\n";

    // --- second letter: numbering continues, expiry, signer change, cancel
    $second = $letters->createDraft($secretary, 1, 1, 'Peminjaman proyektor', $fields, $blocks, $stages, 1);
    $s = $letters->submit($second, $secretary);
    check(str_starts_with($s['letter_number'], '002/'), 'Second letter should get 002');
    $requestId = array_key_first($s['links']);
    $now = $now->modify('+8 days');
    fails(fn() => $reviews->open($s['links'][$requestId]), 'expired link');
    $fresh = $letters->reissueLink($second, $secretary, $requestId);
    check(isset($reviews->open($fresh)['letter']), 'Reissued link works');
    $member = (int)$db->value('SELECT m.id FROM approval_stage_members m JOIN approval_stages s ON s.id = m.stage_id
                               WHERE s.letter_id = ? AND m.signer_id = 3', [$second]);
    $letters->replaceSigner($second, $secretary, $member, 2);
    check($db->value('SELECT status FROM letters WHERE id = ?', [$second]) === 'revision_requested', 'Signer change mid-review needs a revision');
    fails(fn() => $reviews->open($fresh), 'link after signer change');
    $letters->cancel($second, $secretary, 'Kegiatan dibatalkan');
    check($db->value('SELECT letter_number FROM letters WHERE id = ?', [$second]) === $s['letter_number'], 'Cancelled letter keeps its number');
    $third = $letters->createDraft($secretary, 1, 2, 'Permohonan dana', [], [], [['name' => 'Ketua', 'signer_ids' => [1]]], 1);
    check($third > 0, 'Second template usable');
    echo "PASS lifecycle: numbering continues (002), expiry + reissue, signer change forces revision, cancel keeps number\n";

    check((int)$db->value('SELECT COUNT(*) FROM letter_events WHERE letter_id = ?', [$letterId]) >= 10, 'Audit trail recorded');
    echo "PASS audit: events recorded for the full lifecycle\n";
} finally {
    $drop();
}
