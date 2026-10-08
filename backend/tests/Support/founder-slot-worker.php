<?php

declare(strict_types=1);

/*
 * Child process for Tests\Feature\Billing\FounderPricingServiceTest (group `mysql`).
 *
 * Boots the application on the CENTRAL test database, prints READY, waits for GO on
 * stdin (so every worker starts the race at the same moment), claims a founder slot for
 * one subscription inside its own central transaction and prints one JSON result line.
 *
 * Not a test and not a tool: it refuses to run unless APP_ENV=testing, the driver is
 * MySQL and the database is a throwaway test schema (name ends with _test / _testing).
 *
 * Usage (by the test only): php founder-slot-worker.php <base64-json-payload>
 */

use App\Models\BillingPayment;
use App\Models\Subscription;
use App\Services\Billing\FounderPricingService;
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
if (! is_array($payload) || ! isset($payload['subscription_id'], $payload['payment_id'])) {
    $respond(['ok' => false, 'error_class' => 'Refused', 'error' => 'Invalid payload.'], 2);
}

DB::connection($central)->getPdo();

fwrite(STDOUT, 'READY'.PHP_EOL);
fflush(STDOUT);
fgets(STDIN);

try {
    $claimed = DB::connection($central)->transaction(static function () use ($payload): bool {
        $subscription = Subscription::query()->findOrFail((int) $payload['subscription_id']);
        $payment = BillingPayment::query()->findOrFail((int) $payload['payment_id']);

        return app(FounderPricingService::class)->claim($subscription, $payment);
    });

    $respond(['ok' => true, 'claimed' => $claimed, 'subscription_id' => (int) $payload['subscription_id']]);
} catch (Throwable $e) {
    $respond(['ok' => false, 'error_class' => $e::class, 'error' => $e->getMessage()], 1);
}
