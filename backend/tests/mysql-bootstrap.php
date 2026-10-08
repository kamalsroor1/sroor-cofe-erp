<?php

declare(strict_types=1);

/*
 * QA-1 bootstrap for phpunit.mysql.xml.
 *
 * RefreshDatabase runs `migrate:fresh` on the configured database and the tenant
 * harness creates/drops `tenant_qa*` schemas, so this suite must never point at a
 * real database. Abort before anything connects unless the target is clearly a
 * throwaway test schema (name ends with `_test` or `_testing`).
 */

require __DIR__.'/../vendor/autoload.php';

$connection = (string) (getenv('DB_CONNECTION') ?: '');
$database = (string) (getenv('DB_DATABASE') ?: '');

if (! in_array($connection, ['mysql', 'mariadb'], true)) {
    fwrite(STDERR, "phpunit.mysql.xml: DB_CONNECTION must be mysql or mariadb, got [{$connection}].".PHP_EOL);
    exit(1);
}

if (preg_match('/_(test|testing)$/', $database) !== 1) {
    fwrite(STDERR, "phpunit.mysql.xml: refusing to run against [{$database}]. Use a throwaway schema whose name ends with _test or _testing.".PHP_EOL);
    exit(1);
}

if (getenv('APP_ENV') !== 'testing') {
    fwrite(STDERR, 'phpunit.mysql.xml: APP_ENV must be testing.'.PHP_EOL);
    exit(1);
}

// No APP_KEY is committed in phpunit.mysql.xml. When the caller did not export one
// (CI does), generate a random throwaway key for this run only.
if ((string) (getenv('APP_KEY') ?: '') === '') {
    $key = 'base64:'.base64_encode(random_bytes(32));
    putenv('APP_KEY='.$key);
    $_ENV['APP_KEY'] = $key;
    $_SERVER['APP_KEY'] = $key;
}
