<?php
use Pena\Service\{OperatorService,PenaException};use Pena\Support\Db;

class operator extends Controller {
    private OperatorService $operatorService;
    private int $actor;
    public function __construct(){
        parent::__construct();$this->start_default_session_app();
        header('Cache-Control: private, no-store');header('X-Robots-Tag: noindex');
        if(empty($_SESSION['user-id']))$this->_go('/login/');
        $this->actor=(int)$_SESSION['user-id'];$this->operatorService=new OperatorService(Db::fromTemplate(new Database()));
        try{$this->operatorService->requireAccess($this->actor);}catch(PenaException $e){$this->abort(403,$e->getMessage());}
    }
    public function index(){ $this->_page('overview',$this->operatorService->overview($this->actor)); }
    public function akun($id=0){
        if($id!==0&&(!ctype_digit((string)$id)||(int)$id<1))$this->abort(404,'Akun tidak ditemukan.');
        if((int)$id>0){try{$this->_page('account',['account'=>$this->operatorService->account($this->actor,(int)$id)]);}catch(PenaException $e){$this->abort(404,$e->getMessage());}return;}
        $this->_page('users',$this->operatorService->users($this->actor,$this->_search(),$this->_number()));
    }
    public function organisasi(){ $this->_page('organizations',$this->operatorService->organizations($this->actor,$this->_search(),$this->_number())); }
    public function layanan(){ $this->_page('services',$this->operatorService->services($this->actor)); }
    public function log(){ $this->_page('logs',$this->operatorService->logs($this->actor,is_string($_GET['sumber']??null)?$_GET['sumber']:'operator',$this->_number())); }
    public function status($id=0){
        $this->_post();
        try{
            if(!ctype_digit((string)$id)||(int)$id<1)throw new PenaException('Akun tidak ditemukan.');
            $this->operatorService->setAccountStatus($this->actor,(int)$id,(string)($_POST['status']??''),(string)($_POST['reason']??''),($_POST['confirm']??'')==='1');
            $_SESSION['flash_message']=['type'=>'success','text'=>'Status akun diperbarui. Sesi sebelumnya telah diakhiri.'];
        }catch(PenaException $e){$_SESSION['flash_message']=['type'=>'danger','text'=>$e->getMessage()];}
        $this->_go('/operator/akun/'.(int)$id);
    }
    public function uji_ai(){
        $this->_post();
        try{$r=$this->operatorService->testAi($this->actor);$_SESSION['flash_message']=['type'=>$r['status']==='ok'?'success':'danger','text'=>$r['message']];}
        catch(PenaException $e){$_SESSION['flash_message']=['type'=>'danger','text'=>$e->getMessage()];}
        $this->_go('/operator/layanan/');
    }
    private function _post():void {if($_SERVER['REQUEST_METHOD']!=='POST')$this->abort(405);if(!$this->verify_csrf_token())$this->abort(403,'Sesi formulir berakhir. Muat ulang halaman.');}
    private function _number():int{return max(1,min(100000,(int)($_GET['page']??1)));}
    private function _search():string{return is_string($_GET['q']??null)?$_GET['q']:'';}
    private function _go(string $path):never{header('Location: '.APP_PATH.$path);exit;}
    private function _page(string $section,array $values):void{
        $flash=$_SESSION['flash_message']??null;unset($_SESSION['flash_message']);
        $this->display('operator/index',$values+['title'=>'Operator platform — PENA','operator_area'=>true,'active_menu'=>['overview'=>'operator','users'=>'accounts','account'=>'accounts','organizations'=>'organizations','services'=>'services','logs'=>'logs'][$section],
            'section'=>$section,'flash'=>$flash,'org'=>null,'workspaces'=>[]]);
    }
}
