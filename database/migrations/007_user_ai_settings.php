<?php
declare(strict_types=1);
use Pena\Support\Migrator;
return static function(mysqli $db):void{
    if(!Migrator::hasTable($db,'users'))return;
    $db->query((string)file_get_contents(dirname(__DIR__).'/ai_settings.sql'));
};
