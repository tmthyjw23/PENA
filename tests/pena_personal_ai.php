<?php
declare(strict_types=1);
require __DIR__.'/support/throwaway_db.php';
require dirname(__DIR__).'/app/controller/surat.php';
use Pena\Service\{PersonalTemplate,WorkspaceService,LetterQuery,LetterService,AiClient,AiDraftStore,PenaException,ExportService};
use Pena\Support\Db;
function check(bool $ok,string $why):void{if(!$ok)throw new RuntimeException($why);}
function rejects(callable $fn):void{try{$fn();}catch(PenaException){return;}throw new RuntimeException('Expected rejection');}
final class PersonalTestReply extends RuntimeException {public function __construct(public array $data,public int $status=200){parent::__construct('Fixture response');}}
final class PersonalTestRedirect extends RuntimeException {public function __construct(public string $path){parent::__construct('Fixture redirect');}}
final class PersonalControllerFixture extends surat {
 public array $page=[];
 public function __construct(private AiClient $client){}
 public function start_default_session_app(){}
 protected function _aiClient():?AiClient{return $this->client;}
 protected function _go(string $path):never{throw new PersonalTestRedirect($path);}
 public function json_response($data,$status=200){throw new PersonalTestReply($data,$status);}
 public function abort($code=404,$message=''){throw new PersonalTestReply(['message'=>$message],(int)$code);}
 public function display($view,$data=[]){$this->page=$data;}
}
$design=['status'=>'ok','name'=>'Surat Permintaan Maaf','description'=>'Permintaan maaf pribadi','tone'=>'hangat dan jujur',
 'presentation'=>['show_subject'=>false,'show_date'=>false,'formal_recipient'=>false,'signature_label'=>'Dengan sayang'],
 'fields'=>[['key'=>'nama_penerima','label'=>'Nama penerima','type'=>'text','required'=>false],['key'=>'hal_yang_disesali','label'=>'Hal yang disesali','type'=>'text','required'=>true]],
 'blocks'=>[
  ['id'=>'sapaan','type'=>'paragraph','label'=>'Sapaan pribadi','instruction'=>'Sapa penerima dengan hangat, tanpa bahasa birokratis.','required'=>true],
  ['id'=>'isi','type'=>'paragraph','label'=>'Permintaan maaf','instruction'=>'Minta maaf dengan tulus, jangan mengarang kejadian.','required'=>true],
  ['id'=>'penutup','type'=>'paragraph','label'=>'Penutup','instruction'=>'Penutup hangat tanpa memaksa penerima memaafkan.','required'=>true],
 ]];
