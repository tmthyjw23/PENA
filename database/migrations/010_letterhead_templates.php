<?php
declare(strict_types=1);
use Pena\Support\Migrator;

/**
 * 21 Nov 2026: organization letterhead templates. A kop is either text + optional logo, or one full image.
 * Rows are revised, never edited in place (replaced_by), so submitted snapshots keep pointing at what was approved.
 * Each organization gets one default kop: its oldest active one, when it has none yet.
 */
return static function (mysqli $c): void {
    if (!Migrator::hasTable($c, 'letterheads')) return; // empty database: the schema creates the current shape
    if (Migrator::columnType($c, 'letterheads', 'kind') === null) {
        $c->query("ALTER TABLE `letterheads`
            ADD COLUMN `kind` ENUM('text', 'image') NOT NULL DEFAULT 'text' AFTER `logo_path`,
            ADD COLUMN `subtitle` VARCHAR(255) NULL AFTER `kind`,
            ADD COLUMN `logo_png` MEDIUMBLOB NULL AFTER `subtitle`,
            ADD COLUMN `logo_sha256` CHAR(64) NULL AFTER `logo_png`,
            ADD COLUMN `image_png` MEDIUMBLOB NULL AFTER `logo_sha256`,
            ADD COLUMN `image_sha256` CHAR(64) NULL AFTER `image_png`,
            ADD COLUMN `is_default` TINYINT(1) NOT NULL DEFAULT 0 AFTER `image_sha256`,
            ADD COLUMN `replaced_by` INT NULL AFTER `is_default`,
            ADD COLUMN `created_by` INT NULL AFTER `replaced_by`");
    }
    $c->query("UPDATE `letterheads` l
                 JOIN (SELECT organization_id, MIN(id) AS id FROM `letterheads` WHERE is_active = 1 GROUP BY organization_id) first
                   ON first.id = l.id
                SET l.is_default = 1
              WHERE NOT EXISTS (SELECT 1 FROM (SELECT organization_id FROM `letterheads` WHERE is_active = 1 AND is_default = 1) d
                                 WHERE d.organization_id = l.organization_id)");
};
