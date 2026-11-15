<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Models\Item;
use App\Models\Payment;
use App\Models\Purchase;
use App\Models\Store;
use App\Models\StoreStock;
use App\Models\Supplier;
use App\Models\Tenant;
use App\Services\SupplierBalanceService;
use App\Services\TreasuryService;
use RuntimeException;
use Tests\TenantTestCase;

/**
 * PUR-01 (owner decision, BE-21 / Q11): cancelling a purchase that has payments to the supplier
 * reverses the stock as before, but the money already paid is neither lost nor put back in the
 * treasury: it stays as a credit in our favour on the supplier's account (negative balance),
 * shown on the statement and consumed by later purchases / payments.
 */
final class PurchaseCancelSupplierCreditApiTest extends TenantTestCase
{
    private Tenant $tenant;

    private int $storeId;

    private int $supplierId;

    private int $itemId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = $this->createTenant();
        $this->storeId = (int) $this->tenantStore($this->tenant)->id;
        [$this->supplierId, $this->itemId] = $this->inTenant($this->tenant, fn (): array => $this->seedCatalog($this->storeId));
    }

    public function test_cancelling_a_fully_paid_purchase_keeps_the_payment_as_supplier_credit(): void
    {
        $purchaseId = $this->createPurchase(quantity: '2.500', unitCost: '100.000', paid: '250.000');
        $this->assertSame('0.000', $this->supplierBalance());
        $this->assertSame('12.500', $this->storeStock());
        $cashBefore = $this->treasuryCash();

        $this->postJson('/api/v1/purchases/'.$purchaseId.'/cancel', ['reason' => 'بضاعة تالفة'], $this->tenantHeaders($this->tenant))
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->assertSame('-250.000', $this->supplierBalance());
        $this->assertSame($cashBefore, $this->treasuryCash(), 'the paid money must not come back into the treasury');
        $this->assertSame('10.000', $this->storeStock());

        $this->inTenant($this->tenant, function () use ($purchaseId): void {
            $this->assertSame('10.000', (string) Item::findOrFail($this->itemId)->current_stock);
            $purchase = Purchase::findOrFail($purchaseId);
            $this->assertSame('cancelled', $purchase->status);
            $this->assertSame('250.000', (string) $purchase->paid_amount);
            $this->assertSame('0.000', (string) $purchase->remaining_amount);
            $this->assertStringContainsString('250.000', (string) $purchase->notes);

            $voucher = Payment::where('purchase_id', $purchaseId)->sole();
            $this->assertNull($voucher->deleted_at);
            $this->assertSame('250.000', (string) $voucher->amount);
            $this->assertDatabaseHas('stock_movements', ['item_id' => $this->itemId, 'movement_type' => 'purchase_cancel_out', 'quantity' => '2.500']);
        });
    }

    public function test_statement_shows_the_credit_line_of_the_cancelled_purchase(): void
    {
        $purchaseId = $this->createPurchase(quantity: '2.500', unitCost: '100.000', paid: '250.000');
        $number = (string) $this->inTenant($this->tenant, fn () => Purchase::findOrFail($purchaseId)->purchase_number);
        $this->postJson('/api/v1/purchases/'.$purchaseId.'/cancel', [], $this->tenantHeaders($this->tenant))->assertOk();

        $response = $this->getJson('/api/v1/suppliers/'.$this->supplierId.'/statement', $this->tenantHeaders($this->tenant))
            ->assertOk()
            ->assertJsonPath('data.summary.current_balance', '-250.000')
            ->assertJsonCount(1, 'data.ledger');

        $line = $response->json('data.ledger.0');
        $this->assertSame(__('contacts.supplier_ledger_types.cancelled_purchase_credit', ['number' => $number]), $line['type']);
        $this->assertStringContainsString($number, $line['type']);
        $this->assertSame('250.000', $line['debit']);
        $this->assertSame('0.000', $line['credit']);
        $this->assertSame('-250.000', $line['balance_after']);
    }

    public function test_partial_payments_become_credit_and_a_later_purchase_and_payment_consume_it(): void
    {
        // 4.000 x 250.000 = 1000.000, 300.000 paid on the invoice + 200.000 voucher later.
        $purchaseId = $this->createPurchase(quantity: '4.000', unitCost: '250.000', paid: '300.000');
        $this->postJson('/api/v1/payments/supplier-voucher', [
            'supplier_id' => $this->supplierId,
            'purchase_id' => $purchaseId,
            'amount' => '200.000',
            'payment_method' => 'cash',
        ], $this->tenantHeaders($this->tenant))->assertSuccessful();
        $this->assertSame('500.000', $this->supplierBalance());
        $cashBefore = $this->treasuryCash();

        $this->postJson('/api/v1/purchases/'.$purchaseId.'/cancel', [], $this->tenantHeaders($this->tenant))->assertOk();

        $this->assertSame('-500.000', $this->supplierBalance());
        $this->assertSame($cashBefore, $this->treasuryCash());
        $this->assertSame('10.000', $this->storeStock());
        $this->assertSame(2, $this->inTenant($this->tenant, fn (): int => Payment::where('purchase_id', $purchaseId)->count()));

        // A later unpaid purchase of 0.250 x 3000.000 = 750.000 eats the 500.000 credit.
        $this->createPurchase(quantity: '0.250', unitCost: '3000.000', paid: '0.000');
        $this->assertSame('250.000', $this->supplierBalance());

        // Paying the rest settles the account exactly.
        $this->postJson('/api/v1/payments/supplier-voucher', [
            'supplier_id' => $this->supplierId,
            'amount' => '250.000',
            'payment_method' => 'cash',
        ], $this->tenantHeaders($this->tenant))->assertSuccessful();
        $this->assertSame('0.000', $this->supplierBalance());
    }

    public function test_treasury_paid_expense_vouchers_are_not_turned_into_supplier_credit(): void
    {
        $purchaseId = (int) $this->postJson('/api/v1/purchases', [
            'supplier_id' => $this->supplierId,
            'purchase_date' => now()->toDateString(),
            'paid_amount' => '100.000',
            'store_id' => $this->storeId,
            'items' => [['item_id' => $this->itemId, 'quantity' => '1.000', 'unit_cost' => '100.000']],
            'additional_expenses' => [['title' => 'نقل', 'amount' => '15.500', 'paid_by' => 'treasury_cash', 'allocation_method' => 'by_quantity']],
        ], $this->tenantHeaders($this->tenant))->assertCreated()->json('data.id');

        $this->postJson('/api/v1/purchases/'.$purchaseId.'/cancel', [], $this->tenantHeaders($this->tenant))->assertOk();

        // Only the 100.000 paid to the supplier is credit; the freight voucher is voided as before.
        $this->assertSame('-100.000', $this->supplierBalance());
        $this->inTenant($this->tenant, function () use ($purchaseId): void {
            $live = Payment::where('purchase_id', $purchaseId)->pluck('amount')->map(fn ($a): string => (string) $a)->all();
            $this->assertSame(['100.000'], $live);
        });
    }

    public function test_a_failure_mid_cancel_rolls_everything_back(): void
    {
        $purchaseId = $this->createPurchase(quantity: '2.500', unitCost: '100.000', paid: '100.000');
        $balanceBefore = $this->supplierBalance();
        $this->assertSame('150.000', $balanceBefore);
        $cashBefore = $this->treasuryCash();

        // The balance recompute runs after the stock reversal and the voucher handling.
        $this->app->instance(SupplierBalanceService::class, new class extends SupplierBalanceService
        {
            public function updateBalance(int $supplierId): string
            {
                throw new RuntimeException('forced failure mid-cancel');
            }
        });

        $this->postJson('/api/v1/purchases/'.$purchaseId.'/cancel', [], $this->tenantHeaders($this->tenant))
            ->assertStatus(500);

        $this->app->forgetInstance(SupplierBalanceService::class);

        $this->assertSame($balanceBefore, $this->supplierBalance());
        $this->assertSame('12.500', $this->storeStock());
        $this->assertSame($cashBefore, $this->treasuryCash());
        $this->inTenant($this->tenant, function () use ($purchaseId): void {
            $this->assertSame('confirmed', Purchase::findOrFail($purchaseId)->status);
            $this->assertSame('12.500', (string) Item::findOrFail($this->itemId)->current_stock);
            $this->assertSame(1, Payment::where('purchase_id', $purchaseId)->count());
            $this->assertDatabaseMissing('stock_movements', ['movement_type' => 'purchase_cancel_out']);
        });
    }

    public function test_a_user_of_another_store_cannot_cancel_and_nothing_changes(): void
    {
        $storeB = (int) $this->inTenant($this->tenant, function (): int {
            $store = Store::query()->create(['name' => 'فرع ب', 'code' => 'BR-B', 'type' => 'retail', 'is_main' => false, 'is_active' => true]);
            StoreStock::create(['store_id' => $store->id, 'item_id' => $this->itemId, 'quantity' => '0.000']);

            return (int) $store->id;
        });

        $purchaseB = (int) $this->postJson('/api/v1/purchases', [
            'supplier_id' => $this->supplierId,
            'purchase_date' => now()->toDateString(),
            'paid_amount' => '50.000',
            'store_id' => $storeB,
            'items' => [['item_id' => $this->itemId, 'quantity' => '1.000', 'unit_cost' => '80.000']],
        ], $this->tenantHeaders($this->tenant, null, $storeB))->assertCreated()->json('data.id');

        $userOfA = $this->createTenantUser($this->tenant, 'cashier', ['purchases.view', 'purchases.delete']);
        $this->postJson('/api/v1/purchases/'.$purchaseB.'/cancel', [], $this->tenantHeaders($this->tenant, $userOfA))
            ->assertNotFound();

        $this->assertSame('30.000', $this->supplierBalance());
        $this->inTenant($this->tenant, function () use ($purchaseB, $storeB): void {
            $this->assertSame('confirmed', Purchase::findOrFail($purchaseB)->status);
            $this->assertSame('1.000', (string) StoreStock::where('store_id', $storeB)->where('item_id', $this->itemId)->value('quantity'));
            $this->assertSame(1, Payment::where('purchase_id', $purchaseB)->count());
        });
    }

    public function test_cancel_without_permission_is_forbidden_and_changes_nothing(): void
    {
        $purchaseId = $this->createPurchase(quantity: '1.000', unitCost: '100.000', paid: '100.000');
        $viewer = $this->createTenantUser($this->tenant, 'cashier', ['purchases.view']);

        $this->postJson('/api/v1/purchases/'.$purchaseId.'/cancel', [], $this->tenantHeaders($this->tenant, $viewer))
            ->assertForbidden();

        $this->assertSame('0.000', $this->supplierBalance());
        $this->assertSame('confirmed', $this->inTenant($this->tenant, fn () => Purchase::findOrFail($purchaseId)->status));
    }

    public function test_cancel_without_token_is_unauthenticated(): void
    {
        $purchaseId = $this->createPurchase(quantity: '1.000', unitCost: '100.000', paid: '100.000');

        $this->postJson('/api/v1/purchases/'.$purchaseId.'/cancel', [], $this->tenantGuestHeaders($this->tenant))
            ->assertUnauthorized();

        $this->assertSame('confirmed', $this->inTenant($this->tenant, fn () => Purchase::findOrFail($purchaseId)->status));
    }

    private function createPurchase(string $quantity, string $unitCost, string $paid): int
    {
        return (int) $this->postJson('/api/v1/purchases', [
            'supplier_id' => $this->supplierId,
            'purchase_date' => now()->toDateString(),
            'paid_amount' => $paid,
            'payment_method' => 'cash',
            'store_id' => $this->storeId,
            'items' => [['item_id' => $this->itemId, 'quantity' => $quantity, 'unit_cost' => $unitCost]],
        ], $this->tenantHeaders($this->tenant))->assertCreated()->json('data.id');
    }

    private function supplierBalance(): string
    {
        return (string) $this->inTenant($this->tenant, fn () => Supplier::findOrFail($this->supplierId)->current_balance);
    }

    private function storeStock(): string
    {
        return (string) $this->inTenant($this->tenant, fn () => StoreStock::where('store_id', $this->storeId)
            ->where('item_id', $this->itemId)->value('quantity'));
    }

    private function treasuryCash(): string
    {
        return (string) $this->inTenant($this->tenant, fn () => app(TreasuryService::class)->getBalances($this->storeId)['cash']['balance']);
    }

    /**
     * @return array{int, int}
     */
    private function seedCatalog(int $storeId): array
    {
        $supplier = Supplier::create(['name' => 'مورد الرصيد الدائن', 'phone' => '01000002101', 'is_active' => true]);
        $item = Item::create([
            'name' => 'بن رصيد دائن',
            'code' => 'PUR01-ITEM',
            'unit' => 'كجم',
            'cost_price' => '100.000',
            'selling_price' => '150.000',
            'current_stock' => '10.000',
            'is_active' => true,
        ]);
        StoreStock::create(['store_id' => $storeId, 'item_id' => $item->id, 'quantity' => '10.000']);

        return [(int) $supplier->id, (int) $item->id];
    }
}
