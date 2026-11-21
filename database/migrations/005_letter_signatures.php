<?php
declare(strict_types=1);
use Pena\Support\Migrator;
return static function(mysqli $db):void {
    if(Migrator::hasTable($db,'letter_versions')&&Migrator::hasTable($db,'approval_requests'))$db->query((string)file_get_contents(dirname(__DIR__).'/signatures.sql'));
};
