<?php
declare(strict_types=1);
use Pena\Support\Migrator;
return static function(mysqli $db):void{
    if(!Migrator::hasTable($db,'users'))return;
    if(Migrator::columnType($db,'users','guide_dismissed_at')===null){
        $db->query('ALTER TABLE users ADD guide_dismissed_at DATETIME NULL');
    }
    // Pending migrations run before activation; keep existing accounts accessible without a forced tour.
    $db->query('UPDATE users SET guide_dismissed_at=UTC_TIMESTAMP(),updated_at=updated_at WHERE guide_dismissed_at IS NULL');
};
