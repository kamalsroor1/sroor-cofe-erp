<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Models\Store;
use App\Models\Tenant;
use App\Models\User;
use App\Support\TenantClock;
use Carbon\CarbonImmutable;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\Group;
use Tests\TenantTestCase;

/**
 * PERF-02: GET /api/v1/treasury/summary keeps its exact figures (computed by hand below) once
 * the branch voucher filter is read as its two disjoint halves (stamped store_id / legacy rows
 * without store_id) and the day filters use the date indexes, and its query count does not
 * grow with the voucher history.
 *
 * Main branch A, other branch B; "today" is the tenant business date, "old" 40 days earlier.
 *  cash vouchers of A:  +100.000 sale (today, stamped)      +40.250 sale (today, legacy: invoice in A)
 *                       +7.000 receipt (today, legacy: unlinked, user of A)
 *                       -5.000 refund (today)               -25.500 supplier (today)
 *                       -10.125 PAY-EXP voucher (old)        30.000 sale of a cancelled invoice (hidden)
 *  instapay of A:       +60.000 (old)
 *  not A:               999.000 legacy voucher of an invoice in B, 500.000 stamped B
 *  expenses:            A cash 12.000 (today), B cash 50.000 (today)
 *  transfer in A:       cash -> instapay 20.000, fee 1.000 (old)
 *
 *  cash:     in 100 + 40.250 + 7 - 5 = 142.250; out 25.500 + 12 + 10.125 + 20 + 1 = 68.625; balance 73.625
 *  instapay: in 60 + 20 = 80.000; out 0; balance 80.000. Liquidity 153.625.
 *  today:    sales 100 + 40.250 = 140.250; cash sales 140.250; receipts 7.000; in 147.250;
 *            supplier 25.500; expenses 12.000; refunds 5.000; out 42.500; net 104.750.
 */
#[Group('mysql')]
#[Group('mariadb-reports')]
final class TreasurySummaryFiguresApiTest extends TenantTestCase
{
    private const QUERY_BUDGET = 45;

    private Tenant $tenant;

    private int $storeA;

    private int $storeB;

    private int $userA;

    private int $customerId;

    private int $supplierId;

    private string $today;

    private string $old;

