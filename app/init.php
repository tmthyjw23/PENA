<?php
// Bootstrap: environment, configuration, framework core (app/core) and the model autoloader.
date_default_timezone_set('Asia/Makassar');

@ini_set('ignore_user_abort', '1');
@ini_set('display_errors', '0');
@ini_set('log_errors', '1');
if ((int)ini_get('memory_limit') > 256 || ini_get('memory_limit') === '-1') {
    @ini_set('memory_limit', '256M');
}

$autoload = dirname(__DIR__) . '/vendor/autoload.php';
if (!is_file($autoload)) {
    throw new RuntimeException('Dependensi belum tersedia. Jalankan composer install.');
}
require_once $autoload;
// PHP may omit $_ENV depending on variables_order; preserve OS configuration.
foreach (['APP_ENV', 'APP_URL', 'DB_HOST', 'DB_PORT', 'DB_NAME', 'DB_USER', 'DB_PASSWORD', 'ALLOW_DB_SETUP', 'SESSION_IDLE_MINUTES',
          'AI_BASE_URL', 'AI_API_KEY', 'AI_MODEL', 'AI_TIMEOUT'] as $name) {
    $value = getenv($name);
    if ($value !== false) $_ENV[$name] = $value;
}
unset($name, $value);
Dotenv\Dotenv::createImmutable(dirname(__DIR__))->safeLoad();
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/core/Security.php';
require_once __DIR__ . '/core/Database.php';
require_once __DIR__ . '/core/Controller.php';
require_once __DIR__ . '/core/App.php';

spl_autoload_register(function ($className) {
    $modelPath = __DIR__ . '/model/' . $className . '.php';
    if (is_file($modelPath)) require_once $modelPath;
});
