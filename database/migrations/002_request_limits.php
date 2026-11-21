<?php
declare(strict_types=1);
return static function(mysqli $db):void {
    $db->query('CREATE TABLE IF NOT EXISTS request_limits (
        bucket CHAR(64) NOT NULL,window_start BIGINT NOT NULL,hits INT UNSIGNED NOT NULL,
        expires_at BIGINT NOT NULL,PRIMARY KEY(bucket,window_start),INDEX(expires_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');
};
