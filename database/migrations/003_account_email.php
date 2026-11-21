<?php
declare(strict_types=1);
use Pena\Support\Migrator;
return static function(mysqli $db): void {
    if (Migrator::hasTable($db, 'users')) {
        if (Migrator::columnType($db, 'users', 'email_verified_at') === null) {
            $db->query('ALTER TABLE users ADD email_verified_at DATETIME NULL');
        }
        if (Migrator::columnType($db, 'users', 'auth_version') === null) {
            $db->query('ALTER TABLE users ADD auth_version INT UNSIGNED NOT NULL DEFAULT 1');
        }
    }
    // Fresh installs create users afterwards; the schema creates this table there too.
    if (Migrator::hasTable($db, 'users')) {
        $db->query((string)file_get_contents(__DIR__.'/../account_tokens.sql'));
    }
};