$normalized=PersonalTemplate::normalize($design);
check($normalized['definition']['blocks'][0]['source']==='ai','Unsafe block source');
foreach([
 ['fields'=>[['key'=>'x','label'=>'X','type'=>'html','required'=>false]]],
 ['fields'=>[['key'=>'x','label'=>'X','type'=>'text','required'=>false],['key'=>'x','label'=>'X','type'=>'text','required'=>false]]],
 ['blocks'=>[['id'=>'x','type'=>'html','label'=>'X','required'=>true]]],
 ['blocks'=>[['id'=>'x','type'=>'table','label'=>'X','field'=>'missing','required'=>true]]],
 ['presentation'=>['show_subject'=>'false','show_date'=>false,'formal_recipient'=>false]],
]as$bad)rejects(fn()=>PersonalTemplate::normalize(array_replace($design,$bad)));
echo "PASS personal schema: bounded typed fields/blocks, safe sources, duplicate keys/references and invalid presentation rejected\n";
[$conn,$drop]=pena_throwaway_db('pena_test_personal_ai');
$exports=sys_get_temp_dir().'/pena-personal-'.bin2hex(random_bytes(8));
try{
 $db=new Db($conn);$spaces=new WorkspaceService($db);$query=new LetterQuery($db);
 $user=$spaces->register('Penguji Pribadi','personal-ai@example.invalid','fixture-password-only');
 $db->run('UPDATE users SET email_verified_at=NOW() WHERE id=?',[$user]);
 $orgId=(int)$spaces->forUser($user)[0]['id'];$org=$spaces->requireMember($orgId,$user);
 $queue=[];$sent=[];
 $client=new AiClient('http://fixture.invalid','fixture-model',null,5,static function($url,$headers,$body)use(&$queue,&$sent){
  $sent[]=json_decode($body,true);$out=array_shift($queue);if($out===null)throw new RuntimeException('Unexpected AI call');
  return[200,json_encode(['choices'=>[['message'=>['content'=>json_encode($out)]]]])];
 });
 $controller=new PersonalControllerFixture($client);(new ReflectionProperty(surat::class,'db'))->setValue($controller,$db);
 $_SESSION=['user-id'=>$user,'workspace-id'=>$orgId,'user-name'=>'Penguji Pribadi','csrf_token'=>'fixture-csrf'];
 $_SERVER['REQUEST_METHOD']='POST';$_GET=[];
 $store=new AiDraftStore($_SESSION);$run=$store->create($user,$orgId);
 $prompt='Buat surat permintaan maaf untuk pacar Dira karena aku terlambat menepati janji. Gunakan gaya hangat.';
 $_POST=['ai_run'=>$run,'ai_revision'=>'0','workspace_id'=>(string)$orgId,'csrf_token'=>'fixture-csrf','prompt'=>$prompt];
 $_ENV['AI_PERSONAL_MAX_TOKENS']='3100';
 $queue[]=$design;
 $queue[]=['fields'=>['nama_penerima'=>['value'=>'Dira','quote'=>'Dira'],'hal_yang_disesali'=>['value'=>'terlambat menepati janji','quote'=>'terlambat menepati janji']],'route'=>[],'missing'=>[]];
 $queue[]=['blocks'=>['sapaan'=>'Hai {{nama_penerima}},','isi'=>'Aku meminta maaf karena {{hal_yang_disesali}}. Aku ingin lebih menghargai waktu dan kepercayaanmu.','penutup'=>'Terima kasih sudah membaca isi hatiku.']];
 $step=new ReflectionMethod(surat::class,'_aiStep');
 foreach(['kerangka','data','paragraf']as$part){$r=$step->invoke($controller,$part,$org,$user);check($r['status']==='ok','Personal step failed: '.$part);$_POST['ai_revision']=(string)$r['revision'];}
 check(count(array_filter(array_slice($sent,0,3),static fn($request)=>$request['max_tokens']===3100))===3,'Personal design/extract/write response budget not applied');unset($_ENV['AI_PERSONAL_MAX_TOKENS']);
 $state=$store->read($run,$user,$orgId)['data'];
 check(isset($state['dynamic_template'])&&!isset($state['template_id']),'Personal flow still selected closed catalogue');
 check((int)$db->value('SELECT COUNT(*) FROM letter_templates WHERE is_generated=1')===0,'Abandoned form persisted a template');
 check(str_contains($sent[2]['messages'][0]['content'],'hangat'),'Writer lost tone');
 $post=$_POST;$_POST=[];$_GET=['proses'=>$run];$_SERVER['REQUEST_METHOD']='GET';$controller->hasil();
 check($controller->page['preview']['kop']===false && $controller->page['preview']['metadata']===[] && $controller->page['preview']['date']==='' && $controller->page['preview']['recipient']===[],'Informal preview forced formal furniture');
 $previewDir=getenv('PENA_PERSONAL_PREVIEW');
 if($previewDir){
  if(!is_dir($previewDir))mkdir($previewDir,0700,true);
  ob_start();(new ReflectionMethod(Controller::class,'display'))->invoke($controller,'surat/generate_hasil',$controller->page);file_put_contents($previewDir.'/result.html',ob_get_clean());
 }
 $_SERVER['REQUEST_METHOD']='POST';$_GET=[];$_POST=$post;
 $_POST['title']='';$_POST['fields']=['nama_penerima'=>'Dira','hal_yang_disesali'=>'terlambat menepati janji'];$_POST['blocks']=array_map(static fn($b)=>$b['text'],$state['blocks']);$_POST['stages']=[];
 try{$controller->simpan_ai();throw new RuntimeException('Empty title accepted');}catch(PersonalTestRedirect $r){check(str_starts_with($r->path,'/surat/hasil/'),'Failed save lost form');}
 check((int)$db->value('SELECT COUNT(*) FROM letter_templates WHERE is_generated=1')===0,'Failed save left generated schema');
 $_POST['ai_revision']=(string)$store->read($run,$user,$orgId)['revision'];$_POST['title']='Untuk Dira';
 try{$controller->simpan_ai();throw new RuntimeException('No save redirect');}catch(PersonalTestRedirect $r){check(str_starts_with($r->path,'/surat/detail/'),'Personal save failed');}
 $letter=$db->one("SELECT id,template_id FROM letters WHERE title='Untuk Dira'");check($letter!==null,'Draft missing');
 check((int)$db->value('SELECT COUNT(*) FROM letter_templates WHERE is_generated=1')===1,'Generated schema not persisted once');
 check(count($query->templates($orgId))===2,'Per-letter schema polluted catalogue');
 check($query->template($orgId,(int)$letter['template_id'])!==null,'Saved private schema cannot be opened');
 check($query->template(1,(int)$letter['template_id'])===null,'Private schema leaked to organization');
 rejects(fn()=>PersonalTemplate::store($db,2,1,$normalized));
 $_POST=[];$_GET=[];$_SERVER['REQUEST_METHOD']='GET';$controller->edit((int)$letter['id']);
 check(!empty($controller->page['template']['definition']['ai_designed']),'Editing lost generated structure');
 (new LetterService($db))->submit((int)$letter['id'],$user);
 $detail=$query->detail((int)$letter['id'],$orgId);
 check($detail['letter']['status']==='approved' && $detail['letter']['letter_number']===null,'Personal completion forced number/reviewer');
 check($detail['version']['snapshot']['template']['definition']['ai_designed']===true,'Snapshot lost custom schema');
 $file=(new ExportService($db,$exports))->create((int)$letter['id'],$orgId,$user,'docx');
 $zip=new ZipArchive();$zip->open($file['path']);$xml=$zip->getFromName('word/document.xml');
 check(!str_contains($xml,'Perihal')&&!str_contains($xml,'Yth.')&&!str_contains($xml,'Hormat saya')&&str_contains($xml,'Dira'),'Informal export forced formal furniture or lost recipient');
 check($zip->getFromName('word/header1.xml')===false,'Personal export has blank letterhead');$zip->close();
 if(PHP_OS_FAMILY!=='Windows'){
  $pdf=(new ExportService($db,$exports))->create((int)$letter['id'],$orgId,$user,'pdf');check(str_starts_with(file_get_contents($pdf['path']),'%PDF-'),'Personal PDF invalid');
  if($previewDir)copy($pdf['path'],$previewDir.'/personal.pdf');
 }
 check((int)$db->value('SELECT COUNT(*) FROM ai_generations WHERE letter_id=?',[$letter['id']])===3,'Personal AI provenance lost');
 echo "PASS personal flow: AI-designed structure, no pre-save DB rows, informal preview/export, rollback, private scope, edit, frozen snapshot and PDF\n";

 $beforeCalls=count($sent);
 $queue[]=['blocks'=>['sapaan'=>'Hai {{nama_penerima}},','isi'=>'Aku ingin meminta maaf karena {{hal_yang_disesali}}.','penutup'=>'Terima kasih sudah membaca.']];
 $queue[]=['blocks'=>['sapaan'=>'Hai,','isi'=>'Aku ingin meminta maaf karena {{hal_yang_disesali}}.','penutup'=>'Terima kasih sudah membaca.']];
 $optional=(new \Pena\Service\LetterAiService($db,$client))->writeParagraphs($orgId,$user,'Buat surat permintaan maaf.',$normalized,['hal_yang_disesali'=>'terlambat menepati janji']);
 check(count($optional['generation_ids'])===2 && $optional['blocks']['sapaan']['text']==='Hai,','Empty optional reference was not rejected/retried');
 check(!str_contains($sent[$beforeCalls]['messages'][1]['content'],'{{nama_penerima}}'),'Blank optional placeholder offered to model');
 $rules=(new ReflectionMethod(\Pena\Service\LetterAiService::class,'personalWritingRules'))->invoke(new \Pena\Service\LetterAiService($db,$client),['presentation'=>['formal_recipient'=>true],'blocks'=>[['type'=>'signatures']]]);
 check(str_contains($rules,'Jangan ulangi Yth.')&&str_contains($rules,'Jangan menulis label Hormat saya')&&str_contains($rules,'lampiran/bukti'),'Formal presentation writing guidance missing');
 echo "PASS personal generation: design token budget and retry for blank optional references\n";
 $_SERVER['REQUEST_METHOD']='POST';$_GET=[];$follow=$store->create($user,$orgId);
 $_POST=['ai_run'=>$follow,'ai_revision'=>'0','workspace_id'=>(string)$orgId,'prompt'=>'Buatkan surat'];
 $queue[]=['status'=>'clarify','question'=>'Surat untuk siapa dan apa yang ingin disampaikan?'];
 $r=$step->invoke($controller,'kerangka',$org,$user);$_POST['ai_revision']=(string)$r['revision'];
 check($r['status']==='clarify' && $store->read($follow,$user,$orgId)['data']['clarify']['mode']==='personal','Personal forced closed choices');
 if($previewDir){
  $savedPost=$_POST;$_POST=[];$_GET=['proses'=>$follow];$_SERVER['REQUEST_METHOD']='GET';$controller->buat();
  ob_start();(new ReflectionMethod(Controller::class,'display'))->invoke($controller,'surat/generate',$controller->page);file_put_contents($previewDir.'/personal-clarify.html',ob_get_clean());
  $_POST=$savedPost;$_GET=[];$_SERVER['REQUEST_METHOD']='POST';
 }
 $_POST['clarification_answer']='Permintaan maaf untuk pacar karena aku terlambat menepati janji.';$queue[]=$design;
 $r=$step->invoke($controller,'kerangka',$org,$user);
 $state=$store->read($follow,$user,$orgId)['data'];
 check($r['status']==='ok' && $state['prompt']==='Buatkan surat' && count($state['clarifications'])===1,'Clarification appended to original prompt or was lost');
 check(str_contains(end($sent)['messages'][1]['content'],'Permintaan maaf untuk pacar'),'Follow-up not used for design');
 echo "PASS personal clarification: open answer retained separately and used as context, no catalogue choices\n";
 $before=count($sent);$bad=$design;unset($bad['blocks'][0]['required']);$queue[]=$bad;$queue[]=$design;
 $retry=(new \Pena\Service\LetterAiService($db,$client))->designPersonalTemplate($orgId,$user,'Buat surat maaf untuk pacar.');
 check(count($sent)===$before+2&&count($retry['generation_ids'])===2,'Invalid design did not get one correction with both logs');
 check(str_contains(end($sent)['messages'][3]['content'],'required berupa boolean'),'Correction missing schema reason');
 $queue[]=$bad;$queue[]=$bad;$before=count($sent);
 try{(new \Pena\Service\LetterAiService($db,$client))->designPersonalTemplate($orgId,$user,'Buat surat maaf untuk pacar.');throw new RuntimeException('Invalid retry accepted');}catch(\Pena\Service\AiException $e){check(str_contains($e->getMessage(),'setelah diperbaiki'),'Wrong schema failure');}
 check(count($sent)===$before+2,'Schema correction retried more than once');
 echo "PASS personal schema correction: one bounded retry, strict validation, both generation logs retained\n";
}finally{
 $conn->close();$drop();
 if(is_dir($exports)){foreach(glob($exports.'/*')as$f)unlink($f);rmdir($exports);}
}
