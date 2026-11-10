<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Models\Customer;
use App\Models\Item;
use App\Models\ReturnItem;
use App\Models\StockMovement;
use App\Models\Store;
use App\Models\StoreStock;
use App\Models\Supplier;
use App\Models\Tenant;
use App\Services\AuditLogService;
use RuntimeException;
use Tests\TenantTestCase;

/**
 * BE-02: deleting a return exactly reverses its stock, item cost and party balance.
 * (A cash refund voucher is reversed with its return: see ReturnRefundMethodTest.)
 */
final class ReturnsDeleteReversalApiTest extends TenantTestCase
{
    private Tenant $tenant;

    /** @var array<string, string> */
    private array $headers;

    private int $storeId;

    private int $customerId;

    private int $supplierId;

    private int $itemId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = $this->createTenant();
        $this->storeId = (int) $this->tenantStore($this->tenant)->id;
        $this->headers = $this->tenantHeaders($this->tenant);

        [$this->customerId, $this->supplierId, $this->itemId] = $this->inTenant($this->tenant, function (): array {
            $customer = Customer::create(['name' => 'عميل', 'phone' => '01000007003', 'price_tier' => 'retail', 'is_active' => true]);
            $supplier = Supplier::create(['name' => 'مورد', 'phone' => '01234567890', 'current_balance' => '0.000', 'is_active' => true]);
            $item = Item::create([
                'name' => 'بن', 'code' => 'BN-1', 'category' => 'coffee_beans', 'unit' => 'كجم',
                'cost_price' => '350.000', 'weighted_avg_cost' => '350.000', 'selling_price' => '480.000',
                'price_retail' => '480.000', 'price_wholesale' => '440.000',
                'current_stock' => '100.000', 'min_stock_level' => '15.000', 'is_active' => true,
            ]);
            StoreStock::create(['store_id' => $this->storeId, 'item_id' => $item->id, 'quantity' => '100.000']);

            return [$customer->id, $supplier->id, $item->id];
        });
    }

    public function test_deleting_a_sales_return_restores_stock_and_customer_balance_exactly(): void
    {
        $id = $this->createSalesReturn('7.250', '20.000');
        $this->assertSame('107.250', $this->stock());
        $this->assertSame('107.250', $this->storeStock());
        $this->assertSame('-145.000', $this->customerBalance());

        $this->deleteJson('/api/v1/returns/'.$id, [], $this->headers)
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->assertSame('100.000', $this->stock());
        $this->assertSame('100.000', $this->storeStock());
        $this->assertSame('0.000', $this->customerBalance());
        $this->inTenant($this->tenant, function () use ($id): void {
            $this->assertSoftDeleted('returns', ['id' => $id]);
            $movement = StockMovement::query()->where('movement_type', 'sales_return_cancel_out')->sole();
            $this->assertSame('7.250', (string) $movement->quantity);
            $this->assertSame($this->storeId, (int) $movement->store_id);
        });
    }

    public function test_deleting_a_purchase_return_restores_stock_supplier_balance_and_average_cost(): void
    {
        $id = $this->postJson('/api/v1/returns', [
            'return_type' => 'purchase_return',
            'supplier_id' => $this->supplierId,
            'return_date' => now()->toDateString(),
            'items' => [['item_id' => $this->itemId, 'quantity' => '10.000', 'unit_price' => '400.000']],
        ], $this->headers)->assertCreated()->json('data.id');

        $this->assertSame('90.000', $this->stock());
        $this->assertSame('-4000.000', $this->supplierBalance());
        $this->assertNotSame('350.000', $this->wac());

        $this->deleteJson('/api/v1/returns/'.$id, [], $this->headers)->assertOk();

        $this->assertSame('100.000', $this->stock());
        $this->assertSame('100.000', $this->storeStock());
        $this->assertSame('0.000', $this->supplierBalance());
        $this->assertSame('350.000', $this->wac());
        $this->inTenant($this->tenant, fn () => $this->assertSame(1, StockMovement::query()->where('movement_type', 'purchase_return_cancel_in')->count()));
    }

    public function test_second_delete_does_not_reverse_twice(): void
    {
        $id = $this->createSalesReturn('5.000', '20.000');

        $this->deleteJson('/api/v1/returns/'.$id, [], $this->headers)->assertOk();
        $this->deleteJson('/api/v1/returns/'.$id, [], $this->headers)->assertNotFound();

        $this->assertSame('100.000', $this->stock());
        $this->assertSame('100.000', $this->storeStock());
        $this->assertSame('0.000', $this->customerBalance());
        $this->inTenant($this->tenant, fn () => $this->assertSame(1, StockMovement::query()->where('movement_type', 'sales_return_cancel_out')->count()));
    }

    public function test_deleting_a_sales_return_is_refused_with_422_when_the_stock_was_consumed(): void
    {
        $id = $this->createSalesReturn('10.000', '20.000');
        $this->inTenant($this->tenant, function (): void {
            Item::whereKey($this->itemId)->update(['current_stock' => '4.000']);
            StoreStock::where('item_id', $this->itemId)->update(['quantity' => '4.000']);
        });

        $this->deleteJson('/api/v1/returns/'.$id, [], $this->headers)
            ->assertStatus(422)
            ->assertJsonValidationErrors(['id']);

        $this->assertSame('4.000', $this->stock());
        $this->assertSame('4.000', $this->storeStock());
        $this->assertSame('-200.000', $this->customerBalance());
        $this->inTenant($this->tenant, fn () => $this->assertNotSoftDeleted('returns', ['id' => $id]));
    }

    public function test_failure_midway_rolls_everything_back(): void
    {
        $id = $this->createSalesReturn('5.000', '20.000');

        $this->mock(AuditLogService::class)->shouldReceive('log')->andThrow(new RuntimeException('boom'));
        $this->withoutExceptionHandling();

        try {
            $this->deleteJson('/api/v1/returns/'.$id, [], $this->headers);
            $this->fail('Expected the failure to propagate.');
        } catch (RuntimeException) {
        }

        $this->assertSame('105.000', $this->stock());
        $this->assertSame('105.000', $this->storeStock());
        $this->assertSame('-100.000', $this->customerBalance());
        $this->inTenant($this->tenant, function () use ($id): void {
            $this->assertNotSoftDeleted('returns', ['id' => $id]);
            $this->assertSame(0, StockMovement::query()->where('movement_type', 'sales_return_cancel_out')->count());
        });
    }

    public function test_legacy_purchase_return_without_snapshot_uses_the_inverse_formula(): void
    {
        $id = $this->createPurchaseReturn([['quantity' => '10.000', 'unit_price' => '400.000']]);
        $this->inTenant($this->tenant, fn () => ReturnItem::query()->where('return_id', $id)->update(['wac_before' => null]));

        $this->deleteJson('/api/v1/returns/'.$id, [], $this->headers)->assertOk();

        // (90 x 344.444 + 10 x 400) / 100 = 349.9996 -> 350.000
        $this->assertSame('100.000', $this->stock());
        $this->assertSame('350.000', $this->wac());
    }

    public function test_purchase_return_delete_falls_back_to_the_formula_when_the_cost_changed_since(): void
    {
        $id = $this->createPurchaseReturn([['quantity' => '10.000', 'unit_price' => '400.000']]);
        $this->inTenant($this->tenant, fn () => Item::whereKey($this->itemId)->update(['weighted_avg_cost' => '360.000']));

        $this->deleteJson('/api/v1/returns/'.$id, [], $this->headers)->assertOk();

        // The snapshot (350) is not trusted: (90 x 360 + 10 x 400) / 100 = 364.000
        $this->assertSame('100.000', $this->stock());
        $this->assertSame('364.000', $this->wac());
    }

    public function test_multi_line_same_item_purchase_return_is_reversed_exactly(): void
    {
        $id = $this->createPurchaseReturn([
            ['quantity' => '4.000', 'unit_price' => '400.000'],
            ['quantity' => '6.000', 'unit_price' => '410.000'],
        ]);
        $this->assertSame('90.000', $this->stock());
        $this->assertSame('-4060.000', $this->supplierBalance());

        $this->deleteJson('/api/v1/returns/'.$id, [], $this->headers)->assertOk();

        $this->assertSame('100.000', $this->stock());
        $this->assertSame('100.000', $this->storeStock());
        $this->assertSame('0.000', $this->supplierBalance());
        $this->assertSame('350.000', $this->wac());
        $this->inTenant($this->tenant, fn () => $this->assertSame(2, StockMovement::query()->where('movement_type', 'purchase_return_cancel_in')->count()));
    }

    public function test_failure_midway_rolls_back_a_purchase_return_delete(): void
    {
        $id = $this->createPurchaseReturn([['quantity' => '10.000', 'unit_price' => '400.000']]);
        $wacAfterReturn = $this->wac();

        $this->mock(AuditLogService::class)->shouldReceive('log')->andThrow(new RuntimeException('boom'));
        $this->withoutExceptionHandling();

        try {
            $this->deleteJson('/api/v1/returns/'.$id, [], $this->headers);
            $this->fail('Expected the failure to propagate.');
        } catch (RuntimeException) {
        }

        $this->assertSame('90.000', $this->stock());
        $this->assertSame('90.000', $this->storeStock());
        $this->assertSame('-4000.000', $this->supplierBalance());
        $this->assertSame($wacAfterReturn, $this->wac());
        $this->inTenant($this->tenant, function () use ($id): void {
            $this->assertNotSoftDeleted('returns', ['id' => $id]);
            $this->assertSame(0, StockMovement::query()->where('movement_type', 'purchase_return_cancel_in')->count());
        });
    }

    public function test_delete_requires_authentication_and_permission(): void
    {
        $id = $this->createSalesReturn('1.000', '20.000');

        $this->deleteJson('/api/v1/returns/'.$id, [], $this->tenantGuestHeaders($this->tenant))->assertStatus(401);

        $nobody = $this->createTenantUser($this->tenant, attributes: ['name' => 'بدون صلاحيات']);
        $this->deleteJson('/api/v1/returns/'.$id, [], $this->tenantHeaders($this->tenant, $nobody))->assertStatus(403);

        $this->assertSame('101.000', $this->stock());
        $this->inTenant($this->tenant, fn () => $this->assertNotSoftDeleted('returns', ['id' => $id]));
    }

    public function test_return_of_another_branch_cannot_be_deleted_by_a_user_of_the_first(): void
    {
        $storeB = $this->inTenant($this->tenant, function (): int {
            $b = (int) Store::query()->create(['name' => 'فرع ب', 'code' => 'BR-B', 'type' => 'retail', 'is_main' => false, 'is_active' => true])->id;
            StoreStock::create(['store_id' => $b, 'item_id' => $this->itemId, 'quantity' => '0.000']);

            return $b;
        });
        $headersB = $this->tenantHeaders($this->tenant, null, $storeB);
        $id = (int) $this->postJson('/api/v1/returns', [
            'return_type' => 'sales_return', 'customer_id' => $this->customerId, 'return_date' => now()->toDateString(),
            'items' => [['item_id' => $this->itemId, 'quantity' => '3.000', 'unit_price' => '20.000']],
        ], $headersB)->assertCreated()->json('data.id');

        $userA = $this->createTenantUser($this->tenant, 'cashier', ['returns.manage']);
        $this->deleteJson('/api/v1/returns/'.$id, [], $this->tenantHeaders($this->tenant, $userA))->assertNotFound();
        $this->assertSame('103.000', $this->stock());

        // The branch's own delete reverses that branch's stock only.
        $this->deleteJson('/api/v1/returns/'.$id, [], $headersB)->assertOk();
        $this->assertSame('100.000', $this->stock());
        $this->assertSame('100.000', $this->storeStock());
        $this->inTenant($this->tenant, fn () => $this->assertSame('0.000', (string) StoreStock::where('store_id', $storeB)->value('quantity')));
    }

    public function test_deleted_return_cannot_be_restored_from_the_trash(): void
    {
        $id = $this->createSalesReturn('2.000', '20.000');
        $this->deleteJson('/api/v1/returns/'.$id, [], $this->headers)->assertOk();

        $this->postJson('/api/v1/trash/returns/'.$id.'/restore', [], $this->headers)
            ->assertStatus(422)
            ->assertJsonValidationErrors(['type']);

        $this->assertSame('100.000', $this->stock());
        $this->inTenant($this->tenant, fn () => $this->assertSoftDeleted('returns', ['id' => $id]));
    }

    /**
     * @param  list<array{quantity: string, unit_price: string}>  $lines
     */
    private function createPurchaseReturn(array $lines): int
    {
        return (int) $this->postJson('/api/v1/returns', [
            'return_type' => 'purchase_return',
            'supplier_id' => $this->supplierId,
            'return_date' => now()->toDateString(),
            'items' => array_map(fn (array $l): array => ['item_id' => $this->itemId] + $l, $lines),
        ], $this->headers)->assertCreated()->json('data.id');
    }

    private function createSalesReturn(string $qty, string $price): int
    {
        return (int) $this->postJson('/api/v1/returns', [
            'return_type' => 'sales_return',
            'customer_id' => $this->customerId,
            'return_date' => now()->toDateString(),
            'items' => [['item_id' => $this->itemId, 'quantity' => $qty, 'unit_price' => $price]],
        ], $this->headers)->assertCreated()->json('data.id');
    }

    private function stock(): string
    {
        return $this->inTenant($this->tenant, fn (): string => (string) Item::findOrFail($this->itemId)->current_stock);
    }

    private function storeStock(): string
    {
        return $this->inTenant($this->tenant, fn (): string => (string) StoreStock::where('store_id', $this->storeId)->where('item_id', $this->itemId)->value('quantity'));
    }

    private function wac(): string
    {
        return $this->inTenant($this->tenant, fn (): string => (string) Item::findOrFail($this->itemId)->weighted_avg_cost);
    }

    private function customerBalance(): string
    {
        return $this->inTenant($this->tenant, fn (): string => (string) Customer::findOrFail($this->customerId)->current_balance);
    }

    private function supplierBalance(): string
    {
        return $this->inTenant($this->tenant, fn (): string => (string) Supplier::findOrFail($this->supplierId)->current_balance);
    }
}
