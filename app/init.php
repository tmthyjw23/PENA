<?php
// Bootstrap: environment, configuration, framework core (app/core) and the model autoloader.
date_default_timezone_set('Asia/Makassar');

@ini_set('ignore_user_abort', '1');
@ini_set('display_errors', '0');
@ini_set('log_errors', '1');
@ini_set('zend.exception_ignore_args', '1'); // Stack traces must not retain credential arguments.
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
          'AI_CREDENTIAL_KEY_FILE', 'AI_CREDENTIAL_KEY', 'AI_BASE_URL', 'AI_API_KEY', 'AI_MODEL', 'AI_TIMEOUT', 'AI_MAX_TOKENS',
          'AI_USER_HOURLY_LIMIT', 'AI_WORKSPACE_HOURLY_LIMIT', 'AI_PLATFORM_HOURLY_LIMIT', 'SOFFICE_BINARY', 'RESEND_API_KEY', 'MAIL_FROM', 'AI_PERSONAL_MAX_TOKENS'] as $name) {
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
