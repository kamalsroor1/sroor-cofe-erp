<?php

declare(strict_types=1);

/*
 * Router for `php -S` in the local smoke harness (e2e/smoke).
 *
 * Same job as Laravel's built-in server.php + public/index.php, with one difference: Laravel's
 * public_path() points at a temp mirror that holds only a junction to public/build. The tracked
 * backend/public/hot file (stale Vite dev URL) is therefore invisible, and the SPA loads the
 * built assets, without touching any file in the working tree.
 */

use Illuminate\Foundation\Application;
use Illuminate\Http\Request;

$realPublic = (string) getenv('E2E_SMOKE_REAL_PUBLIC');
$mirrorPublic = (string) getenv('E2E_SMOKE_PUBLIC_MIRROR');

$uri = urldecode(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?? '');

// Static files are served by the PHP built-in server from the real public dir (-t).
if ($uri !== '/' && is_file($realPublic.$uri)) {
    return false;
}

define('LARAVEL_START', microtime(true));

$backend = dirname(__DIR__, 2).DIRECTORY_SEPARATOR.'backend';

require $backend.'/vendor/autoload.php';

/** @var Application $app */
$app = require_once $backend.'/bootstrap/app.php';
$app->usePublicPath($mirrorPublic);

$app->handleRequest(Request::capture());
