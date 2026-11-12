<?php

declare(strict_types=1);

namespace Tests\Feature\Console;

use App\Actions\Perf\SeedPerfDatasetAction;
use App\Actions\Perf\VerifyTenantConsistencyAction;
use App\Actions\Reports\GetStockDriftReportAction;
use App\Models\Customer;
use App\Models\Expense;
use App\Models\Invoice;
use App\Models\Item;
use App\Models\Payment;
use App\Models\Purchase;
use App\Models\ReturnDocument;
use App\Models\Store;
use App\Models\StoreStock;
use App\Models\Tenant;
use App\Support\TenantClock;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Group;
use Tests\TenantTestCase;

/**
 * PERF-04: `tenant:seed-perf` writes exactly what was asked, keeps the app's invariants (stock,
 * vouchers, balances, numbering), is idempotent, refuses outside local/testing and only ever
 * touches the tenant it was given.
 *
 * Also in the `mysql` group: the bulk inserts must be valid on MySQL/MariaDB too.
 */
#[Group('mysql')]
final class SeedPerfDataCommandTest extends TenantTestCase
{
    private const OPTIONS = ['--invoices' => 150, '--items' => 30, '--stores' => 3, '--days' => 30, '--customers' => 12, '--suppliers' => 4];

    public function test_it_refuses_outside_local_and_testing_and_writes_nothing(): void
    {
        $tenant = $this->createTenant();

        foreach (['production', 'staging'] as $environment) {
            $this->app['env'] = $environment;
            try {
                $this->artisan('tenant:seed-perf', ['tenant' => $tenant->getTenantKey()] + self::OPTIONS)
                    ->expectsOutputToContain('tenant:seed-perf')
                    ->assertExitCode(1);
            } finally {
                $this->app['env'] = 'testing';
            }
        }

        $this->assertSame(0, $this->inTenant($tenant, fn (): int => Invoice::query()->count() + Item::query()->count()));
    }

    public function test_an_unknown_tenant_or_an_invalid_option_is_an_error(): void
    {
        $this->artisan('tenant:seed-perf', ['tenant' => 'qa-no-such-tenant'] + self::OPTIONS)->assertExitCode(1);

        $tenant = $this->createTenant();
        $this->artisan('tenant:seed-perf', ['tenant' => $tenant->getTenantKey(), '--invoices' => '-5'])->assertExitCode(1);
        $this->artisan('tenant:seed-perf', ['tenant' => $tenant->getTenantKey(), '--stores' => 'abc'])->assertExitCode(1);
        $this->assertSame(0, $this->inTenant($tenant, fn (): int => Invoice::query()->count()));
    }

    public function test_it_seeds_exact_counts_and_a_dataset_on_which_the_invariants_hold(): void
    {
        $tenant = $this->createTenant();

        $this->artisan('tenant:seed-perf', ['tenant' => $tenant->getTenantKey()] + self::OPTIONS)->assertExitCode(0);

        $state = $this->inTenant($tenant, fn (): array => [
            'invoices' => Invoice::query()->count(),
            'items' => Item::query()->where('code', 'like', 'PERF-I%')->count(),
            'stores' => Store::query()->count(),
            'customers' => Customer::query()->where('name', 'like', 'عميل أداء%')->count(),
            'expenses' => Expense::query()->count(),
            'returns' => ReturnDocument::query()->count(),
            'purchases' => Purchase::query()->count(),
            'cash_invoices_without_voucher' => Invoice::query()->where('payment_type', 'cash')
                ->whereDoesntHave('payments')->count(),
            'sale_vouchers' => Payment::query()->where('payment_number', 'like', 'PAY-INV-%')->count(),
            'cash_invoices' => Invoice::query()->where('payment_type', 'cash')->count(),
            'stock_rows' => StoreStock::query()->count(),
            'negative' => StoreStock::query()->where('quantity', '<', 0)->count(),
            'drift' => app(GetStockDriftReportAction::class)->execute(1, 0)['items'],
            'verify' => app(VerifyTenantConsistencyAction::class)->execute()['problems'],
            'dates' => [Invoice::query()->min('invoice_date'), Invoice::query()->max('invoice_date')],
            'today' => app(TenantClock::class)->businessDate(),
            'future' => Invoice::query()->where('created_at', '>', now())->count(),
        ]);

        $this->assertSame(150, $state['invoices']);
        $this->assertSame(30, $state['items']);
        $this->assertSame(3, $state['stores'], 'The main store plus two created branches.');
        $this->assertSame(12, $state['customers']);
        $this->assertSame(60, $state['expenses'], 'Two expenses per day.');
        $this->assertGreaterThan(0, $state['returns']);
        $this->assertSame(3, $state['purchases'], 'max(stores, invoices / 50)');
        $this->assertSame(0, $state['cash_invoices_without_voucher']);
        $this->assertSame($state['cash_invoices'], $state['sale_vouchers'], 'One sale voucher per cash invoice.');
        $this->assertSame(90, $state['stock_rows']);
        $this->assertSame(0, $state['negative']);
        $this->assertSame([], $state['drift'], 'SUM(store_stocks) must equal items.current_stock.');
        $this->assertSame([], $state['verify']);
        $this->assertSame($state['today'], substr((string) $state['dates'][1], 0, 10));
        $this->assertSame(0, $state['future'], 'Nothing is stamped after the seed ran.');

        // The read-only commands agree.
        $this->artisan('stock:report-drift', ['--tenant' => $tenant->getTenantKey()])->assertExitCode(0);
        $this->artisan('tenant:perf-verify', ['tenant' => $tenant->getTenantKey()])->assertExitCode(0);
    }

