<?php
use Pena\Service\{AiSettings,AiException,PenaException};
use Pena\Support\Db;

class preferensi extends Controller {
    private Db $db;
    private AiSettings $settings;
    private int $owner;

    public function __construct(){
        parent::__construct();$this->start_default_session_app();
        header('Cache-Control: private, no-store');header('X-Robots-Tag: noindex');
        if(empty($_SESSION['user-id']))$this->_go('/login/');
        $this->owner=(int)$_SESSION['user-id'];
        $this->db=Db::fromTemplate(new Database());$this->settings=new AiSettings($this->db);
        try{$this->settings->metadata($this->owner);}catch(PenaException $e){$this->abort(403,$e->getMessage());}
    }

    public function index(){
        $flash=$_SESSION['flash_message']??null;unset($_SESSION['flash_message']);
        $this->display('preferensi/ai',['title'=>'Pengaturan AI — PENA','active_menu'=>'ai','settings'=>$this->settings->metadata($this->owner),
            'providers'=>AiSettings::PROVIDERS,'flash'=>$flash,'org'=>null,'workspaces'=>[]]);
    }

    public function simpan(){
        $this->_post();
        try{
            $this->_password();
            $needsSetup=$this->settings->requiresSetup($this->owner);
            if($this->_field('mode')==='platform')$this->settings->usePlatform($this->owner);
            else{
                if($this->_field('consent')!=='1')throw new PenaException('Konfirmasikan pengiriman data dan pemakaian kuota provider.');
                $this->settings->save($this->owner,$this->_field('mode'),$this->_field('provider'),$this->_field('model'),$this->_field('api_key'),$this->_field('base_url'));
            }
            $this->_done('success','Pengaturan AI tersimpan. Berlaku untuk permintaan AI berikutnya dari akun Anda.',$needsSetup?'/dashboard/':'/preferensi/');
        }catch(PenaException $e){$this->_done('danger',$e->getMessage());}
    }

    public function hapus(){
        $this->_post();
        try{$this->_password();$this->settings->delete($this->owner);$this->_done('success','Koneksi pribadi dihapus. '.($this->settings->requiresSetup($this->owner)?'Pilih provider dan simpan kunci baru sebelum membuka ruang kerja. ':'Akun operator kembali memakai AI bawaan. ').'Cabut juga kunci di dashboard provider bila tidak dipakai lagi.');}
        catch(PenaException $e){$this->_done('danger',$e->getMessage());}
    }

    public function uji(){
        $this->_post();
        try{$this->settings->probe($this->owner);$this->_done('success','Provider menjawab uji koneksi. Kesesuaian model untuk menyusun surat tetap perlu dicoba.');}
        catch(AiException|PenaException $e){$this->_done('danger',$e->getMessage());}
    }

    private function _password():void{
        if(!(new \Pena\Service\RateLimiter($this->db))->allow('ai-settings-password:'.$this->owner,10,900))throw new PenaException('Terlalu banyak percobaan. Coba lagi dalam 15 menit.');
        $hash=$this->db->value('SELECT password FROM users WHERE id=?',[$this->owner]);
        if(!is_string($hash)||!password_verify($this->_field('password'),$hash)){
            throw new PenaException('Kata sandi akun tidak cocok. Pengaturan belum diubah.');
        }
    }
    private function _field(string $name):string{return is_string($_POST[$name]??null)?$_POST[$name]:'';}
    private function _post():void{if($_SERVER['REQUEST_METHOD']!=='POST')$this->abort(405);if(!$this->verify_csrf_token())$this->abort(403,'Sesi formulir berakhir. Muat ulang halaman.');}
    private function _done(string $type,string $text,string $path='/preferensi/'):never{$_SESSION['flash_message']=['type'=>$type,'text'=>$text];$this->_go($path);}
    private function _go(string $path):never{header('Location: '.APP_PATH.$path);exit;}
}
