<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Actions\Dashboard\GetTenantDashboardAnalyticsAction;
use App\Actions\Invoices\ProcessPOSInvoiceAction;
use App\Actions\POS\GetPOSBootstrapDataAction;
use App\DTOs\POSInvoiceDTO;
use App\Models\CashShift;
use App\Models\Customer;
use App\Models\Item;
use App\Models\Store;
use App\Models\Tenant;
use App\Models\User;
use Tests\TenantTestCase;

class POSSolidArchitectureTest extends TenantTestCase
{
    protected Tenant $tenant;

    protected User $user;

    protected Store $store;

    protected Item $item;

    protected Customer $customer;

    protected function setUp(): void
    {
        parent::setUp();

        // Service-level test: the whole test body runs inside the tenant database,
        // whose main store the harness already created.
        $this->tenant = $this->createTenant();
        $this->store = $this->tenantStore($this->tenant);
        tenancy()->initialize($this->tenant);

        // Create User (the admin role is seeded in every tenant)
        $role = 'admin';
        $this->user = User::factory()->create([
            'name' => 'مدير النظام',
            'phone' => '01000000000',
            'email' => 'admin@test.com',
            'default_store_id' => $this->store->id,
            'is_active' => true,
        ]);
        $this->user->assignRole($role);

        // Open Cash Shift
        CashShift::create([
            'store_id' => $this->store->id,
            'user_id' => $this->user->id,
            'shift_number' => 1,
            'status' => 'open',
            'opened_at' => now(),
            'opening_cash_balance' => '500.000',
        ]);

        // Create Item with Stock
        $this->item = Item::create([
            'name' => 'بن برازيلي كولومبي متميز',
            'code' => 'COFFEE-01',
            'category' => 'بن محوج',
            'unit' => 'كجم',
            'cost_price' => '200.000',
            'selling_price' => '300.000',
            'current_stock' => '50.000',
            'min_stock_level' => '5.000',
            'is_active' => true,
        ]);

        // Create Customer
        $this->customer = Customer::create([
            'name' => 'كافيه الياسمين',
            'phone' => '01111111111',
            'price_tier' => 'retail',
            'current_balance' => '0.000',
            'is_active' => true,
        ]);
    }

    public function test_pos_bootstrap_data_action_returns_valid_json_resources(): void
    {
        $action = app(GetPOSBootstrapDataAction::class);
        $data = $action->execute($this->user);

        $this->assertArrayHasKey('items', $data);
        $this->assertArrayHasKey('customers', $data);
        $this->assertArrayHasKey('categories', $data);
        $this->assertNotEmpty($data['items']);
        $this->assertEquals('COFFEE-01', $data['items'][0]['code']);
        $this->assertEquals(300.0, $data['items'][0]['price_retail']);
    }

    public function test_process_pos_cash_invoice_action_executes_atomically(): void
    {
        $action = app(ProcessPOSInvoiceAction::class);

        $dto = POSInvoiceDTO::fromArray([
            'customer_id' => $this->customer->id,
            'store_id' => $this->store->id,
            'invoice_date' => now()->toDateString(),
            'payment_type' => 'cash',
            'payment_method' => 'cash',
            'discount_type' => 'fixed',
            'discount_value' => '20.000',
            'paid_amount' => '280.000',
            'items' => [
                [
                    'item_id' => $this->item->id,
                    'quantity' => 1.000,
                    'unit_price' => 300.000,
                ],
            ],
            'additional_expenses' => [],
        ]);

        $this->actingAs($this->user);
        $invoice = $action->execute($dto);

        $this->assertNotNull($invoice);
        $this->assertEquals('confirmed', $invoice->status);
        $this->assertEquals('300.000', (string) $invoice->subtotal);
        $this->assertEquals('280.000', (string) $invoice->net_total);
        $this->assertEquals('280.000', (string) $invoice->paid_amount);

        // Verify stock deducted accurately with bcmath (50.000 - 1.000 = 49.000)
        $this->item->refresh();
        $this->assertEquals('49.000', (string) $this->item->current_stock);
    }

