<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Item;
use App\Models\ReturnDocument;
use App\Models\Store;
use App\Models\StoreStock;
use App\Models\Supplier;
use App\Models\Tenant;
use App\Models\User;
use App\Services\ReturnService;
use App\Services\StockService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Tests\TenantTestCase;

class ReturnServiceTest extends TenantTestCase
{
    protected Tenant $tenant;

    protected User $user;

    protected int $mainStoreId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = $this->createTenant();
        $this->user = $this->createTenantUser($this->tenant, 'admin');

        $this->mainStoreId = $this->inTenant($this->tenant, fn (): int => Store::create([
            'name' => 'المخزن الرئيسي',
            'code' => 'MAIN-02',
            'type' => 'main_store',
            'is_active' => true,
            'is_default' => true,
        ])->id);
    }

    public function test_sales_return_increases_stock_and_reduces_customer_debt(): void
    {
        $this->inTenant($this->tenant, function (): void {
            $this->actingAs(User::findOrFail($this->user->id));
            $mainStore = Store::findOrFail($this->mainStoreId);

            $customer = Customer::create([
                'name' => 'عميل تجريبي',
                'current_balance' => '500.000',
                'initial_balance' => '500.000',
            ]);

            $item = $this->stockedItem($mainStore, 'BRZ-01', 'بن برازيلي وسط', '240.000', '180.000', '10.000', 'INIT-001');

            $returnDoc = app(ReturnService::class)->createSalesReturn([
                'customer_id' => $customer->id,
                'store_id' => $mainStore->id,
                'reason' => 'إرجاع نصف كيلو بالخطأ',
                'items' => [
                    [
                        'item_id' => $item->id,
                        'quantity' => '0.500',
                        'unit_price' => '240.000',
                    ],
                ],
            ]);

            $this->assertDatabaseHas('returns', [
                'id' => $returnDoc->id,
                'return_type' => 'sales_return',
                'total_amount' => '120.000',
            ]);

            // Stock must increase by 0.500 kg -> 10.500
            $item->refresh();
            $this->assertEquals('10.500', $item->current_stock);

            $stock = StoreStock::where('store_id', $mainStore->id)->where('item_id', $item->id)->first();
            $this->assertEquals('10.500', $stock->quantity);
        });
    }

    public function test_purchase_return_deducts_stock_and_reduces_supplier_debt(): void
    {
        $this->inTenant($this->tenant, function (): void {
            $this->actingAs(User::findOrFail($this->user->id));
            $mainStore = Store::findOrFail($this->mainStoreId);

            $supplier = Supplier::create([
                'name' => 'شركة الأهرام للبن',
                'company_name' => 'الأهرام',
                'current_balance' => '2000.000',
                'initial_balance' => '2000.000',
            ]);

            $item = $this->stockedItem($mainStore, 'COL-01', 'بن كولومبي سوبريمو', '320.000', '250.000', '20.000', 'INIT-002');

            $returnDoc = app(ReturnService::class)->createPurchaseReturn([
                'supplier_id' => $supplier->id,
                'store_id' => $mainStore->id,
                'reason' => 'إرجاع 5 كجم لوجود عيب في التحميص',
                'items' => [
                    [
                        'item_id' => $item->id,
                        'quantity' => '5.000',
                        'unit_price' => '250.000',
                    ],
                ],
            ]);

            $this->assertDatabaseHas('returns', [
                'id' => $returnDoc->id,
                'return_type' => 'purchase_return',
                'total_amount' => '1250.000',
            ]);

            // Stock must decrease by 5.000 kg -> 15.000
            $item->refresh();
            $this->assertEquals('15.000', $item->current_stock);

            $stock = StoreStock::where('store_id', $mainStore->id)->where('item_id', $item->id)->first();
            $this->assertEquals('15.000', $stock->quantity);
        });
    }

    public function test_a_return_cannot_reference_another_tenants_item_or_customer(): void
    {
        $other = $this->createTenant();
        $otherStoreId = (int) $this->tenantStore($other)->id;
        $otherAdminId = (int) $this->tenantAdmin($other)->id;
        [$foreignCustomerId, $foreignItemId] = $this->inTenant($other, function () use ($otherStoreId, $otherAdminId): array {
            $this->actingAs(User::findOrFail($otherAdminId));
            $customer = Customer::create(['name' => 'عميل مستأجر آخر', 'current_balance' => '300.000']);
            $item = $this->stockedItem(Store::findOrFail($otherStoreId), 'OTHER-01', 'صنف مستأجر آخر', '100.000', '60.000', '8.000', 'INIT-OTHER');

            return [$customer->id, $item->id];
        });

        $this->inTenant($this->tenant, function () use ($foreignCustomerId, $foreignItemId): void {
            $this->actingAs(User::findOrFail($this->user->id));

            try {
                app(ReturnService::class)->createSalesReturn([
                    'customer_id' => $foreignCustomerId,
                    'store_id' => $this->mainStoreId,
                    'reason' => 'مرتجع على بيانات مستأجر آخر',
                    'items' => [['item_id' => $foreignItemId, 'quantity' => '1.000', 'unit_price' => '100.000']],
                ]);
                $this->fail('A return against ids that only exist in another tenant was accepted.');
            } catch (ModelNotFoundException) {
                // expected: neither id exists in this tenant's database
            }

            $this->assertSame(0, ReturnDocument::query()->count());
            $this->assertSame(0, StoreStock::query()->count());
        });

        $this->inTenant($other, function () use ($foreignCustomerId, $foreignItemId, $otherStoreId): void {
            $this->assertSame('8.000', (string) Item::findOrFail($foreignItemId)->current_stock);
            $this->assertSame('8.000', (string) StoreStock::where('store_id', $otherStoreId)->where('item_id', $foreignItemId)->value('quantity'));
            $this->assertSame('300.000', (string) Customer::findOrFail($foreignCustomerId)->current_balance);
            $this->assertSame(0, ReturnDocument::query()->count());
        });
    }

    /** Runs inside the tenant: an item with an opening balance in $store through the stock engine. */
    private function stockedItem(Store $store, string $code, string $name, string $price, string $cost, string $qty, string $document): Item
    {
        $item = Item::create([
            'name' => $name,
            'code' => $code,
            'unit' => 'كجم',
            'selling_price' => $price,
            'cost_price' => $cost,
            'weighted_avg_cost' => $cost,
            'current_stock' => '0.000',
        ]);

        app(StockService::class)->addStock(
            item: $item,
            quantity: $qty,
            unitCost: $cost,
            source: $store,
            documentNumber: $document,
            movementType: 'initial_balance',
            notes: 'رصيد',
            storeId: $store->id
        );

        return $item;
    }
}
