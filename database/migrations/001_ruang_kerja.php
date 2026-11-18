<?php
declare(strict_types=1);

use Pena\Support\Migrator;

/**
 * 18 Nov 2026: organizations become workspaces (personal | organization) with an owner; the admin role
 * is removed (every member is a secretary); templates get an audience. Personal workspaces for existing
 * users and the personal templates are seeded by pena_schema.sql, which runs after the migrations.
 * The unused "member" role never had access; its rows are removed.
 */
return static function (mysqli $c): void {
    if (!Migrator::hasTable($c, 'organizations')) return; // empty database: the schema creates the current shape

    if (Migrator::columnType($c, 'organizations', 'kind') === null) {
        $c->query("ALTER TABLE `organizations`
            ADD COLUMN `kind` ENUM('personal', 'organization') NOT NULL DEFAULT 'organization' AFTER `id`,
            ADD COLUMN `owner_user_id` INT NULL AFTER `short_name`,
            ADD COLUMN `profile` JSON NULL AFTER `owner_user_id`,
            ADD COLUMN `verified_at` DATETIME NULL AFTER `profile`,
            ADD INDEX `idx_org_owner` (`owner_user_id`, `kind`),
            ADD CONSTRAINT `fk_org_owner` FOREIGN KEY (`owner_user_id`) REFERENCES `users`(`id`) ON DELETE SET NULL");
    }

    $role = (string)Migrator::columnType($c, 'organization_members', 'role');
    if (str_contains($role, "'admin'") || str_contains($role, "'member'")) {
        // The first admin (else the first secretary) becomes the owner of each organization.
        $c->query("UPDATE `organizations` o SET o.`owner_user_id` = (
                     SELECT m.`user_id` FROM `organization_members` m
                      WHERE m.`organization_id` = o.`id` AND m.`role` IN ('admin', 'secretary')
                      ORDER BY m.`role` = 'admin' DESC, m.`created_at`, m.`user_id` LIMIT 1)
                   WHERE o.`owner_user_id` IS NULL");
        $c->query("DELETE FROM `organization_members` WHERE `role` = 'member'");
        $c->query("UPDATE `organization_members` SET `role` = 'secretary' WHERE `role` = 'admin'");
        $c->query("ALTER TABLE `organization_members` MODIFY `role` ENUM('secretary') NOT NULL DEFAULT 'secretary'");
    }

    if (Migrator::columnType($c, 'letter_templates', 'audience') === null) {
        $c->query("ALTER TABLE `letter_templates`
            ADD COLUMN `audience` ENUM('organization', 'personal') NOT NULL DEFAULT 'organization' AFTER `organization_id`");
    }
};
