<?php
declare(strict_types=1);
namespace Pena\Service;

use Closure;
use Pena\Support\Db;

/** Owns user credentials; callers receive safe metadata or a ready client, never a decrypted key. */
final class AiSettings
{
    public const PROVIDERS = [
        'openrouter' => ['name'=>'OpenRouter', 'url'=>'https://openrouter.ai/api/v1'],
        'openai' => ['name'=>'OpenAI', 'url'=>'https://api.openai.com/v1'],
        'google' => ['name'=>'Google AI Studio (Gemini)', 'url'=>'https://generativelanguage.googleapis.com/v1beta/openai', 'default_model'=>'gemini-3.5-flash-lite'],
        'groq' => ['name'=>'Groq', 'url'=>'https://api.groq.com/openai/v1'],
        'custom' => ['name'=>'Endpoint kustom', 'url'=>''],
    ];

    public function __construct(private readonly Db $db) {}

    public function metadata(int $userId): array
    {
        $this->requireUser($userId);
        $row=$this->db->one('SELECT mode,provider,model,base_url,revision,probe_at,updated_at FROM user_ai_settings WHERE user_id=?',[$userId]);
        $platform=(new OperatorService($this->db))->allowed($userId);
        if($row&&$row['mode']==='platform'&&!$platform)$row['mode']='unconfigured';
        return ($row??['mode'=>$platform?'platform':'unconfigured','provider'=>'openrouter','model'=>'','base_url'=>'','revision'=>'default','probe_at'=>null,'updated_at'=>null])
            + ['platform_allowed'=>$platform,'has_key'=>$row!==null,'storage_ready'=>$this->storageReady()];
    }

