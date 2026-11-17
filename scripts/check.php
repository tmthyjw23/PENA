<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$count = 0;
foreach (['app', 'scripts', 'tests'] as $directory) {
    $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root . '/' . $directory));
    foreach ($files as $file) {
        if ($file->getExtension() !== 'php') continue;
        passthru(escapeshellarg(PHP_BINARY) . ' -l ' . escapeshellarg($file->getPathname()), $status);
        if ($status !== 0) exit($status);
        $count++;
    }
}
passthru(escapeshellarg(PHP_BINARY) . ' -l ' . escapeshellarg($root . '/index.php'), $status);
if ($status !== 0) exit($status);
echo 'Syntax OK: ' . ($count + 1) . " PHP files.\n";
