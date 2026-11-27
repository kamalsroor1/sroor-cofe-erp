<?php

declare(strict_types=1);

namespace Tests\Feature\Entitlements;

use App\Models\Customer;
use App\Models\Expense;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Item;
use App\Models\Plan;
use App\Models\StoreStock;
use App\Models\Supplier;
use App\Models\Tenant;
use App\Models\User;
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

    public function test_credit_receipt_voucher_and_editing_existing_records_work_at_invoice_limit(): void
    {
        $tenant = $this->tenantOn($this->plan(['max_invoices_per_month' => 2]));
        $headers = $this->tenantHeaders($tenant);
        [$itemId, $customerId] = $this->saleFixture($tenant);
        $storeId = (int) $this->tenantStore($tenant)->getKey();

        [$supplierId, $expenseId, $cashierId] = $this->inTenant($tenant, function () use ($storeId): array {
            $supplier = Supplier::query()->create(['name' => 'مورد قديم', 'phone' => '01000001111', 'current_balance' => '0.000', 'is_active' => true]);
            $expense = Expense::query()->create([
                'expense_number' => 'EXP-'.Str::upper(Str::random(6)),
                'store_id' => $storeId,
                'user_id' => User::query()->value('id'),
                'title' => 'إيجار المحل',
                'category' => 'rent',
                'cost_center' => 'general',
                'amount' => '100.000',
                'expense_date' => now()->toDateString(),
                'payment_method' => 'cash',
            ]);
            $cashier = User::query()->create([
                'name' => 'كاشير أول',
                'phone' => '01000000072',
                'password' => bcrypt('secret123'),
                'default_store_id' => $storeId,
                'is_active' => true,
            ]);
            $cashier->assignRole('cashier');
            $cashier->stores()->sync([$storeId]);

            return [(int) $supplier->id, (int) $expense->id, (int) $cashier->id];
        });

        // Two credit/cash invoices reach the monthly limit (credit invoice = 40.000 owed).
        $creditId = (int) $this->postJson('/api/v1/invoices', $this->sale($itemId, $customerId, 'credit', '2.000'), $headers)->assertCreated()->json('data.id');
        $this->postJson('/api/v1/pos/checkout', $this->sale($itemId, $customerId, 'cash', '1.000'), $headers)->assertCreated();

        // Confirm creating another invoice returns 403 subscription.limit_reached.
        $this->postJson('/api/v1/invoices', $this->sale($itemId, $customerId, 'cash', '1.000'), $headers)
            ->assertStatus(403)
            ->assertJsonPath('error_code', 'subscription.limit_reached')
            ->assertJsonPath('details.limit', 'invoices_month');

        // Credit collection via receipt voucher (linked to the credit invoice) succeeds at limit.
        $this->postJson('/api/v1/payments/customer-receipt', [
            'customer_id' => $customerId,
            'invoice_id' => $creditId,
            'amount' => '15.000',
            'payment_method' => 'cash',
            'payment_date' => now()->toDateString(),
        ], $headers)->assertCreated()->assertJsonPath('customer_current_balance', '25.000');

        // Credit collection via customer endpoint settles the rest cleanly.
        $this->postJson("/api/v1/customers/{$customerId}/collect-payment", [
            'amount' => '25.000',
            'payment_method' => 'cash',
            'payment_date' => now()->toDateString(),
        ], $headers)->assertOk();
        $this->assertSame('0.000', $this->inTenant($tenant, fn (): string => (string) Customer::query()->findOrFail($customerId)->current_balance));

        // Editing existing records of every kind succeeds at the monthly invoice limit.
        $this->putJson("/api/v1/items/{$itemId}", ['name' => 'بن معدل', 'unit' => 'كجم', 'cost_price' => '10.000', 'selling_price' => '22.000'], $headers)->assertOk();
        $this->putJson("/api/v1/suppliers/{$supplierId}", ['name' => 'مورد معدل', 'phone' => '01000002222'], $headers)->assertOk();
        $this->putJson("/api/v1/customers/{$customerId}", ['name' => 'عميل محدث', 'phone' => '01000003333'], $headers)->assertOk();
        $this->putJson("/api/v1/expenses/{$expenseId}", [
            'title' => 'إيجار معدل',
            'category' => 'rent',
            'cost_center' => 'general',
            'amount' => '120.000',
            'expense_date' => now()->toDateString(),
            'payment_method' => 'cash',
        ], $headers)->assertOk();
        $this->putJson("/api/v1/users/{$cashierId}", [
            'name' => 'كاشير معدل',
            'phone' => '01000000072',
            'role' => 'cashier',
            'store_ids' => [$storeId],
            'is_active' => true,
        ], $headers)->assertOk();
        $this->putJson("/api/v1/stores/{$storeId}", ['name' => 'الفرع الرئيسي المعدل', 'type' => 'retail_shop'], $headers)->assertOk();

        // Still only the 2 invoices created before hitting the cap.
        $this->assertSame(2, $this->inTenant($tenant, fn (): int => Invoice::withTrashed()->count()));
    }

    public function test_editing_existing_records_is_never_blocked_by_their_own_resource_limits(): void
    {
        $tenant = $this->tenantOn($this->plan([
            'max_items' => 1,
            'max_customers' => 1,
            'max_suppliers' => 1,
            'max_stores' => 1,
            'max_users' => 1,
        ]));
        $headers = $this->tenantHeaders($tenant);
        $storeId = (int) $this->tenantStore($tenant)->getKey();
        $adminId = (int) $this->inTenant($tenant, fn (): int => (int) User::query()->value('id'));

        $itemId = (int) $this->postJson('/api/v1/items', $this->item('One'), $headers)->assertCreated()->json('data.id');
        $customerId = (int) $this->postJson('/api/v1/customers', ['name' => 'First Customer'], $headers)->assertCreated()->json('data.id');
        $supplierId = (int) $this->postJson('/api/v1/suppliers', ['name' => 'First Supplier'], $headers)->assertCreated()->json('data.id');

        // Creating one more of each capped resource returns 403 subscription.limit_reached.
        $this->postJson('/api/v1/items', $this->item('Two'), $headers)->assertStatus(403)->assertJsonPath('error_code', 'subscription.limit_reached');
        $this->postJson('/api/v1/customers', ['name' => 'Second Customer'], $headers)->assertStatus(403)->assertJsonPath('error_code', 'subscription.limit_reached');
        $this->postJson('/api/v1/suppliers', ['name' => 'Second Supplier'], $headers)->assertStatus(403)->assertJsonPath('error_code', 'subscription.limit_reached');
        $this->postJson('/api/v1/stores', ['name' => 'Second Branch', 'type' => 'retail_shop'], $headers)->assertStatus(403)->assertJsonPath('error_code', 'subscription.limit_reached');
        $this->postJson('/api/v1/users', [
            'name' => 'Second User',
            'phone' => '01000000073',
            'password' => 'secret123',
            'role' => 'cashier',
            'store_ids' => [$storeId],
        ], $headers)->assertStatus(403)->assertJsonPath('error_code', 'subscription.limit_reached');

        // Editing each existing record at 100% of its own cap continues to succeed.
        $this->putJson("/api/v1/items/{$itemId}", $this->item('One Updated'), $headers)->assertOk();
        $this->putJson("/api/v1/customers/{$customerId}", ['name' => 'First Customer Updated'], $headers)->assertOk();
        $this->putJson("/api/v1/suppliers/{$supplierId}", ['name' => 'First Supplier Updated'], $headers)->assertOk();
        $this->putJson("/api/v1/stores/{$storeId}", ['name' => 'Main Branch Updated', 'type' => 'retail_shop'], $headers)->assertOk();
        $this->putJson("/api/v1/users/{$adminId}", [
            'name' => 'Admin Updated',
            'phone' => '01000000074',
            'email' => $this->inTenant($tenant, fn (): string => (string) User::query()->findOrFail($adminId)->email),
            'role' => 'admin',
            'store_ids' => [$storeId],
            'is_active' => true,
        ], $headers)->assertOk();
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
