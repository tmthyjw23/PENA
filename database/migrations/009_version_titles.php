<?php
declare(strict_types=1);
use Pena\Support\Migrator;
return static function(mysqli $db):void{
    if(!Migrator::hasTable($db,'letter_versions'))return;
    if(Migrator::columnType($db,'letter_versions','title')===null){
        $db->query('ALTER TABLE letter_versions ADD title VARCHAR(200) NULL');
    }
    // Record the currently known legacy title without rewriting protected snapshots/hashes.
    $db->query('UPDATE letter_versions v JOIN letters l ON l.id=v.letter_id SET v.title=l.title WHERE v.title IS NULL');
};
