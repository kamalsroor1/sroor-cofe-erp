<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Expense;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Item;
use App\Models\ReturnDocument;
use App\Models\ReturnItem;
use App\Models\Store;
use App\Models\Tenant;
use App\Models\User;
use App\Services\ProfitLossService;
use Tests\TenantTestCase;

class ProfitLossServiceTest extends TenantTestCase
{
    protected Tenant $tenant;

    protected Store $mainStore;

    protected Store $branchStore;

    protected User $user;

    protected Item $item;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = $this->createTenant();

        // The harness tenant is born with its main store (is_main = true).
        $this->mainStore = $this->tenantStore($this->tenant);

        $this->inTenant($this->tenant, function (): void {
            $this->branchStore = Store::create([
                'name' => 'Branch',
                'code' => 'PNL-BR',
                'type' => 'branch',
                'is_main' => false,
                'is_active' => true,
            ]);

            $this->user = User::factory()->create([
                'phone' => self::ADMIN_PHONE,
                'is_active' => true,
                'default_store_id' => $this->mainStore->id,
            ]);

            $this->item = Item::create([
                'name' => 'Beans',
                'code' => 'PNL-BEANS',
                'category' => 'coffee_beans',
                'unit' => 'kg',
                'cost_price' => '400.000',
                'weighted_avg_cost' => '400.000',
                'selling_price' => '650.000',
                'current_stock' => '10.000',
                'is_active' => true,
            ]);
        });
    }

    public function test_report_computes_store_pnl_and_flags_the_main_store(): void
    {
        $today = now()->toDateString();

        $this->inTenant($this->tenant, function () use ($today): void {
            $customer = Customer::create(['name' => 'Walk-in', 'phone' => '01000000501', 'is_active' => true]);

            $invoice = Invoice::create([
                'invoice_number' => 'INV-PNL-1',
                'store_id' => $this->mainStore->id,
                'customer_id' => $customer->id,
                'user_id' => $this->user->id,
                'invoice_date' => $today,
                'subtotal' => '1300.000',
                'discount_amount' => '0.000',
                'tax_amount' => '0.000',
                'net_total' => '1300.000',
                'paid_amount' => '1300.000',
                'remaining_amount' => '0.000',
                'status' => 'confirmed',
                'payment_type' => 'cash',
            ]);
            InvoiceItem::create([
                'invoice_id' => $invoice->id,
                'item_id' => $this->item->id,
                'quantity' => '2.000',
                'unit_price' => '650.000',
                'cost_price' => '400.000',
                'unit_cost' => '400.000',
                'total_price' => '1300.000',
                'discount_amount' => '0.000',
                'tax_amount' => '0.000',
                'net_price' => '1300.000',
            ]);

            $return = ReturnDocument::create([
                'return_number' => 'RET-PNL-1',
                'return_type' => 'sales_return',
                'invoice_id' => $invoice->id,
                'user_id' => $this->user->id,
                'store_id' => $this->mainStore->id,
                'total_amount' => '325.000',
                'return_date' => $today,
            ]);
            ReturnItem::create([
                'return_id' => $return->id,
                'item_id' => $this->item->id,
                'quantity' => '0.500',
                'unit_price' => '650.000',
                'total_price' => '325.000',
            ]);

            Expense::create([
                'expense_number' => 'EXP-PNL-1',
                'title' => 'Rent',
                'category' => 'rent',
                'cost_center' => 'rent',
                'amount' => '100.000',
                'expense_date' => $today,
                'payment_method' => 'cash',
                'user_id' => $this->user->id,
                'store_id' => $this->mainStore->id,
            ]);

            $report = app(ProfitLossService::class)->getProfitLossReport($today, $today);

            $stores = collect($report['stores'])->keyBy('store_id');
            $main = $stores[$this->mainStore->id];
            $branch = $stores[$this->branchStore->id];

            // The main-store flag follows the real `is_main` column (response key stays `is_default`).
            $this->assertTrue($main['is_default']);
            $this->assertFalse($branch['is_default']);

            // Revenue 1300 - returns 325 = 975; COGS 800 - 200 = 600; gross 375; net 375 - 100 = 275.
            $this->assertSame(1, $main['invoices_count']);
            $this->assertSame('1300.000', $main['gross_sales']);
            $this->assertSame('325.000', $main['returns_amount']);
            $this->assertSame('975.000', $main['net_revenue']);
            $this->assertSame('600.000', $main['cogs']);
            $this->assertSame('375.000', $main['gross_profit']);
            $this->assertSame('100.000', $main['expenses_total']);
            $this->assertSame('100.000', $main['cost_centers']['rent']);
            $this->assertSame('275.000', $main['net_operating_profit']);

            $this->assertSame('0.000', $branch['net_revenue']);

            $this->assertSame('975.000', $report['grand_revenue']);
            $this->assertSame('600.000', $report['grand_cogs']);
            $this->assertSame('375.000', $report['grand_gross_profit']);
            $this->assertSame('100.000', $report['grand_expenses']);
            $this->assertSame('275.000', $report['grand_net_profit']);
        });
    }

    public function test_report_ignores_another_tenants_sales_and_expenses(): void
    {
        $today = now()->toDateString();
        $other = $this->createTenant();
        $otherStoreId = (int) $this->tenantStore($other)->id;
        $otherAdminId = (int) $this->tenantAdmin($other)->id;

        $this->inTenant($other, function () use ($today, $otherStoreId, $otherAdminId): void {
            $customer = Customer::create(['name' => 'عميل المستأجر الآخر', 'is_active' => true]);
            Invoice::create([
                'invoice_number' => 'INV-PNL-OTHER',
                'store_id' => $otherStoreId,
                'customer_id' => $customer->id,
                'user_id' => $otherAdminId,
                'invoice_date' => $today,
                'subtotal' => '5000.000',
                'net_total' => '5000.000',
                'paid_amount' => '5000.000',
                'remaining_amount' => '0.000',
                'status' => 'confirmed',
                'payment_type' => 'cash',
            ]);
            Expense::create([
                'expense_number' => 'EXP-PNL-OTHER',
                'title' => 'Rent',
                'category' => 'rent',
                'cost_center' => 'rent',
                'amount' => '900.000',
                'expense_date' => $today,
                'payment_method' => 'cash',
                'user_id' => $otherAdminId,
                'store_id' => $otherStoreId,
            ]);
        });

        $this->inTenant($this->tenant, function () use ($today): void {
            $report = app(ProfitLossService::class)->getProfitLossReport($today, $today);

            $this->assertEqualsCanonicalizing(
                [$this->mainStore->id, $this->branchStore->id],
                collect($report['stores'])->pluck('store_id')->all(),
            );
            $this->assertSame('0.000', $report['grand_revenue']);
            $this->assertSame('0.000', $report['grand_expenses']);
            $this->assertSame('0.000', $report['grand_net_profit']);
        });
    }
}
