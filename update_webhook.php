<?php

// Secure Deployment Webhook for Sroor ERP
$secretToken = 'sroor_secure_deploy_token_2026_ks';

if (($_GET['token'] ?? '') !== $secretToken && ($_POST['token'] ?? '') !== $secretToken) {
    http_response_code(403);
    die(json_encode(['status' => 'error', 'message' => 'Forbidden: Invalid token.']));
}

header('Content-Type: application/json; charset=utf-8');

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

$targetDirs = [
    '/home/u910151740/domains/sroor.baraa-solutions.com/public_html',
    '/home/u910151740/domains/baraa-solutions.com/public_html/sroor',
    '/home/u910151740/domains/shipping.baraa-solutions.com/public_html',
];

$output = [];
foreach ($targetDirs as $dir) {
    if (!is_dir($dir)) continue;
    chdir($dir);
    
    exec("git fetch origin main 2>&1", $output);
    exec("git reset --hard origin/main 2>&1", $output);
    
    @mkdir("{$dir}/storage/framework/cache/data", 0775, true);
    @mkdir("{$dir}/storage/framework/views", 0775, true);
    @mkdir("{$dir}/storage/framework/sessions", 0775, true);
    
    exec("rm -rf {$dir}/storage/framework/views/* {$dir}/storage/framework/cache/data/* 2>&1", $output);
    exec("{$phpBin} artisan optimize:clear 2>&1", $output);
    exec("{$phpBin} artisan view:cache 2>&1", $output);
    exec("{$phpBin} artisan config:cache 2>&1", $output);
    exec("{$phpBin} artisan route:cache 2>&1", $output);
}

if (function_exists('opcache_reset')) {
    opcache_reset();
}

echo json_encode([
    'status' => 'success',
    'message' => 'All Sroor ERP directories deployed, optimized, and OPcache reset successfully!',
    'php_bin' => $phpBin,
    'timestamp' => date('Y-m-d H:i:s'),
    'output' => $output
], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
