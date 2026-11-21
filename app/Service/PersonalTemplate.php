<?php
declare(strict_types=1);
namespace Pena\Service;
use Pena\Support\Db;

/** AI chooses a personal letter's structure; only renderable, bounded data enters the editor. */
final class PersonalTemplate
{
    public static function normalize(array $design): array
    {
        $text=static function(mixed $value,int $max):string {
            if(!is_string($value)||trim($value)===''||mb_strlen(trim($value))>$max)throw new PenaException('Kerangka AI memuat teks yang kosong atau terlalu panjang. Coba susun kembali.');
            return trim($value);
        };
        $key=static function(mixed $value):string {
            if(!is_string($value)||!preg_match('/^[a-z][a-z0-9_]{0,39}$/D',$value))throw new PenaException('Identitas isian/blok kerangka AI tidak valid.');
            return $value;
        };
        $name=$text($design['name']??null,150);
        $fields=$design['fields']??null;$blocks=$design['blocks']??null;
        if(!is_array($fields)||!array_is_list($fields)||count($fields)>16||!is_array($blocks)||!array_is_list($blocks)||count($blocks)<1||count($blocks)>10) {
            throw new PenaException('Kerangka AI perlu daftar isian dan 1–10 bagian surat yang valid.');
        }
        $normalized=[];$known=[];
        foreach($fields as $field){
            if(!is_array($field))throw new PenaException('Isian kerangka AI tidak valid.');
            $id=$key($field['key']??null);
            if(isset($known[$id]))throw new PenaException('Isian kerangka AI berulang.');
            $type=$field['type']??'text';
            if(!in_array($type,['text','date','money','table'],true))throw new PenaException('Jenis isian kerangka AI tidak didukung.');
            if(!is_bool($field['required']??null))throw new PenaException('Aturan wajib isian kerangka AI tidak valid.');
            $out=['key'=>$id,'label'=>$text($field['label']??null,100),'type'=>$type,'required'=>$field['required']];
            if($type==='table'){
                $columns=$field['columns']??null;
                if(!is_array($columns)||!array_is_list($columns)||count($columns)<1||count($columns)>6)throw new PenaException('Tabel kerangka AI perlu 1–6 kolom.');
                $seen=[];$out['columns']=[];
                foreach($columns as $column){
                    if(!is_array($column))throw new PenaException('Kolom kerangka AI tidak valid.');
                    $columnId=$key($column['key']??null);
                    if(isset($seen[$columnId]))throw new PenaException('Kolom kerangka AI berulang.');
                    $seen[$columnId]=true;
                    $out['columns'][]=['key'=>$columnId,'label'=>$text($column['label']??null,80)];
                }
            }
            $known[$id]=$type;$normalized[]=$out;
        }
        $parts=[];$seen=[];$paragraphs=0;
        foreach($blocks as $block){
            if(!is_array($block))throw new PenaException('Bagian kerangka AI tidak valid.');
            $id=$key($block['id']??null);
            if(isset($seen[$id]))throw new PenaException('Bagian kerangka AI berulang.');
            $seen[$id]=true;$type=$block['type']??'paragraph';
            if(!in_array($type,['paragraph','table','signatures'],true))throw new PenaException('Jenis bagian kerangka AI tidak didukung.');
            if(!is_bool($block['required']??null))throw new PenaException('Aturan wajib bagian kerangka AI tidak valid.');
            $out=['id'=>$id,'type'=>$type,'label'=>$text($block['label']??null,100),'required'=>$block['required']];
            if($type==='paragraph'){
                $out+=['source'=>'ai','instruction'=>$text($block['instruction']??null,500)];$paragraphs++;
            }elseif($type==='table'){
                $field=$key($block['field']??null);
                if(($known[$field]??null)!=='table')throw new PenaException('Bagian tabel merujuk isian yang tidak tersedia.');
                $out['field']=$field;
            }
            $parts[]=$out;
        }
        if($paragraphs===0)throw new PenaException('Kerangka surat perlu sedikitnya satu paragraf.');
        $p=$design['presentation']??[];
        if(!is_array($p))throw new PenaException('Tampilan kerangka AI tidak valid.');
        $presentation=[];
        foreach(['show_subject','show_date','formal_recipient']as$flag){
            if(!is_bool($p[$flag]??null))throw new PenaException('Pilihan tampilan kerangka AI tidak valid.');
            $presentation[$flag]=$p[$flag];
        }
        foreach(['penerima_jabatan','penerima_instansi','nama_pengirim','nama_mahasiswa','nim','program_studi'] as $identityKey){
            if(isset($known[$identityKey]) && $known[$identityKey]!=='text')throw new PenaException('Isian identitas/penerima harus berupa teks.');
        }
        $presentation['signature_label']=$text($p['signature_label']??'Hormat saya',60);
        return ['id'=>0,'code'=>'AI','name'=>$name,'description'=>$text($design['description']??$name,255),
            'definition'=>['ai_designed'=>true,'tone'=>$text($design['tone']??'Sesuai tujuan surat',120),
                'presentation'=>$presentation,'fields'=>$normalized,'blocks'=>$parts]];
    }

    /** Persist only on save, in the caller's transaction with the draft, so abandoned forms leave no rows. */
    public static function store(Db $db,int $userId,int $workspaceId,array $template): int
    {
        $workspace=(new WorkspaceService($db))->requireMember($workspaceId,$userId);
        if($workspace['kind']!=='personal')throw new PenaException('Kerangka pribadi AI hanya tersedia di ruang pribadi.');
        $row=self::normalize(['name'=>$template['name'],'description'=>$template['description']]+$template['definition']);
        return $db->insert("INSERT INTO letter_templates (organization_id,audience,code,name,description,definition,is_generated) VALUES (?,'personal',?,?,?,?,1)",
            [$workspaceId,'AI-'.bin2hex(random_bytes(8)),$row['name'],$row['description'],LetterService::json($row['definition'])]);
    }
}
