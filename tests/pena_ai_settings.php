<?php
declare(strict_types=1);
require __DIR__.'/support/throwaway_db.php';
use Pena\Service\{AiSettings,AiException,WorkspaceService,PenaException};
use Pena\Support\Db;
function check(bool $ok,string $why):void{if(!$ok)throw new RuntimeException($why);}
function denies(callable $fn):void{try{$fn();}catch(PenaException|AiException){return;}throw new RuntimeException('Expected rejection');}
[$conn,$drop]=pena_throwaway_db('pena_test_ai_settings');
try{
 $db=new Db($conn);$spaces=new WorkspaceService($db);$settings=new AiSettings($db);
 $u=$spaces->register('Pemilik Kunci','key-owner@example.invalid','Fixture-Owner-123');
 $v=$spaces->register('Pengguna Lain','key-other@example.invalid','Fixture-Other-123');
 denies(fn()=>$settings->metadata($u));
 $db->run('UPDATE users SET email_verified_at=NOW() WHERE id IN(?,?)',[$u,$v]);
 $_ENV['AI_BASE_URL']='http://platform.invalid/v1';$_ENV['AI_MODEL']='platform-model';$_ENV['AI_API_KEY']='platform-key';
 unset($_ENV['AI_CREDENTIAL_KEY']);
 check($settings->client($u)===null&&$settings->requiresSetup($u),'Ordinary user inherited platform default');
 check($settings->metadata($u)['mode']==='unconfigured'&&!$settings->metadata($u)['platform_allowed'],'Setup metadata incorrect');
 denies(fn()=>$settings->usePlatform($u));
 denies(fn()=>$settings->save($u,'platform','google','','fixture-key-123'));
 (new \Pena\Service\OperatorService($db))->changeRoleFromCli('key-other@example.invalid',true,'Fixture operator');
 check($settings->client($v)?->model()==='platform-model'&&!$settings->requiresSetup($v),'Explicit operator lost platform default');
 denies(fn()=>$settings->save($u,'personal','openrouter','example/model','user-canary-key-123'));
 $_ENV['AI_CREDENTIAL_KEY']=base64_encode(random_bytes(32));$master=$_ENV['AI_CREDENTIAL_KEY'];
 $key='user-canary-key-123';$replacement='replacement-private-key-456';
 denies(fn()=>$settings->save($u,'personal','http://127.0.0.1','model',$key));
 denies(fn()=>$settings->save($u,'personal','openai','model',"key-with\r\nInjected: yes"));
 denies(fn()=>$settings->save($u,'personal','openai',$key,$key));
 $settings->save($u,'personal','openrouter','example/model',$key);
 $row=$db->one('SELECT * FROM user_ai_settings WHERE user_id=?',[$u]);$cipher=$row['encrypted_key'];
 check(!str_contains($cipher,$key)&&str_starts_with($cipher,'v1:'),'Plaintext credential in database');
 check(!str_contains(json_encode($settings->metadata($u)),$key)&&!isset($settings->metadata($u)['encrypted_key']),'Metadata exposed secret');
 check(!$settings->metadata($v)['has_key']&&$settings->client($v)?->model()==='platform-model','Cross-owner connection leak');
 $calls=[];$transport=static function($url,$headers,$body)use(&$calls){$calls[]=[$url,$headers,json_decode($body,true)];return [200,json_encode(['choices'=>[['message'=>['content'=>'koneksi berhasil']]]])];};
 $settings->client($u,$transport)->chat([['role'=>'user','content'=>'fixture']]);
 check($calls[0][0]==='https://openrouter.ai/api/v1/chat/completions'&&in_array('Authorization: Bearer '.$key,$calls[0][1],true),'Wrong endpoint/owner key');
 check(!str_contains(json_encode($calls[0][2]),$key),'Credential entered model prompt');
 $settings->save($u,'personal','openrouter','example/other-model','');
 check($settings->metadata($u)['revision']!==$row['revision'],'Config revision did not change');
 $settings->client($u,$transport)->chat([['role'=>'user','content'=>'fixture']]);
 check(in_array('Authorization: Bearer '.$key,$calls[1][1],true),'Blank replacement lost same-provider key');
 denies(fn()=>$settings->save($u,'personal','groq','model',''));
 $settings->save($u,'personal','groq','model',$replacement);
 $settings->client($u,$transport)->chat([['role'=>'user','content'=>'fixture']]);
 check($calls[2][0]==='https://api.groq.com/openai/v1/chat/completions'&&in_array('Authorization: Bearer '.$replacement,$calls[2][1],true),'Rotation/provider switch failed');
 $row=$db->one('SELECT * FROM user_ai_settings WHERE user_id=?',[$u]);
 $db->run('UPDATE user_ai_settings SET encrypted_key=? WHERE user_id=?',[$cipher,$u]);
 denies(fn()=>$settings->client($u,$transport)); // provider/model authentication
 $db->run('INSERT INTO user_ai_settings(user_id,mode,provider,model,encrypted_key,revision) VALUES(?,?,?,?,?,?)',[$v,'personal','openrouter','example/model',$cipher,bin2hex(random_bytes(16))]);
 denies(fn()=>$settings->client($v,$transport)); // copied ciphertext cannot cross accounts
 $settings->delete($v);
 $db->run('UPDATE user_ai_settings SET encrypted_key=? WHERE user_id=?',[$row['encrypted_key'],$u]);
 $_ENV['AI_CREDENTIAL_KEY']=base64_encode(random_bytes(32));denies(fn()=>$settings->client($u));
 $_ENV['AI_CREDENTIAL_KEY']=$master;
 $privateFile=tempnam(sys_get_temp_dir(),'pena-master-');
 try{
  file_put_contents($privateFile,$master);chmod($privateFile,0600);
  $_ENV['AI_CREDENTIAL_KEY_FILE']=$privateFile;unset($_ENV['AI_CREDENTIAL_KEY']);
  check($settings->client($u,$transport)!==null,'Private key file could not decrypt saved credential');
 }finally{unlink($privateFile);unset($_ENV['AI_CREDENTIAL_KEY_FILE']);$_ENV['AI_CREDENTIAL_KEY']=$master;}

 $n=count($calls);$settings->probe($u,$transport);check(count($calls)===$n+1,'Probe did not make exactly one request');denies(fn()=>$settings->probe($u,$transport));
 denies(fn()=>$settings->usePlatform($u));
 check(!$settings->requiresSetup($u),'Saved personal connection did not unlock workspace');
 $db->run("UPDATE user_ai_settings SET mode='platform' WHERE user_id=?",[$u]);
 check($settings->client($u)===null&&$settings->requiresSetup($u),'Legacy platform row bypassed operator restriction');
 $settings->save($u,'personal','openai','fixture-model',$key);
 $settings->client($u,$transport)->chat([['role'=>'user','content'=>'fixture']]);
 $last=$calls[array_key_last($calls)];
 check(isset($last[2]['max_completion_tokens'])&&!isset($last[2]['max_tokens'],$last[2]['temperature']),'OpenAI reasoning parameters incompatible');
 $n=0;$failing=static function()use(&$n){$n++;return [401,'{"error":"user-canary-key-123"}'];};
 denies(fn()=>$settings->client($u,$failing)->chat([['role'=>'user','content'=>'fixture']]));
 check($n===1,'Failed personal request retried/fell back');
 $settings->save($u,'personal','google','',$key);
 $google=$settings->metadata($u);
 check($google['model']===AiSettings::PROVIDERS['google']['default_model'],'Google did not default the model server-side');
 $settings->client($u,$transport)->chat([['role'=>'user','content'=>'fixture']]);
 $last=$calls[array_key_last($calls)];
 check($last[0]==='https://generativelanguage.googleapis.com/v1beta/openai/chat/completions','Google endpoint incorrect');
 check(in_array('Authorization: Bearer '.$key,$last[1],true)&&$last[2]['model']===$google['model'],'Google auth/model incorrect');
 check(!str_contains(json_encode($last[2]),$key)&&!str_contains($last[0],$key),'Google key leaked into body/URL');
 $settings->save($u,'personal','google','gemini-fixture-override','');
 check($settings->client($u,$transport)?->model()==='gemini-fixture-override','Google model override not retained');
 $settings->save($u,'personal','custom','custom/model',$key,'https://gateway.example/api/v1');
 $settings->client($u,$transport)->chat([['role'=>'user','content'=>'fixture']]);
 check($calls[array_key_last($calls)][0]==='https://gateway.example/api/v1/chat/completions','Custom destination not used');
 denies(fn()=>$settings->save($u,'personal','custom','custom/model','','https://other.example/v1'));
 $db->run("UPDATE user_ai_settings SET base_url='https://other.example/v1' WHERE user_id=?",[$u]);
 denies(fn()=>$settings->client($u,$transport));
 $db->run("UPDATE users SET status='inactive' WHERE id=?",[$u]);denies(fn()=>$settings->client($u));denies(fn()=>$settings->delete($u));
 $db->run("UPDATE users SET status='active' WHERE id=?",[$u]);$settings->delete($u);
 check(!$settings->metadata($u)['has_key']&&$settings->client($u)===null&&$settings->requiresSetup($u),'Deletion enabled platform for ordinary user');
 $settings->save($v,'personal','google','',$key);$settings->usePlatform($v);
 check($settings->client($v)?->model()==='platform-model','Operator could not select platform');
 (new \Pena\Service\OperatorService($db))->changeRoleFromCli('key-owner@example.invalid',true,'Fixture replacement');
 (new \Pena\Service\OperatorService($db))->changeRoleFromCli('key-other@example.invalid',false,'Fixture removal');
 check($settings->client($v)===null&&$settings->requiresSetup($v),'Revoked operator retained platform access');
 echo "PASS AI settings: encrypted keys, authenticated owner/provider/model, rotation, isolated accounts, fixed endpoints, no secret metadata, rate-limited probe, no fallback, inactive denial, delete\n";
}finally{$drop();}
