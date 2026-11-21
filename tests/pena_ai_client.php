<?php
declare(strict_types=1);
require dirname(__DIR__).'/app/init.php';
use Pena\Service\{AiClient,AiException};
function check(bool $ok,string $why):void {if(!$ok)throw new RuntimeException($why);}
$cases=[
 ['headers'=>['retry-after'=>'41544'],'expected'=>'11 jam 33 menit'],
 ['headers'=>['retry-after'=>'30'],'expected'=>'30 detik'],
 ['headers'=>['retry-after'=>'90'],'expected'=>'2 menit'],
 ['headers'=>[],'expected'=>'Batas pemakaian layanan AI tercapai'],
 ['headers'=>['retry-after'=>'invalid secret-marker'],'expected'=>'Batas pemakaian layanan AI tercapai'],
];
foreach($cases as $case){
 $calls=0;
 $ai=new AiClient('http://fixture.invalid','fixture-model','fixture-key',5,static function()use($case,&$calls){$calls++;return [429,'{"error":{"message":"secret-marker","code":"rate_limit_exceeded"}}',$case['headers']];});
 try{$ai->chat([['role'=>'user','content'=>'Fixture']]);throw new RuntimeException('429 ignored');}catch(AiException $e){
  check(str_contains($e->getMessage(),$case['expected']),'429 wait not explained: '.$e->getMessage());
  check(!str_contains($e->getMessage(),'secret-marker'),'Provider body/header leaked');
  check($calls===1,'429 retried automatically');
 }
}
$legacy=new AiClient('http://fixture.invalid','fixture-model',null,5,static fn()=>[200,'{"choices":[{"message":{"content":"ok"}}]}']);
check($legacy->chat([['role'=>'user','content'=>'Fixture']])['text']==='ok','Two-value transports broken');
echo "PASS 429 client: bounded wait message, missing/invalid header, no raw provider leak, no retry, legacy transport\n";

$reflected=new AiClient('https://gateway.example/v1','model','key-with-quote"suffix',5,static fn()=>[200,json_encode(['model'=>'key-with-quote"suffix','choices'=>[['message'=>['content'=>'reflected key-with-quote"suffix']]]])]);
$response=$reflected->chat([['role'=>'user','content'=>'fixture']]);
check(!str_contains(json_encode($response),'key-with-quote'),'Reflected credential leaked into response');
echo "PASS reflected gateway credential redacted before logging or rendering\n";