    public function test_process_pos_credit_invoice_updates_customer_balance(): void
    {
        $action = app(ProcessPOSInvoiceAction::class);

        $dto = POSInvoiceDTO::fromArray([
            'customer_id' => $this->customer->id,
            'store_id' => $this->store->id,
            'invoice_date' => now()->toDateString(),
            'payment_type' => 'credit',
            'payment_method' => 'cash',
            'discount_type' => 'fixed',
            'discount_value' => '0.000',
            'paid_amount' => '0.000',
            'items' => [
                [
                    'item_id' => $this->item->id,
                    'quantity' => 0.500, // Fractional 500g
                    'unit_price' => 300.000,
                ],
            ],
            'additional_expenses' => [],
        ]);

        $this->actingAs($this->user);
        $invoice = $action->execute($dto);

        $this->assertNotNull($invoice);
        $this->assertEquals('150.000', (string) $invoice->net_total);
        $this->assertEquals('150.000', (string) $invoice->remaining_amount);

        // Customer balance should be updated with remaining amount (150.000)
        $this->customer->refresh();
        $this->assertEquals('150.000', (string) $this->customer->current_balance);

        // Stock should be exactly (50.000 - 0.500 = 49.500)
        $this->item->refresh();
        $this->assertEquals('49.500', (string) $this->item->current_stock);
    }

    public function test_dashboard_analytics_action_returns_correct_kpis(): void
    {
        $action = app(GetTenantDashboardAnalyticsAction::class);
        $this->actingAs($this->user);

        $data = $action->execute($this->user);

        $this->assertArrayHasKey('metrics', $data);
        $this->assertArrayHasKey('recent_invoices', $data);
        $this->assertArrayHasKey('low_stock_items', $data);
        $this->assertIsArray($data['metrics']);
    }

    public function test_pos_bootstrap_and_sale_never_see_another_tenants_catalog(): void
    {
        $other = $this->createTenant(); // ends tenancy
        tenancy()->initialize($this->tenant);
        $otherItemId = $this->inTenant($other, function (): int {
            Customer::create(['name' => 'عميل مستأجر آخر', 'current_balance' => '0.000', 'is_active' => true]);

            return (int) Item::create([
                'name' => 'صنف مستأجر آخر',
                'code' => 'FOREIGN-01',
                'unit' => 'كجم',
                'cost_price' => '10.000',
                'selling_price' => '20.000',
                'current_stock' => '7.000',
                'is_active' => true,
            ])->id;
        });

        $data = app(GetPOSBootstrapDataAction::class)->execute($this->user);

        $codes = collect($data['items'])->pluck('code')->all();
        $this->assertSame(['COFFEE-01'], $codes);
        $this->assertNotContains('عميل مستأجر آخر', collect($data['customers'])->pluck('name')->all());

        $this->actingAs($this->user);
        app(ProcessPOSInvoiceAction::class)->execute(POSInvoiceDTO::fromArray([
            'customer_id' => $this->customer->id,
            'store_id' => $this->store->id,
            'invoice_date' => now()->toDateString(),
            'payment_type' => 'cash',
            'payment_method' => 'cash',
            'discount_type' => 'fixed',
            'discount_value' => '0.000',
            'paid_amount' => '75.000',
            'items' => [['item_id' => $this->item->id, 'quantity' => 0.250, 'unit_price' => 300.000]],
            'additional_expenses' => [],
        ]));

        $this->assertSame('49.750', (string) $this->item->fresh()?->current_stock);
        $this->inTenant($other, function () use ($otherItemId): void {
            $this->assertSame('7.000', (string) Item::query()->findOrFail($otherItemId)->current_stock);
            $this->assertDatabaseCount('invoices', 0);
        });
    }
}
