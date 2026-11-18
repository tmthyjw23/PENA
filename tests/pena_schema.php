<?php
declare(strict_types=1);

// Builds a throwaway database pena_test_schema_<random>, applies the schema twice,
// checks the rules the schema itself must enforce, then drops the database.
// Never touches DB_NAME from .env.
require dirname(__DIR__) . '/app/init.php';
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

$name = 'pena_test_schema_' . bin2hex(random_bytes(4));
$server = new mysqli(HOST_DB, USER_DB, PASS_DB, '', PORT_DB);
$server->query("CREATE DATABASE `$name` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");

function check(bool $condition, string $message): void {
    if (!$condition) throw new RuntimeException($message);
}
function runSql(mysqli $db, string $sql): void {
    $db->multi_query($sql);
    do { if ($r = $db->store_result()) $r->free(); } while ($db->more_results() && $db->next_result());
}
function rejected(mysqli $db, string $sql, string $why): void {
    try { $db->query($sql); } catch (mysqli_sql_exception) { return; }
    throw new RuntimeException('Schema accepted invalid data: ' . $why);
}

try {
    $db = new mysqli(HOST_DB, USER_DB, PASS_DB, $name, PORT_DB);
    $db->set_charset('utf8mb4');
    Database::applyStrictMode($db); // same session mode the application uses
    $mode = $db->query('SELECT @@SESSION.sql_mode m')->fetch_assoc()['m'];
    check(str_contains($mode, 'STRICT_TRANS_TABLES'), 'Strict mode not applied');
    $pena = (string)file_get_contents(dirname(__DIR__) . '/database/pena_schema.sql');
    runSql($db, $pena);
    runSql($db, $pena); // re-running setup must not fail or duplicate seeds
    check((int)$db->query('SELECT COUNT(*) c FROM letter_templates')->fetch_assoc()['c'] === 4, 'Seeds duplicated on re-run');
    check((int)$db->query('SELECT COUNT(*) c FROM users')->fetch_assoc()['c'] === 3, 'User seeds duplicated or missing');
    check((int)$db->query("SELECT COUNT(*) c FROM information_schema.TABLE_CONSTRAINTS WHERE CONSTRAINT_SCHEMA = DATABASE() AND CONSTRAINT_NAME = 'fk_letter_current_version'")->fetch_assoc()['c'] === 1, 'current_version FK missing');
    $defs = $db->query('SELECT definition FROM letter_templates')->fetch_all(MYSQLI_ASSOC);
    foreach ($defs as $row) {
        $definition = json_decode($row['definition'], true, 32, JSON_THROW_ON_ERROR);
        check(isset($definition['fields'], $definition['blocks']), 'Template definition shape');
        $keys = array_column($definition['fields'], 'key');
        // Every {{placeholder}} in an instruction must name a declared field.
        foreach ($definition['blocks'] as $block) {
            preg_match_all('/\{\{(\w+)\}\}/', ($block['instruction'] ?? '') . ($block['text'] ?? ''), $m);
            foreach ($m[1] as $key) check(in_array($key, $keys, true), "Unknown placeholder {{{$key}}}");
        }
    }
    echo "PASS schema: imports cleanly twice, seeds idempotent, template JSON and placeholders valid\n";

    rejected($db, "INSERT INTO letter_templates (code, name, definition) VALUES ('X', 'X', 'not json')", 'invalid template JSON');

    // Number counter: locked read + increment inside a transaction.
    $db->begin_transaction();
    $db->query("INSERT INTO number_counters (organization_id, series, year, last_value) VALUES (1, 'UMUM', 2026, 0) ON DUPLICATE KEY UPDATE last_value = last_value");
    $seq = (int)$db->query("SELECT last_value FROM number_counters WHERE organization_id = 1 AND series = 'UMUM' AND year = 2026 FOR UPDATE")->fetch_assoc()['last_value'] + 1;
    $db->query("UPDATE number_counters SET last_value = $seq WHERE organization_id = 1 AND series = 'UMUM' AND year = 2026");
    $db->query("INSERT INTO letters (id, organization_id, template_id, letterhead_id, created_by, title, status, number_series, number_year, number_seq, letter_number, number_assigned_at)
                VALUES (1, 1, 1, 1, 2, 'Peminjaman aula', 'in_review', 'UMUM', 2026, $seq, '001/BEM-FILKOM/PMJ/XI/2026', NOW())");
    $db->commit();
    rejected($db, "INSERT INTO letters (organization_id, template_id, created_by, title, number_series, number_year, number_seq, letter_number) VALUES (1, 1, 2, 'dup', 'UMUM', 2026, 2, '001/BEM-FILKOM/PMJ/XI/2026')", 'duplicate letter number');
    rejected($db, "INSERT INTO letters (organization_id, template_id, created_by, title, number_series, number_year, number_seq, letter_number) VALUES (1, 1, 2, 'dup', 'UMUM', 2026, 1, '999')", 'duplicate sequence');
    rejected($db, "INSERT INTO letters (organization_id, template_id, created_by, title, letter_number) VALUES (1, 1, 2, 'half', 'X/1')", 'number without sequence');
    rejected($db, "INSERT INTO letters (organization_id, template_id, created_by, title, status) VALUES (1, 1, 2, 'bad', 'signed')", 'unknown status');
    echo "PASS numbering: locked counter, unique number and sequence per organization, complete number fields\n";

    $db->query("INSERT INTO letter_versions (id, letter_id, version_no, fields, blocks, snapshot, content_hash, created_by, submitted_at)
                VALUES (1, 1, 1, '{\"nama_kegiatan\":\"FilkomDay\"}', '[]', '{\"letterhead\":{}}', REPEAT('a', 64), 2, NOW())");
    $db->query('UPDATE letters SET current_version_id = 1 WHERE id = 1');
    rejected($db, "INSERT INTO letter_versions (letter_id, version_no, fields, blocks, created_by) VALUES (1, 1, '{}', '[]', 2)", 'duplicate version number');
    rejected($db, "INSERT INTO letter_versions (letter_id, version_no, fields, blocks, created_by, submitted_at) VALUES (1, 2, '{}', '[]', 2, NOW())", 'submitted without snapshot/hash');
    rejected($db, "INSERT INTO letter_versions (letter_id, version_no, fields, blocks, created_by) VALUES (1, 2, '{bad', '[]', 2)", 'invalid fields JSON');
    echo "PASS versions: unique per letter, submitted versions must be frozen, JSON enforced\n";

    $db->query("INSERT INTO approval_stages (id, letter_id, stage_order, name) VALUES (1, 1, 1, 'Ketua'), (2, 1, 2, 'Advisor')");
    $db->query("INSERT INTO approval_stage_members (id, stage_id, signer_id) VALUES (1, 1, 1), (2, 2, 3)");
    rejected($db, "INSERT INTO approval_stages (letter_id, stage_order, name) VALUES (1, 1, 'dup')", 'duplicate stage order');
    $db->query("INSERT INTO approval_requests (letter_id, version_id, stage_id, stage_member_id, signer_id, signer_name, signer_position, status, token_hash, token_expires_at)
                VALUES (1, 1, 1, 1, 1, 'Contoh Ketua Panitia', 'Ketua Panitia', 'pending', REPEAT('b', 64), NOW() + INTERVAL 7 DAY)");
    rejected($db, "INSERT INTO approval_requests (letter_id, version_id, stage_id, stage_member_id, signer_id, signer_name, signer_position) VALUES (1, 1, 1, 1, 1, 'x', 'x')", 'two requests for one reviewer on one version');
    rejected($db, "INSERT INTO approval_requests (letter_id, version_id, stage_id, stage_member_id, signer_id, signer_name, signer_position, token_hash) VALUES (1, 1, 2, 2, 3, 'x', 'x', REPEAT('b', 64))", 'reused token');
    rejected($db, "UPDATE approval_requests SET status = 'approved' WHERE id = 1", 'approval without time and method');
    $db->query("UPDATE approval_requests SET status = 'approved', decided_at = NOW(), auth_method = 'link' WHERE id = 1");
    echo "PASS approvals: ordered stages, one request per reviewer per version, unique tokens, complete decisions\n";

    // Revision: new version; old approval stays on version 1 and a reconfirm request goes to version 2.
    $db->query("UPDATE approval_requests SET status = 'superseded' WHERE version_id = 1 AND status IN ('waiting', 'pending')");
    $db->query("INSERT INTO letter_versions (id, letter_id, version_no, fields, blocks, created_by) VALUES (2, 1, 2, '{}', '[]', 2)");
    $db->query("INSERT INTO approval_requests (letter_id, version_id, stage_id, stage_member_id, signer_id, signer_name, signer_position, kind, status)
                VALUES (1, 2, 1, 1, 1, 'Contoh Ketua Panitia', 'Ketua Panitia', 'reconfirm', 'pending')");
    $kept = $db->query("SELECT status FROM approval_requests WHERE version_id = 1 AND stage_member_id = 1")->fetch_assoc()['status'];
    check($kept === 'approved', 'Old approval must remain recorded on the old version');
    $db->query("INSERT INTO letter_comments (letter_id, version_id, request_id, author_name, kind, body) VALUES (1, 1, 1, 'Contoh Advisor', 'revision_request', 'Perbaiki tanggal')");
    $db->query("INSERT INTO letter_exports (version_id, format, purpose, status, error) VALUES (1, 'pdf', 'final', 'failed', 'Word tidak tersedia')");
    $db->query("INSERT INTO letter_events (letter_id, version_id, actor_label, event, detail) VALUES (1, 2, 'sekretaris', 'version_created', '{\"from\":1}')");
    $db->query("INSERT INTO ai_generations (organization_id, letter_id, purpose, model, input, status) VALUES (1, 1, 'extract', 'test-model', '{\"prompt\":\"x\"}', 'ok')");
    rejected($db, 'DELETE FROM signers WHERE id = 1', 'deleting a signer referenced by approvals');
    echo "PASS revisions: reconfirm on new version keeps old approval, comments/exports/events/AI log accepted\n";
} finally {
    $server->query("DROP DATABASE IF EXISTS `$name`");
}
