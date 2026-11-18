<?php
declare(strict_types=1);

namespace Pena\Support;

use mysqli;

/**
 * Versioned migrations: database/migrations/NNN_name.php, each returning function (mysqli $c): void.
 *
 * Order on install: migrations first (they bring an existing database to the current shape and
 * skip themselves on an empty one), then database/pena_schema.sql (creates what is missing and seeds).
 * Each migration checks before it alters, so a partly applied one can be re-run safely.
 */
final class Migrator
{
    public function __construct(private readonly mysqli $conn, private readonly string $dir) {}

    public static function forProject(mysqli $conn): self
    {
        return new self($conn, dirname(__DIR__, 2) . '/database/migrations');
    }

    /** @return string[] versions applied in this run */
    public function run(): array
    {
        $this->conn->query('CREATE TABLE IF NOT EXISTS `schema_migrations` (
            `version` VARCHAR(100) NOT NULL PRIMARY KEY,
            `applied_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
        $done = array_column($this->conn->query('SELECT version FROM schema_migrations')->fetch_all(MYSQLI_ASSOC), 'version');
        $files = glob($this->dir . '/*.php') ?: [];
        sort($files, SORT_STRING);
        $applied = [];
        foreach ($files as $file) {
            $version = basename($file, '.php');
            if (in_array($version, $done, true)) continue;
            $migration = require $file;
            $migration($this->conn);
            $stmt = $this->conn->prepare('INSERT INTO schema_migrations (version) VALUES (?)');
            $stmt->bind_param('s', $version);
            $stmt->execute();
            $applied[] = $version;
        }
        return $applied;
    }

    public static function hasTable(mysqli $c, string $table): bool
    {
        $stmt = $c->prepare('SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?');
        $stmt->bind_param('s', $table);
        $stmt->execute();
        return (int)$stmt->get_result()->fetch_row()[0] > 0;
    }

    /** Column type (e.g. "enum('a','b')") or null when the column does not exist. */
    public static function columnType(mysqli $c, string $table, string $column): ?string
    {
        $stmt = $c->prepare('SELECT COLUMN_TYPE FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?');
        $stmt->bind_param('ss', $table, $column);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_row();
        return $row ? (string)$row[0] : null;
    }
}
