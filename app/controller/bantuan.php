<?php
use Pena\Service\{UserGuide,PenaException};
use Pena\Support\Db;

class bantuan extends Controller {
    private UserGuide $guide;
    private int $owner;
    public function __construct(){
        parent::__construct();$this->start_default_session_app();header('Cache-Control: private, no-store');
        if(empty($_SESSION['user-id']))$this->_go('/login/');
        $this->owner=(int)$_SESSION['user-id'];$this->guide=new UserGuide(Db::fromTemplate(new Database()));
    }
    public function index(){
        $this->display('bantuan/index',['title'=>'Bantuan — PENA','active_menu'=>'bantuan','org'=>null,'workspaces'=>[]]);
    }
    public function selesai(){
        if($_SERVER['REQUEST_METHOD']!=='POST')$this->abort(405);
        if(!$this->verify_csrf_token())$this->abort(403,'Sesi formulir berakhir. Muat ulang halaman.');
        try{$this->guide->dismiss($this->owner);}catch(PenaException $e){$this->abort(403,$e->getMessage());}
        if(str_contains($_SERVER['HTTP_ACCEPT']??'','application/json'))$this->json_response(['status'=>'ok']);
        $this->_go('/dashboard/');
    }
    private function _go(string $path):never{header('Location: '.APP_PATH.$path);exit;}
}
