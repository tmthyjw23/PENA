<?php
declare(strict_types=1);
require __DIR__.'/support/throwaway_db.php';
use Pena\Service\{SignatureService,WorkspaceService,LetterQuery,LetterService,ApprovalService,ExportService,PenaException,PersonalTemplate};
use Pena\Support\Db;
function check(bool $ok,string $message):void {if(!$ok)throw new RuntimeException($message);}
function rejects(callable $fn):void {try{$fn();}catch(PenaException){return;}throw new RuntimeException('Expected rejection');}
function png(bool $blank=false):string {$im=imagecreatetruecolor(300,100);imagefill($im,0,0,imagecolorallocate($im,255,255,255));if(!$blank){$ink=imagecolorallocate($im,20,40,90);imagesetthickness($im,3);imageline($im,25,70,65,20,$ink);imageline($im,65,20,110,70,$ink);imageline($im,110,70,260,35,$ink);}ob_start();imagepng($im);$bytes=ob_get_clean();imagedestroy($im);return $bytes;}
$raw=png();$canonical=SignatureService::normalize($raw);check(str_starts_with($canonical,"\x89PNG"),'Invalid normalized PNG');
rejects(fn()=>SignatureService::normalize(png(true)));rejects(fn()=>SignatureService::normalize('<svg/>'));rejects(fn()=>SignatureService::normalize(str_repeat('x',524289)));
$post=['signature_data'=>'data:image/png;base64,'.base64_encode($raw),'signature_consent'=>'1','signature_version_id'=>'1'];
rejects(fn()=>SignatureService::fromRequest(array_diff_key($post,['signature_consent'=>true]),[]));
rejects(fn()=>SignatureService::fromRequest(['signature_consent'=>'1'],[]));
check(SignatureService::fromRequest([],[])===null,'Optional signature forced');
$image=SignatureService::fromRequest($post,[]);check($image['png']===$canonical,'Drawing path changed image');
echo "PASS images: PNG decoding, blank/oversize/non-PNG rejection, explicit consent, optional unsigned path\n";
[$conn,$drop]=pena_throwaway_db('pena_test_signatures');$dir=sys_get_temp_dir().'/pena-signatures-'.bin2hex(random_bytes(5));
try{
 $db=new Db($conn);$spaces=new WorkspaceService($db);$letters=new LetterService($db);$query=new LetterQuery($db);$reviews=new ApprovalService($db);
 $user=$spaces->register('Penguji Tanda Tangan','signature@example.invalid','fixture-password-123');$workspace=(int)$spaces->forUser($user)[0]['id'];
 $tpl=(int)$db->value("SELECT id FROM letter_templates WHERE code='IZN'");
 $fields=['nama_mahasiswa'=>'Penguji Tanda Tangan','nim'=>'123','program_studi'=>'Informatika','penerima_jabatan'=>'Dosen','mata_kuliah'=>'Basis Data','tanggal_mulai'=>'2026-11-20','alasan'=>'sakit'];
 $blocks=[['id'=>'pembuka'],['id'=>'identitas'],['id'=>'izin','text'=>'Mohon izin {{mata_kuliah}} karena {{alasan}}.'],['id'=>'penutup'],['id'=>'tanda_tangan']];
 $id=$letters->createDraft($user,$workspace,$tpl,'Izin',$fields,$blocks,[]);$page=$query->detail($id,$workspace);
 $image['version_id']=(int)$page['version']['id'];$image['preview_hash']=SignatureService::previewHash($page['preview']);
 rejects(fn()=>$letters->submit($id,1,$image));
 $changed=$fields;$changed['alasan']='urusan keluarga';$letters->updateDraft($id,$user,$changed,$blocks);
 rejects(fn()=>$letters->submit($id,$user,$image));check((int)$db->value('SELECT COUNT(*) FROM letter_signatures')===0,'Stale signing left image');
 $page=$query->detail($id,$workspace);$image['preview_hash']=SignatureService::previewHash($page['preview']);$letters->submit($id,$user,$image);
 $page=$query->detail($id,$workspace);$signers=array_merge(...array_map(static fn($b)=>$b['signers']??[],$page['preview']['blocks']));
 check(count(array_filter($signers,static fn($s)=>!empty($s['signature_png'])))===1,'Personal image not rendered');
 $stored=$db->one('SELECT * FROM letter_signatures WHERE version_id=?',[$image['version_id']]);check($stored['content_hash']===$page['version']['content_hash']&&$stored['image_sha256']===hash('sha256',$stored['png']),'Evidence hash not tied to approved content');
 $file=(new ExportService($db,$dir))->create($id,$workspace,$user,'docx');$zip=new ZipArchive();$zip->open($file['path']);$media=0;for($i=0;$i<$zip->numFiles;$i++)if(str_starts_with($zip->getNameIndex($i),'word/media/'))$media++;check($media===1,'DOCX signature image missing');$zip->close();
 if(PHP_OS_FAMILY!=='Windows'){$pdf=(new ExportService($db,$dir))->create($id,$workspace,$user,'pdf');check(str_starts_with(file_get_contents($pdf['path']),'%PDF-'),'Signed PDF missing');if($out=getenv('PENA_SIGNATURE_PREVIEW')){if(!is_dir($out))mkdir($out,0700,true);copy($pdf['path'],$out.'/personal.pdf');}}
 echo "PASS personal signing: ownership, stale preview, atomic image/hash, private preview, DOCX and PDF\n";

 $orgFields=['penerima_jabatan'=>'Kabag Umum','penerima_instansi'=>'Fakultas','nama_kegiatan'=>'Kegiatan uji','tanggal_mulai'=>'2026-11-30','tempat_kegiatan'=>'Aula','barang'=>[['nama'=>'Kursi','jumlah'=>'5','keterangan'=>'Baik']]];
 $orgBlocks=[['id'=>'pembuka'],['id'=>'maksud','text'=>'Mohon meminjam barang untuk {{nama_kegiatan}}.'],['id'=>'daftar_barang'],['id'=>'penutup'],['id'=>'tanda_tangan']];
 $id=$letters->createDraft(2,1,1,'Peminjaman uji',$orgFields,$orgBlocks,[['name'=>'Ketua','signer_ids'=>[1]],['name'=>'Advisor','signer_ids'=>[3]]],1);
 rejects(fn()=>$letters->submit($id,2,$image));$sent=$letters->submit($id,2);$token=array_values($sent['links'])[0];$page=$reviews->open($token);
 // Simulate an active version frozen before signature keys existed; do not rewrite it in production.
 $legacy=$page['version']['snapshot'];foreach($legacy['approval']as&$slot)unset($slot['signature_key']);unset($slot);
 $db->run('UPDATE letter_versions SET snapshot=? WHERE id=?',[LetterService::json($legacy),$page['version']['id']]);
 $image['version_id']=(int)$page['version']['id'];
 rejects(fn()=>$reviews->approve('invalid-token',$page['version']['content_hash'],null,'127.0.0.1','fixture',$image));
 rejects(fn()=>$reviews->approve($token,str_repeat('0',64),null,'127.0.0.1','fixture',$image));
 $wrong=$image;$wrong['version_id']++;rejects(fn()=>$reviews->approve($token,$page['version']['content_hash'],null,'127.0.0.1','fixture',$wrong));
 $reviews->approve($token,$page['version']['content_hash'],null,'127.0.0.1','fixture',$image);
 rejects(fn()=>$reviews->approve($token,$page['version']['content_hash'],null,'127.0.0.1','fixture',$image));
 $shown=$query->detail($id,1);$signed=0;foreach($shown['preview']['blocks']as$b)foreach($b['signers']??[]as$s)if(!empty($s['signature_png']))$signed++;check($signed===1,'Reviewer image not mapped to correct slot');
 $oldVersion=$image['version_id'];$new=$letters->startRevision($id,2,'Ubah surat uji');$shown=$query->detail($id,1);
 check((int)$db->value('SELECT COUNT(*) FROM letter_signatures WHERE version_id=?',[$new])===0,'Image transferred to revision');
 foreach($shown['preview']['blocks']as$b)foreach($b['signers']??[]as$s)check(empty($s['signature_png']),'Old signature shown on new version');
 $sent=$letters->submit($id,2);$token=array_values($sent['links'])[0];$page=$reviews->open($token);
 check($page['request']['kind']==='reconfirm','Signed reviewer not asked to reconfirm');
 $reviews->approve($token,$page['version']['content_hash'],null,'127.0.0.1','fixture');
 check((int)$db->value('SELECT COUNT(*) FROM letter_signatures WHERE version_id=?',[$new])===0,'Unsigned reconfirm reused image');
 check((int)$db->value('SELECT COUNT(*) FROM letter_signatures WHERE version_id=?',[$oldVersion])===1,'Old evidence lost');
 echo "PASS reviewer signing: token/hash/version guards, atomic decision, spent token rejection, correct slot, no transfer on revision/reconfirm\n";
 $pending=(int)$db->value("SELECT id FROM approval_requests WHERE version_id=? AND status='pending'",[$new]);
 $next=$letters->reissueLink($id,2,$pending);$page=$reviews->open($next);$image['version_id']=$new;
 $reviews->approve($next,$page['version']['content_hash'],null,'127.0.0.1','fixture',$image);
 $final=$query->detail($id,1);check($final['letter']['status']==='approved','Organization final approval failed');
 if(PHP_OS_FAMILY!=='Windows'){$pdf=(new ExportService($db,$dir))->create($id,1,2,'pdf');if($out=getenv('PENA_SIGNATURE_PREVIEW'))copy($pdf['path'],$out.'/organization.pdf');}
 echo "PASS organization: unsigned reconfirm plus next reviewer image, final PDF export\n";
 $custom=PersonalTemplate::normalize(['name'=>'Surat pribadi bebas','description'=>'Tanpa blok tanda tangan','tone'=>'sopan','presentation'=>['show_subject'=>false,'show_date'=>false,'formal_recipient'=>false,'signature_label'=>'Salam'],'fields'=>[],'blocks'=>[['id'=>'isi','type'=>'paragraph','label'=>'Isi','instruction'=>'Tulis isi surat','required'=>true]]]);
 $customId=PersonalTemplate::store($db,$user,$workspace,$custom);
 $one=$spaces->addSigner($workspace,$user,'Pemeriksa Satu','Pemeriksa');$two=$spaces->addSigner($workspace,$user,'Pemeriksa Dua','Pemeriksa');
 $freeId=$letters->createDraft($user,$workspace,$customId,'Surat pribadi dengan pemeriksa',[],[['id'=>'isi','text'=>'Ini isi surat uji.']],[['name'=>'Satu','signer_ids'=>[$one]],['name'=>'Dua','signer_ids'=>[$two]]]);
 $sent=$letters->submit($freeId,$user);$freeVersion=$sent['version_id'];
 for($i=0;$i<2;$i++){
  $pending=(int)$db->value("SELECT id FROM approval_requests WHERE version_id=? AND status='pending'",[$freeVersion]);
  $token=$letters->reissueLink($freeId,$user,$pending);$page=$reviews->open($token);$image['version_id']=$freeVersion;
  $reviews->approve($token,$page['version']['content_hash'],null,'127.0.0.1','fixture',$image);
 }
 $free=$query->detail($freeId,$workspace);$rendered=[];
 foreach($free['preview']['blocks']as$b)foreach($b['signers']??[]as$signer)if(!empty($signer['signature_png']))$rendered[]=$signer['name'];
 check($rendered===['Pemeriksa Satu','Pemeriksa Dua'],'Confirmed reviewer images disappeared when AI omitted signature block');
 $exports=new ExportService($db,$dir);$file=$exports->create($freeId,$workspace,$user,'docx');
 $legacy='v'.$freeVersion.'-'.bin2hex(random_bytes(12)).'.docx';rename($file['path'],$dir.'/'.$legacy);
 $db->run("UPDATE letter_exports SET file_path=? WHERE version_id=? AND format='docx' AND status='done'",[$legacy,$freeVersion]);
 $fresh=$exports->create($freeId,$workspace,$user,'docx');check($fresh['path']!==$dir.'/'.$legacy,'Legacy export cache reused after rendering correction');
 $zip=new ZipArchive();$zip->open($fresh['path']);check(str_contains($zip->getFromName('word/document.xml'),'Pemeriksa Dua'),'Export lost signed reviewer names');$zip->close();
 check($exports->create($freeId,$workspace,$user,'docx')['path']===$fresh['path'],'Current rendering cache not reused');
 echo "PASS dynamic letter: reviewer images without schema signature block, legacy export invalidation, current cache reuse\n";
 if($out=getenv('PENA_SIGNATURE_PREVIEW')){
  $version=['id'=>$new,'version_no'=>2];$data=['preview'=>$shown['preview']];ob_start();require dirname(__DIR__).'/app/view/surat/_signature.php';file_put_contents($out.'/widget.html',ob_get_clean());
 }
}finally{$conn->close();$drop();if(is_dir($dir)){foreach(glob($dir.'/*')as$f)unlink($f);rmdir($dir);}}
