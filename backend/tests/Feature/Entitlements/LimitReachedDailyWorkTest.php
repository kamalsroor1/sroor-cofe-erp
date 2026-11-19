<?php

declare(strict_types=1);

namespace Tests\Feature\Entitlements;

use App\Models\Customer;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Item;
use App\Models\Plan;
use App\Models\StoreStock;
use App\Models\Tenant;
use Database\Seeders\Catalog\FeatureCatalog;
use Database\Seeders\PlansAndFeaturesSeeder;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Group;
use Tests\Concerns\SeedsCentralPlatformRoles;
use Tests\TenantTestCase;

/**
 * PLAN-AUDIT (2026-11-19), owner decision: at 100% of a limit only CREATING one more record is
 * blocked. Reads, edits, sales returns, debt collection and the shift close keep working; the POS
 * banner endpoint says exactly what the checkout enforces; and a limit changed from the console
 * plan editor reaches the shops on that plan on their next request.
 */
#[Group('entitlements')]
final class LimitReachedDailyWorkTest extends TenantTestCase
{
    use SeedsCentralPlatformRoles;

    public function test_at_the_monthly_invoice_cap_only_a_new_sale_is_refused(): void
    {
        $tenant = $this->tenantOn($this->plan(['max_invoices_per_month' => 3]));
        $headers = $this->tenantHeaders($tenant);
        [$itemId, $customerId] = $this->saleFixture($tenant);
        // The shift close may queue a Telegram discrepancy alert: never real network in tests.
        Http::fake();

        $this->postJson('/api/v1/shifts/open', ['opening_cash_balance' => '100.000'], $headers)->assertSuccessful();

        // One credit sale (the customer owes 40) and one cash sale; then the month is full.
        $creditId = (int) $this->postJson('/api/v1/invoices', $this->sale($itemId, $customerId, 'credit', '2.000'), $headers)->assertCreated()->json('data.id');
        $cashId = (int) $this->postJson('/api/v1/invoices', $this->sale($itemId, $customerId, 'cash', '1.000'), $headers)->assertCreated()->json('data.id');
        $this->postJson('/api/v1/pos/checkout', $this->sale($itemId, $customerId, 'cash', '1.000'), $headers)->assertCreated();

        $this->getJson('/api/v1/pos/invoice-limit', $headers)->assertOk()
            ->assertJsonPath('data.limit', 3)
            ->assertJsonPath('data.used', 3)
            ->assertJsonPath('data.remaining', 0)
            ->assertJsonPath('data.reached', true);

        // Creating: refused on every sales path.
        $this->postJson('/api/v1/pos/checkout', $this->sale($itemId, $customerId, 'cash', '1.000'), $headers)
            ->assertStatus(403)->assertJsonPath('error_code', 'subscription.limit_reached')->assertJsonPath('details.limit', 'invoices_month');
        $this->postJson('/api/v1/invoices', $this->sale($itemId, $customerId, 'cash', '1.000'), $headers)
            ->assertStatus(403)->assertJsonPath('details.limit', 'invoices_month');

        // Reading: untouched.
        $this->getJson('/api/v1/invoices', $headers)->assertOk();
        $this->getJson("/api/v1/invoices/{$creditId}", $headers)->assertOk();
        $this->getJson('/api/v1/customers', $headers)->assertOk();
        $this->getJson('/api/v1/reports/summary', $headers)->assertOk();

        // A sales return of a capped month's invoice: allowed (returns are not invoices).
        $lineId = (int) $this->inTenant($tenant, fn (): int => (int) InvoiceItem::query()->where('invoice_id', $cashId)->value('id'));
        $this->postJson('/api/v1/returns', [
            'return_type' => 'sales_return', 'customer_id' => $customerId, 'invoice_id' => $cashId,
            'return_date' => now()->toDateString(),
            'items' => [['invoice_item_id' => $lineId, 'quantity' => '1.000']],
        ], $headers)->assertCreated();

        // Debt collection: allowed (40 owed, less the 20 the return credited back).
        $this->postJson("/api/v1/customers/{$customerId}/collect-payment", [
            'amount' => '20.000', 'payment_method' => 'cash', 'payment_date' => now()->toDateString(),
        ], $headers)->assertOk();
        $this->assertSame('0.000', $this->inTenant($tenant, fn (): string => (string) Customer::query()->findOrFail($customerId)->current_balance));

        // Editing an existing customer: allowed.
        $this->putJson("/api/v1/customers/{$customerId}", ['name' => 'عميل معدل'], $headers)->assertOk();

        // Closing the shift: allowed.
        $this->postJson('/api/v1/shifts/close', ['actual_cash_balance' => '160.000'], $headers)->assertOk();

        // Still 3 invoices this month: nothing above wrote a new one.
        $this->assertSame(3, $this->inTenant($tenant, fn (): int => Invoice::withTrashed()->count()));
    }

    public function test_the_pos_banner_endpoint_matches_the_checkout_one_sale_at_a_time(): void
    {
        $tenant = $this->tenantOn($this->plan(['max_invoices_per_month' => 2]));
        $headers = $this->tenantHeaders($tenant);
        [$itemId, $customerId] = $this->saleFixture($tenant);

        foreach ([[0, 2, false], [1, 1, false]] as [$used, $remaining, $reached]) {
            $this->getJson('/api/v1/pos/invoice-limit', $headers)->assertOk()
                ->assertJsonPath('data.used', $used)
                ->assertJsonPath('data.remaining', $remaining)
                ->assertJsonPath('data.reached', $reached);
            $this->postJson('/api/v1/pos/checkout', $this->sale($itemId, $customerId, 'cash', '1.000'), $headers)->assertCreated();
        }

        $this->getJson('/api/v1/pos/invoice-limit', $headers)->assertOk()
            ->assertJsonPath('data.used', 2)
            ->assertJsonPath('data.remaining', 0)
            ->assertJsonPath('data.percent', 100)
            ->assertJsonPath('data.reached', true);
        $this->postJson('/api/v1/pos/checkout', $this->sale($itemId, $customerId, 'cash', '1.000'), $headers)->assertStatus(403);

        // The subscription screen (dashboard widget) reports the same usage.
        $usage = collect($this->getJson('/api/v1/billing/subscription', $headers)->assertOk()->json('data.usage'))->keyBy('resource');
        $this->assertSame(2, $usage['invoices_month']['used']);
        $this->assertSame(2, $usage['invoices_month']['limit']);
    }

