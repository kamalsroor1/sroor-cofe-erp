<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Item;
use App\Models\Store;
use App\Models\Supplier;
use App\Models\Tenant;
use App\Models\User;
use Tests\TenantTestCase;

class SoftDeletesTest extends TenantTestCase
{
    protected Tenant $tenant;

    protected User $user;

    protected int $storeId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = $this->createTenant();
        $this->user = $this->createTenantUser($this->tenant);
        $this->storeId = $this->inTenant($this->tenant, fn (): int => Store::create([
            'name' => 'المحل الرئيسي',
            'code' => 'MAIN-SHOP',
            'type' => 'retail_shop',
            'is_active' => true,
            'is_main' => true,
        ])->id);
    }

    public function test_item_can_be_soft_deleted_and_restored(): void
    {
        $this->inTenant($this->tenant, function (): void {
            $item = Item::create([
                'code' => 'COF-BRAZIL',
                'name' => 'بن برازيلي فاخر',
                'category' => 'بن',
                'unit' => 'كجم',
                'current_stock' => '25.000',
                'cost_price' => '200.000',
                'selling_price' => '280.000',
                'is_active' => true,
            ]);

            $this->assertNull($item->deleted_at);

            // Delete item
            $item->delete();

            $this->assertSoftDeleted('items', ['id' => $item->id]);
            $this->assertNotNull($item->fresh()->deleted_at);

            // Should not be in normal queries
            $this->assertNull(Item::find($item->id));
            $this->assertNotNull(Item::withTrashed()->find($item->id));

            // Restore item
            $item->restore();
            $this->assertNull($item->fresh()->deleted_at);
            $this->assertNotNull(Item::find($item->id));
        });
    }

    public function test_customer_can_be_soft_deleted_and_restored(): void
    {
        $this->inTenant($this->tenant, function (): void {
            $customer = Customer::create([
                'name' => 'عميل تجربة الحذف',
                'phone' => '01000007002',
                'current_balance' => '500.000',
                'is_active' => true,
            ]);

            $customer->delete();
            $this->assertSoftDeleted('customers', ['id' => $customer->id]);

            $customer->restore();
            $this->assertNull($customer->fresh()->deleted_at);
        });
    }

    public function test_supplier_can_be_soft_deleted_and_restored(): void
    {
        $this->inTenant($this->tenant, function (): void {
            $supplier = Supplier::create([
                'name' => 'مورد تجربة الحذف',
                'company_name' => 'شركة البن الدولية',
                'current_balance' => '1000.000',
                'is_active' => true,
            ]);

            $supplier->delete();
            $this->assertSoftDeleted('suppliers', ['id' => $supplier->id]);

            $supplier->restore();
            $this->assertNull($supplier->fresh()->deleted_at);
        });
    }

    public function test_store_can_be_soft_deleted_and_restored(): void
    {
        $this->inTenant($this->tenant, function (): void {
            $van = Store::create([
                'name' => 'عربية توزيع رقم 1',
                'code' => 'VAN-01',
                'type' => 'wholesale_van',
                'is_active' => true,
            ]);

            $van->delete();
            $this->assertSoftDeleted('stores', ['id' => $van->id]);

            $van->restore();
            $this->assertNull($van->fresh()->deleted_at);
        });
    }

    public function test_historical_invoices_retain_relations_after_customer_and_item_soft_delete(): void
    {
        $this->inTenant($this->tenant, function (): void {
            $user = User::findOrFail($this->user->id);
            $store = Store::findOrFail($this->storeId);

            $customer = Customer::create([
                'name' => 'أحمد محمود',
                'phone' => '01000000001',
                'current_balance' => '0.000',
                'is_active' => true,
            ]);

            $item = Item::create([
                'code' => 'COF-01',
                'name' => 'بن محوج خاص',
                'category' => 'بن',
                'unit' => 'كجم',
                'current_stock' => '50.000',
                'cost_price' => '150.000',
                'selling_price' => '220.000',
                'is_active' => true,
            ]);

            $invoice = Invoice::create([
                'invoice_number' => 'INV-2026-001',
                'customer_id' => $customer->id,
                'user_id' => $user->id,
                'store_id' => $store->id,
                'invoice_date' => now()->toDateString(),
                'payment_type' => 'cash',
                'status' => 'confirmed',
                'payment_status' => 'paid',
                'subtotal' => '220.000',
                'discount_amount' => '0.000',
                'net_total' => '220.000',
                'paid_amount' => '220.000',
                'remaining_amount' => '0.000',
                'total_cost' => '150.000',
            ]);

            InvoiceItem::create([
                'invoice_id' => $invoice->id,
                'item_id' => $item->id,
                'quantity' => '1.000',
                'cost_price' => '150.000',
                'unit_price' => '220.000',
                'discount_amount' => '0.000',
                'total_price' => '220.000',
            ]);

            // Soft delete the customer, item, store, and user
            $customer->delete();
            $item->delete();
            $store->delete();
            $user->delete();

            // Refresh invoice
            $freshInvoice = Invoice::find($invoice->id);
            $this->assertNotNull($freshInvoice);

            // Relations must still resolve with withTrashed()
            $this->assertEquals('أحمد محمود', $freshInvoice->customer->name);
            $this->assertEquals('المحل الرئيسي', $freshInvoice->store->name);
            $this->assertEquals($user->name, $freshInvoice->user->name);

            $freshInvoiceItem = $freshInvoice->items->first();
            $this->assertEquals('بن محوج خاص', $freshInvoiceItem->item->name);
        });
    }

    public function test_soft_deleting_in_one_tenant_does_not_trash_the_same_records_in_another(): void
    {
        $other = $this->createTenant();
        $make = fn (): array => [
            Item::create([
                'code' => 'COF-TWIN',
                'name' => 'بن توأم',
                'current_stock' => '3.000',
                'cost_price' => '100.000',
                'selling_price' => '150.000',
                'is_active' => true,
            ])->id,
            Customer::create(['name' => 'عميل توأم', 'current_balance' => '0.000', 'is_active' => true])->id,
        ];

        [$itemA, $customerA] = $this->inTenant($this->tenant, $make);
        [$itemB, $customerB] = $this->inTenant($other, $make);

        $this->inTenant($this->tenant, function () use ($itemA, $customerA): void {
            Item::findOrFail($itemA)->delete();
            Customer::findOrFail($customerA)->delete();
            $this->assertSame(1, Item::onlyTrashed()->count());
        });

        $this->inTenant($other, function () use ($itemB, $customerB): void {
            $this->assertNotSoftDeleted('items', ['id' => $itemB]);
            $this->assertNotSoftDeleted('customers', ['id' => $customerB]);
            $this->assertSame(0, Item::onlyTrashed()->count());
            $this->assertSame(0, Customer::onlyTrashed()->count());
        });
    }
}
