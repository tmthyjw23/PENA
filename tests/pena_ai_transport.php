<?php
declare(strict_types=1);
require dirname(__DIR__).'/app/init.php';
use Pena\Service\{AiClient,AiException};
function check(bool $ok,string $why):void{if(!$ok)throw new RuntimeException($why);}
$directory=sys_get_temp_dir().'/pena-transport-'.bin2hex(random_bytes(8));mkdir($directory,0700);
$router=$directory.'/router.php';$log=$directory.'/server.log';$process=null;
file_put_contents($router, <<<'PHP'
<?php
$body=json_decode(file_get_contents('php://input'),true);
$model=$body['model']??'';
header('Content-Type: application/json');
if($model==='large'){echo str_repeat('a',1100000);return;}
if($model==='redirect'){http_response_code(302);header('Location: /redirect-target');echo '{}';return;}
echo json_encode(['choices'=>[['message'=>['content'=>'fixture transport ok']]]]);
PHP);
try{
 $socket=stream_socket_server('tcp://127.0.0.1:0',$errno,$message);check($socket!==false,'Fixture listener failed');
 $address=stream_socket_get_name($socket,false);fclose($socket);
 $process=proc_open([PHP_BINARY,'-S',$address,$router],[0=>['pipe','r'],1=>['file',$log,'a'],2=>['file',$log,'a']],$pipes,$directory,null,['bypass_shell'=>true]);
 check(is_resource($process),'Fixture process failed');fclose($pipes[0]);
 $port=(int)substr($address,strrpos($address,':')+1);$ready=false;
 for($i=0;$i<50;$i++){$connection=@fsockopen('127.0.0.1',$port,$errno,$message,0.1);if($connection){fclose($connection);$ready=true;break;}usleep(100000);}
 check($ready,'Fixture HTTP server did not start');
 $base='http://'.$address.'/v1';
 $response=(new AiClient($base,'normal','fixture-key',5))->chat([['role'=>'user','content'=>'fictional']]);
 check($response['text']==='fixture transport ok','cURL response callback lost JSON body');
 foreach(['large'=>'terlalu besar','redirect'=>'HTTP 302']as$model=>$expected){
  try{(new AiClient($base,$model,'fixture-key',5))->chat([['role'=>'user','content'=>'fictional']]);throw new RuntimeException('Expected bounded/redirect error');}
  catch(AiException $e){check(str_contains($e->getMessage(),$expected),'Unexpected transport error');}
 }
 echo "PASS real loopback cURL: JSON body delivered, response capped at 1 MiB, redirect rejected\n";
}finally{
 if(is_resource($process)){proc_terminate($process);proc_close($process);}
 @unlink($router);@unlink($log);@rmdir($directory);
}
