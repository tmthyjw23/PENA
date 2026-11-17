<?php
declare(strict_types=1);

// Run against the local development server started with composer serve.
$base = 'http://127.0.0.1:8787';
// Smoke test over HTTP. Default: composer serve; pass another base URL, e.g. http://localhost/pena (XAMPP).
$base = rtrim($argv[1] ?? $base, '/');
foreach (['/' => 200, '/login/' => 200, '/assets/css/app.css' => 200, '/assets/fonts/barlow-400.woff2' => 200,
          '/.env' => 403, '/composer.json' => 403, '/scripts/setup-local.php' => 403] as $path => $expected) {
    $request = curl_init($base . $path);
    curl_setopt_array($request, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 5]);
    $body = curl_exec($request);
    $status = curl_getinfo($request, CURLINFO_RESPONSE_CODE);
    if ($body === false || $status !== $expected) {
        throw new RuntimeException("HTTP $path: expected $expected, got $status. Start composer serve first.");
    }
    if ($path === '/' && !str_contains($body, 'PENA')) throw new RuntimeException('PENA homepage missing');
    echo "PASS HTTP $path: $status\n";
}
