<?php
declare(strict_types=1);
$root=dirname(__DIR__);$views=0;$errors=[];
$files=new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root.'/app/view',FilesystemIterator::SKIP_DOTS));
foreach($files as $file){
 if($file->getExtension()!=='php')continue;$views++;
 $php='';foreach(token_get_all((string)file_get_contents($file->getPathname()))as$token){
  if(is_array($token)&&in_array($token[0],[T_INLINE_HTML,T_COMMENT,T_DOC_COMMENT,T_CONSTANT_ENCAPSED_STRING],true))continue;
  $php.=is_array($token)?$token[1]:$token;
 }
 if(preg_match('/new\s+\\\\?(?:Database|mysqli|PDO|Db)\b|(?:->|::)\s*(?:query|prepare|execute|transaction|all|one|run|value|fromTemplate)\s*\(/i',$php))$errors[]=$file->getPathname().': akses database di view';
}
$controllers=0;
foreach(glob($root.'/app/controller/*.php')as$file){$source=(string)file_get_contents($file);if(!preg_match('/class\s+\w+\s+extends\s+Controller\b/',$source))$errors[]=$file.': controller tidak memakai kelas dasar';$controllers++;}
foreach(['app/core/App.php','app/core/Controller.php','app/model/User_model.php','app/Service/LetterService.php','app/Service/LetterQuery.php','app/Support/Db.php']as$file)if(!is_file($root.'/'.$file))$errors[]='Berkas arsitektur tidak ditemukan: '.$file;
if($errors){fwrite(STDERR,implode("\n",$errors)."\n");exit(1);}
echo "PASS architecture boundary: $controllers native MVC controllers; $views views without database access; model/service/query entry points present\n";
echo "LIMIT: static checks do not prove every business rule belongs in the correct layer; see the architecture audit.\n";
