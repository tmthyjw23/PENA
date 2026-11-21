<?php
declare(strict_types=1);
use Pena\Support\Migrator;
return static function(mysqli $db):void {
    if(Migrator::hasTable($db,'letter_templates') && Migrator::columnType($db,'letter_templates','is_generated')===null){
        $db->query('ALTER TABLE letter_templates ADD is_generated TINYINT(1) NOT NULL DEFAULT 0');
    }
};
