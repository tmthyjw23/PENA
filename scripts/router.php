<?php
declare(strict_types=1);

// Development server only: do not expose the project root as static files.
if (PHP_SAPI !== 'cli-server') {
    http_response_code(403);
    exit;
}
$root = dirname(__DIR__);
$path = rawurldecode(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?: '/');
if (str_contains($path, "\0")) {
    http_response_code(400);
    exit('Bad request');
}
if (preg_match('~(?:^/(?:app|vendor|scripts|tests|storage|database)(?:/|$)|(?:^|/)\.)~i', $path)) {
    http_response_code(403);
    exit('Forbidden');
}
$assetPath = preg_replace('~^/(?:public/)?assets/~', '/', $path);
$assetRoot = realpath($root . '/public/assets');
$file = realpath($root . '/public/assets' . $assetPath);
$types = ['css' => 'text/css', 'js' => 'application/javascript', 'png' => 'image/png', 'jpg' => 'image/jpeg', 'svg' => 'image/svg+xml', 'woff' => 'font/woff', 'woff2' => 'font/woff2', 'ico' => 'image/x-icon'];
$extension = strtolower(pathinfo($file ?: '', PATHINFO_EXTENSION));
if ($file && is_file($file) && str_starts_with($file, $assetRoot . DIRECTORY_SEPARATOR) && isset($types[$extension])) {
    header('Content-Type: ' . $types[$extension]);
    readfile($file);
    exit;
}
// Follow the host the browser used (localhost or 127.0.0.1): links, CSS and fonts then stay same-origin,
// so fonts are not blocked as cross-origin and the session cookie matches.
$host = $_SERVER['HTTP_HOST'] ?? '127.0.0.1:8787';
$_ENV['APP_URL'] = 'http://' . (preg_match('/^(localhost|127\.0\.0\.1)(:\d+)?$/', $host) ? $host : '127.0.0.1:8787');
if (trim($path, '/') === '') {
    unset($_GET['url']);
} else {
    $_GET['url'] = trim($path, '/');
}
require $root . '/index.php';
