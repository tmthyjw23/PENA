<?php
declare(strict_types=1);
require __DIR__.'/support/throwaway_db.php';
use Pena\Service\{OperatorService,WorkspaceService,AccountService,AiClient,PenaException};use Pena\Support\Db;
function check(bool $ok,string $why):void{if(!$ok)throw new RuntimeException($why);}
function denies(callable $fn):void{try{$fn();}catch(PenaException){return;}throw new RuntimeException('Expected rejection');}
[$conn,$drop]=pena_throwaway_db('pena_test_operator');
try{
 $db=new Db($conn);$spaces=new WorkspaceService($db);$ops=new OperatorService($db);
 $actor=$spaces->register('Penguji Operator','operator@example.invalid','Fixture-Operator-123');
 $user=$spaces->register('Penguji Akun','normal@example.invalid','Fixture-Account-123');
 $other=$spaces->register('Operator Pengganti','operator-two@example.invalid','Fixture-Other-123');
 check((int)$db->value('SELECT COUNT(*) FROM platform_operators')===0,'Migration auto-granted platform role');
 check(!$ops->allowed(1)&&!$ops->allowed($actor),'Legacy name/first signup got platform authority');
 foreach([fn()=>$ops->overview($user),fn()=>$ops->users($user,'',1),fn()=>$ops->account($user,$actor),fn()=>$ops->organizations($user,'',1),fn()=>$ops->services($user),fn()=>$ops->logs($user,'ai',1)]as$read)denies($read);
 denies(fn()=>$ops->changeRoleFromCli('operator@example.invalid',true,'Pemberian hak uji'));
 $db->run('UPDATE users SET email_verified_at=NOW() WHERE id IN(?,?,?)',[$actor,$user,$other]);
 $ops->changeRoleFromCli('operator@example.invalid',true,'Bootstrap operator pengujian');
 check($ops->allowed($actor)&&!$ops->allowed($user),'Explicit operator grant failed');
 denies(fn()=>$ops->changeRoleFromCli('operator@example.invalid',false,'Mencabut operator terakhir'));
 denies(fn()=>$ops->setAccountStatus($actor,$actor,'inactive','Mencoba menonaktifkan diri',true));
 denies(fn()=>$ops->setAccountStatus($user,$actor,'inactive','Pemanggil bukan operator',true));
 denies(fn()=>$ops->setAccountStatus($actor,$user,'inactive','Alasan valid uji',false));
 denies(fn()=>$ops->setAccountStatus($actor,$user,'inactive','pendek',true));
 denies(fn()=>$ops->setAccountStatus($actor,$user,'invalid','Alasan valid uji',true));
 $version=(int)$db->value('SELECT auth_version FROM users WHERE id=?',[$user]);
 $db->run("INSERT INTO account_tokens(user_id,purpose,token_hash,expires_at) VALUES(?,'reset_password',?,DATE_ADD(NOW(),INTERVAL 1 DAY))",[$user,hash('sha256','fixture-token')]);
 $ops->setAccountStatus($actor,$user,'inactive','Penonaktifan akun pengujian',true);
 $state=$db->one('SELECT status,auth_version,email_verified_at FROM users WHERE id=?',[$user]);
 check($state['status']==='inactive'&&!AccountService::sessionIsCurrent($state,$version),'Account sessions remained valid');
 check($db->value('SELECT used_at FROM account_tokens WHERE user_id=?',[$user])!==null,'Recovery token survived deactivation');
 $ops->setAccountStatus($actor,$user,'active','Mengaktifkan kembali akun uji',true);
 $state=$db->one('SELECT status,auth_version,email_verified_at FROM users WHERE id=?',[$user]);
 check($state['status']==='active'&&!AccountService::sessionIsCurrent($state,$version),'Old sessions revived on activation');
 check((int)$db->value("SELECT COUNT(*) FROM operator_events WHERE action='account_status'")===2,'Status audit did not match changes');
 $ops->changeRoleFromCli('operator-two@example.invalid',true,'Menyiapkan operator pengganti');
 $ops->changeRoleFromCli('operator-two@example.invalid',false,'Mencabut operator pengganti uji');
 check(!$ops->allowed($other)&&$ops->allowed($actor),'Role revocation failed');
 echo "PASS operator authority: no auto role, verified explicit grant, non-operator denial, protected operator accounts, last-role guard, audit and session/token revocation\n";

 $secret='CANARY_PRIVATE_PROMPT_OR_KEY';$db->run("INSERT INTO ai_generations(organization_id,purpose,model,input,output,validation,status,created_by) VALUES(1,'generate','fixture-model',?,?,?,'error',?)",[json_encode(['prompt'=>$secret]),json_encode(['text'=>$secret]),json_encode(['raw'=>$secret]),$user]);
 $log=$ops->logs($actor,'ai',1);check(!str_contains(json_encode($log),$secret),'Raw AI content leaked into operator logs');
 $list=$ops->users($actor,'normal@example.invalid',1);check(count($list['rows'])===1&&!isset($list['rows'][0]['password']),'Account search leaked hash or failed');
 $_ENV['AI_BASE_URL']='http://fixture.invalid/private-endpoint';$_ENV['AI_MODEL']='fixture-model';$_ENV['AI_API_KEY']=$secret;$_ENV['RESEND_API_KEY']='CANARY_RESEND_PRIVATE';$_ENV['MAIL_FROM']='PENA <fixture@example.invalid>';
 $service=$ops->services($actor);$safe=json_encode($service);
 check(!str_contains($safe,$secret)&&!str_contains($safe,'CANARY_RESEND_PRIVATE')&&!str_contains($safe,'private-endpoint'),'Services page exposed credentials or origin');
 check($service['ai_configured']&&$service['email_configured'],'Configured status incorrect');
 $_ENV['AI_MODEL']=$secret;check(!str_contains(json_encode($ops->services($actor)),$secret),'Misconfigured model exposed an API key');$_ENV['AI_MODEL']='fixture-model';
 denies(fn()=>$ops->setAccountStatus($actor,$user,'inactive','Reason '.$secret,true));
 $calls=0;$client=new AiClient('http://fixture.invalid','fixture-model',null,5,static function()use(&$calls){$calls++;return[200,'{"model":"fixture-model","choices":[{"message":{"content":"{\\"ok\\":true}"}}]}'];});
 denies(fn()=>$ops->testAi($user,$client));check($calls===0,'Unauthorized operator called provider');
 $result=$ops->testAi($actor,$client);check($result['status']==='ok'&&$calls===1,'AI health test failed');
 denies(fn()=>$ops->testAi($actor,$client));check($calls===1,'Health probe rate limit bypassed');
 $ops->changeRoleFromCli('operator-two@example.invalid',true,'Operator kedua untuk uji gagal');
 $failure=new AiClient('http://fixture.invalid','fixture-model',null,5,static fn()=>[429,'{"error":{"message":"'.$secret.'"}}']);
 $result=$ops->testAi($other,$failure);check($result['status']==='error','Failed health test shown as successful');
 check(!str_contains(json_encode($ops->services($actor)),$secret),'Provider error leaked into saved health result');
 check(!str_contains(json_encode($ops->logs($actor,'operator',1)),$secret),'Operator audit exposed key');
 echo "PASS operator privacy/health: selected metadata only, no prompt/body/token/key/origin leak, provider authorization, success/failure separation and probe limits\n";
 $hash=password_hash('Fixture-Only-123',PASSWORD_DEFAULT);
 for($i=0;$i<28;$i++)$db->run('INSERT INTO users(username,password,nama_lengkap,email) VALUES(?,?,?,?)',['page'.$i,$hash,'Pagination '.$i,'page'.$i.'@example.invalid']);
 check(count($ops->users($actor,'Pagination',1)['rows'])===25&&count($ops->users($actor,'Pagination',2)['rows'])===3,'Bounded pagination failed');
 $ops->overview($actor);$org=$ops->organizations($actor,'',1);check(!isset($org['rows'][0]['definition'])&&!isset($org['rows'][0]['snapshot']),'Organization list exposed documents');
 echo "PASS operator read models: bounded search/pagination, organization metadata, overview and log queries\n";
}finally{$conn->close();$drop();}
