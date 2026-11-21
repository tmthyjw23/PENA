<?php
declare(strict_types=1);
require __DIR__.'/support/throwaway_db.php';
use Pena\Service\{WorkspaceService,LetterService,ExportService,WordPdfConverter,LibreOfficePdfConverter,PdfConverter,PenaException};
use Pena\Support\Db;
function check(bool $ok,string $message):void {if(!$ok)throw new RuntimeException($message);}
function fails(callable $fn):void {try{$fn();}catch(PenaException){return;}throw new RuntimeException('Expected rejection');}
[$conn,$drop]=pena_throwaway_db('pena_test_exports');
$dir=sys_get_temp_dir().'/pena-export-'.bin2hex(random_bytes(8));
try {
    $db=new Db($conn);$spaces=new WorkspaceService($db);$letters=new LetterService($db);
    $user=$spaces->register('Penguji','export@example.invalid','uji-secret-123');
    $workspace=$spaces->forUser($user)[0]['id'];
    $template=(int)$db->value("SELECT id FROM letter_templates WHERE code='IZN'");
    $fields=['nama_mahasiswa'=>'Penguji','nim'=>'123','program_studi'=>'Informatika','penerima_jabatan'=>'Dosen','mata_kuliah'=>'Basis Data','tanggal_mulai'=>'2026-11-20','alasan'=>'sakit'];
    $blocks=[['id'=>'pembuka'],['id'=>'identitas'],['id'=>'izin','text'=>'Mohon izin {{mata_kuliah}} pada {{tanggal_mulai}} karena {{alasan}}.'],['id'=>'penutup'],['id'=>'tanda_tangan']];
    $id=$letters->createDraft($user,$workspace,$template,'Izin',$fields,$blocks,[]);
    $exports=new ExportService($db,$dir);
    fails(fn()=>$exports->create($id,$workspace,$user,'docx'));
    $letters->submit($id,$user);
    fails(fn()=>$exports->create($id,$workspace,1,'docx'));
    fails(fn()=>$exports->create($id,1,$user,'docx'));
    $file=$exports->create($id,$workspace,$user,'docx');
    check(is_file($file['path']),'DOCX missing');
    $again=$exports->create($id,$workspace,$user,'docx');
    check($file['path']===$again['path'],'Immutable export not reused');
    check((int)$db->value("SELECT COUNT(*) FROM letter_exports WHERE status='done'")===1,'Export row missing');
    $failure=new class implements PdfConverter {
        public function isAvailable():bool{return true;}
        public function convert(string $source,string $target):void{throw new RuntimeException('intentional test failure');}
    };
    fails(fn()=>(new ExportService($db,$dir,$failure))->create($id,$workspace,$user,'pdf'));
    check($db->value('SELECT status FROM letters WHERE id=?',[$id])==='approved','PDF failure removed approval');
    check((int)$db->value("SELECT COUNT(*) FROM letter_exports WHERE status='failed'")===1,'Failure not recorded');
    $word=PHP_OS_FAMILY==='Windows' ? new WordPdfConverter() : new LibreOfficePdfConverter();
    if($word->isAvailable()){
        $pdf=(new ExportService($db,$dir,$word))->create($id,$workspace,$user,'pdf');
        check(str_starts_with(file_get_contents($pdf['path']),'%PDF-'),'Invalid exported PDF');
        echo "PASS export PDF: real platform converter of approved snapshot\n";
    }
    echo "PASS exports: approval and workspace guards, immutable DOCX cache, audit status, failed PDF preserves approval\n";
}finally{
    $conn->close();$drop();
    if(is_dir($dir)){foreach(glob($dir.'/*') as $file)unlink($file);rmdir($dir);}
}
