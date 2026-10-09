<?php

declare(strict_types=1);

/*
 * Child process for Tests\Feature\Billing\CreditBalanceConcurrencyTest (group `mysql-only`).
 *
 * Boots the application on the CENTRAL test database, prints READY, waits for GO on
 * stdin (so every worker debits at the same moment), debits one amount through
 * CreditBalanceService in its own transaction and prints one JSON result line.
 *
 * Not a test and not a tool: it refuses to run unless APP_ENV=testing, the driver is
 * MySQL and the database is a throwaway test schema (name ends with _test / _testing).
 *
 * Usage (by the test only): php credit-debit-worker.php <base64-json-payload>
 */

use App\Exceptions\Billing\CreditLedgerException;
use App\Services\Billing\CreditBalanceService;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;

require __DIR__.'/../../vendor/autoload.php';

$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

/** @param array<string, mixed> $result */
$respond = static function (array $result, int $exitCode = 0): never {
    fwrite(STDOUT, json_encode($result, JSON_UNESCAPED_UNICODE).PHP_EOL);
    exit($exitCode);
};

$payload = json_decode((string) base64_decode((string) ($argv[1] ?? ''), true), true);
$central = (string) config('tenancy.database.central_connection', config('database.default'));
$connection = (array) config('database.connections.'.$central);

if (! app()->environment('testing')) {
    $respond(['ok' => false, 'error_class' => 'Refused', 'error' => 'APP_ENV must be testing.'], 2);
}
if (! in_array($connection['driver'] ?? null, ['mysql', 'mariadb'], true)) {
    $respond(['ok' => false, 'error_class' => 'Refused', 'error' => 'MySQL only.'], 2);
}
if (preg_match('/_(test|testing)$/', (string) ($connection['database'] ?? '')) !== 1) {
    $respond(['ok' => false, 'error_class' => 'Refused', 'error' => 'Not a throwaway test database.'], 2);
}
if (! is_array($payload) || ! isset($payload['tenant_id'], $payload['addon_key'], $payload['amount'])) {
    $respond(['ok' => false, 'error_class' => 'Refused', 'error' => 'Invalid payload.'], 2);
}

DB::connection($central)->getPdo();

fwrite(STDOUT, 'READY'.PHP_EOL);
fflush(STDOUT);
fgets(STDIN);

try {
    app(CreditBalanceService::class)->debit((string) $payload['tenant_id'], (string) $payload['addon_key'], (string) $payload['amount']);

    $respond(['ok' => true, 'debited' => true]);
} catch (CreditLedgerException $e) {
    $respond(['ok' => true, 'debited' => false, 'reason' => $e->reason()]);
} catch (Throwable $e) {
    $respond(['ok' => false, 'error_class' => $e::class, 'error' => $e->getMessage()], 1);
}
