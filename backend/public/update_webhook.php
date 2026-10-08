<?php

// Secure Deployment Webhook for Sroor ERP
// Requires POST + X-Deploy-Timestamp + X-Deploy-Signature: sha256=HMAC_SHA256(secret, "{timestamp}.{body}")
// using DEPLOY_WEBHOOK_HMAC_SECRET (env or backend/.env). Refuses all requests when the secret is unset.
// The legacy static token is no longer accepted anywhere and must be rotated on the server.

header('Content-Type: application/json; charset=utf-8');

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    http_response_code(405);
    header('Allow: POST');
    exit(json_encode(['status' => 'error', 'message' => 'Method Not Allowed']));
}

$hmacSecret = (string) (getenv('DEPLOY_WEBHOOK_HMAC_SECRET') ?: ($_SERVER['DEPLOY_WEBHOOK_HMAC_SECRET'] ?? ''));
if ($hmacSecret === '') {
    $envFile = dirname(__DIR__).'/.env';
    if (is_readable($envFile)) {
        $envContents = (string) file_get_contents($envFile);
        if (preg_match('/^DEPLOY_WEBHOOK_HMAC_SECRET="?([^"\r\n]*)"?$/m', $envContents, $matches)) {
            $hmacSecret = $matches[1];
        }
    }
}
$hmacSecret = trim($hmacSecret);

if ($hmacSecret === '' || strlen($hmacSecret) < 32) {
    http_response_code(503);
    exit(json_encode(['status' => 'error', 'message' => 'Webhook disabled']));
}

$body = (string) file_get_contents('php://input');
$ts = (string) ($_SERVER['HTTP_X_DEPLOY_TIMESTAMP'] ?? '');
$sig = (string) ($_SERVER['HTTP_X_DEPLOY_SIGNATURE'] ?? '');

if (! ctype_digit($ts) || abs(time() - (int) $ts) > 300) {
    http_response_code(403);
    exit(json_encode(['status' => 'error', 'message' => 'Forbidden']));
}

$expected = 'sha256='.hash_hmac('sha256', $ts.'.'.$body, $hmacSecret);
if (! hash_equals($expected, $sig)) {
    http_response_code(403);
    exit(json_encode(['status' => 'error', 'message' => 'Forbidden']));
}

// Find available PHP CLI binary
$phpBin = 'php';
if (file_exists('/opt/alt/php84/usr/bin/php')) {
    $phpBin = '/opt/alt/php84/usr/bin/php';
} elseif (file_exists('/opt/alt/php83/usr/bin/php')) {
    $phpBin = '/opt/alt/php83/usr/bin/php';
} elseif (file_exists('/usr/bin/php8.4')) {
    $phpBin = '/usr/bin/php8.4';
} elseif (file_exists('/usr/bin/php8.3')) {
    $phpBin = '/usr/bin/php8.3';
}

$output = [];
$projectRoot = dirname(__DIR__);
if (file_exists($projectRoot.'/artisan')) {
    chdir($projectRoot);
}

// 1. Pull latest git code
exec('git fetch --all 2>&1', $output);
exec('git reset --hard origin/main 2>&1', $output);

// 2. Clear & Optimize caches & migrate
exec("{$phpBin} artisan migrate --force 2>&1", $output);
exec("{$phpBin} artisan optimize:clear 2>&1", $output);

echo json_encode([
    'status' => 'success',
    'message' => 'Sroor ERP deployed successfully via secure webhook!',
    'timestamp' => date('Y-m-d H:i:s'),
    'output' => $output,
], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
