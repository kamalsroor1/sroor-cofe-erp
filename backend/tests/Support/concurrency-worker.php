<?php

declare(strict_types=1);

/*
 * QA-1 child process for Tests\Feature\Concurrency\MysqlConcurrencyTest.
 *
 * Boots the application, connects straight to one harness tenant schema on the
 * test MySQL server, prints READY, waits for GO on stdin (so every worker starts
 * the race at the same moment) and prints one JSON result line.
 *
 * Not a test and not a tool: it refuses to run unless APP_ENV=testing, the
 * driver is MySQL and the schema is a harness tenant (`<prefix>qa…`).
 *
 * Usage (by the test only): php concurrency-worker.php <scenario> <tenant-db> <base64-json-payload>
 */

use App\Models\Item;
use App\Services\InvoiceService;
use App\Services\StockService;
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

$scenario = (string) ($argv[1] ?? '');
$database = (string) ($argv[2] ?? '');
$payload = json_decode((string) base64_decode((string) ($argv[3] ?? ''), true), true);

$baseConnection = (array) config('database.connections.'.config('database.default'));
$harnessPrefix = (string) config('tenancy.database.prefix', 'tenant_').'qa';

if (! app()->environment('testing')) {
    $respond(['ok' => false, 'error_class' => 'Refused', 'error' => 'APP_ENV must be testing.'], 2);
}
if (! in_array($baseConnection['driver'] ?? null, ['mysql', 'mariadb'], true)) {
    $respond(['ok' => false, 'error_class' => 'Refused', 'error' => 'MySQL only.'], 2);
}
if (! str_starts_with($database, $harnessPrefix) || preg_match('/^[A-Za-z0-9_]+$/', $database) !== 1) {
    $respond(['ok' => false, 'error_class' => 'Refused', 'error' => 'Not a harness tenant database.'], 2);
}
if (! is_array($payload)) {
    $respond(['ok' => false, 'error_class' => 'Refused', 'error' => 'Invalid payload.'], 2);
}

config(['database.connections.concurrency_worker' => array_merge($baseConnection, ['database' => $database])]);
DB::setDefaultConnection('concurrency_worker');
DB::connection()->getPdo();

fwrite(STDOUT, 'READY'.PHP_EOL);
fflush(STDOUT);
fgets(STDIN);

try {
    $result = match ($scenario) {
        'deduct' => DB::transaction(static function () use ($payload): array {
            $item = Item::query()->findOrFail((int) $payload['item_id']);
            $movement = app(StockService::class)->deductStock(
                $item,
                (string) $payload['quantity'],
                $item,
                (string) $payload['document'],
                'sales_out',
                null,
                (int) $payload['store_id'],
            );

            return ['stock_after' => (string) $movement->stock_after];
        }),
        'checkout' => (static function () use ($payload): array {
            $invoice = app(InvoiceService::class)->confirmInvoice($payload);

            return ['invoice_id' => (int) $invoice->getKey(), 'created' => $invoice->wasRecentlyCreated];
        })(),
        default => throw new InvalidArgumentException("Unknown scenario [{$scenario}]."),
    };

    $respond(['ok' => true] + $result);
} catch (Throwable $e) {
    $respond(['ok' => false, 'error_class' => $e::class, 'error' => $e->getMessage()], 1);
}
