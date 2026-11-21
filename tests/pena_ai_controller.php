<?php
declare(strict_types=1);
require __DIR__.'/support/throwaway_db.php';
require dirname(__DIR__).'/app/controller/surat.php';
use Pena\Service\{AiClient,AiDraftStore,LetterQuery};
use Pena\Support\Db;
function check(bool $ok,string $why):void{if(!$ok)throw new RuntimeException($why);}
final class TestAiReply extends RuntimeException {
    public function __construct(public readonly array $data,public readonly int $status=200){parent::__construct('Fixture response');}
}
final class TestAiRedirect extends RuntimeException {
    public function __construct(public readonly string $path){parent::__construct('Fixture redirect');}
}
final class SuratAiFixture extends surat {
    public array $page=[];
    public function __construct(private AiClient $client){}
    public function start_default_session_app(){}
    protected function _aiClient():?AiClient{return $this->client;}
    protected function _go(string $path):never{throw new TestAiRedirect($path);}
    public function json_response($data,$status=200){throw new TestAiReply($data,$status);}
    public function display($view,$data=[]){$this->page=$data;}
}
[$conn,$drop]=pena_throwaway_db('pena_test_ai_controller');
try{
    $db=new Db($conn);$db->run('UPDATE users SET email_verified_at=NOW() WHERE id=2');$queue=[];$requests=[];
    $client=new AiClient('http://fixture.invalid/v1','fixture-model','fixture-key',5,
        static function($url,$headers,$body)use(&$queue,&$requests){
            $requests[]=json_decode($body,true);$out=array_shift($queue);
            if($out===null)throw new RuntimeException('Unexpected provider call');
            return [200,json_encode(['model'=>'fixture-model','choices'=>[['message'=>['content'=>json_encode($out)]]]])];
        });
    $controller=new SuratAiFixture($client);
    (new ReflectionProperty(surat::class,'db'))->setValue($controller,$db);
    $org=$db->one('SELECT * FROM organizations WHERE id=1');
    $_SESSION=['user-id'=>2,'workspace-id'=>1,'csrf_token'=>'fixture-csrf'];
    $_SERVER['REQUEST_METHOD']='POST';$_POST=['csrf_token'=>'fixture-csrf','workspace_id'=>'1'];
    $store=new AiDraftStore($_SESSION);$run=$store->create(2,1);$other=$store->create(2,1);
    $_POST+=['ai_run'=>$run,'ai_revision'=>'0','prompt'=>'Kami meminjam kursi untuk Kegiatan Uji di Aula Kampus pada 21 November 2026 kepada Bagian Umum.'];
    $queue[]=['status'=>'ok','template'=>'PMJ','reason'=>'meminjam kursi','title'=>'Peminjaman kegiatan uji'];
    $queue[]=['fields'=>[
        'nama_kegiatan'=>['value'=>'Kegiatan Uji','quote'=>'Kegiatan Uji'],
        'tempat_kegiatan'=>['value'=>'Aula Kampus','quote'=>'Aula Kampus'],
        'tanggal_mulai'=>['value'=>'2026-11-21','quote'=>'21 November 2026'],
        'penerima_jabatan'=>['value'=>'Bagian Umum','quote'=>'Bagian Umum'],
    ],'route'=>[],'missing'=>[]];
    $queue[]=['blocks'=>['maksud'=>'Untuk {{nama_kegiatan}} di {{tempat_kegiatan}} pada {{tanggal_mulai}}, kami memohon peminjaman barang.','ketentuan'=>'Barang akan dijaga dengan baik.']];
    $step=new ReflectionMethod(surat::class,'_aiStep');
    foreach(['kerangka','data','paragraf']as$name){
        $reply=$step->invoke($controller,$name,$org,2);
        check($reply['status']==='ok','AI step failed: '.$name);
        $_POST['ai_revision']=(string)$reply['revision'];
    }
    check(count($requests)===3,'Unexpected call count');
    $_ENV['AI_CREDENTIAL_KEY']=base64_encode(random_bytes(32));
    $settings=new \Pena\Service\AiSettings($db);
    $settings->save(2,'personal','openrouter','fixture-model','fixture-personal-key');
    $blocked=$step->invoke($controller,'data',$org,2);
    check($blocked['status']==='error'&&count($requests)===3,'Provider switch continued a run without confirmation');
    $settings->delete(2); // Restore the original platform connection for the existing run.

    check($store->read($other,2,1)['data']===[],'Other process overwritten');
    $posted=$_POST;
    $_SERVER['REQUEST_METHOD']='GET';$_GET=['proses'=>$run];$_POST=[];
    $controller->hasil();
    check($controller->page['ai_run']===$run && $controller->page['ai_revision']===(int)$posted['ai_revision'],'Result page lost process/revision');
    check($controller->page['state']['blocks']['maksud']['text']!=='','Result page lost generated text');
    $_GET=[];
    try{$controller->buat();throw new RuntimeException('New form did not allocate process');}
    catch(TestAiRedirect $redirect){check(str_starts_with($redirect->path,'/surat/buat/?proses='),'New process URL wrong');}
    check($store->read($run,2,1)['data']['blocks']!==[],'Opening a new form cleared the result');
    $_GET=[];$_POST=$posted;$_SERVER['REQUEST_METHOD']='POST';
    $revision=$_POST['ai_revision'];$_POST['ai_revision']='0';
    try{$step->invoke($controller,'data',$org,2);throw new RuntimeException('Stale continuation accepted');}
    catch(TestAiReply $reply){check($reply->status===409,'Stale request status');}
    $_POST['ai_revision']=$revision;$_POST['workspace_id']='99';
    try{$step->invoke($controller,'data',$org,2);throw new RuntimeException('Wrong workspace accepted');}
    catch(TestAiReply $reply){check($reply->status===409,'Workspace request status');}
    check(count($requests)===3,'Rejected request called provider');
    $_POST['workspace_id']='1';$_POST['fields']=['nama_kegiatan'=>'Kegiatan Uji','tempat_kegiatan'=>'Ruang Koreksi','tanggal_mulai'=>'2026-11-21'];
    $_POST['block']='maksud';$_POST['current']='Untuk {{nama_kegiatan}} di {{tempat_kegiatan}}.';$_POST['instruction']='Lebih singkat';
    $queue[]=['text'=>'Kami memohon peminjaman untuk {{nama_kegiatan}} di {{tempat_kegiatan}}.'];
    try{$controller->tulis_ulang();throw new RuntimeException('No rewrite response');}
    catch(TestAiReply $reply){check($reply->data['status']==='ok','Rewrite rejected');$_POST['ai_revision']=(string)$reply->data['revision'];}
    check(!str_contains($requests[3]['messages'][1]['content'],'{{penerima_jabatan}}'),'Rewrite kept a field cleared by the user');
    $state=$store->read($run,2,1)['data'];
    check($state['fields']['tempat_kegiatan']['value']==='Ruang Koreksi','Correction not retained');
    check($state['fields']['tempat_kegiatan']['quote']===null && $state['fields']['tempat_kegiatan']['note']==='Dikoreksi oleh Anda','Old AI evidence survived correction');
    $_POST['title']='Surat dikoreksi';$_POST['letterhead_id']='1';$_POST['stages']=[];
    $_POST['blocks']=array_map(static fn($block)=>$block['text'],$state['blocks']);
    try{$controller->simpan_ai();throw new RuntimeException('Missing approval plan accepted');}
    catch(TestAiRedirect $redirect){check(str_starts_with($redirect->path,'/surat/hasil/'),'Validation did not return to process');}
    check((int)$db->value('SELECT COUNT(*) FROM letters')===0,'Failed save left partial draft');
    check($store->read($run,2,1)['data']['fields']['tempat_kegiatan']['value']==='Ruang Koreksi','Failed save lost edits');
    $_POST['ai_revision']=(string)$store->read($run,2,1)['revision'];
    $_POST['stages']=[['name'=>'Ketua','signer_ids'=>[1]]];
    try{$controller->simpan_ai();throw new RuntimeException('No save redirect');}
    catch(TestAiRedirect $redirect){check(str_starts_with($redirect->path,'/surat/detail/'),'Wrong save redirect');}
    $saved=$db->one("SELECT id,current_version_id FROM letters WHERE title='Surat dikoreksi'");
    check($saved!==null,'Draft not saved');
    $detail=(new LetterQuery($db))->detail((int)$saved['id'],1);
    check($detail['version']['fields']['tempat_kegiatan']==='Ruang Koreksi','Saved draft lost correction');
    check((int)$db->value('SELECT COUNT(*) FROM ai_generations WHERE letter_id=?',[$saved['id']])===4,'AI provenance not linked');
    check($store->read($other,2,1)['data']===[],'Saving cleared another process');
    try{$store->read($run,2,1);throw new RuntimeException('Saved process not removed');}catch(\Pena\Service\PenaException){}
    echo "PASS AI controller: three steps, stale/workspace rejection before provider call, rewrite uses corrected fields, save retains correction/logs and other process\n";
}finally{$conn->close();$drop();}
