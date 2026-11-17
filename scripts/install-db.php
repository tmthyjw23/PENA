<?php
declare(strict_types=1);

// Creates the database named in .env (DB_NAME) if needed and applies database/pena_schema.sql.
// Safe to re-run: tables use IF NOT EXISTS and seeds never overwrite existing rows.
// Usage: composer db:install            (database from .env)
//        php scripts/install-db.php nama (another database name)
if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit;
}

require dirname(__DIR__) . '/app/init.php';

$database = $argv[1] ?? NAME_DB;
if (!preg_match('/^[A-Za-z0-9_]+$/', $database)) {
    fwrite(STDERR, "Nama database tidak valid.\n");
    exit(1);
}

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
$server = new mysqli(HOST_DB, USER_DB, PASS_DB, '', PORT_DB);
$server->query("CREATE DATABASE IF NOT EXISTS `$database` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
$server->close();

$conn = new mysqli(HOST_DB, USER_DB, PASS_DB, $database, PORT_DB);
$conn->set_charset('utf8mb4');
Database::applyStrictMode($conn);
$conn->multi_query((string)file_get_contents(dirname(__DIR__) . '/database/pena_schema.sql'));
$count = 0;
do {
    if ($result = $conn->store_result()) $result->free();
    $count++;
} while ($conn->more_results() && $conn->next_result());

echo "Skema PENA diterapkan ke '$database' ($count pernyataan).\n";