    public function save(int $userId, string $mode, string $provider, string $model, #[\SensitiveParameter] string $key, string $baseUrl=''): void
    {
        $this->requireUser($userId);
        if($mode==='platform')(new OperatorService($this->db))->requireAccess($userId);
        if(!in_array($mode,['platform','personal'],true))throw new PenaException('Pilih AI bawaan atau AI pribadi.');
        if(!isset(self::PROVIDERS[$provider]))throw new PenaException('Provider belum didukung.');
        $baseUrl=$provider==='custom'?AiEndpoint::normalize(trim($baseUrl)):self::PROVIDERS[$provider]['url'];
        $model=trim($model);
        if($model==='')$model=self::PROVIDERS[$provider]['default_model']??'';
        if(!preg_match('~^[a-zA-Z0-9][a-zA-Z0-9._:/@+\-]{0,149}$~D',$model))throw new PenaException('Isi ID model dari provider, maksimal 150 karakter.');
        if($key!==''&&(!preg_match('/^[\x21-\x7E]{8,4096}$/D',$key)))throw new PenaException('API key harus berisi 8–4.096 karakter tanpa spasi atau baris baru.');
        $this->db->transaction(function()use($userId,$mode,$provider,$model,$key,$baseUrl):void{
            // Serialize concurrent edits for one owner, including the first insert.
            $this->db->one('SELECT id FROM users WHERE id=? FOR UPDATE',[$userId]);
            $old=$this->db->one('SELECT * FROM user_ai_settings WHERE user_id=?',[$userId]);
            if($key===''){
                if(!$old||$old['provider']!==$provider||$old['base_url']!==$baseUrl)throw new PenaException('Masukkan API key untuk provider dan endpoint ini.');
                $key=$this->decrypt($old,$userId);
            }
            if(hash_equals($key,$model))throw new PenaException('Isi ID model, bukan API key.');
            $encrypted=$this->encrypt($key,$userId,$provider,$model,$baseUrl);
            $revision=bin2hex(random_bytes(16));
            $this->db->run('INSERT INTO user_ai_settings(user_id,mode,provider,model,base_url,encrypted_key,revision) VALUES(?,?,?,?,?,?,?) ON DUPLICATE KEY UPDATE mode=VALUES(mode),provider=VALUES(provider),model=VALUES(model),base_url=VALUES(base_url),encrypted_key=VALUES(encrypted_key),revision=VALUES(revision),probe_at=NULL,updated_at=CURRENT_TIMESTAMP',[$userId,$mode,$provider,$model,$baseUrl,$encrypted,$revision]);
        });
    }

    public function usePlatform(int $userId): void
    {
        (new OperatorService($this->db))->requireAccess($userId);
        $this->db->run("UPDATE user_ai_settings SET mode='platform',revision=?,updated_at=CURRENT_TIMESTAMP WHERE user_id=?",[bin2hex(random_bytes(16)),$userId]);
    }

    /** Configuration only: login never sends a provider request or consumes quota. */
    public function requiresSetup(int $userId): bool
    {
        $this->requireUser($userId);
        if((new OperatorService($this->db))->allowed($userId))return false;
        return !$this->db->value("SELECT user_id FROM user_ai_settings WHERE user_id=? AND mode='personal'",[$userId]);
    }

    public function delete(int $userId): void
    {
        $this->requireUser($userId);
        $this->db->run('DELETE FROM user_ai_settings WHERE user_id=?',[$userId]);
    }

    public function client(int $userId, ?Closure $transport=null): ?AiClient
    {
        $this->requireUser($userId);
        $row=$this->db->one('SELECT * FROM user_ai_settings WHERE user_id=?',[$userId]);
        if(!$row||$row['mode']==='platform'){
            return (new OperatorService($this->db))->allowed($userId)?AiClient::fromEnv($transport):null;
        }
        $provider=self::PROVIDERS[$row['provider']]??null;
        if(!$provider)throw new PenaException('Provider pribadi tidak tersedia. Atur ulang koneksi AI.');
        $url=$row['provider']==='custom'?AiEndpoint::normalize($row['base_url']):$provider['url'];
        return new AiClient($url,$row['model'],$this->decrypt($row,$userId),max(5,min(120,(int)($_ENV['AI_TIMEOUT']??60))),$transport,$row['provider']==='openai',true);
    }

    /** Explicit manual test, one call with fictional data. Timestamp reserves the rate limit even on failure. */
    public function probe(int $userId, ?Closure $transport=null): void
    {
        $this->requireUser($userId);
        $limiter=new RateLimiter($this->db);
        if(!$limiter->allow('ai-private-probe:'.$userId,5,3600))throw new PenaException('Batas lima uji koneksi per jam tercapai. Coba lagi nanti.');
        $client=$this->db->transaction(function()use($userId,$transport):?AiClient{
            $reserved=$this->db->run("UPDATE user_ai_settings SET probe_at=UTC_TIMESTAMP() WHERE user_id=? AND mode='personal' AND (probe_at IS NULL OR probe_at<DATE_SUB(UTC_TIMESTAMP(),INTERVAL 1 MINUTE))",[$userId]);
            if($reserved!==1)throw new PenaException('Aktifkan AI pribadi terlebih dahulu. Jeda antar-uji adalah satu menit.');
            return $this->client($userId,$transport);
        });
        if(!$client)throw new PenaException('Koneksi belum tersedia.');
        $client->chat([['role'=>'user','content'=>'Balas hanya: koneksi berhasil. Ini uji koneksi, tanpa data surat.']],0.2,128);
    }

    private function requireUser(int $id):void
    {
        if(!$this->db->value("SELECT id FROM users WHERE id=? AND status='active' AND email_verified_at IS NOT NULL",[$id]))throw new PenaException('Akun aktif dan email terverifikasi diperlukan.');
    }

    private function storageReady():bool
    {
        $key=$this->masterValue();
        return extension_loaded('openssl')&&is_string($key)&&strlen($key)===32;
    }

    private function master():string
    {
        if(!$this->storageReady())throw new PenaException('Penyimpanan API key belum tersedia. Hubungi pengelola PENA.');
        return (string)$this->masterValue();
    }

    private function masterValue():string|false
    {
        $encoded=(string)($_ENV['AI_CREDENTIAL_KEY']??'');
        $file=(string)($_ENV['AI_CREDENTIAL_KEY_FILE']??'');
        if($encoded===''&&$file!=='')$encoded=is_readable($file)?trim((string)file_get_contents($file)):'';
        return base64_decode($encoded,true);
    }

    private function encrypt(#[\SensitiveParameter] string $key,int $userId,string $provider,string $model,string $baseUrl):string
    {
        $iv=random_bytes(12);$tag='';
        $cipher=openssl_encrypt($key,'aes-256-gcm',$this->master(),OPENSSL_RAW_DATA,$iv,$tag,"pena-ai:v1:$userId:$provider:$model:$baseUrl",16);
        if($cipher===false)throw new PenaException('API key belum dapat disimpan.');
        return 'v1:'.base64_encode($iv.$tag.$cipher);
    }

    private function decrypt(#[\SensitiveParameter] array $row,int $userId):string
    {
        $raw=str_starts_with($row['encrypted_key'],'v1:')?base64_decode(substr($row['encrypted_key'],3),true):false;
        if(!is_string($raw)||strlen($raw)<36)throw new PenaException('Kunci tersimpan tidak dapat dibuka. Masukkan kembali API key.');
        $key=openssl_decrypt(substr($raw,28),'aes-256-gcm',$this->master(),OPENSSL_RAW_DATA,substr($raw,0,12),substr($raw,12,16),"pena-ai:v1:$userId:{$row['provider']}:{$row['model']}:{$row['base_url']}");
        if(!is_string($key)||$key==='')throw new PenaException('Kunci tersimpan tidak dapat dibuka. Masukkan kembali API key.');
        return $key;
    }
}
