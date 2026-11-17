<?php
declare(strict_types=1);

// Creates .env from .env.example for a new local install. Never overwrites an existing .env.
if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit;
}

$root = dirname(__DIR__);
$path = $root . '/.env';
if (is_file($path)) {
    fwrite(STDERR, ".env sudah ada; tidak diubah.\n");
    exit(0);
}
if (!copy($root . '/.env.example', $path)) {
    throw new RuntimeException('Tidak dapat membuat .env. Periksa izin direktori.');
}
echo ".env dibuat dari .env.example. Sesuaikan APP_URL, koneksi DB, dan pengaturan AI, lalu jalankan composer db:install.\n";
