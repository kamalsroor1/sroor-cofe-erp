<?php

declare(strict_types=1);

namespace Tests\Feature\Console;

use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Item;
use App\Models\Payment;
use App\Models\Store;
use App\Models\StoreStock;
use App\Models\Tenant;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Testing\PendingCommand;
use PHPUnit\Framework\Attributes\Group;
use Tests\TenantTestCase;

/**
 * PERF-04: `tenant:perf-verify` passes on a consistent tenant and FAILS (exit code 1, the
 * problem named in the output) for each injected inconsistency: a duplicate invoice number,
 * a stock drift, a missing cash voucher, a balance that no longer matches its documents, and
 * a branch balance that is not start - sold.
 *
 * Duplicate invoice numbers cannot be written while invoices_invoice_number_unique exists, so
 * that case drops the index first (as on a database where the index never landed): the check
 * is the second line of defence behind the index, and the test proves it would see one.
 */
#[Group('mysql')]
final class PerfVerifyCommandTest extends TenantTestCase
{
    private const SEED = ['--invoices' => 60, '--items' => 12, '--stores' => 2, '--days' => 10, '--customers' => 6, '--suppliers' => 2];

    private Tenant $tenant;

    private string $snapshot = '';

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = $this->createTenant();
        $this->artisan('tenant:seed-perf', ['tenant' => $this->tenant->getTenantKey()] + self::SEED)->assertExitCode(0);
    }

    protected function tearDown(): void
    {
        if ($this->snapshot !== '' && is_file($this->snapshot)) {
            unlink($this->snapshot);
        }

        parent::tearDown();
    }

    public function test_a_consistent_tenant_passes(): void
    {
        $this->verify()->assertExitCode(0);
    }

    public function test_an_unknown_tenant_is_an_error(): void
    {
        $this->artisan('tenant:perf-verify', ['tenant' => 'qa-no-such-tenant'])->assertExitCode(1);
    }

    public function test_a_duplicate_invoice_number_fails_the_check(): void
    {
        $duplicated = $this->inTenant($this->tenant, function (): string {
            if (DB::connection()->getDriverName() === 'sqlite') {
                DB::statement('DROP INDEX invoices_invoice_number_unique');
            } else {
                Schema::table('invoices', fn ($table) => $table->dropUnique('invoices_invoice_number_unique'));
            }
            [$first, $second] = Invoice::query()->orderBy('id')->limit(2)->get()->all();
            DB::table('invoices')->where('id', $second->id)->update(['invoice_number' => $first->invoice_number]);

            return (string) $first->invoice_number;
        });

        $this->verify()->expectsOutputToContain('invoices: '.$duplicated.' x2')->assertExitCode(1);
    }

    public function test_a_stock_drift_fails_the_check(): void
    {
        $code = $this->inTenant($this->tenant, function (): string {
            $row = StoreStock::query()->orderBy('id')->firstOrFail();
            DB::table('store_stocks')->where('id', $row->id)->update(['quantity' => bcadd((string) $row->quantity, '0.250', 3)]);

            return (string) Item::query()->whereKey($row->item_id)->value('code');
        });

        $this->verify()->expectsOutputToContain($code.':')->assertExitCode(1);
    }

    public function test_a_missing_cash_voucher_fails_the_check(): void
    {
        $number = $this->inTenant($this->tenant, function (): string {
            $payment = Payment::query()->where('payment_number', 'like', 'PAY-INV-%')->orderBy('id')->firstOrFail();
            $invoice = Invoice::query()->findOrFail($payment->invoice_id);
            DB::table('payments')->where('id', $payment->id)->delete();

            return (string) $invoice->invoice_number;
        });

        // The customer's balance no longer matches either (one fewer payment).
        $this->verify()->expectsOutputToContain($number.': paid')->assertExitCode(1);
    }

    public function test_a_balance_that_does_not_match_its_documents_fails_the_check(): void
    {
        $this->inTenant($this->tenant, fn () => DB::table('customers')
            ->where('id', Customer::query()->where('name', 'like', 'عميل أداء%')->orderBy('id')->value('id'))
            ->update(['current_balance' => '0.001']));

        $this->verify()->expectsOutputToContain(': 0.001 != ')->assertExitCode(1);
    }

    public function test_stock_is_start_minus_sold_after_real_sales_and_fails_when_stock_vanishes(): void
    {
        $this->snapshot = (string) tempnam(sys_get_temp_dir(), 'perf-snapshot-');
        $this->artisan('tenant:perf-verify', ['tenant' => $this->tenant->getTenantKey(), '--snapshot' => $this->snapshot])->assertExitCode(0);

        [$storeId, $itemId, $customerId] = $this->inTenant($this->tenant, fn (): array => [
            (int) Store::query()->where('is_main', true)->value('id'),
            (int) StoreStock::query()->where('store_id', Store::query()->where('is_main', true)->value('id'))->where('quantity', '>=', 3)->value('item_id'),
            (int) Customer::query()->where('name', 'like', 'عميل أداء%')->value('id'),
        ]);

        // phpunit.mysql.xml keeps the shipped default (a branch requires an open shift).
        $this->postJson('/api/v1/shifts/open', ['opening_cash_balance' => '0.000'], $this->tenantHeaders($this->tenant, null, $storeId))->assertSuccessful();

        foreach (['1.000', '2.000'] as $quantity) {
            $this->postJson('/api/v1/pos/checkout', [
                'customer_id' => $customerId,
                'payment_type' => 'cash',
                'payment_method' => 'cash',
                'items' => [['item_id' => $itemId, 'quantity' => $quantity]],
            ], $this->tenantHeaders($this->tenant, null, $storeId))->assertStatus(201);
        }

        $this->verify(['--baseline' => $this->snapshot])->assertExitCode(0);

        // Stock leaves the branch without a document (item total adjusted too, so no drift).
        $this->inTenant($this->tenant, function () use ($storeId, $itemId): void {
            DB::table('store_stocks')->where('store_id', $storeId)->where('item_id', $itemId)->decrement('quantity', 1);
            DB::table('items')->where('id', $itemId)->decrement('current_stock', 1);
        });

        $this->verify()->assertExitCode(0);
        $this->verify(['--baseline' => $this->snapshot])
            ->expectsOutputToContain('store '.$storeId.' item '.$itemId.':')
            ->assertExitCode(1);
    }

    public function test_an_invalid_baseline_file_is_an_error(): void
    {
        $this->snapshot = (string) tempnam(sys_get_temp_dir(), 'perf-snapshot-');
        file_put_contents($this->snapshot, '{"stocks": "nope"}');

        $this->verify(['--baseline' => $this->snapshot])->assertExitCode(1);
    }

    /**
     * @param  array<string, string>  $options
     */
    private function verify(array $options = []): PendingCommand
    {
        return $this->artisan('tenant:perf-verify', ['tenant' => $this->tenant->getTenantKey()] + $options);
    }
}
