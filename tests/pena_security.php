<?php
declare(strict_types=1);

// Guards for the Content-Security-Policy and logout hardening (21 Nov 2026):
// views must not contain markup the policy blocks, and logout must not be reachable by a plain link.
require dirname(__DIR__) . '/app/init.php';

function check(bool $ok, string $why): void { if (!$ok) throw new RuntimeException($why); }

$problems = [];
$files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(dirname(__DIR__) . '/app/view', FilesystemIterator::SKIP_DOTS));
foreach ($files as $file) {
    if ($file->getExtension() !== 'php') continue;
    $name = substr($file->getPathname(), strlen(dirname(__DIR__)) + 1);
    $html = (string)file_get_contents($file->getPathname());
    if (preg_match_all('/<script\b(?![^>]*\bsrc=)[^>]*>/i', $html, $m)) {
        foreach ($m[0] as $tag) if (!str_contains($tag, 'pena_nonce()')) $problems[] = "$name: inline <script> without pena_nonce()";
    }
    if (preg_match('/\son[a-z]+\s*=\s*["\']/i', $html)) $problems[] = "$name: inline event handler (blocked by CSP)";
    if (preg_match('/\sstyle\s*=\s*["\']/i', $html)) $problems[] = "$name: style attribute (blocked by style-src 'self')";
    if (preg_match('#<a\b[^>]*href="[^"]*/login/logout/#i', $html)) $problems[] = "$name: logout as a link (must be a POST form with CSRF)";
}
check($problems === [], "CSP/logout guard:\n" . implode("\n", $problems));

// The policy itself: nonce-based scripts, no unsafe-inline/eval, framing and plugins blocked.
$controller = (string)file_get_contents(dirname(__DIR__) . '/app/core/Controller.php');
check(preg_match('/Content-Security-Policy:([^"]+)"/', $controller) === 1, 'CSP header missing');
check(!str_contains($controller, "'unsafe-inline'") && !str_contains($controller, "'unsafe-eval'"), 'CSP allows unsafe-inline/eval');
foreach (["script-src 'self' 'nonce-", "object-src 'none'", "frame-ancestors 'self'", "base-uri 'self'", "form-action 'self'"] as $directive) {
    check(str_contains($controller, $directive), "CSP lacks $directive");
}
$nonce = Controller::csp_nonce();
check($nonce === Controller::csp_nonce() && strlen(base64_decode($nonce, true) ?: '') === 16, 'Nonce not stable per request or not 128-bit');

$login = (string)file_get_contents(dirname(__DIR__) . '/app/controller/login.php');
check(preg_match('/function logout\(\).*?REQUEST_METHOD\'\] !== \'POST\'.*?verify_csrf_token\(\).*?destroy_session_cleanly/s', $login) === 1,
    'logout() must require POST and a CSRF token before ending the session');
$model = (string)file_get_contents(dirname(__DIR__) . '/app/model/User_model.php');
check(preg_match('/if \(!\$user\) \{\s*\/\/[^\n]*\n\s*password_hash\(/', $model) === 1, 'Unknown accounts skip the bcrypt work (timing leak)');

echo "PASS security: views fit the CSP (nonce on inline scripts, no inline handlers/styles), logout POST+CSRF, equal login work\n";
