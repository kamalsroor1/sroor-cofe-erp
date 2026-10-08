<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Item;
use App\Models\Store;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SoftDeletesTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected Store $store;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->store = Store::create([
            'name' => 'المحل الرئيسي',
            'code' => 'MAIN-SHOP',
            'type' => 'retail_shop',
            'is_active' => true,
            'is_main' => true,
        ]);
    }

    public function test_item_can_be_soft_deleted_and_restored(): void
    {
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
    }

    public function test_customer_can_be_soft_deleted_and_restored(): void
    {
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
    }

    public function test_supplier_can_be_soft_deleted_and_restored(): void
    {
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
    }

    public function test_store_can_be_soft_deleted_and_restored(): void
    {
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
    }

    public function test_historical_invoices_retain_relations_after_customer_and_item_soft_delete(): void
    {
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
            'user_id' => $this->user->id,
            'store_id' => $this->store->id,
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

        $invoiceItem = InvoiceItem::create([
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
        $this->store->delete();
        $this->user->delete();

        // Refresh invoice
        $freshInvoice = Invoice::find($invoice->id);
        $this->assertNotNull($freshInvoice);

        // Relations must still resolve with withTrashed()
        $this->assertEquals('أحمد محمود', $freshInvoice->customer->name);
        $this->assertEquals('المحل الرئيسي', $freshInvoice->store->name);
        $this->assertEquals($this->user->name, $freshInvoice->user->name);

        $freshInvoiceItem = $freshInvoice->items->first();
        $this->assertEquals('بن محوج خاص', $freshInvoiceItem->item->name);
    }
}
