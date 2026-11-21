<?php
declare(strict_types=1);
require dirname(__DIR__).'/vendor/autoload.php';
use Pena\Service\{AiDraftStore,PenaException};
function check(bool $ok,string $why):void{if(!$ok)throw new RuntimeException($why);}
function rejects(callable $fn):void{try{$fn();}catch(PenaException){return;}throw new RuntimeException('Expected rejection');}
$session=[];$store=new AiDraftStore($session,10000);
$a=$store->create(1,10);$b=$store->create(1,10);$other=$store->create(1,20);
check($a!==$b,'Two forms shared a process');
$revision=$store->write($a,1,10,0,['prompt'=>'Surat A']);
check($revision===1 && $store->read($b,1,10)['data']===[],'Other tab overwritten');
rejects(fn()=>$store->read($a,2,10));
rejects(fn()=>$store->read($a,1,20));
rejects(fn()=>$store->write($a,1,10,0,['prompt'=>'Stale']));
check($store->read($a,1,10)['data']['prompt']==='Surat A','Stale write changed draft');
$store->write($other,1,20,0,['prompt'=>'Surat B']);
check($store->read($a,1,10)['data']['prompt']==='Surat A','Workspace switch lost earlier process');
$store->remove($a,1,10);
rejects(fn()=>$store->read($a,1,10));
check($store->read($b,1,10)['data']===[],'Removing a saved process cleared another tab');
$expired=new AiDraftStore($session,17201);
rejects(fn()=>$expired->read($b,1,10));
check($session['pena_ai_runs']===[],'Expired drafts retained');
for($i=0;$i<12;$i++)$expired->create(1,10);
rejects(fn()=>$expired->create(1,10));
echo "PASS AI drafts: separate processes, user/workspace guards, stale revision rejection, independent save cleanup, expiry and limit\n";
