<?php
declare(strict_types=1);
namespace Pena\Service;
use Pena\Support\Db;

/** Platform metadata only. Document bodies, tokens, provider credentials and signature images stay outside this boundary. */
final class OperatorService
{
    public function __construct(private readonly Db $db) {}

    public function allowed(int $userId): bool
    {
        return (bool)$this->db->value("SELECT EXISTS(SELECT 1 FROM platform_operators p JOIN users u ON u.id=p.user_id WHERE u.id=? AND u.status='active' AND u.email_verified_at IS NOT NULL)",[$userId]);
    }
    public function requireAccess(int $userId): void
    {
        if(!$this->allowed($userId))throw new PenaException('Akses ini hanya tersedia untuk operator platform.');
    }
    public function overview(int $actor): array
    {
        $this->requireAccess($actor);
        return [
            'users'=>$this->db->one("SELECT COUNT(*) AS total,SUM(status='active') AS active,SUM(email_verified_at IS NULL) AS unverified FROM users"),
            'organizations'=>(int)$this->db->value("SELECT COUNT(*) FROM organizations WHERE kind='organization'"),
            'ai'=>$this->db->one("SELECT COUNT(*) AS total,SUM(status='ok') AS ok,SUM(status<>'ok') AS failed,ROUND(AVG(latency_ms)) AS latency FROM ai_generations WHERE created_at>=DATE_SUB(NOW(),INTERVAL 1 DAY)"),
            'exports'=>$this->db->one("SELECT COUNT(*) AS total,SUM(status='failed') AS failed FROM letter_exports WHERE created_at>=DATE_SUB(NOW(),INTERVAL 1 DAY)"),
        ];
    }
    public function users(int $actor,string $search,int $page): array
    {
        $this->requireAccess($actor);$page=max(1,min(100000,$page));$search=mb_substr(trim($search),0,100);$like='%'.$search.'%';
        $total=(int)$this->db->value('SELECT COUNT(*) FROM users WHERE nama_lengkap LIKE ? OR email LIKE ? OR username LIKE ?',[$like,$like,$like]);
        return ['total'=>$total,'page'=>$page,'search'=>$search,'rows'=>$this->db->all('SELECT u.id,u.nama_lengkap,u.email,u.status,u.email_verified_at,u.last_login,p.user_id AS operator_id FROM users u LEFT JOIN platform_operators p ON p.user_id=u.id WHERE u.nama_lengkap LIKE ? OR u.email LIKE ? OR u.username LIKE ? ORDER BY u.id DESC LIMIT 25 OFFSET ?',[$like,$like,$like,($page-1)*25])];
    }
    public function account(int $actor,int $id): array
    {
        $this->requireAccess($actor);
        $row=$this->db->one('SELECT u.id,u.nama_lengkap,u.email,u.username,u.status,u.email_verified_at,u.last_login,p.user_id AS operator_id FROM users u LEFT JOIN platform_operators p ON p.user_id=u.id WHERE u.id=?',[$id]);
        if(!$row)throw new PenaException('Akun tidak ditemukan.');return $row;
    }
    public function organizations(int $actor,string $search,int $page): array
    {
        $this->requireAccess($actor);$page=max(1,min(100000,$page));$search=mb_substr(trim($search),0,100);$like='%'.$search.'%';
        $total=(int)$this->db->value("SELECT COUNT(*) FROM organizations WHERE kind='organization' AND (name LIKE ? OR short_name LIKE ?)",[$like,$like]);
        return ['total'=>$total,'page'=>$page,'search'=>$search,'rows'=>$this->db->all("SELECT o.id,o.name,o.short_name,u.nama_lengkap AS owner_name,u.status AS owner_status,(SELECT COUNT(*) FROM organization_members m WHERE m.organization_id=o.id) AS members,(SELECT COUNT(*) FROM letters l WHERE l.organization_id=o.id) AS letters FROM organizations o LEFT JOIN users u ON u.id=o.owner_user_id WHERE o.kind='organization' AND (o.name LIKE ? OR o.short_name LIKE ?) ORDER BY o.id DESC LIMIT 25 OFFSET ?",[$like,$like,($page-1)*25])];
    }
    public function setAccountStatus(int $actor,int $id,string $status,string $reason,bool $confirmed): void
    {
        if(!in_array($status,['active','inactive'],true))throw new PenaException('Status akun tidak valid.');
        $reason=$this->reason($reason);
        if(!$confirmed)throw new PenaException('Konfirmasikan perubahan status akun.');
        $this->db->transaction(function(Db $db)use($actor,$id,$status,$reason){
            // Serialize operator authority with a possible CLI revocation.
            $db->all('SELECT user_id FROM platform_operators ORDER BY user_id FOR UPDATE');
            $this->requireAccess($actor);
            $user=$db->one('SELECT id,status FROM users WHERE id=? FOR UPDATE',[$id]);
            if(!$user)throw new PenaException('Akun tidak ditemukan.');
            if($db->value('SELECT user_id FROM platform_operators WHERE user_id=?',[$id])!==null)throw new PenaException('Status akun operator tidak dapat diubah dari halaman ini.');
            if($user['status']===$status)throw new PenaException('Status akun sudah sesuai. Tidak ada perubahan.');
            $db->run('UPDATE users SET status=?,auth_version=auth_version+1 WHERE id=?',[$status,$id]);
            $db->run('UPDATE account_tokens SET used_at=NOW() WHERE user_id=? AND used_at IS NULL',[$id]);
            $this->event($actor,'account_status',$id,$reason,$user['status'],$status);
        });
    }
    /** Server console only; web routes never grant/revoke platform authority. */
    public function changeRoleFromCli(string $email,bool $grant,string $reason): int
    {
        if(PHP_SAPI!=='cli')throw new PenaException('Perubahan hak operator hanya tersedia melalui pengelola server.');
        $reason=$this->reason($reason);
        return $this->db->transaction(function(Db $db)use($email,$grant,$reason):int{
            $db->all('SELECT user_id FROM platform_operators ORDER BY user_id FOR UPDATE');
            $user=$db->one('SELECT id,status,email_verified_at FROM users WHERE email=? FOR UPDATE',[mb_strtolower(trim($email))]);
            if(!$user)throw new PenaException('Akun tidak ditemukan.');$id=(int)$user['id'];
            $exists=$db->value('SELECT user_id FROM platform_operators WHERE user_id=?',[$id])!==null;
            if($grant){
                if($user['status']!=='active'||$user['email_verified_at']===null)throw new PenaException('Operator harus memakai akun aktif dengan email terverifikasi.');
                if($exists)throw new PenaException('Akun sudah memiliki hak operator.');
                $db->run('INSERT INTO platform_operators(user_id) VALUES(?)',[$id]);
            }else{
                if(!$exists)throw new PenaException('Akun tidak memiliki hak operator.');
                $remaining=(int)$db->value("SELECT COUNT(*) FROM platform_operators p JOIN users u ON u.id=p.user_id WHERE p.user_id<>? AND u.status='active' AND u.email_verified_at IS NOT NULL",[$id]);
                if($remaining<1)throw new PenaException('Hak operator terakhir tidak dapat dicabut. Tetapkan pengganti terlebih dahulu.');
                $db->run('DELETE FROM platform_operators WHERE user_id=?',[$id]);
            }
            $db->run('UPDATE users SET auth_version=auth_version+1 WHERE id=?',[$id]);
            $this->event(null,$grant?'operator_granted':'operator_revoked',$id,$reason,null,null);return $id;
        });
    }
    public function services(int $actor): array
    {
        $this->requireAccess($actor);
        $configured=trim((string)($_ENV['AI_BASE_URL']??''))!==''&&trim((string)($_ENV['AI_MODEL']??''))!=='';
        return ['ai_configured'=>$configured,'model'=>$this->modelLabel((string)($_ENV['AI_MODEL']??'')),'ai_key'=>trim((string)($_ENV['AI_API_KEY']??''))!=='',
            'email_configured'=>trim((string)($_ENV['RESEND_API_KEY']??''))!==''&&trim((string)($_ENV['MAIL_FROM']??''))!=='',
            'pdf_available'=>(PHP_OS_FAMILY==='Windows'?new WordPdfConverter():new LibreOfficePdfConverter())->isAvailable(),
            'last_check'=>$this->db->one('SELECT status,model,latency_ms,message,created_at FROM operator_checks ORDER BY id DESC LIMIT 1')];
    }
    public function testAi(int $actor,?AiClient $client=null): array
    {
        $this->requireAccess($actor);$limit=new RateLimiter($this->db);
        if(!$limit->allow('operator-ai:'.$actor,1,60)||!$limit->allow('operator-ai:platform',5,3600))throw new PenaException('Batas uji koneksi tercapai. Coba lagi nanti.');
        $client??=AiClient::fromEnv();if(!$client)throw new PenaException('Konfigurasi AI belum tersedia.');
        $status='error';$latency=null;$model=$client->model();$message='AI belum memberi respons yang sesuai.';
        try{
            $result=$client->chat([['role'=>'user','content'=>'Jawab hanya JSON: {"ok":true}']],0.2,256);
            $out=json_decode(trim((string)preg_replace('/^```(?:json)?\s*|\s*```$/i','',trim($result['text']))),true);
            $latency=$result['latency_ms'];$model=$result['model'];
            if(($out['ok']??null)===true){$status='ok';$message='Uji koneksi AI berhasil.';}
        }catch(AiException $e){$message='Uji koneksi AI gagal. Periksa ketersediaan model dan batas provider.';$latency=$e->latencyMs;}
        $model=$this->modelLabel($model);
        $this->db->insert('INSERT INTO operator_checks(actor_user_id,status,model,latency_ms,message) VALUES(?,?,?,?,?)',[$actor,$status,$model,$latency,$message]);
        $this->event($actor,'ai_test',null,$status==='ok'?'Uji koneksi berhasil':'Uji koneksi gagal',null,null);
        return ['status'=>$status,'message'=>$message];
    }
    public function logs(int $actor,string $source,int $page): array
    {
        $this->requireAccess($actor);$page=max(1,min(100000,$page));$offset=($page-1)*25;
        if($source==='ai'){$table='ai_generations';$select="SELECT id,model AS label,status AS result,latency_ms,created_at FROM ai_generations";}
        elseif($source==='export'){$table='letter_exports';$select="SELECT id,format AS label,status AS result,NULL AS latency_ms,created_at FROM letter_exports";}
        else{$source='operator';$table='operator_events';$select="SELECT e.id,e.action AS label,e.reason,e.before_status,e.after_status,e.target_user_id,u.nama_lengkap AS actor_name,e.created_at FROM operator_events e LEFT JOIN users u ON u.id=e.actor_user_id";}
        $rows=$this->db->all($select.' ORDER BY '.($source==='operator'?'e.id':'id').' DESC LIMIT 25 OFFSET ?',[$offset]);
        if($source==='ai')foreach($rows as &$row)$row['label']=$this->modelLabel($row['label']);unset($row);
        return ['source'=>$source,'page'=>$page,'total'=>(int)$this->db->value('SELECT COUNT(*) FROM '.$table),'rows'=>$rows];
    }
    private function modelLabel(string $model):string
    {
        if($model==='')return '';
        foreach(['AI_API_KEY','RESEND_API_KEY']as$key)if(!empty($_ENV[$key])&&str_contains($model,(string)$_ENV[$key]))return 'Nama model disamarkan';
        return preg_match('/^[A-Za-z0-9][A-Za-z0-9_.:\/@+-]{0,149}$/D',$model)?$model:'Nama model tidak valid';
    }
    private function reason(string $reason): string
    {
        $reason=trim($reason);
        foreach(['AI_API_KEY','RESEND_API_KEY']as$key)if(!empty($_ENV[$key])&&str_contains($reason,(string)$_ENV[$key]))throw new PenaException('Hapus kredensial dari alasan perubahan.');
        if(mb_strlen($reason)<8||mb_strlen($reason)>300)throw new PenaException('Tuliskan alasan perubahan, 8–300 karakter.');
        if(preg_match('/(?:Authorization\s*:|BEGIN .*PRIVATE KEY|\b(?:sk-|sk_)[a-zA-Z0-9_-]{20,})/i',$reason))throw new PenaException('Hapus kredensial dari alasan perubahan.');
        return $reason;
    }
    private function event(?int $actor,string $action,?int $target,string $reason,?string $before,?string $after):void
    {
        $this->db->insert('INSERT INTO operator_events(actor_user_id,action,target_user_id,reason,before_status,after_status) VALUES(?,?,?,?,?,?)',[$actor,$action,$target,$reason,$before,$after]);
    }
}
