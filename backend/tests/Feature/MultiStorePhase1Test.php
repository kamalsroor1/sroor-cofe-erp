<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Item;
use App\Models\StockTransfer;
use App\Models\StockTransferItem;
use App\Models\Store;
use App\Models\StoreStock;
use App\Models\Tenant;
use App\Models\User;
use Tests\TenantTestCase;

/**
 * QA-4: every model below lives in the harness tenant's own database, so each test body
 * runs inside inTenant(); models are re-read by id there and never saved outside it.
 */
class MultiStorePhase1Test extends TenantTestCase
{
    protected Tenant $tenant;

    protected int $adminId;

    protected int $mainStoreId;

    protected int $vanStoreId;

    protected int $itemId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = $this->createTenant();
        $this->adminId = (int) $this->tenantAdmin($this->tenant)->id;
        $this->mainStoreId = (int) $this->tenantStore($this->tenant)->id;

        [$this->vanStoreId, $this->itemId] = $this->inTenant($this->tenant, function (): array {
            // The harness main store (code MAIN-01, is_main) becomes this fixture's main warehouse.
            Store::query()->findOrFail($this->mainStoreId)->update(['name' => 'المخزن الرئيسي', 'type' => 'main_warehouse']);

            $van = Store::create([
                'name' => 'عربية توزيع رقم 1',
                'code' => 'VAN-01',
                'type' => 'wholesale_van',
                'is_active' => true,
                'is_main' => false,
            ]);

            User::query()->findOrFail($this->adminId)->stores()->attach([$this->mainStoreId, $van->id]);

            $item = Item::create([
                'code' => 'COF-001',
                'name' => 'بن كولومبي فاخر',
                'category' => 'بن سادة',
                'unit' => 'كجم',
                'current_stock' => '100.000',
                'cost_price' => '250.000',
                'weighted_avg_cost' => '250.000',
                'selling_price' => '400.000',
                'min_stock_level' => '10.000',
                'is_active' => true,
            ]);

            return [(int) $van->id, (int) $item->id];
        });
    }

    public function test_store_creation_and_scopes(): void
    {
        $this->inTenant($this->tenant, function (): void {
            $mainStore = Store::query()->findOrFail($this->mainStoreId);

            $this->assertEquals('MAIN-01', $mainStore->code);
            $this->assertTrue($mainStore->is_main);
            $this->assertEquals($this->mainStoreId, Store::getMainStore()->id);

            $this->assertCount(1, Store::vans()->get());
            $this->assertEquals('VAN-01', Store::vans()->first()->code);
        });
    }

    public function test_store_stock_custom_price_fallback(): void
    {
        $this->inTenant($this->tenant, function (): void {
            $item = Item::query()->findOrFail($this->itemId);

            // 1. Stock in main store with NO custom price (should fallback to 400.000)
            $mainStock = StoreStock::create([
                'store_id' => $this->mainStoreId,
                'item_id' => $this->itemId,
                'quantity' => '80.000',
                'min_stock' => '10.000',
                'custom_selling_price' => null,
            ]);

            $this->assertEquals('400.000', $mainStock->effective_selling_price);
            $this->assertEquals('400.000', $item->getEffectivePriceForStore($this->mainStoreId));
            $this->assertEquals('80.000', $item->getStockInStore($this->mainStoreId));

            // 2. Stock in van store with CUSTOM wholesale price 360.000
            $vanStock = StoreStock::create([
                'store_id' => $this->vanStoreId,
                'item_id' => $this->itemId,
                'quantity' => '20.000',
                'min_stock' => '5.000',
                'custom_selling_price' => '360.000',
            ]);

            $item->refresh();
            $this->assertEquals('360.000', $vanStock->effective_selling_price);
            $this->assertEquals('360.000', $item->getEffectivePriceForStore($this->vanStoreId));
            $this->assertEquals('20.000', $item->getStockInStore($this->vanStoreId));
        });
    }

    public function test_user_store_relationships_and_current_store(): void
    {
        $this->inTenant($this->tenant, function (): void {
            $admin = User::query()->findOrFail($this->adminId);

            $this->assertCount(2, $admin->stores);
            $this->assertEquals($this->mainStoreId, $admin->defaultStore->id);
            $this->assertEquals($this->mainStoreId, $admin->getCurrentStore()->id);

            // Simulate session switch to van
            session(['current_store_id' => $this->vanStoreId]);
            $this->assertEquals($this->vanStoreId, $admin->getCurrentStore()->id);
        });
    }

    public function test_stock_transfer_models_creation(): void
    {
        $this->inTenant($this->tenant, function (): void {
            $transfer = StockTransfer::create([
                'transfer_number' => 'TRF-20260811-0001',
                'from_store_id' => $this->mainStoreId,
                'to_store_id' => $this->vanStoreId,
                'user_id' => $this->adminId,
                'transfer_date' => now()->toDateString(),
                'status' => 'pending',
                'notes' => 'شحن عهدة بضاعة لعربية التوزيع',
            ]);

            StockTransferItem::create([
                'stock_transfer_id' => $transfer->id,
                'item_id' => $this->itemId,
                'quantity' => '25.000',
            ]);

            $this->assertEquals('TRF-20260811-0001', $transfer->transfer_number);
            $this->assertEquals($this->mainStoreId, $transfer->fromStore->id);
            $this->assertEquals($this->vanStoreId, $transfer->toStore->id);
            $this->assertCount(1, $transfer->items);
            $this->assertEquals('25.000', $transfer->items->first()->quantity);
        });
    }

    public function test_another_tenants_vans_and_branch_stock_are_invisible(): void
    {
        $other = $this->createTenant();
        $otherVanId = $this->inTenant($other, function (): int {
            $van = Store::create(['name' => 'عربية مستأجر آخر', 'code' => 'VAN-B1', 'type' => 'wholesale_van', 'is_active' => true, 'is_main' => false]);
            Store::create(['name' => 'عربية مستأجر آخر 2', 'code' => 'VAN-B2', 'type' => 'wholesale_van', 'is_active' => true, 'is_main' => false]);

            return (int) $van->id;
        });

        $this->inTenant($this->tenant, function () use ($otherVanId): void {
            $this->assertSame(['VAN-01'], Store::vans()->pluck('code')->all());
            $this->assertNull(Store::query()->find($otherVanId));
            $this->assertEquals($this->mainStoreId, Store::getMainStore()->id);
        });

        $this->assertSame(2, $this->inTenant($other, fn (): int => Store::vans()->count()));
    }
}
