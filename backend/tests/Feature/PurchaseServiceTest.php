<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Item;
use App\Models\Supplier;
use App\Models\Tenant;
use App\Models\User;
use App\Services\PurchaseService;
use Tests\TenantTestCase;

class PurchaseServiceTest extends TenantTestCase
{
    protected Tenant $tenant;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = $this->createTenant();
        $this->user = $this->createTenantUser($this->tenant);
    }

    public function test_purchase_adds_stock_and_computes_weighted_average_cost(): void
    {
        $this->inTenant($this->tenant, function (): void {
            $this->actingAs(User::findOrFail($this->user->id));
            $purchaseService = app(PurchaseService::class);

            // Initial state: 10 units @ 100.000 cost
            $item = Item::create([
                'code' => 'ITM-WAC',
                'name' => 'قرص صلب SSD 1TB',
                'current_stock' => '10.000',
                'cost_price' => '100.000',
                'weighted_avg_cost' => '100.000',
                'selling_price' => '150.000',
                'is_active' => true,
            ]);

            $supplier = Supplier::create([
                'name' => 'مورد التكنولوجيا الحديثة',
                'is_active' => true,
            ]);

            // Purchase: 10 new units @ 200.000 cost
            // Expected WAC = (10*100 + 10*200) / 20 = 3000 / 20 = 150.000
            $purchase = $purchaseService->createPurchase([
                'supplier_id' => $supplier->id,
                'paid_amount' => '1000.000',
                'items' => [
                    [
                        'item_id' => $item->id,
                        'quantity' => '10.000',
                        'cost_price' => '200.000',
                    ],
                ],
            ]);

            $item->refresh();
            $this->assertEquals('20.000', $item->current_stock);
            $this->assertEquals('200.000', $item->cost_price);
            $this->assertEquals('150.000', $item->weighted_avg_cost);

            $this->assertEquals('2000.000', $purchase->net_total);
            $this->assertEquals('1000.000', $purchase->paid_amount);
            $this->assertEquals('1000.000', $purchase->remaining_amount);
            $this->assertEquals('partially_paid', $purchase->payment_status);
        });
    }

    public function test_generate_unique_number_prevents_duplicate_after_soft_delete(): void
    {
        $this->inTenant($this->tenant, function (): void {
            $this->actingAs(User::findOrFail($this->user->id));
            $purchaseService = app(PurchaseService::class);

            $item = Item::create([
                'code' => 'ITM-PUR-UNIQ',
                'name' => 'صنف اختبار توريد فريد',
                'current_stock' => '0.000',
                'cost_price' => '100.000',
                'selling_price' => '150.000',
                'is_active' => true,
            ]);

            $supplier = Supplier::create(['name' => 'مورد اختبار فريد', 'is_active' => true]);
            $todayPrefix = 'PUR-'.date('Ymd');

            // 1. Create first purchase
            $pur1 = $purchaseService->createPurchase([
                'supplier_id' => $supplier->id,
                'items' => [['item_id' => $item->id, 'quantity' => '5.000', 'cost_price' => '100.000']],
            ]);
            $this->assertEquals($todayPrefix.'-0001', $pur1->purchase_number);

            // 2. Soft-delete purchase
            $pur1->delete();
            $this->assertSoftDeleted('purchases', ['id' => $pur1->id]);

            // 3. Create second purchase - must be PUR-YYYYMMDD-0002 without collision
            $pur2 = $purchaseService->createPurchase([
                'supplier_id' => $supplier->id,
                'items' => [['item_id' => $item->id, 'quantity' => '5.000', 'cost_price' => '100.000']],
            ]);
            $this->assertEquals($todayPrefix.'-0002', $pur2->purchase_number);
        });
    }

    public function test_purchase_numbering_and_stock_are_independent_per_tenant(): void
    {
        $other = $this->createTenant();
        $todayPrefix = 'PUR-'.date('Ymd');

        $run = function (string $code, string $qty): array {
            $item = Item::create([
                'code' => $code,
                'name' => 'صنف عزل المستأجرين',
                'current_stock' => '1.000',
                'cost_price' => '100.000',
                'weighted_avg_cost' => '100.000',
                'selling_price' => '150.000',
                'is_active' => true,
            ]);
            $supplier = Supplier::create(['name' => 'مورد عزل', 'is_active' => true]);

            $purchase = app(PurchaseService::class)->createPurchase([
                'supplier_id' => $supplier->id,
                'items' => [['item_id' => $item->id, 'quantity' => $qty, 'cost_price' => '100.000']],
            ]);

            return [$purchase->purchase_number, $item->id];
        };

        [$numberA, $itemA] = $this->inTenant($this->tenant, function () use ($run): array {
            $this->actingAs(User::findOrFail($this->user->id));
            $run('ITM-ISO-1', '2.000');

            return $run('ITM-ISO-2', '3.000');
        });
        [$numberB, $itemB] = $this->inTenant($other, function () use ($run): array {
            $this->actingAs($this->tenantAdmin(tenant()));

            return $run('ITM-ISO-1', '0.250');
        });

        // A's two purchases do not advance B's sequence.
        $this->assertSame($todayPrefix.'-0002', $numberA);
        $this->assertSame($todayPrefix.'-0001', $numberB);
        $this->assertSame('4.000', $this->inTenant($this->tenant, fn (): string => (string) Item::findOrFail($itemA)->current_stock));
        $this->assertSame('1.250', $this->inTenant($other, fn (): string => (string) Item::findOrFail($itemB)->current_stock));
    }
}
