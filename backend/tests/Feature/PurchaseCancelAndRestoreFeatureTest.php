<?php

namespace Tests\Feature;

use App\Models\Item;
use App\Models\Purchase;
use App\Models\Supplier;
use App\Models\User;
use App\Services\PurchaseService;
use App\Services\StockService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PurchaseCancelAndRestoreFeatureTest extends TestCase
{
    use RefreshDatabase;

    protected PurchaseService $purchaseService;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->purchaseService = app(PurchaseService::class);
        $this->user = User::factory()->create();
        $this->actingAs($this->user);
    }

    public function test_canceling_purchase_reverses_stock_and_creates_movement(): void
    {
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
        $purchase = $this->purchaseService->createPurchase([
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
        $cancelledPurchase = $this->purchaseService->cancelPurchase($purchase, 'خطأ في إدخال وزن الشيكارة');

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
    }

    public function test_canceling_purchase_fails_if_stock_was_already_sold(): void
    {
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
        $purchase = $this->purchaseService->createPurchase([
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

        $this->purchaseService->cancelPurchase($purchase, 'محاولة إلغاء بعد بيع الصنف');
    }

    public function test_restoring_cancelled_purchase_re_adds_stock_and_restores_balance(): void
    {
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
        $purchase = $this->purchaseService->createPurchase([
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

        $this->purchaseService->cancelPurchase($purchase, 'إلغاء مؤقت');
        $item->refresh();
        $this->assertEquals('0.000', $item->current_stock);

        // 2. Restore Purchase
        $restored = $this->purchaseService->restorePurchase($purchase);

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
    }
}