    public function test_a_second_run_writes_nothing_and_other_options_are_refused(): void
    {
        $tenant = $this->createTenant();
        $this->artisan('tenant:seed-perf', ['tenant' => $tenant->getTenantKey()] + self::OPTIONS)->assertExitCode(0);
        $before = $this->rowCounts($tenant);

        $this->artisan('tenant:seed-perf', ['tenant' => $tenant->getTenantKey()] + self::OPTIONS)->assertExitCode(0);
        $this->assertSame($before, $this->rowCounts($tenant));

        $this->artisan('tenant:seed-perf', ['tenant' => $tenant->getTenantKey(), '--invoices' => 151] + self::OPTIONS)->assertExitCode(1);
        $this->assertSame($before, $this->rowCounts($tenant));
    }

    public function test_a_tenant_that_already_holds_documents_is_refused_and_left_untouched(): void
    {
        $tenant = $this->createTenant();
        $this->artisan('tenant:seed-perf', ['tenant' => $tenant->getTenantKey()] + self::OPTIONS)->assertExitCode(0);
        // Without the marker the tenant is just "a tenant with invoices", like a restored real one.
        $this->inTenant($tenant, fn () => DB::table('settings')->where('key', SeedPerfDatasetAction::MARKER_KEY)->delete());
        $before = $this->rowCounts($tenant);

        $this->artisan('tenant:seed-perf', ['tenant' => $tenant->getTenantKey()] + self::OPTIONS)->assertExitCode(1);

        $this->assertSame($before, $this->rowCounts($tenant));
    }

    public function test_the_dataset_is_deterministic(): void
    {
        $a = $this->createTenant();
        $b = $this->createTenant();
        $this->artisan('tenant:seed-perf', ['tenant' => $a->getTenantKey()] + self::OPTIONS)->assertExitCode(0);
        $this->artisan('tenant:seed-perf', ['tenant' => $b->getTenantKey()] + self::OPTIONS)->assertExitCode(0);

        $fingerprint = fn (Tenant $tenant): array => $this->inTenant($tenant, fn (): array => [
            'invoices' => Invoice::query()->orderBy('id')->get(['invoice_number', 'net_total', 'payment_type'])
                ->map(fn (Invoice $i): string => $i->invoice_number.'|'.$i->net_total.'|'.$i->payment_type)->all(),
            'stock' => StoreStock::query()->join('items', 'items.id', '=', 'store_stocks.item_id')
                ->orderBy('items.code')->orderBy('store_stocks.id')->pluck('store_stocks.quantity')->map(fn ($q): string => (string) $q)->all(),
        ]);

        $this->assertSame($fingerprint($a), $fingerprint($b));
    }

    public function test_seeding_one_tenant_writes_nothing_into_another(): void
    {
        $a = $this->createTenant();
        $b = $this->createTenant();
        $before = $this->rowCounts($b);

        $this->artisan('tenant:seed-perf', ['tenant' => $a->getTenantKey()] + self::OPTIONS)->assertExitCode(0);

        $this->assertSame($before, $this->rowCounts($b));
        $this->assertSame(150, $this->rowCounts($a)['invoices']);
    }

    public function test_a_real_sale_after_seeding_succeeds_with_the_next_free_number(): void
    {
        config(['pos.require_shift_by_default' => true]);
        $tenant = $this->createTenant();
        $this->artisan('tenant:seed-perf', ['tenant' => $tenant->getTenantKey()] + self::OPTIONS)->assertExitCode(0);

        [$prefix, $lastToday, $item, $customer] = $this->inTenant($tenant, function (): array {
            $prefix = 'INV-MAIN01-'.str_replace('-', '', app(TenantClock::class)->businessDate());
            $numbers = Invoice::query()->where('invoice_number', 'like', $prefix.'-%')->pluck('invoice_number')->all();
            $store = Store::query()->where('is_main', true)->value('id');
            $item = (int) StoreStock::query()->where('store_id', $store)->where('quantity', '>=', 2)->value('item_id');

            return [$prefix, count($numbers), $item, (int) Customer::query()->where('name', 'like', 'عميل أداء%')->value('id')];
        });
        $this->assertGreaterThan(0, $lastToday, 'The seed covers today.');

        $headers = $this->tenantHeaders($tenant);
        $this->postJson('/api/v1/shifts/open', ['opening_cash_balance' => '100.000'], $headers)->assertSuccessful();
        $sale = $this->postJson('/api/v1/pos/checkout', [
            'customer_id' => $customer,
            'payment_type' => 'cash',
            'payment_method' => 'cash',
            'items' => [['item_id' => $item, 'quantity' => '1.000']],
        ], $headers)->assertStatus(201)->json('data');
        $number = $sale['invoice_number'];

        $this->assertSame($prefix.'-'.str_pad((string) ($lastToday + 1), 4, '0', STR_PAD_LEFT), $number);
        $this->assertSame([], $this->inTenant($tenant, fn (): array => app(VerifyTenantConsistencyAction::class)->execute()['problems']));

        // The shift only sees the real sale, never the seeded vouchers of earlier today.
        $current = $this->getJson('/api/v1/shifts/current', $headers)->assertOk()->assertJsonPath('has_active', true);
        $this->assertSame(bcadd('100.000', (string) $sale['net_total'], 3), (string) $current->json('metrics.expected_cash_balance'));
    }

    /** @return array<string, int> */
    private function rowCounts(Tenant $tenant): array
    {
        return $this->inTenant($tenant, fn (): array => collect(['invoices', 'invoice_items', 'payments', 'returns', 'purchases', 'expenses', 'items', 'store_stocks', 'stock_movements', 'customers', 'suppliers', 'stores', 'document_sequences'])
            ->mapWithKeys(fn (string $table): array => [$table => DB::table($table)->count()])->all());
    }
}
