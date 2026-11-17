<?php
/**
 * Application configuration. Values come from the environment (.env); nothing secret has a default here.
 */

// Base URL (APP_PATH): from APP_URL, otherwise derived from the request.
if (!defined('APP_PATH')) {
    if (!empty($_ENV['APP_URL'])) {
        define('APP_PATH', rtrim($_ENV['APP_URL'], '/'));
    } elseif (php_sapi_name() === 'cli' || empty($_SERVER['HTTP_HOST'])) {
        define('APP_PATH', 'http://localhost/' . basename(dirname(__DIR__, 2)));
    } else {
        $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (isset($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] == 443);
        $dir = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '')), '/');
        define('APP_PATH', ($https ? 'https://' : 'http://') . $_SERVER['HTTP_HOST'] . preg_replace('#/public$#', '', $dir));
    }
}

if (!defined('BASEPATH')) {
    define('BASEPATH', dirname(__DIR__, 2));
}

define('APP_ENV', $_ENV['APP_ENV'] ?? 'production');
define('ALLOW_DB_SETUP', APP_ENV === 'local' && filter_var($_ENV['ALLOW_DB_SETUP'] ?? false, FILTER_VALIDATE_BOOL));
define('HOST_DB', $_ENV['DB_HOST'] ?? '127.0.0.1');
define('PORT_DB', (int)($_ENV['DB_PORT'] ?? 3306));
define('NAME_DB', $_ENV['DB_NAME'] ?? 'pena');
define('USER_DB', $_ENV['DB_USER'] ?? 'root');
define('PASS_DB', $_ENV['DB_PASSWORD'] ?? '');
define('APP_NAME', 'PENA');
define('APP_SHORT_NAME', 'PENA');
define('APP_DESC', 'Penyusunan dan persetujuan surat organisasi mahasiswa');
define('APP_VERSION', '0.1.0');
