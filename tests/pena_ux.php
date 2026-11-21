<?php
declare(strict_types=1);
require __DIR__.'/support/throwaway_db.php';
use Pena\Service\{UserGuide,WorkspaceService,LetterService,PenaException};use Pena\Support\Db;
function check(bool $ok,string $why):void{if(!$ok)throw new RuntimeException($why);}
function denies(callable $fn):void{try{$fn();}catch(PenaException){return;}throw new RuntimeException('Expected rejection');}
[$conn,$drop]=pena_throwaway_db('pena_test_ux');
try{
 $db=new Db($conn);$spaces=new WorkspaceService($db);$guide=new UserGuide($db);
 $a=$spaces->register('Pengguna Baru','guide-new@example.invalid','Fixture-Guide-123');
 $b=$spaces->register('Pengguna Lain','guide-other@example.invalid','Fixture-Other-123');
 check(!$guide->needsIntroduction($a),'Unverified account received app introduction');denies(fn()=>$guide->dismiss($a));
 $db->run('UPDATE users SET email_verified_at=NOW() WHERE id IN(?,?)',[$a,$b]);
 check($guide->needsIntroduction($a)&&$guide->needsIntroduction($b),'New verified user did not get a guide');
 $guide->dismiss($a);$stamp=$db->value('SELECT guide_dismissed_at FROM users WHERE id=?',[$a]);
 check(!(new UserGuide($db))->needsIntroduction($a)&&$guide->needsIntroduction($b),'Guide preference not persistent or crossed accounts');
 $guide->dismiss($a);check($stamp===$db->value('SELECT guide_dismissed_at FROM users WHERE id=?',[$a]),'Repeated dismissal rewrote history');
 $db->run("UPDATE users SET status='inactive' WHERE id=?",[$b]);denies(fn()=>$guide->dismiss($b));
 echo "PASS user guide: verified new accounts, persistent owner-only dismissal, idempotence, inactive denial\n";

 $letters=new LetterService($db);$fields=['penerima_jabatan'=>'Kabag Umum','penerima_instansi'=>'Fakultas Uji','nama_kegiatan'=>'Kegiatan Uji','tanggal_mulai'=>'2026-11-30','tempat_kegiatan'=>'Aula','barang'=>[['nama'=>'Kursi','jumlah'=>'5','keterangan'=>'Baik']]];
 $blocks=[['id'=>'pembuka'],['id'=>'maksud','text'=>'Untuk {{nama_kegiatan}}, kami memohon peminjaman barang.'],['id'=>'daftar_barang'],['id'=>'penutup'],['id'=>'tanda_tangan']];
 $template=(int)$db->value("SELECT id FROM letter_templates WHERE code='PMJ' AND organization_id IS NULL");
 $id=$letters->createDraft(2,1,$template,'Perihal Awal',$fields,$blocks,[['name'=>'Ketua','signer_ids'=>[1]]],1);
 $letters->updateDraft($id,2,$fields,$blocks,null,'Perihal Baru');
 check($db->value('SELECT title FROM letters WHERE id=?',[$id])==='Perihal Baru','Unsubmitted title could not be edited');
 denies(fn()=>$letters->updateDraft($id,2,$fields,$blocks,null,''));
 denies(fn()=>$letters->updateDraft($id,2,$fields,$blocks,null,str_repeat('x',201)));
 check($db->value('SELECT title FROM letters WHERE id=?',[$id])==='Perihal Baru','Failed edit changed title');
 $letters->submit($id,2);
 denies(fn()=>$letters->updateDraft($id,2,$fields,$blocks,null,'Forbidden change'));
 check($db->value('SELECT title FROM letters WHERE id=?',[$id])==='Perihal Baru','Submitted version allowed title change');
 $snapshot=json_decode($db->value('SELECT snapshot FROM letter_versions WHERE id=(SELECT current_version_id FROM letters WHERE id=?)',[$id]),true);
 check($snapshot['title']==='Perihal Baru','Submission snapshot lost edited title');
 $old=$db->one('SELECT * FROM letter_versions WHERE id=(SELECT current_version_id FROM letters WHERE id=?)',[$id]);
 $oldHash=$old['content_hash'];$oldVersion=(int)$old['id'];
 $letters->startRevision($id,2,'Revisi perihal untuk pengujian');
 $letters->updateDraft($id,2,$fields,$blocks,null,'Perihal Revisi');
 $letter=$db->one('SELECT * FROM letters WHERE id=?',[$id]);
 $old['fields']=json_decode($old['fields'],true);$old['blocks']=json_decode($old['blocks'],true);$old['snapshot']=json_decode($old['snapshot'],true);
 $rendered=(new \Pena\Service\LetterQuery($db))->preview($letter,$old);
 check(str_contains(json_encode($rendered),'Perihal Baru')&&!str_contains(json_encode($rendered),'Perihal Revisi'),'Old version title changed with revised draft');
 unset($old['snapshot']['title']);
 $legacy=(new \Pena\Service\LetterQuery($db))->preview($letter,$old);
 check(str_contains(json_encode($legacy),'Perihal Baru'),'Legacy title column fallback failed');
 check($db->value('SELECT content_hash FROM letter_versions WHERE id=?',[$oldVersion])===$oldHash,'Old signed hash was modified');
 require_once dirname(__DIR__).'/app/controller/review.php';
 $review=(new ReflectionClass(review::class))->newInstanceWithoutConstructor();
 $changes=(new ReflectionMethod(review::class,'_changes'))->invoke($review,['previous'=>['title'=>'Perihal Baru','fields'=>$fields,'blocks'=>$blocks],'version'=>['title'=>'Perihal Revisi','fields'=>$fields,'blocks'=>$blocks]],['fields'=>[]]);
 check(count($changes)===1&&$changes[0]['label']==='Perihal','Reconfirmation omitted title change');
 echo "PASS draft title: editable before submission, invalid input rolled back, frozen version denied, snapshot preserved\n";
}finally{$drop();}