    public function test_a_limit_changed_in_the_console_plan_editor_reaches_existing_shops_at_once(): void
    {
        $this->seedCentralPlatformRoles();
        $this->seed(PlansAndFeaturesSeeder::class);
        $plan = $this->plan(['max_items' => 1]);
        $tenant = $this->tenantOn($plan);
        $headers = $this->tenantHeaders($tenant);
        $console = $this->steppedUpCentralHeaders($this->centralOperator());
        $url = "/api/v1/super-admin/plans/{$plan->id}";

        $this->postJson('/api/v1/items', $this->item('A'), $headers)->assertCreated();
        // Warm the shop's cached entitlements at the old limit.
        $this->postJson('/api/v1/items', $this->item('B'), $headers)->assertStatus(403)->assertJsonPath('details.max', 1);

        // Raise: the very next shop request sees it.
        $this->putJson($url, $this->planPayload($plan, ['max_items' => 2]), $console)->assertOk()->assertJsonPath('data.max_items', 2);
        $this->postJson('/api/v1/items', $this->item('B'), $this->tenantHeaders($tenant))->assertCreated();
        $this->postJson('/api/v1/items', $this->item('C'), $this->tenantHeaders($tenant))->assertStatus(403)->assertJsonPath('details.max', 2);

        // Unlimited.
        $this->putJson($url, $this->planPayload($plan, ['max_items' => null]), $console)->assertOk();
        $this->postJson('/api/v1/items', $this->item('C'), $this->tenantHeaders($tenant))->assertCreated();

        // Lower below the current usage: nothing is deleted, creating is refused.
        $this->putJson($url, $this->planPayload($plan, ['max_items' => 1]), $console)->assertOk();
        $this->postJson('/api/v1/items', $this->item('D'), $this->tenantHeaders($tenant))->assertStatus(403)->assertJsonPath('details.max', 1);
        $this->getJson('/api/v1/items', $this->tenantHeaders($tenant))->assertOk();
        $this->assertSame(3, $this->inTenant($tenant, fn (): int => Item::query()->count()));
    }

    // ----------------------------------------------------------------- helpers

    private function tenantOn(Plan $plan): Tenant
    {
        return $this->createTenant(['plan_id' => $plan->id, 'status' => 'active']);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function plan(array $attributes = []): Plan
    {
        $slug = 'daily-'.Str::lower(Str::random(10));

        return Plan::query()->create(array_merge([
            'name' => $slug,
            'slug' => $slug,
            'price_monthly' => '499.000',
            'price_yearly' => '4990.000',
            'features' => array_fill_keys(FeatureCatalog::keys(), true),
            'max_users' => null,
            'max_stores' => null,
            'max_warehouses' => null,
            'max_vans' => null,
            'max_items' => null,
            'max_invoices_per_month' => null,
            'max_storage_mb' => null,
            'max_customers' => null,
            'max_suppliers' => null,
            'is_active' => true,
            'sort_order' => 1,
        ], $attributes));
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function planPayload(Plan $plan, array $overrides): array
    {
        return array_merge([
            'name' => $plan->name,
            'price_monthly' => '499',
            'price_yearly' => '4990',
            'is_active' => true,
            'is_popular' => false,
            'max_users' => $plan->max_users,
            'max_stores' => $plan->max_stores,
            'features' => array_fill_keys(FeatureCatalog::keys(), true),
        ], $overrides);
    }

    /**
     * @return array<string, string>
     */
    private function item(string $name): array
    {
        return ['name' => 'Item '.$name, 'unit' => 'كجم', 'cost_price' => '10.000', 'selling_price' => '15.000'];
    }

    /**
     * @return array{0: int, 1: int}
     */
    private function saleFixture(Tenant $tenant): array
    {
        return $this->inTenant($tenant, function () use ($tenant): array {
            $item = Item::query()->create([
                'name' => 'بن', 'code' => 'DLY-'.Str::random(6), 'unit' => 'كجم', 'is_weighted' => true,
                'cost_price' => '10.000', 'selling_price' => '20.000', 'current_stock' => '50.000', 'is_active' => true,
            ]);
            StoreStock::query()->create(['store_id' => $this->tenantStore($tenant)->getKey(), 'item_id' => $item->id, 'quantity' => '50.000']);
            $customer = Customer::query()->create(['name' => 'عميل آجل', 'phone' => '01000008'.random_int(100, 999), 'current_balance' => '0.000', 'is_active' => true]);

            return [(int) $item->id, (int) $customer->id];
        });
    }

    /**
     * @return array<string, mixed>
     */
    private function sale(int $itemId, int $customerId, string $paymentType, string $qty): array
    {
        return [
            'customer_id' => $customerId,
            'payment_type' => $paymentType,
            'payment_method' => 'cash',
            'items' => [['item_id' => $itemId, 'quantity' => $qty, 'unit_price' => '20.000']],
        ];
    }
}
