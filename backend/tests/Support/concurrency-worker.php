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

use App\Exceptions\DatabaseBusyException;
use App\Models\Invoice;
use App\Models\Item;
use App\Services\InvoiceService;
use App\Services\PaymentService;
use App\Services\ReturnService;
use App\Services\StockService;
use App\Services\StockTransferService;
use App\Support\Database\TransactionRetry;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Database\Events\TransactionRolledBack;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;

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

// BUG-07: every rolled-back attempt is counted, so the test can report how many lock conflicts
// the retry helper absorbed (a nested savepoint rollback would count too; these flows have none).
$rollbacks = 0;
Event::listen(TransactionRolledBack::class, static function () use (&$rollbacks): void {
    $rollbacks++;
});

fwrite(STDOUT, 'READY'.PHP_EOL);
fflush(STDOUT);
fgets(STDIN);

try {
    $result = match ($scenario) {
        // Through the same retry helper the sale/transfer/return/purchase flows use (BUG-07).
        'deduct' => TransactionRetry::run(static function () use ($payload): array {
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

            return ['invoice_id' => (int) $invoice->getKey(), 'invoice_number' => (string) $invoice->invoice_number, 'created' => $invoice->wasRecentlyCreated];
        })(),
        'update' => (static function () use ($payload): array {
            $invoice = app(InvoiceService::class)->updateInvoice(Invoice::query()->findOrFail((int) $payload['invoice_id']), (array) $payload['data']);

            return ['invoice_id' => (int) $invoice->getKey(), 'invoice_number' => (string) $invoice->invoice_number, 'created' => false];
        })(),
        'payment' => (static function () use ($payload): array {
            $payment = app(PaymentService::class)->recordCustomerPayment($payload);

            return ['payment_id' => (int) $payment->getKey(), 'payment_number' => (string) $payment->payment_number];
        })(),
        'return' => (static function () use ($payload): array {
            $return = app(ReturnService::class)->createReturn($payload);

            return ['return_id' => (int) $return->getKey(), 'return_number' => (string) $return->return_number];
        })(),
        // BUG-07: a REAL lock conflict the helper must absorb. Locks counter row `first`, waits, then
        // locks `second` (two workers with opposite orders deadlock: 1213), bumping both by one. The
        // optional `isolation` override ('' = server level) lets a plain read open a snapshot first,
        // which MariaDB answers with 1020 once another worker changed the row.
        'lockorder', 'snapshot' => (static function () use ($payload, $scenario): array {
            if (array_key_exists('isolation', $payload)) {
                config(['database.transaction_retry.isolation' => (string) $payload['isolation']]);
            }

            TransactionRetry::run(static function () use ($payload, $scenario): void {
                if ($scenario === 'snapshot') {
                    DB::table('document_sequences')->where('scope', (string) $payload['first'])->value('last_value');
                    usleep((int) $payload['pause_ms'] * 1000);
                }
                DB::table('document_sequences')->where('scope', (string) $payload['first'])->lockForUpdate()->value('last_value');
                DB::table('document_sequences')->where('scope', (string) $payload['first'])->increment('last_value');
                if ($scenario === 'lockorder') {
                    usleep((int) $payload['pause_ms'] * 1000);
                    DB::table('document_sequences')->where('scope', (string) $payload['second'])->lockForUpdate()->value('last_value');
                    DB::table('document_sequences')->where('scope', (string) $payload['second'])->increment('last_value');
                }
            });

            return [];
        })(),
        'transfer' => (static function () use ($payload): array {
            $transfer = app(StockTransferService::class)->createTransfer($payload);

            return ['transfer_id' => (int) $transfer->getKey(), 'transfer_number' => (string) $transfer->transfer_number];
        })(),
        default => throw new InvalidArgumentException("Unknown scenario [{$scenario}]."),
    };

    $respond(['ok' => true, 'rollbacks' => $rollbacks] + $result);
} catch (Throwable $e) {
    // The driver error code (1213 / 1205 / 1020 ...) of the root database error, if any.
    $root = $e instanceof DatabaseBusyException && $e->getPrevious() !== null ? $e->getPrevious() : $e;
    $driverCode = $root instanceof PDOException ? ($root->errorInfo[1] ?? null) : null;

    $respond(['ok' => false, 'error_class' => $e::class, 'error' => $e->getMessage(), 'driver_code' => $driverCode, 'rollbacks' => $rollbacks], 1);
}
