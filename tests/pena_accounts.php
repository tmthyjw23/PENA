<?php
declare(strict_types=1);
require __DIR__.'/support/throwaway_db.php';
use Pena\Service\{AccountService,WorkspaceService,ResendMailer,PenaException};
use Pena\Support\Db;

function check(bool $condition,string $message): void { if(!$condition) throw new RuntimeException($message); }
function rejects(callable $action): void {
    try { $action(); } catch(PenaException) { return; }
    throw new RuntimeException('Expected safe rejection');
}
function token(array $message): string {
    if(!preg_match('/#([a-f0-9]{64})/',$message['text'],$match)) throw new RuntimeException('Missing fragment token');
    return $match[1];
}
[$conn,$drop]=pena_throwaway_db('pena_test_accounts');
try {
    $db=new Db($conn);
    $spaces=new WorkspaceService($db);
    $messages=[];
    $mailer=new ResendMailer('test-key','PENA <noreply@notify.egg3l.com>',static function($url,$headers,$body) use (&$messages) {
        check($url==='https://api.resend.com/emails','Unexpected email endpoint');
        check(in_array('Authorization: Bearer test-key',$headers,true),'Missing authorization');
        $message=json_decode($body,true,512,JSON_THROW_ON_ERROR);
        check($message['from']==='PENA <noreply@notify.egg3l.com>','Wrong sender domain');
        check(is_string($message['html']??null) && str_contains($message['html'],'<html lang="id">'),'HTML email missing');
        preg_match('/#([a-f0-9]{64})/',$message['text'],$tokenMatch);
        check(isset($tokenMatch[1]) && str_contains($message['html'],'#'.$tokenMatch[1]),'HTML/text token mismatch');
        check(str_contains($message['html'],'role="presentation"') && !str_contains($message['html'],'<script'),'Unsafe email markup');
        $messages[]=$message;
        return [200,'{"id":"test-message-id"}'];
    });
    $now=new DateTimeImmutable('2026-11-18 15:00:00');
    $clock=static function() use (&$now) { return $now; };
    $accounts=new AccountService($db,$mailer,'https://pena.egg3l.site',$clock);
    $uid=$spaces->register('Penguji','akun@example.invalid','old-password-123');
    check($db->value('SELECT email_verified_at FROM users WHERE id=?',[$uid])===null,'New account falsely verified');
    check(!$accounts->hasPendingVerification($uid),'Unsent email displayed as waiting');
    $accounts->sendVerification($uid);
    check($accounts->hasPendingVerification($uid),'Accepted email missing waiting state');
    check(!$accounts->hasPendingVerification($uid+1000),'Waiting state leaked to another account');
    $first=token($messages[0]);
    $row=$db->one('SELECT token_hash,expires_at FROM account_tokens WHERE user_id=?',[$uid]);
    check($row['token_hash']===hash('sha256',$first) && $row['token_hash']!==$first,'Raw token persisted');
    check($row['expires_at']==='2026-11-19 15:00:00','Wrong verification expiry');
    check(str_contains($messages[0]['text'],'https://pena.egg3l.site/akun/konfirmasi/#'),'Link did not use configured app origin');
    $accounts->sendVerification($uid);
    $second=token($messages[1]);
    rejects(fn()=>$accounts->verifyEmail($first));
    rejects(fn()=>$accounts->resetPassword($second,'new-password-123','new-password-123'));
    $accounts->verifyEmail($second);
    rejects(fn()=>$accounts->verifyEmail($second));
    check(!$accounts->hasPendingVerification($uid),'Verified account still waiting');
    check($db->value('SELECT email_verified_at FROM users WHERE id=?',[$uid])!==null,'Email not verified');
    echo "PASS verification: fragment URL, hashed token, expiry, resend revokes old link, purpose, one-use\n";
    $sample=\Pena\Service\AccountEmail::compose(false,'https://example.invalid/?a=1&b="quoted"');
    check(str_contains($sample['html'],'&amp;b=&quot;quoted&quot;'),'Email URL not escaped');
    check(str_contains($sample['text'],'30 menit') && str_contains($sample['html'],'30 menit'),'Reset email expiry mismatch');
    check(str_contains($messages[0]['html'],'24 jam'),'Verification email expiry missing');
    echo "PASS email presentation: HTML/text same token, purpose-specific copy and expiry, escaped URL\n";


    $count=count($messages);
    $accounts->requestPasswordReset('unknown@example.invalid');
    $accounts->requestPasswordReset('invalid');
    check(count($messages)===$count,'Unknown account sent email');
    $accounts->requestPasswordReset(' AKUN@example.invalid ');
    $reset=token($messages[array_key_last($messages)]);
    $before=$db->one('SELECT status,auth_version,password FROM users WHERE id=?',[$uid]);
    check(AccountService::sessionIsCurrent($before,1),'Current session rejected');
    rejects(fn()=>$accounts->resetPassword($reset,'new-password-123','different'));
    rejects(fn()=>$accounts->resetPassword($reset,str_repeat('é',37),str_repeat('é',37)));
    $accounts->resetPassword($reset,' new-password-123 ',' new-password-123 ');
    $after=$db->one('SELECT status,auth_version,password FROM users WHERE id=?',[$uid]);
    check(password_verify(' new-password-123 ',$after['password']),'Password or whitespace lost');
    check(!password_verify('old-password-123',$after['password']),'Old password still works');
    check(!AccountService::sessionIsCurrent($after,1) && AccountService::sessionIsCurrent($after,2),'Old session survived password reset');
    check(!AccountService::sessionIsCurrent($after,null),'Legacy session accepted');
    rejects(fn()=>$accounts->resetPassword($reset,'another-password','another-password'));
    echo "PASS reset: unknown account, validation, password hashing, whitespace, session revocation, one-use\n";

    $expiryUser=$spaces->register('Kedaluwarsa','expiry@example.invalid','old-password-123');
    $accounts->requestPasswordReset('expiry@example.invalid');
    $expired=token($messages[array_key_last($messages)]);
    $now=$now->modify('+30 minutes');
    rejects(fn()=>$accounts->resetPassword($expired,'new-password-123','new-password-123'));
    $accounts->sendVerification($expiryUser);
    $expired=token($messages[array_key_last($messages)]);
    $now=$now->modify('+24 hours');
    rejects(fn()=>$accounts->verifyEmail($expired));
    check(!$accounts->hasPendingVerification($expiryUser),'Expired link still waiting');
    echo "PASS expiry: reset at 30 minutes and verification at 24 hours rejected\n";

    $failureUser=$spaces->register('Gagal','failure@example.invalid','old-password-123');
    $accounts->sendVerification($failureUser);
    $stillValid=token($messages[array_key_last($messages)]);
    $failing=new ResendMailer('test-key','PENA <noreply@notify.egg3l.com>',static fn()=>[401,'{"message":"private provider detail"}']);
    $failedService=new AccountService($db,$failing,'https://pena.egg3l.site',$clock);
    rejects(fn()=>$failedService->sendVerification($failureUser));
    check($accounts->hasPendingVerification($failureUser),'Failed resend lost prior waiting state');
    $accounts->verifyEmail($stillValid);
    $unsent=$spaces->register('Belum Terkirim','unsent@example.invalid','old-password-123');
    rejects(fn()=>$failedService->sendVerification($unsent));
    check(!$accounts->hasPendingVerification($unsent),'Failed first send falsely waiting');
    $bad=new ResendMailer('test-key','PENA <noreply@notify.egg3l.com>',static fn()=>[200,'not-json']);
    rejects(fn()=>$bad->send('test@example.invalid','Test','Test'));
    $bad=new ResendMailer('test-key','PENA <noreply@notify.egg3l.com>',static function(){throw new RuntimeException('private-key-and-token');});
    try { $bad->send('test@example.invalid','Test','Test'); } catch(PenaException $e) {
        check(!str_contains($e->getMessage(),'private'),'Transport detail exposed');
    }
    $budgetUser=$spaces->register('Batas','limit@example.invalid','old-password-123');
    for($i=0;$i<3;$i++)$accounts->sendVerification($budgetUser);
    $count=count($messages);
    rejects(fn()=>$accounts->sendVerification($budgetUser));
    check(count($messages)===$count,'Rate-limited request called provider');
    $inactive=$spaces->register('Nonaktif','inactive@example.invalid','old-password-123');
    $accounts->sendVerification($inactive);
    $inactiveToken=token($messages[array_key_last($messages)]);
    $db->run("UPDATE users SET status='inactive' WHERE id=?",[$inactive]);
    rejects(fn()=>$accounts->verifyEmail($inactiveToken));
    check(!$accounts->hasPendingVerification($inactive),'Inactive account waiting');
    $accounts->requestPasswordReset('inactive@example.invalid');
    check(count($messages)===$count+1,'Inactive account requested recovery');
    echo "PASS email failures: rollback preserves prior link, no false provider success, safe errors, limits, inactive account\n";
    unset($_ENV['RESEND_API_KEY']);
    rejects(fn()=>ResendMailer::fromEnv());
    rejects(fn()=>new AccountService($db,$mailer,'https://pena.egg3l.site/?from=untrusted',$clock));
    echo "PASS configuration: missing key and unsafe application origin rejected; no real email sent\n";
    $controller=(new ReflectionClass(Controller::class))->newInstanceWithoutConstructor();
    $_POST=['password'=>' password with spaces ','password_confirmation'=>' password with spaces '];
    $_REQUEST=$_POST;
    $controller->sanitize_global_inputs();
    check($_POST['password_confirmation']===' password with spaces ','Confirmation whitespace changed');
    $log=tempnam(sys_get_temp_dir(),'pena-account-log-');
    $previous=ini_get('error_log');
    try {
        ini_set('error_log',$log);
        $_SERVER['REQUEST_URI']='/akun/reset/?private-url-token';
        Security::log_incident('test-secret-protection','private-password-and-token');
        $logged=file_get_contents($log);
        check(str_contains($logged,'test-secret-protection') && !str_contains($logged,'private-'),'Request secrets logged');
    } finally { ini_set('error_log',$previous); unlink($log); }
    echo "PASS request handling: both password fields preserve whitespace; security log excludes payload and URL secrets\n";
} finally { $conn->close(); $drop(); }
