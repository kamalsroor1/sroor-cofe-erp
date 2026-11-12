<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Models\Customer;
use App\Models\Tenant;
use App\Models\User;
use App\Support\TenantClock;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\Group;
use Tests\TenantTestCase;

/**
 * PERF-01: the customer statement is paged in SQL with a carried-forward opening balance.
 *
 * 5,000 movements (2,000 invoices, 2,000 receipts, 1,000 sales returns) spread over 300 days,
 * written with bulk inserts. The expected figures are recomputed here with bcmath from the
 * generated amounts, independently of the code under test.
 */
#[Group('mysql')]
final class CustomerStatementLedgerApiTest extends TenantTestCase
{
    private Tenant $tenant;

    private int $customerId;

    private int $otherCustomerId;

    private string $today;

    /** @var list<array{date: string, net: string}> */
    private array $movements = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = $this->createTenant();
        $storeId = (int) $this->tenantStore($this->tenant)->id;

        $this->inTenant($this->tenant, function () use ($storeId): void {
            $this->today = app(TenantClock::class)->businessDate();
            $this->customerId = (int) Customer::create(['name' => 'عميل كشف كبير', 'phone' => '01000008201', 'price_tier' => 'retail', 'is_active' => true])->id;
            $this->otherCustomerId = (int) Customer::create(['name' => 'عميل آخر', 'phone' => '01000008202', 'price_tier' => 'retail', 'is_active' => true])->id;
            $this->seedMovements($storeId, (int) User::query()->orderBy('id')->value('id'));
        });
    }

    public function test_default_period_is_the_last_90_days_with_the_balance_before_it_carried_forward(): void
    {
        $from = CarbonImmutable::parse($this->today)->subDays(89)->toDateString();
        [$opening, $debit, $credit, $count] = $this->expected($from, $this->today);

        $started = hrtime(true);
        $response = $this->statement('')->assertOk();
        $seconds = (hrtime(true) - $started) / 1e9;

        $response
            ->assertJsonPath('data.filters.from_date', $from)
            ->assertJsonPath('data.filters.to_date', $this->today)
            ->assertJsonPath('data.summary.opening_balance', $opening)
            ->assertJsonPath('data.summary.total_debit', $debit)
            ->assertJsonPath('data.summary.total_credit', $credit)
            ->assertJsonPath('data.summary.transactions_count', $count)
            ->assertJsonPath('data.summary.closing_balance', bcsub(bcadd($opening, $debit, 3), $credit, 3))
            ->assertJsonPath('data.meta.total', $count)
            ->assertJsonPath('data.meta.per_page', 100);
        $this->assertCount(100, $response->json('data.ledger'));
        $this->assertLessThan(2.0, $seconds, 'first statement page');

        // The first row continues the carried-forward balance.
        $first = $response->json('data.ledger.0');
        $this->assertSame(bcsub(bcadd($opening, $first['debit'], 3), $first['credit'], 3), $first['balance_after']);
    }

    public function test_page_two_continues_the_running_balance_exactly_and_the_last_page_closes_the_period(): void
    {
        $from = CarbonImmutable::parse($this->today)->subDays(199)->toDateString();
        $query = "from_date={$from}&to_date={$this->today}&per_page=200";
        [$opening, $debit, $credit, $count] = $this->expected($from, $this->today);

        $page1 = $this->statement($query.'&page=1')->assertOk()->json('data');
        $page2 = $this->statement($query.'&page=2')->assertOk()->json('data');

        $last = $page1['ledger'][199];
        $next = $page2['ledger'][0];
        $this->assertSame(bcsub(bcadd($last['balance_after'], $next['debit'], 3), $next['credit'], 3), $next['balance_after']);
        $this->assertSame($page1['summary'], $page2['summary'], 'totals do not depend on the page');

        // Walk every page: the balances chain and the last one is the closing balance.
        $lastPage = (int) $page1['meta']['last_page'];
        $balance = $opening;
        $rows = 0;
        for ($page = 1; $page <= $lastPage; $page++) {
            foreach ($this->statement($query.'&page='.$page)->assertOk()->json('data.ledger') as $row) {
                $balance = bcsub(bcadd($balance, $row['debit'], 3), $row['credit'], 3);
                $this->assertSame($balance, $row['balance_after']);
                $rows++;
            }
        }
        $this->assertSame($count, $rows);
        $this->assertSame(bcsub(bcadd($opening, $debit, 3), $credit, 3), $balance);
        $this->assertSame($balance, $page1['summary']['closing_balance']);
    }

    public function test_all_history_has_no_opening_balance_and_closes_on_the_sum_of_every_movement(): void
    {
        [, $debit, $credit, $count] = $this->expected(null, null);

        $data = $this->statement('period=all&per_page=200&page=999')->assertOk()->json('data');
        $this->assertSame('0.000', $data['summary']['opening_balance']);
        $this->assertSame($count, $data['summary']['transactions_count']);
        $this->assertSame(bcsub($debit, $credit, 3), $data['summary']['closing_balance']);
        $this->assertSame([], $data['ledger'], 'a page past the end is empty');
        $this->assertTrue($data['filters']['all_history']);

        $lastPage = (int) $data['meta']['last_page'];
        $tail = $this->statement("period=all&per_page=200&page={$lastPage}")->assertOk()->json('data.ledger');
        $this->assertSame(bcsub($debit, $credit, 3), end($tail)['balance_after']);
    }

    public function test_another_customers_movements_never_appear(): void
    {
        $data = $this->statement('period=all&per_page=200', $this->otherCustomerId)->assertOk()->json('data');

        $this->assertSame(1, $data['summary']['transactions_count']);
        $this->assertSame('999.000', $data['summary']['total_debit']);
        $this->assertSame('OTHER-INV-1', $data['ledger'][0]['ref_number']);
    }

    public function test_statement_validation_auth_and_permission(): void
    {
        $this->statement('per_page=500')->assertStatus(422)->assertJsonValidationErrors(['per_page']);
        $this->statement('from_date=not-a-date')->assertStatus(422)->assertJsonValidationErrors(['from_date']);
        $this->statement('period=forever')->assertStatus(422)->assertJsonValidationErrors(['period']);
        $this->statement('page=0')->assertStatus(422)->assertJsonValidationErrors(['page']);
        $this->statement('from_date=2026-03-10&to_date=2026-03-01')->assertStatus(422)->assertJsonValidationErrors(['to_date']);
        $this->statement('from=2026-03-10&to=2026-03-01')->assertStatus(422)->assertJsonValidationErrors(['to']);
        $this->statement('from_date=2026-03-10&to_date=2026-03-10')->assertOk();

        $this->getJson("/api/v1/customers/{$this->customerId}/statement", $this->tenantGuestHeaders($this->tenant))->assertStatus(401);

        $cashier = $this->createTenantUser($this->tenant, null, ['pos.access']);
        $this->getJson("/api/v1/customers/{$this->customerId}/statement", $this->tenantHeaders($this->tenant, $cashier))->assertStatus(403);

        $this->getJson('/api/v1/customers/999999999/statement', $this->tenantHeaders($this->tenant))->assertStatus(404);
    }

    private function statement(string $query, ?int $customerId = null): TestResponse
    {
        $id = $customerId ?? $this->customerId;

        return $this->getJson("/api/v1/customers/{$id}/statement".($query !== '' ? '?'.$query : ''), $this->tenantHeaders($this->tenant));
    }

    /**
     * Opening (before $from), debit, credit and count of the period, from the generated rows.
     *
     * @return array{0: string, 1: string, 2: string, 3: int}
     */
    private function expected(?string $from, ?string $to): array
    {
        $opening = '0.000';
        $debit = '0.000';
        $credit = '0.000';
        $count = 0;
        foreach ($this->movements as $m) {
            if ($from !== null && $m['date'] < $from) {
                $opening = bcadd($opening, $m['net'], 3);

                continue;
            }
            if ($to !== null && $m['date'] > $to) {
                continue;
            }
            $count++;
            if (bccomp($m['net'], '0', 3) >= 0) {
                $debit = bcadd($debit, $m['net'], 3);
            } else {
                $credit = bcsub($credit, $m['net'], 3);
            }
        }

        return [$opening, $debit, $credit, $count];
    }

    private function seedMovements(int $storeId, int $userId): void
    {
        $now = CarbonImmutable::parse($this->today);
        $invoices = [];
        $payments = [];
        $returns = [];

        for ($i = 0; $i < 2000; $i++) {
            $date = $now->subDays($i % 300);
            $at = $date->setTime(8 + ($i % 10), $i % 60)->format('Y-m-d H:i:s');
            $amount = (100 + ($i % 37)).'.250';
            $invoices[] = [
                'invoice_number' => sprintf('LDG-INV-%05d', $i), 'customer_id' => $this->customerId, 'user_id' => $userId,
                'store_id' => $storeId, 'invoice_date' => $date->toDateString(), 'payment_type' => 'credit', 'status' => 'confirmed',
                'subtotal' => $amount, 'net_total' => $amount, 'paid_amount' => '0.000', 'remaining_amount' => $amount,
                'created_at' => $at, 'updated_at' => $at,
            ];
            $this->movements[] = ['date' => $date->toDateString(), 'net' => $amount];

            $payDate = $now->subDays(($i * 3) % 300);
            $payAt = $payDate->setTime(9 + ($i % 9), ($i * 7) % 60)->format('Y-m-d H:i:s');
            $pay = (50 + ($i % 11)).'.125';
            $payments[] = [
                'payment_number' => sprintf('LDG-PAY-%05d', $i), 'customer_id' => $this->customerId, 'user_id' => $userId,
                'store_id' => $storeId, 'amount' => $pay, 'payment_date' => $payDate->toDateString(), 'payment_method' => 'cash',
                'created_at' => $payAt, 'updated_at' => $payAt,
            ];
            $this->movements[] = ['date' => $payDate->toDateString(), 'net' => bcsub('0', $pay, 3)];

            if ($i < 1000) {
                $retDate = $now->subDays(($i * 7) % 300);
                $retAt = $retDate->setTime(12, $i % 60)->format('Y-m-d H:i:s');
                $ret = (10 + ($i % 5)).'.500';
                $returns[] = [
                    'return_number' => sprintf('LDG-RET-%05d', $i), 'return_type' => 'sales_return', 'customer_id' => $this->customerId,
                    'user_id' => $userId, 'store_id' => $storeId, 'total_amount' => $ret, 'return_date' => $retDate->toDateString(),
                    'created_at' => $retAt, 'updated_at' => $retAt,
                ];
                $this->movements[] = ['date' => $retDate->toDateString(), 'net' => bcsub('0', $ret, 3)];
            }
        }

        foreach ([['invoices', $invoices], ['payments', $payments], ['returns', $returns]] as [$table, $rows]) {
            foreach (array_chunk($rows, 500) as $chunk) {
                DB::table($table)->insert($chunk);
            }
        }

        // Another customer's invoice: never on this customer's statement.
        DB::table('invoices')->insert([
            'invoice_number' => 'OTHER-INV-1', 'customer_id' => $this->otherCustomerId, 'user_id' => $userId, 'store_id' => $storeId,
            'invoice_date' => $this->today, 'payment_type' => 'credit', 'status' => 'confirmed',
            'subtotal' => '999.000', 'net_total' => '999.000', 'paid_amount' => '0.000', 'remaining_amount' => '999.000',
            'created_at' => $now->setTime(10, 0)->format('Y-m-d H:i:s'), 'updated_at' => $now->setTime(10, 0)->format('Y-m-d H:i:s'),
        ]);
    }
}