    private int $seq = 0;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = $this->createTenant();
        $this->storeA = (int) $this->tenantStore($this->tenant)->id;
        $this->inTenant($this->tenant, function (): void {
            $this->storeB = (int) Store::query()->create(['name' => 'فرع ب', 'code' => 'TRS-B', 'type' => 'retail', 'is_main' => false, 'is_active' => true])->id;
            $this->userA = (int) User::query()->orderBy('id')->value('id');
            DB::table('users')->where('id', $this->userA)->update(['default_store_id' => $this->storeA]);
            $this->today = app(TenantClock::class)->businessDate();
            $this->old = CarbonImmutable::parse($this->today)->subDays(40)->toDateString();
            $this->customerId = (int) DB::table('customers')->insertGetId(['name' => 'عميل', 'phone' => '01000008601', 'price_tier' => 'retail', 'is_active' => true, 'current_balance' => '0.000', 'created_at' => now(), 'updated_at' => now()]);
            $this->supplierId = (int) DB::table('suppliers')->insertGetId(['name' => 'مورد', 'phone' => '01000008602', 'is_active' => true, 'created_at' => now(), 'updated_at' => now()]);
        });
    }

    public function test_the_summary_figures_are_exact_for_stamped_and_legacy_vouchers(): void
    {
        $this->seedLedger();

        $response = $this->summary()->assertOk();

        $response->assertJsonPath('store_id', $this->storeA)
            ->assertJsonPath('today.date', $this->today)
            ->assertJsonPath('today.sales_total', '140.250')
            ->assertJsonPath('today.cash_collected', '140.250')
            ->assertJsonPath('today.customer_receipts', '7.000')
            ->assertJsonPath('today.total_inflow', '147.250')
            ->assertJsonPath('today.supplier_paid', '25.500')
            ->assertJsonPath('today.expenses_total', '12.000')
            ->assertJsonPath('today.cash_expenses', '12.000')
            ->assertJsonPath('today.cash_refunds', '5.000')
            ->assertJsonPath('today.total_outflow', '42.500')
            ->assertJsonPath('today.net_cash', '104.750')
            ->assertJsonPath('balances.accounts.cash.inflows', '142.250')
            ->assertJsonPath('balances.accounts.cash.outflows', '68.625')
            ->assertJsonPath('balances.accounts.cash.balance', '73.625')
            ->assertJsonPath('balances.accounts.instapay.inflows', '80.000')
            ->assertJsonPath('balances.accounts.instapay.outflows', '0.000')
            ->assertJsonPath('balances.accounts.instapay.balance', '80.000')
            ->assertJsonPath('balances.accounts.total_liquidity', '153.625');
    }

    public function test_branch_b_sees_only_its_own_vouchers(): void
    {
        $this->seedLedger();

        $this->getJson('/api/v1/treasury/summary', $this->tenantHeaders($this->tenant, null, $this->storeB))->assertOk()
            ->assertJsonPath('balances.accounts.cash.inflows', '1499.000')
            ->assertJsonPath('balances.accounts.cash.outflows', '50.000')
            ->assertJsonPath('balances.accounts.cash.balance', '1449.000')
            ->assertJsonPath('today.cash_expenses', '50.000');
    }

    public function test_the_query_count_does_not_grow_with_the_voucher_history(): void
    {
        $this->seedLedger();
        $this->summary()->assertOk(); // warm-up: token, settings and permission caches
        $small = $this->countQueries();

        $this->inTenant($this->tenant, function (): void {
            for ($i = 0; $i < 200; $i++) {
                $invoice = $this->invoiceIn($this->storeA, $this->old, '1.000');
                $this->voucher(['store_id' => $i % 2 === 0 ? $this->storeA : null, 'invoice_id' => $invoice, 'customer_id' => $this->customerId, 'amount' => '1.000', 'payment_date' => $this->old]);
            }
        });
        $large = $this->countQueries();

        $this->assertSame($small, $large);
        $this->assertLessThanOrEqual(self::QUERY_BUDGET, $large);
        $this->summary()->assertJsonPath('balances.accounts.cash.inflows', '342.250');
    }

    private function summary(): TestResponse
    {
        return $this->getJson('/api/v1/treasury/summary', $this->tenantHeaders($this->tenant, null, $this->storeA));
    }

    private function countQueries(): int
    {
        $count = 0;
        $counting = true;
        DB::listen(function (QueryExecuted $query) use (&$count, &$counting): void {
            if ($counting) {
                $count++;
            }
        });
        $this->summary()->assertOk();
        $counting = false;

        return $count;
    }

    private function seedLedger(): void
    {
        $this->inTenant($this->tenant, function (): void {
            $saleA = $this->invoiceIn($this->storeA, $this->today, '100.000');
            $legacySaleA = $this->invoiceIn($this->storeA, $this->today, '40.250');
            $saleB = $this->invoiceIn($this->storeB, $this->today, '999.000');
            $cancelledA = $this->invoiceIn($this->storeA, $this->today, '30.000', 'cancelled');

            $this->voucher(['payment_number' => 'PAY-INV-A1', 'store_id' => $this->storeA, 'invoice_id' => $saleA, 'customer_id' => $this->customerId, 'amount' => '100.000']);
            $this->voucher(['payment_number' => 'PAY-INV-A2', 'store_id' => null, 'invoice_id' => $legacySaleA, 'customer_id' => $this->customerId, 'amount' => '40.250']);
            $this->voucher(['store_id' => null, 'invoice_id' => null, 'customer_id' => $this->customerId, 'user_id' => $this->userA, 'amount' => '7.000']);
            $this->voucher(['payment_number' => 'PAY-REF-A1', 'store_id' => $this->storeA, 'invoice_id' => $saleA, 'customer_id' => $this->customerId, 'amount' => '-5.000']);
            $this->voucher(['store_id' => $this->storeA, 'supplier_id' => $this->supplierId, 'amount' => '25.500']);
            $this->voucher(['payment_number' => 'PAY-EXP-A1', 'store_id' => $this->storeA, 'amount' => '10.125', 'payment_date' => $this->old]);
            $this->voucher(['payment_number' => 'PAY-INV-A3', 'store_id' => $this->storeA, 'invoice_id' => $cancelledA, 'customer_id' => $this->customerId, 'amount' => '30.000']);
            $this->voucher(['store_id' => $this->storeA, 'customer_id' => $this->customerId, 'amount' => '60.000', 'payment_method' => 'instapay', 'payment_date' => $this->old]);
            $this->voucher(['payment_number' => 'PAY-INV-B1', 'store_id' => null, 'invoice_id' => $saleB, 'customer_id' => $this->customerId, 'amount' => '999.000']);
            $this->voucher(['store_id' => $this->storeB, 'customer_id' => $this->customerId, 'amount' => '500.000']);

            foreach ([[$this->storeA, '12.000'], [$this->storeB, '50.000']] as [$store, $amount]) {
                DB::table('expenses')->insert([
                    'expense_number' => 'TRS-EXP-'.(++$this->seq), 'category' => 'rent', 'title' => 'مصروف', 'amount' => $amount, 'expense_date' => $this->today,
                    'payment_method' => 'cash', 'user_id' => $this->userA, 'store_id' => $store, 'cost_center' => 'general', 'created_at' => now(), 'updated_at' => now(),
                ]);
            }

            DB::table('treasury_transfers')->insert([
                'transfer_number' => 'TRS-TRF-1', 'from_method' => 'cash', 'to_method' => 'instapay', 'amount' => '20.000', 'transfer_fee' => '1.000',
                'store_id' => $this->storeA, 'user_id' => $this->userA, 'transfer_date' => $this->old, 'created_at' => now(), 'updated_at' => now(),
            ]);
        });
    }

    private function invoiceIn(int $storeId, string $date, string $net, string $status = 'confirmed'): int
    {
        return (int) DB::table('invoices')->insertGetId([
            'invoice_number' => 'TRS-INV-'.(++$this->seq), 'customer_id' => $this->customerId, 'user_id' => $this->userA, 'store_id' => $storeId,
            'invoice_date' => $date, 'payment_type' => 'cash', 'status' => $status, 'subtotal' => $net, 'net_total' => $net,
            'paid_amount' => $net, 'remaining_amount' => '0.000', 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function voucher(array $attributes): void
    {
        DB::table('payments')->insert(array_merge([
            'payment_number' => 'TRS-PAY-'.(++$this->seq), 'customer_id' => null, 'supplier_id' => null, 'invoice_id' => null,
            'user_id' => $this->userA, 'store_id' => $this->storeA, 'amount' => '0.000', 'payment_date' => $this->today,
            'payment_method' => 'cash', 'created_at' => now(), 'updated_at' => now(),
        ], $attributes));
    }
}
