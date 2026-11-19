<?php

declare(strict_types=1);

namespace Tests\Feature\Concurrency;

use App\Exceptions\CreditLimitExceededException;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Item;
use App\Models\StoreStock;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Group;
use Spatie\Permission\Models\Role;
use Tests\Concerns\RunsConcurrencyWorkers;
use Tests\TenantTestCase;

/**
 * SHOP-03: concurrent credit sales on one customer can never take the balance above the credit limit.
 * The limit is checked inside the sale transaction on the customer row locked FOR UPDATE, so the sales
 * are serialised on that row: with a limit of 250.000 and six parallel credit sales of 100.000 each,
 * exactly two pass and four are refused with CreditLimitExceededException (never a lock error).
 */
#[Group('mysql')]
#[Group('mysql-only')]
#[Group('concurrency')]
final class ConcurrentCreditLimitRaceTest extends TenantTestCase
{
    use RunsConcurrencyWorkers;

    private const RACERS = 6;

    protected function setUp(): void
    {
        parent::setUp();

        $driver = DB::connection($this->centralConnectionName())->getDriverName();
        if (! in_array($driver, ['mysql', 'mariadb'], true)) {
            $this->fail("MySQL-only test run on `{$driver}`. Use: php artisan test -c phpunit.mysql.xml");
        }
    }

    public function test_two_concurrent_credit_sales_over_the_limit_cannot_both_pass(): void
    {
        $tenant = $this->createTenant();
        $store = (int) $this->tenantStore($tenant)->getKey();

        $f = $this->inTenant($tenant, function () use ($store): array {
            $cashier = User::factory()->create([
                'name' => 'cashier-race', 'phone' => '01000000091', 'password' => Hash::make('password'),
                'is_active' => true, 'default_store_id' => $store,
            ]);
            $cashier->assignRole(Role::findByName('cashier'));

            $item = Item::query()->create([
                'code' => 'QA-CL', 'name' => 'صنف آجل', 'unit' => 'كجم', 'cost_price' => '50.000', 'weighted_avg_cost' => '50.000',
                'selling_price' => '100.000', 'min_selling_price' => '0.000', 'current_stock' => '100.000', 'min_stock_level' => '0.000', 'is_active' => true,
            ]);
            StoreStock::query()->updateOrCreate(['store_id' => $store, 'item_id' => $item->id], ['quantity' => '100.000']);

            return [
                'cashier' => (int) $cashier->id,
                'item' => (int) $item->id,
                'customer' => (int) Customer::query()->create(['name' => 'عميل آجل', 'current_balance' => '0.000', 'credit_limit' => '250.000', 'is_active' => true])->getKey(),
            ];
        });

        $jobs = [];
        for ($r = 0; $r < self::RACERS; $r++) {
            $jobs[] = ['tenant' => $tenant, 'scenario' => 'batch', 'payload' => ['ops' => [['op' => 'checkout', 'tag' => 'race', 'data' => [
                'acting_user_id' => $f['cashier'], 'client_uuid' => (string) Str::uuid(), 'customer_id' => $f['customer'], 'store_id' => $store,
                'payment_type' => 'credit', 'payment_method' => 'cash', 'discount_type' => 'fixed', 'discount_value' => '0.000',
                'items' => [['item_id' => $f['item'], 'quantity' => '1.000', 'unit_price' => '100.000', 'discount_amount' => '0.000']],
            ]]]]];
        }

        $results = [];
        foreach ($this->runWorkerJobs(base_path('tests/Support/batch-worker.php'), $jobs) as $worker) {
            $this->assertTrue($worker['ok'], (string) json_encode($worker, JSON_UNESCAPED_UNICODE));
            array_push($results, ...(array) $worker['results']);
        }

        $won = array_values(array_filter($results, fn (array $r): bool => $r['ok'] === true));
        $lost = array_values(array_filter($results, fn (array $r): bool => $r['ok'] !== true));
        $this->assertCount(2, $won, '250.000 fits two credit sales of 100.000: '.json_encode($results, JSON_UNESCAPED_UNICODE));
        $this->assertCount(self::RACERS - 2, $lost);
        foreach ($lost as $failure) {
            $this->assertSame(CreditLimitExceededException::class, $failure['error_class'], (string) json_encode($failure, JSON_UNESCAPED_UNICODE));
        }

        $state = $this->inTenant($tenant, fn (): array => [
            'balance' => (string) Customer::query()->whereKey($f['customer'])->value('current_balance'),
            'invoices' => Invoice::query()->count(),
            'stock' => (string) StoreStock::query()->where('item_id', $f['item'])->value('quantity'),
        ]);

        $this->assertSame('200.000', $state['balance']);
        $this->assertSame(2, $state['invoices']);
        $this->assertSame('98.000', $state['stock']);
    }
}
