<?php
declare(strict_types=1);
use Pena\Support\Migrator;
return static function(mysqli $db):void{
    if(!Migrator::hasTable($db,'users'))return;
    $db->multi_query((string)file_get_contents(dirname(__DIR__).'/operator.sql'));
    do{if($result=$db->store_result())$result->free();}while($db->more_results()&&$db->next_result());
};
