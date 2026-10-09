<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Item;
use App\Models\Purchase;
use App\Models\Supplier;
use App\Models\Tenant;
use App\Models\User;
use App\Services\PurchaseService;
use App\Services\StockService;
use Tests\TenantTestCase;

class PurchaseCancelAndRestoreFeatureTest extends TenantTestCase
{
    protected Tenant $tenant;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = $this->createTenant();
        $this->user = $this->createTenantUser($this->tenant);
    }

    public function test_canceling_purchase_reverses_stock_and_creates_movement(): void
    {
        $this->inTenant($this->tenant, function (): void {
            $this->actingAs(User::findOrFail($this->user->id));
            $purchaseService = app(PurchaseService::class);

            $item = Item::create([
                'code' => 'ITM-CANCEL-TEST',
                'name' => 'بن برازيلي فاخر',
                'current_stock' => '0.000',
                'cost_price' => '100.000',
                'selling_price' => '150.000',
                'is_active' => true,
            ]);

            $supplier = Supplier::create([
                'name' => 'شركة البن العالمية',
                'is_active' => true,
            ]);

            // 1. Create Purchase of 50 kg @ 100 EGP (Total = 5000, Paid = 2000)
            $purchase = $purchaseService->createPurchase([
                'supplier_id' => $supplier->id,
                'paid_amount' => '2000.000',
                'items' => [
                    [
                        'item_id' => $item->id,
                        'quantity' => '50.000',
                        'cost_price' => '100.000',
                    ],
                ],
            ]);

            $item->refresh();
            $supplier->refresh();
            $this->assertEquals('50.000', $item->current_stock);
            $this->assertEquals('3000.000', $supplier->current_balance);

            // 2. Cancel Purchase
            $cancelledPurchase = $purchaseService->cancelPurchase($purchase, 'خطأ في إدخال وزن الشيكارة');

            $item->refresh();
            $supplier->refresh();
            $cancelledPurchase->refresh();

            // Stock reversed to 0.000
            $this->assertEquals('0.000', $item->current_stock);
            $this->assertEquals('cancelled', $cancelledPurchase->status);
            $this->assertEquals('0.000', $supplier->current_balance);

            // Movement record created
            $this->assertDatabaseHas('stock_movements', [
                'item_id' => $item->id,
                'movement_type' => 'purchase_cancel_out',
                'quantity' => '50.000',
                'stock_after' => '0.000',
            ]);
        });
    }

    public function test_canceling_purchase_fails_if_stock_was_already_sold(): void
    {
        $this->inTenant($this->tenant, function (): void {
            $this->actingAs(User::findOrFail($this->user->id));
            $purchaseService = app(PurchaseService::class);

            $item = Item::create([
                'code' => 'ITM-CANCEL-FAIL',
                'name' => 'بن كولومبي مميز',
                'current_stock' => '0.000',
                'cost_price' => '120.000',
                'selling_price' => '180.000',
                'is_active' => true,
            ]);

            $supplier = Supplier::create([
                'name' => 'مورد كولومبيا',
                'is_active' => true,
            ]);

            // Purchase 20 kg
            $purchase = $purchaseService->createPurchase([
                'supplier_id' => $supplier->id,
                'paid_amount' => '0.000',
                'items' => [
                    [
                        'item_id' => $item->id,
                        'quantity' => '20.000',
                        'cost_price' => '120.000',
                    ],
                ],
            ]);

            // Simulate selling 15 kg (remaining stock is 5 kg)
            app(StockService::class)->deductStock($item, '15.000', $item, 'INV-001');

            $item->refresh();
            $this->assertEquals('5.000', $item->current_stock);

            // Attempt to cancel original 20 kg purchase -> Must throw exception because remaining 5 < 20
            $this->expectException(\Exception::class);
            $this->expectExceptionMessage('تعذر إلغاء الفاتورة');

            $purchaseService->cancelPurchase($purchase, 'محاولة إلغاء بعد بيع الصنف');
        });
    }

    public function test_restoring_cancelled_purchase_re_adds_stock_and_restores_balance(): void
    {
        $this->inTenant($this->tenant, function (): void {
            $this->actingAs(User::findOrFail($this->user->id));
            $purchaseService = app(PurchaseService::class);

            $item = Item::create([
                'code' => 'ITM-RESTORE-TEST',
                'name' => 'بن يمني مطري',
                'current_stock' => '0.000',
                'cost_price' => '300.000',
                'selling_price' => '400.000',
                'is_active' => true,
            ]);

            $supplier = Supplier::create([
                'name' => 'مورد اليمن السعيد',
                'is_active' => true,
            ]);

            // 1. Create and then cancel
            $purchase = $purchaseService->createPurchase([
                'supplier_id' => $supplier->id,
                'paid_amount' => '1000.000',
                'items' => [
                    [
                        'item_id' => $item->id,
                        'quantity' => '10.000',
                        'cost_price' => '300.000',
                    ],
                ],
            ]);

            $purchaseService->cancelPurchase($purchase, 'إلغاء مؤقت');
            $item->refresh();
            $this->assertEquals('0.000', $item->current_stock);

            // 2. Restore Purchase
            $restored = $purchaseService->restorePurchase($purchase);

            $item->refresh();
            $supplier->refresh();
            $restored->refresh();

            $this->assertEquals('confirmed', $restored->status);
            $this->assertEquals('10.000', $item->current_stock);
            $this->assertEquals('2000.000', $supplier->current_balance); // (3000 total - 1000 paid = 2000 remaining)

            $this->assertDatabaseHas('stock_movements', [
                'item_id' => $item->id,
                'movement_type' => 'purchase_restore_in',
                'quantity' => '10.000',
                'stock_after' => '10.000',
            ]);
        });
    }

    public function test_cancelling_a_purchase_never_reverses_the_twin_purchase_of_another_tenant(): void
    {
        $other = $this->createTenant();
        $otherAdminId = (int) $this->tenantAdmin($other)->id;

        // Identical documents in both tenants: same item code, supplier name, quantities.
        $seed = function (): array {
            $item = Item::create([
                'code' => 'ITM-TWIN',
                'name' => 'بن توأم',
                'current_stock' => '0.000',
                'cost_price' => '80.000',
                'selling_price' => '120.000',
                'is_active' => true,
            ]);
            $supplier = Supplier::create(['name' => 'مورد توأم', 'is_active' => true]);
            $purchase = app(PurchaseService::class)->createPurchase([
                'supplier_id' => $supplier->id,
                'paid_amount' => '100.000',
                'items' => [['item_id' => $item->id, 'quantity' => '12.500', 'cost_price' => '80.000']],
            ]);

            return [$purchase->id, $item->id, $supplier->id];
        };

        [$purchaseA] = $this->inTenant($this->tenant, function () use ($seed): array {
            $this->actingAs(User::findOrFail($this->user->id));

            return $seed();
        });
        [$purchaseB, $itemB, $supplierB] = $this->inTenant($other, function () use ($seed, $otherAdminId): array {
            $this->actingAs(User::findOrFail($otherAdminId));

            return $seed();
        });

        $this->inTenant($this->tenant, function () use ($purchaseA): void {
            $this->actingAs(User::findOrFail($this->user->id));
            app(PurchaseService::class)->cancelPurchase(Purchase::findOrFail($purchaseA), 'إلغاء في المستأجر أ فقط');
            $this->assertSame('cancelled', Purchase::findOrFail($purchaseA)->status);
        });

        $this->inTenant($other, function () use ($purchaseB, $itemB, $supplierB): void {
            $this->assertSame('confirmed', Purchase::findOrFail($purchaseB)->status);
            $this->assertSame('12.500', (string) Item::findOrFail($itemB)->current_stock);
            $this->assertSame('900.000', (string) Supplier::findOrFail($supplierB)->current_balance); // 1000 - 100 paid
            $this->assertDatabaseMissing('stock_movements', ['movement_type' => 'purchase_cancel_out']);
        });
    }
}
