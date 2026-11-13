<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Actions\Dashboard\GetDashboardApiOverviewAction;
use App\DTOs\Logs\ActivityLogStoreScope;
use App\Http\Resources\Api\DashboardOverviewResource;
use App\Models\ActivityLog;
use App\Models\CashShift;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Item;
use App\Models\Store;
use App\Models\Supplier;
use App\Models\Tenant;
use App\Models\User;
use Tests\TenantTestCase;

class DashboardApiControllerTest extends TenantTestCase
{
    protected Tenant $tenant;

    protected User $user;

    protected Store $store;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = $this->createTenant();
        $this->store = $this->tenantStore($this->tenant);
        $this->user = $this->createTenantUser($this->tenant, 'admin', attributes: [
            'name' => 'مدير الفرع',
            'phone' => '01000007003',
            'default_store_id' => $this->store->id,
        ]);
    }

    public function test_dashboard_api_returns_structured_resource_data(): void
    {
        $this->inTenant($this->tenant, function (): void {
            $this->actingAs($this->user);

            // 1. Customer & Supplier with balances
            $customer = Customer::create([
                'name' => 'عميل تجريبي',
                'phone' => '01011111111',
                'current_balance' => '1500.000',
                'is_active' => true,
            ]);

            Supplier::create([
                'name' => 'مورد تجريبي',
                'phone' => '01022222222',
                'current_balance' => '700.000',
                'is_active' => true,
            ]);

            // 2. Item & Invoice
            $item = Item::create([
                'name' => 'بن برازيلي فاخر',
                'code' => 'BRZ-01',
                'unit' => 'كجم',
                'cost_price' => '100.000',
                'selling_price' => '160.000',
                'current_stock' => '2.000',
                'min_stock_level' => '10.000',
                'is_active' => true,
            ]);

            $invoice = Invoice::create([
                'invoice_number' => 'INV-API-001',
                'customer_id' => $customer->id,
                'store_id' => $this->store->id,
                'user_id' => $this->user->id,
                'invoice_date' => now()->toDateString(),
                'subtotal' => '320.000',
                'total_amount' => '320.000',
                'net_total' => '320.000',
                'paid_amount' => '320.000',
                'remaining_amount' => '0.000',
                'status' => 'confirmed',
                'payment_type' => 'cash',
            ]);

            $invoice->items()->create([
                'item_id' => $item->id,
                'quantity' => '2.000',
                'unit_price' => '160.000',
                'cost_price' => '100.000',
                'total_price' => '320.000',
            ]);

            // 3. Shift
            CashShift::create([
                'store_id' => $this->store->id,
                'user_id' => $this->user->id,
                'shift_number' => 1,
                'status' => 'open',
                'opening_cash_balance' => '500.000',
                'opened_at' => now(),
            ]);

            // 4. Activity Log
            ActivityLog::create([
                'user_id' => $this->user->id,
                'store_id' => $this->store->id,
                'module' => 'invoices',
                'action' => 'create',
                'description' => 'إنشاء فاتورة تجريبية',
                'ip_address' => '127.0.0.1',
            ]);

            // Call Action & Resource
            $action = app(GetDashboardApiOverviewAction::class);
            $data = $action->execute($this->store->id);
            $resource = new DashboardOverviewResource($data);
            $response = $resource->response()->getData(true);

            $this->assertTrue($response['data']['success']);
            $this->assertEquals(1, $response['data']['customers_count']);
            $this->assertEquals(1, $response['data']['suppliers_count']);
            $this->assertEquals('1500.000', $response['data']['total_receivable']);
            $this->assertEquals('700.000', $response['data']['total_payable']);
            $this->assertEquals('320.000', $response['data']['today_metrics']['net_sales']);
            $this->assertEquals('200.000', $response['data']['today_metrics']['total_cogs']); // 2 * 100 = 200
            $this->assertEquals('120.000', $response['data']['today_metrics']['net_profit']); // 320 - 200 = 120
            $this->assertTrue($response['data']['has_active_shift']);
            $this->assertEquals(1, $response['data']['low_stock_count']);
            $this->assertCount(1, $response['data']['recent_invoices']);
            $this->assertCount(1, $response['data']['recent_logs']);
        });
    }

    public function test_dashboard_overview_ignores_another_tenants_balances(): void
    {
        $other = $this->createTenant();
        $this->inTenant($other, function (): void {
            Customer::create(['name' => 'عميل آخر', 'current_balance' => '9999.000', 'is_active' => true]);
            Supplier::create(['name' => 'مورد آخر', 'current_balance' => '8888.000', 'is_active' => true]);
        });

        $this->inTenant($this->tenant, function (): void {
            $this->actingAs($this->user);

            $data = app(GetDashboardApiOverviewAction::class)->execute($this->store->id);
            $response = (new DashboardOverviewResource($data))->response()->getData(true);

            $this->assertEquals(0, $response['data']['customers_count']);
            $this->assertEquals(0, $response['data']['suppliers_count']);
            $this->assertEquals('0.000', $response['data']['total_receivable']);
            $this->assertEquals('0.000', $response['data']['total_payable']);
            $this->assertFalse($response['data']['has_active_shift']);
        });
    }

    public function test_recent_logs_feed_in_all_stores_mode_follows_the_readers_branches(): void
    {
        $this->inTenant($this->tenant, function (): void {
            $this->actingAs($this->user);
            $branchId = Store::create(['name' => 'فرع المعادي', 'code' => 'MAADI', 'type' => 'branch', 'is_default' => false, 'is_active' => true])->id;

            foreach ([[$this->store->id, 'سجل الرئيسي'], [$branchId, 'سجل المعادي'], [null, 'سجل عام بدون فرع']] as [$storeId, $description]) {
                ActivityLog::create(['user_id' => $this->user->id, 'store_id' => $storeId, 'module' => 'sales', 'action' => 'sale', 'description' => $description]);
            }

            $feed = fn (?int $storeId, ?ActivityLogStoreScope $scope): array => array_column(
                (new DashboardOverviewResource(app(GetDashboardApiOverviewAction::class)->execute($storeId, $scope)))->response()->getData(true)['data']['recent_logs'],
                'description',
            );

            // Branch-restricted reader with store_id=all: only their branch, no store-less log.
            $this->assertSame(['سجل المعادي'], $feed(null, new ActivityLogStoreScope([$branchId])));
            // A concrete branch outside the reader's scope yields nothing.
            $this->assertSame([], $feed($this->store->id, new ActivityLogStoreScope([$branchId])));
            // No scope in all-stores mode never falls back to every branch.
            $this->assertSame([], $feed(null, null));
            // All-store readers see every branch and the shop-wide log.
            $this->assertEqualsCanonicalizing(['سجل الرئيسي', 'سجل المعادي', 'سجل عام بدون فرع'], $feed(null, new ActivityLogStoreScope(null)));
            // A concrete, access-checked branch keeps the old behaviour.
            $this->assertSame(['سجل الرئيسي'], $feed($this->store->id, null));
        });
    }
}
