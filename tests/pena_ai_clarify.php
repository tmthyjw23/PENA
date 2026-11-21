<?php
declare(strict_types=1);
require __DIR__.'/support/throwaway_db.php';
require dirname(__DIR__).'/app/controller/surat.php';
use Pena\Service\{AiClient,AiDraftStore,LetterAiService,LetterQuery,AiException};
use Pena\Support\Db;
function check(bool $ok,string $why):void{if(!$ok)throw new RuntimeException($why);}
final class ClarifyReply extends RuntimeException { public function __construct(public array $data,public int $status=200){parent::__construct('Fixture response');} }
final class ClarifyController extends surat {
 public array $page=[];
 public function __construct(private AiClient $client){}
 public function start_default_session_app(){}
 protected function _aiClient():?AiClient{return $this->client;}
 public function json_response($data,$status=200){throw new ClarifyReply($data,$status);}
 public function display($view,$data=[]){$this->page=$data;}
}
[$conn,$drop]=pena_throwaway_db('pena_test_clarification');
try{
 $db=new Db($conn);$db->run('UPDATE users SET email_verified_at=NOW() WHERE id=2');$queue=[];$calls=0;
 $client=new AiClient('http://fixture.invalid','fixture-model',null,5,static function()use(&$queue,&$calls){
  $calls++;$out=array_shift($queue);if($out===null)throw new RuntimeException('Unexpected model call');
  return [200,json_encode(['choices'=>[['message'=>['content'=>json_encode($out)]]]])];
 });
 $controller=new ClarifyController($client);(new ReflectionProperty(surat::class,'db'))->setValue($controller,$db);
 $_SESSION=['user-id'=>2,'workspace-id'=>1,'csrf_token'=>'fixture-csrf','user-name'=>'Sekretaris Uji'];
 $_SERVER['REQUEST_METHOD']='POST';$_GET=[];
 $store=new AiDraftStore($_SESSION);$org=$db->one('SELECT * FROM organizations WHERE id=1');
 $step=new ReflectionMethod(surat::class,'_aiStep');
 $begin=static function(string $prompt)use($store){$id=$store->create(2,1);$_POST=['ai_run'=>$id,'ai_revision'=>'0','workspace_id'=>'1','prompt'=>$prompt];return $id;};
 $unsupported=$begin('surat permintaan maaf untuk pacar');
 $queue[]=['status'=>'unsupported','reason'=>'Belum ada kerangka untuk surat permintaan maaf kepada pacar.','options'=>['PMJ','PMH']];
 $reply=$step->invoke($controller,'kerangka',$org,2);$_POST['ai_revision']=(string)$reply['revision'];
 $state=$store->read($unsupported,2,1)['data'];
 check($reply['status']==='unsupported' && isset($state['unsupported']) && !isset($state['clarify']) && !isset($state['template_id']),'Unsupported was forced into a letter type');
 $reply=$step->invoke($controller,'data',$org,2);
 check($reply['status']==='error' && $calls===1,'Unsupported request continued to extraction');
 echo "PASS unsupported: own state, no unrelated options/template, no extraction\n";

 $vague='buat surat kampus untuk kegiatan';$run=$begin($vague);
 $queue[]=['status'=>'clarify','question'=>'Apakah Anda ingin meminjam barang atau mengajukan permohonan?','options'=>['PMJ','PMH']];
 $reply=$step->invoke($controller,'kerangka',$org,2);$_POST['ai_revision']=(string)$reply['revision'];
 $before=$store->read($run,2,1)['data'];
 check(array_column($before['clarify']['options'],'code')===['PMJ','PMH'],'Choices did not retain valid template codes');
 $_POST['template_choice']='IZN';
 $reply=$step->invoke($controller,'kerangka',$org,2);$_POST['ai_revision']=(string)$reply['revision'];
 check($reply['status']==='error' && $calls===2,'Unoffered choice accepted/called model');
 $_POST['template_choice']='PMJ';
 $reply=$step->invoke($controller,'kerangka',$org,2);
 $state=$store->read($run,2,1)['data'];
 check($reply['status']==='ok' && $state['code']==='PMJ' && $state['prompt']===$vague && $calls===2,'Choice did not select exact template without appending/reclassification');
 echo "PASS choice: offered-code validation, immediate exact selection, original prompt unchanged, no extra classification call\n";

 $changed=$begin($vague);
 $queue[]=['status'=>'clarify','question'=>'Meminjam barang atau meminta izin kegiatan?','options'=>['PMJ','PMH']];
 $reply=$step->invoke($controller,'kerangka',$org,2);$_POST['ai_revision']=(string)$reply['revision'];
 $_POST['prompt']='surat permintaan maaf untuk pacar';$_POST['template_choice']='PMJ';
 $queue[]=['status'=>'unsupported','reason'=>'Kerangka untuk tujuan ini belum tersedia.'];
 $reply=$step->invoke($controller,'kerangka',$org,2);
 check($reply['status']==='unsupported' && !isset($store->read($changed,2,1)['data']['template_id']),'Choice from a different prompt forced a new intent');
 $service=new LetterAiService($db,$client);$templates=(new LetterQuery($db))->templates(1);
 $queue[]=['status'=>'clarify','question'=>'Pilih','options'=>['PMJ','IZN']];
 try{$service->recommendTemplate(1,2,$vague,$templates);throw new RuntimeException('Foreign template offered');}catch(AiException){}
 echo "PASS context: changed prompt reclassified, foreign template choices rejected\n";

 $render=getenv('PENA_CLARIFY_PREVIEW');
 if($render){
  if(!is_dir($render))mkdir($render,0700,true);
  $previewRun=$begin($vague);
  $queue[]=['status'=>'clarify','question'=>'Apakah Anda ingin meminjam barang atau mengajukan permohonan?','options'=>['PMJ','PMH']];
  $step->invoke($controller,'kerangka',$org,2);
  $_SERVER['REQUEST_METHOD']='GET';$_POST=[];$_GET=['proses'=>$previewRun];$controller->buat();
  ob_start();(new ReflectionMethod(Controller::class,'display'))->invoke($controller,'surat/generate',$controller->page);$html=ob_get_clean();
  check(str_contains($html,'name="template_choice"') && !str_contains($html,'data-answer'),'View still appends free-text answers');
  file_put_contents($render.'/clarification.html',$html);
  $_GET=['proses'=>$unsupported];$controller->buat();
  ob_start();(new ReflectionMethod(Controller::class,'display'))->invoke($controller,'surat/generate',$controller->page);$html=ob_get_clean();
  check(str_contains($html,'data-unsupported') && !str_contains($html,'name="template_choice"'),'Unsupported view offered unrelated choices');
  file_put_contents($render.'/unsupported.html',$html);
  echo "PASS rendered views: actionable choices versus unsupported with no unrelated buttons\n";
 }
}finally{$conn->close();$drop();}
