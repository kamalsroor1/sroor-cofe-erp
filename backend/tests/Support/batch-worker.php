<?php

declare(strict_types=1);

/*
 * PERF-04 child process: runs a LIST of business operations, in order, against one harness
 * tenant schema on the test MySQL/MariaDB server. Several of these run at the same time (on
 * one or several tenants) to replay a concurrent business day or a branch race.
 *
 * Same protocol and guards as concurrency-worker.php: boots the app, connects to the tenant
 * schema, prints READY, waits for GO on stdin, prints ONE JSON line
 * {"ok": true, "results": [{"op": ..., "ok": bool, ...}, ...]}. An operation that fails is
 * recorded (error class / message) and the worker goes on with the next one.
 *
 * Refuses to run unless APP_ENV=testing, the driver is MySQL/MariaDB and the schema is a
 * harness tenant (`<prefix>qa…`).
 *
 * Usage (by the tests only): php batch-worker.php batch <tenant-db> <base64-json {"ops": [...]}>
 */

use App\Actions\Expenses\CreateExpenseAction;
use App\DTOs\Expenses\ExpenseDTO;
use App\Services\InvoiceService;
use App\Services\PaymentService;
use App\Services\PurchaseService;
use App\Services\ReturnService;
use App\Services\StockTransferService;
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
if (! is_array($payload) || ! is_array($payload['ops'] ?? null)) {
    $respond(['ok' => false, 'error_class' => 'Refused', 'error' => 'Invalid payload.'], 2);
}

config(['database.connections.batch_worker' => array_merge($baseConnection, ['database' => $database])]);
DB::setDefaultConnection('batch_worker');
DB::connection()->getPdo();

fwrite(STDOUT, 'READY'.PHP_EOL);
fflush(STDOUT);
fgets(STDIN);

$results = [];
foreach ($payload['ops'] as $op) {
    $name = (string) ($op['op'] ?? '');
    $data = (array) ($op['data'] ?? []);

    try {
        $results[] = ['op' => $name, 'ok' => true, 'tag' => $op['tag'] ?? null] + match ($name) {
            'checkout' => (static function () use ($data): array {
                $invoice = app(InvoiceService::class)->confirmInvoice($data);

                return ['id' => (int) $invoice->getKey(), 'number' => (string) $invoice->invoice_number, 'net' => (string) $invoice->net_total];
            })(),
            'collection' => (static function () use ($data): array {
                $payment = app(PaymentService::class)->recordCustomerPayment($data);

                return ['id' => (int) $payment->getKey(), 'number' => (string) $payment->payment_number];
            })(),
            'supplier_payment' => (static function () use ($data): array {
                $payment = app(PaymentService::class)->recordSupplierPayment($data);

                return ['id' => (int) $payment->getKey(), 'number' => (string) $payment->payment_number];
            })(),
            'expense' => (static function () use ($data): array {
                $expense = app(CreateExpenseAction::class)->execute(ExpenseDTO::fromArray($data), (int) $data['user_id']);

                return ['id' => (int) $expense->getKey(), 'number' => (string) $expense->expense_number];
            })(),
            'purchase' => (static function () use ($data): array {
                $purchase = app(PurchaseService::class)->createPurchase($data);

                return ['id' => (int) $purchase->getKey(), 'number' => (string) $purchase->purchase_number, 'net' => (string) $purchase->net_total];
            })(),
            'return' => (static function () use ($data): array {
                $return = app(ReturnService::class)->createReturn($data);

                return ['id' => (int) $return->getKey(), 'number' => (string) $return->return_number, 'total' => (string) $return->total_amount];
            })(),
            'transfer' => (static function () use ($data): array {
                $transfer = app(StockTransferService::class)->createTransfer($data);

                return ['id' => (int) $transfer->getKey(), 'number' => (string) $transfer->transfer_number];
            })(),
            default => throw new InvalidArgumentException("Unknown operation [{$name}]."),
        };
    } catch (Throwable $e) {
        $root = $e->getPrevious() ?? $e;
        $results[] = [
            'op' => $name,
            'ok' => false,
            'tag' => $op['tag'] ?? null,
            'error_class' => $e::class,
            'error' => mb_substr($e->getMessage(), 0, 300),
            'driver_code' => $root instanceof PDOException ? ($root->errorInfo[1] ?? null) : null,
        ];
    }
}

$respond(['ok' => true, 'results' => $results]);
