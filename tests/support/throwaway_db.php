<?php
declare(strict_types=1);

// Shared by the workflow and AI tests: a fresh pena_test_* database with the PENA schema,
// using the same strict session mode as the application.
// Never touches DB_NAME from .env. Call $drop() in a finally block.
require_once dirname(__DIR__, 2) . '/app/init.php';
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

function pena_run_sql(mysqli $db, string $sql): void
{
    $db->multi_query($sql);
    do { if ($r = $db->store_result()) $r->free(); } while ($db->more_results() && $db->next_result());
}

/** @return array{0: mysqli, 1: callable} connection and drop callback */
function pena_throwaway_db(string $prefix): array
{
    $name = $prefix . '_' . bin2hex(random_bytes(4));
    if (!preg_match('/^pena_test_[a-z0-9_]+$/', $name)) throw new RuntimeException('Unsafe test database name');
    $server = new mysqli(HOST_DB, USER_DB, PASS_DB, '', PORT_DB);
    $server->query("CREATE DATABASE `$name` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    $drop = static function () use ($server, $name): void { $server->query("DROP DATABASE IF EXISTS `$name`"); };
    try {
        $db = new mysqli(HOST_DB, USER_DB, PASS_DB, $name, PORT_DB);
        $db->set_charset('utf8mb4');
        Database::applyStrictMode($db);
        \Pena\Support\Migrator::forProject($db)->run();
        pena_run_sql($db, (string)file_get_contents(dirname(__DIR__, 2) . '/database/pena_schema.sql'));
        pena_run_sql($db, (string)file_get_contents(dirname(__DIR__, 2) . '/database/demo_seed.sql'));
        return [$db, $drop];
    } catch (Throwable $error) {
        $drop();
        throw $error;
    }
}
