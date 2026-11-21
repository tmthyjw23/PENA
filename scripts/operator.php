<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'){http_response_code(403);exit;}
require dirname(__DIR__).'/app/init.php';
if(count($argv)!==4||!in_array($argv[1],['grant','revoke'],true)||!filter_var($argv[2],FILTER_VALIDATE_EMAIL)){
 fwrite(STDERR,"Usage: php scripts/operator.php grant|revoke EMAIL 'ALASAN 8-300 KARAKTER'\n");exit(1);
}
try{
 $service=new Pena\Service\OperatorService(Pena\Support\Db::fromTemplate(new Database()));
 $id=$service->changeRoleFromCli($argv[2],$argv[1]==='grant',$argv[3]);
 echo 'Hak operator '.($argv[1]==='grant'?'diberikan kepada':'dicabut dari').' akun #'.$id.". Sesi sebelumnya dicabut.\n";
}catch(Pena\Service\PenaException $e){fwrite(STDERR,$e->getMessage()."\n");exit(1);}
