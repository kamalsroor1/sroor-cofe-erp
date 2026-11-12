<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Item;
use App\Models\OpeningBalance;
use App\Models\StoreStock;
use App\Models\Supplier;
use App\Services\PaymentService;
use App\Services\PurchaseService;
use App\Services\ReturnService;
use Tests\TenantTestCase;

/**
 * BUG-07 review (money): a purchase return recomputed the supplier balance with an inline
 * formula that left out the imported opening balance (PRD-06), so `current_balance` lost it
 * until the next full recompute. It now goes through SupplierBalanceService, the one formula:
 * opening balance + confirmed purchases - payments - purchase returns.
 */
final class PurchaseReturnSupplierOpeningBalanceTest extends TenantTestCase
{
    public function test_a_purchase_return_keeps_the_imported_opening_balance_in_the_supplier_balance(): void
    {
        $tenant = $this->createTenant();
        $storeId = (int) $this->tenantStore($tenant)->getKey();

        $state = $this->inTenant($tenant, function () use ($storeId): array {
            $supplier = Supplier::query()->create(['name' => 'مورد برصيد افتتاحي', 'current_balance' => '0.000', 'is_active' => true]);
            OpeningBalance::query()->create([
                'party_type' => OpeningBalance::PARTY_SUPPLIER,
                'party_id' => $supplier->id,
                'amount' => '1500.250',
                'balance_date' => '2026-01-01',
            ]);

            $item = Item::query()->create([
                'code' => 'QA-OB-01', 'name' => 'بن رصيد افتتاحي', 'category' => 'coffee_beans', 'unit' => 'كجم',
                'cost_price' => '200.000', 'selling_price' => '300.000', 'current_stock' => '0.000',
                'min_stock_level' => '0.000', 'is_active' => true,
            ]);
            StoreStock::query()->updateOrCreate(['store_id' => $storeId, 'item_id' => $item->id], ['quantity' => '0.000']);

            // Purchase 10.250 kg x 200 = 2050.000, 300.500 paid at once (PAY-PUR).
            app(PurchaseService::class)->createPurchase([
                'supplier_id' => $supplier->id,
                'store_id' => $storeId,
                'paid_amount' => '300.500',
                'payment_method' => 'cash',
                'items' => [['item_id' => $item->id, 'quantity' => '10.250', 'cost_price' => '200.000']],
            ]);
            app(PaymentService::class)->recordSupplierPayment(['supplier_id' => $supplier->id, 'amount' => '100.000', 'payment_method' => 'cash', 'store_id' => $storeId]);

            // Return 1.250 kg x 200 = 250.000 to the supplier.
            app(ReturnService::class)->createPurchaseReturn([
                'supplier_id' => $supplier->id,
                'store_id' => $storeId,
                'items' => [['item_id' => $item->id, 'quantity' => '1.250', 'unit_price' => '200.000']],
            ]);

            return [
                'balance' => (string) Supplier::query()->whereKey($supplier->id)->value('current_balance'),
                'stock' => (string) Item::query()->whereKey($item->id)->value('current_stock'),
            ];
        });

        // 1500.250 opening + 2050.000 purchases - (300.500 + 100.000) payments - 250.000 returns.
        $this->assertSame(['balance' => '2899.750', 'stock' => '9.000'], $state);
    }
}
