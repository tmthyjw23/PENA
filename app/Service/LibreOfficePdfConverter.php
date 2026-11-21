<?php
declare(strict_types=1);
namespace Pena\Service;
use RuntimeException;

final class LibreOfficePdfConverter implements PdfConverter
{
    public function isAvailable(): bool
    {
        return is_executable($_ENV['SOFFICE_BINARY'] ?? '/usr/bin/libreoffice');
    }

    public function convert(string $docxPath,string $pdfPath):void
    {
        $source=realpath($docxPath);
        $directory=realpath(dirname($pdfPath));
        if (!$source || !$directory) throw new RuntimeException('Sumber atau direktori keluaran tidak tersedia.');
        $job=$directory.'/lo-'.bin2hex(random_bytes(12));
        if (!mkdir($job,0700)) throw new RuntimeException('Folder konversi tidak tersedia.');
        $profile=$job.'/profile';
        $process=null;
        try {
            $binary=$_ENV['SOFFICE_BINARY'] ?? '/usr/bin/libreoffice';
            // Worker launches must not inherit root-owned XDG/cache directories.
            $environment=array_merge(getenv(), ['HOME'=>$job,'XDG_CACHE_HOME'=>$job.'/cache','XDG_CONFIG_HOME'=>$job.'/config','XDG_RUNTIME_DIR'=>$job]);
            $process=proc_open([$binary,'-env:UserInstallation=file://'.str_replace(' ','%20',$profile),'--headless','--convert-to','pdf','--outdir',$job,$source],
                [0=>['pipe','r'],1=>['file',$job.'/stdout.log','w'],2=>['file',$job.'/stderr.log','w']],$pipes,$job,$environment);
            if (!is_resource($process)) throw new RuntimeException('Converter PDF tidak tersedia.');
            fclose($pipes[0]);
            $deadline=microtime(true)+45;
            do {
                $status=proc_get_status($process);
                if (!$status['running']) break;
                if (microtime(true)>$deadline) {proc_terminate($process,9);throw new RuntimeException('Konversi PDF melewati batas waktu.');}
                usleep(100000);
            } while(true);
            $result=$job.'/'.pathinfo($source,PATHINFO_FILENAME).'.pdf';
            if ($status['exitcode']!==0 || !is_file($result) || filesize($result)===0) throw new RuntimeException('LibreOffice gagal menghasilkan PDF: '.substr((string)file_get_contents($job.'/stderr.log'),0,2000));
            if (!rename($result,$pdfPath)) throw new RuntimeException('Gagal menyimpan PDF.');
        } finally {
            if (is_resource($process)) proc_close($process);
            // Cleanup only this randomly generated job directory, never caller paths.
            $files=new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($job,\FilesystemIterator::SKIP_DOTS),\RecursiveIteratorIterator::CHILD_FIRST);
            foreach($files as $file){$file->isDir()?rmdir($file->getPathname()):unlink($file->getPathname());}
            rmdir($job);
        }
    }
}
