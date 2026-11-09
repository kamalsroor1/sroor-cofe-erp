<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Models\Customer;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Item;
use App\Models\Purchase;
use App\Models\PurchaseItem;
use App\Models\ReturnItem;
use App\Models\Store;
use App\Models\StoreStock;
use App\Models\Supplier;
use App\Models\Tenant;
use App\Models\User;
use App\Services\InvoiceService;
use App\Services\PurchaseService;
use Illuminate\Testing\TestResponse;
use Illuminate\Validation\ValidationException;
use Tests\TenantTestCase;

/**
 * BE-01: a normal return references an invoice / purchase line. Item, price and party come
 * from the source; the quantity is capped by what non-deleted returns left of the line.
 */
final class ReturnsAgainstSourceApiTest extends TenantTestCase
{
    private Tenant $tenant;

    /** @var array<string, string> */
    private array $headers;

    private int $storeId;

    private int $customerId;

    private int $supplierId;

    private int $itemId;

    private int $otherItemId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = $this->createTenant();
        $this->storeId = (int) $this->tenantStore($this->tenant)->id;
        $this->headers = $this->tenantHeaders($this->tenant);

        $this->inTenant($this->tenant, function (): void {
            $this->customerId = (int) Customer::create(['name' => 'عميل', 'phone' => '01000007003', 'price_tier' => 'retail', 'is_active' => true])->id;
            $this->supplierId = (int) Supplier::create(['name' => 'مورد', 'phone' => '01234567890', 'current_balance' => '0.000', 'is_active' => true])->id;
            $this->itemId = $this->makeItem('SRC-1', $this->storeId);
            $this->otherItemId = $this->makeItem('SRC-2', $this->storeId);
        });
    }

    public function test_return_price_comes_from_the_invoice_line_net_of_the_invoice_discount_and_client_price_is_ignored(): void
    {
        [$invoiceId, $lineId] = $this->invoice('10.000', '20.000', discount: '20.000'); // net 180 -> 18 per unit

        $response = $this->postReturn($invoiceId, $lineId, '4.000', clientPrice: '999.000')->assertCreated();

        $response->assertJsonPath('data.total_amount', 72);
        $this->inTenant($this->tenant, function () use ($lineId): void {
            $line = ReturnItem::query()->sole();
            $this->assertSame('18.000', (string) $line->unit_price);
            $this->assertSame('72.000', (string) $line->total_price);
            $this->assertSame($lineId, (int) $line->invoice_item_id);
            $this->assertSame($this->itemId, (int) $line->item_id);
        });
        $this->assertSame('108.000', $this->customerBalance()); // 180 - 72
    }

    public function test_quantity_is_limited_by_what_is_left_and_the_refunds_of_a_line_add_up_exactly(): void
    {
        [$invoiceId, $lineId] = $this->invoice('10.000', '20.000', discount: '20.000');

        $this->postReturn($invoiceId, $lineId, '10.001')->assertStatus(422)->assertJsonValidationErrors(['items.0.quantity']);
        $this->postReturn($invoiceId, $lineId, '6.000')->assertCreated();
        $this->postReturn($invoiceId, $lineId, '5.000')->assertStatus(422)->assertJsonValidationErrors(['items.0.quantity']);
        $this->postReturn($invoiceId, $lineId, '4.000')->assertCreated();
        $this->postReturn($invoiceId, $lineId, '0.001')->assertStatus(422);

        $this->assertSame('0.000', $this->customerBalance()); // refunded exactly the 180 paid on credit
        $this->assertSame('100.000', $this->stock());
    }

    public function test_the_same_line_twice_in_one_request_is_rejected(): void
    {
        [$invoiceId, $lineId] = $this->invoice('10.000', '20.000');

        $this->postJson('/api/v1/returns', [
            'return_type' => 'sales_return', 'customer_id' => $this->customerId, 'invoice_id' => $invoiceId,
            'return_date' => now()->toDateString(),
            'items' => [
                ['invoice_item_id' => $lineId, 'quantity' => '6.000'],
                ['invoice_item_id' => $lineId, 'quantity' => '6.000'],
            ],
        ], $this->headers)->assertStatus(422);

        $this->assertSame('90.000', $this->stock());
    }

    public function test_wrong_item_wrong_line_and_wrong_customer_are_rejected(): void
    {
        [$invoiceId, $lineId] = $this->invoice('10.000', '20.000');
        [, $otherLineId] = $this->invoice('5.000', '20.000');
        $otherCustomer = $this->inTenant($this->tenant, fn (): int => (int) Customer::create(['name' => 'آخر', 'phone' => '01000007004', 'price_tier' => 'retail', 'is_active' => true])->id);

        $this->postReturn($invoiceId, $lineId, '1.000', itemId: $this->otherItemId)
            ->assertStatus(422)->assertJsonValidationErrors(['items.0.item_id']);
        $this->postReturn($invoiceId, $otherLineId, '1.000')
            ->assertStatus(422)->assertJsonValidationErrors(['items.0.invoice_item_id']);
        $this->postReturn($invoiceId, $lineId, '1.000', customerId: $otherCustomer)
            ->assertStatus(422)->assertJsonValidationErrors(['customer_id']);

        $this->inTenant($this->tenant, fn () => $this->assertSame(0, ReturnItem::query()->count()));
    }

    public function test_return_without_invoice_needs_its_own_permission(): void
    {
        $payload = [
            'return_type' => 'sales_return', 'customer_id' => $this->customerId, 'return_date' => now()->toDateString(),
            'items' => [['item_id' => $this->itemId, 'quantity' => '1.000', 'unit_price' => '20.000']],
        ];

        $manager = $this->createTenantUser($this->tenant, null, ['returns.manage']);
        $this->postJson('/api/v1/returns', $payload, $this->tenantHeaders($this->tenant, $manager))->assertStatus(403);

        $allowed = $this->createTenantUser($this->tenant, null, ['returns.manage', 'returns.without_invoice']);
        $this->postJson('/api/v1/returns', $payload, $this->tenantHeaders($this->tenant, $allowed))->assertCreated();

        // Admin holds it by default; and a user with returns.manage still returns against an invoice.
        $this->postJson('/api/v1/returns', $payload, $this->headers)->assertCreated();
        [$invoiceId, $lineId] = $this->invoice('3.000', '20.000');
        $this->postJson('/api/v1/returns', [
            'return_type' => 'sales_return', 'customer_id' => $this->customerId, 'invoice_id' => $invoiceId,
            'return_date' => now()->toDateString(), 'items' => [['invoice_item_id' => $lineId, 'quantity' => '1.000']],
        ], $this->tenantHeaders($this->tenant, $manager))->assertCreated();

        $this->postJson('/api/v1/returns', $payload, $this->tenantGuestHeaders($this->tenant))->assertStatus(401);
    }

    public function test_invoice_of_another_branch_is_not_found(): void
    {
        $storeB = $this->inTenant($this->tenant, function (): int {
            $b = (int) Store::query()->create(['name' => 'فرع ب', 'code' => 'BR-B', 'type' => 'retail', 'is_main' => false, 'is_active' => true])->id;
            StoreStock::create(['store_id' => $b, 'item_id' => $this->itemId, 'quantity' => '50.000']);

            return $b;
        });
        [$invoiceId, $lineId] = $this->invoice('5.000', '20.000', storeId: $storeB);

        $this->postReturn($invoiceId, $lineId, '1.000')->assertNotFound();
        $this->getJson('/api/v1/return-sources/sales_return/'.$invoiceId, $this->headers)->assertNotFound();
        $this->getJson('/api/v1/return-sources/sales_return', $this->headers)->assertOk()->assertJsonCount(0, 'data');
        $this->inTenant($this->tenant, fn () => $this->assertSame(0, ReturnItem::query()->count()));
    }

    public function test_deleting_a_return_frees_its_quantity_again(): void
    {
        [$invoiceId, $lineId] = $this->invoice('10.000', '20.000');

        $returnId = (int) $this->postReturn($invoiceId, $lineId, '10.000')->assertCreated()->json('data.id');
        $this->postReturn($invoiceId, $lineId, '1.000')->assertStatus(422);

        $this->deleteJson('/api/v1/returns/'.$returnId, [], $this->headers)->assertOk();

        $this->postReturn($invoiceId, $lineId, '10.000')->assertCreated();
    }

    public function test_purchase_return_is_priced_from_the_purchase_line_net_of_discount(): void
    {
        $purchaseId = $this->purchase('10.000', '100.000', discount: '50.000'); // net 950 -> 95 per unit
        $lineId = (int) $this->inTenant($this->tenant, fn () => PurchaseItem::query()->where('purchase_id', $purchaseId)->value('id'));

        $post = fn (string $qty, ?int $supplier = null) => $this->postJson('/api/v1/returns', [
            'return_type' => 'purchase_return', 'supplier_id' => $supplier ?? $this->supplierId, 'purchase_id' => $purchaseId,
            'return_date' => now()->toDateString(), 'refund_amount' => '0',
            'items' => [['purchase_item_id' => $lineId, 'quantity' => $qty, 'unit_price' => '1.000']],
        ], $this->headers);

        $post('4.000')->assertCreated()->assertJsonPath('data.total_amount', 380);
        $post('6.001')->assertStatus(422)->assertJsonValidationErrors(['items.0.quantity']);
        $post('1.000', 999999)->assertStatus(422);
        $post('6.000')->assertCreated();

        $this->inTenant($this->tenant, function () use ($lineId): void {
            $this->assertSame('950.000', bcadd((string) ReturnItem::query()->where('purchase_item_id', $lineId)->sum('total_price'), '0', 3));
            $this->assertSame('0.000', (string) Supplier::findOrFail($this->supplierId)->current_balance);
        });
    }

    public function test_return_sources_endpoints_list_lines_with_remaining_quantity(): void
    {
        [$invoiceId, $lineId] = $this->invoice('10.000', '20.000', discount: '20.000');
        $this->postReturn($invoiceId, $lineId, '3.000')->assertCreated();

        $this->getJson('/api/v1/return-sources/sales_return?search=INV', $this->headers)
            ->assertOk()->assertJsonPath('data.0.id', $invoiceId)->assertJsonPath('data.0.party_id', $this->customerId);

        $this->getJson('/api/v1/return-sources/sales_return/'.$invoiceId, $this->headers)
            ->assertOk()
            ->assertJsonPath('data.lines.0.id', $lineId)
            ->assertJsonPath('data.lines.0.returned_quantity', '3.000')
            ->assertJsonPath('data.lines.0.remaining_quantity', '7.000')
            ->assertJsonPath('data.lines.0.unit_price', '18.000');

        $this->getJson('/api/v1/return-sources/bogus', $this->headers)->assertNotFound();
        $this->getJson('/api/v1/return-sources/sales_return', $this->tenantGuestHeaders($this->tenant))->assertStatus(401);
        $nobody = $this->createTenantUser($this->tenant, null, ['items.view']);
        $this->getJson('/api/v1/return-sources/sales_return', $this->tenantHeaders($this->tenant, $nobody))->assertStatus(403);
    }

    public function test_source_line_links_cannot_be_injected_into_a_return_without_a_source(): void
    {
        [, $lineId] = $this->invoice('10.000', '20.000');

        $this->postJson('/api/v1/returns', [
            'return_type' => 'sales_return', 'customer_id' => $this->customerId, 'return_date' => now()->toDateString(),
            'items' => [['item_id' => $this->itemId, 'quantity' => '1.000', 'unit_price' => '20.000', 'invoice_item_id' => $lineId]],
        ], $this->headers)->assertStatus(422)->assertJsonValidationErrors(['items.0.invoice_item_id']);

        $this->postJson('/api/v1/returns', [
            'return_type' => 'purchase_return', 'supplier_id' => $this->supplierId, 'return_date' => now()->toDateString(),
            'items' => [['item_id' => $this->itemId, 'quantity' => '1.000', 'unit_price' => '20.000', 'purchase_item_id' => 1]],
        ], $this->headers)->assertStatus(422)->assertJsonValidationErrors(['items.0.purchase_item_id']);

        // The other kind of link never applies to the type, even against a source.
        [$invoiceId, $lineId2] = $this->invoice('4.000', '20.000');
        $this->postJson('/api/v1/returns', [
            'return_type' => 'sales_return', 'customer_id' => $this->customerId, 'invoice_id' => $invoiceId, 'return_date' => now()->toDateString(),
            'items' => [['invoice_item_id' => $lineId2, 'purchase_item_id' => 1, 'quantity' => '1.000']],
        ], $this->headers)->assertStatus(422)->assertJsonValidationErrors(['items.0.purchase_item_id']);

        $this->inTenant($this->tenant, fn () => $this->assertSame(0, ReturnItem::query()->count()));
    }

    public function test_a_cancelled_invoice_cannot_be_returned_against(): void
    {
        [$invoiceId, $lineId] = $this->invoice('5.000', '20.000');
        $this->postJson('/api/v1/invoices/'.$invoiceId.'/cancel', ['reason' => 'test'], $this->headers)->assertOk();

        $this->postReturn($invoiceId, $lineId, '1.000')->assertStatus(422)->assertJsonValidationErrors(['invoice_id']);
        $this->getJson('/api/v1/return-sources/sales_return', $this->headers)->assertOk()->assertJsonCount(0, 'data');
    }

    public function test_an_invoice_or_purchase_with_returns_cannot_be_cancelled_or_deleted(): void
    {
        [$invoiceId, $lineId] = $this->invoice('5.000', '20.000');
        $this->postReturn($invoiceId, $lineId, '2.000')->assertCreated();

        $this->postJson('/api/v1/invoices/'.$invoiceId.'/cancel', ['reason' => 'test'], $this->headers)
            ->assertStatus(422)->assertJsonValidationErrors(['invoice_id']);
        $this->inTenant($this->tenant, fn () => $this->assertSame('confirmed', (string) Invoice::findOrFail($invoiceId)->status));

        $purchaseId = $this->purchase('10.000', '100.000', discount: '0.000');
        $this->purchaseReturn($purchaseId, '2.000')->assertCreated();
        $this->postJson('/api/v1/purchases/'.$purchaseId.'/cancel', ['reason' => 'test'], $this->headers)
            ->assertStatus(422)->assertJsonValidationErrors(['purchase_id']);
        $this->inTenant($this->tenant, function () use ($purchaseId): void {
            $this->assertSame('confirmed', (string) Purchase::findOrFail($purchaseId)->status);
            $this->expectException(ValidationException::class);
            app(PurchaseService::class)->deletePurchase(Purchase::findOrFail($purchaseId));
        });
    }

    public function test_after_the_returns_are_deleted_the_invoice_can_be_cancelled_again(): void
    {
        [$invoiceId, $lineId] = $this->invoice('5.000', '20.000');
        $returnId = (int) $this->postReturn($invoiceId, $lineId, '2.000')->assertCreated()->json('data.id');
        $this->deleteJson('/api/v1/returns/'.$returnId, [], $this->headers)->assertOk();

        $this->postJson('/api/v1/invoices/'.$invoiceId.'/cancel', ['reason' => 'test'], $this->headers)->assertOk();
    }

    public function test_purchase_of_another_branch_and_wrong_supplier(): void
    {
        $storeB = $this->inTenant($this->tenant, function (): int {
            $b = (int) Store::query()->create(['name' => 'فرع ب', 'code' => 'BR-B', 'type' => 'retail', 'is_main' => false, 'is_active' => true])->id;
            StoreStock::create(['store_id' => $b, 'item_id' => $this->itemId, 'quantity' => '0.000']);

            return $b;
        });
        $purchaseB = $this->purchase('5.000', '100.000', discount: '0.000', storeId: $storeB);

        $this->purchaseReturn($purchaseB, '1.000')->assertNotFound();
        $this->getJson('/api/v1/return-sources/purchase_return/'.$purchaseB, $this->headers)->assertNotFound();
        $this->getJson('/api/v1/return-sources/purchase_return', $this->headers)->assertOk()->assertJsonCount(0, 'data');

        $purchaseA = $this->purchase('5.000', '100.000', discount: '0.000');
        $other = $this->inTenant($this->tenant, fn (): int => (int) Supplier::create(['name' => 'مورد آخر', 'phone' => '01234567891', 'current_balance' => '0.000', 'is_active' => true])->id);
        $this->purchaseReturn($purchaseA, '1.000', $other)->assertStatus(422)->assertJsonValidationErrors(['supplier_id']);
    }

    public function test_second_line_failing_rolls_the_whole_purchase_return_back(): void
    {
        $purchaseId = $this->inTenant($this->tenant, function (): int {
            $this->actingAs(User::query()->orderBy('id')->firstOrFail());

            return (int) app(PurchaseService::class)->createPurchase([
                'supplier_id' => $this->supplierId, 'store_id' => $this->storeId, 'paid_amount' => '0.000',
                'items' => [
                    ['item_id' => $this->itemId, 'quantity' => '5.000', 'cost_price' => '100.000'],
                    ['item_id' => $this->otherItemId, 'quantity' => '5.000', 'cost_price' => '100.000'],
                ],
            ])->id;
        });
        $lines = $this->inTenant($this->tenant, fn (): array => PurchaseItem::query()->where('purchase_id', $purchaseId)->orderBy('id')->pluck('id')->all());
        // The second item was sold off afterwards: returning it to the supplier cannot be fulfilled.
        $this->inTenant($this->tenant, function (): void {
            Item::whereKey($this->otherItemId)->update(['current_stock' => '0.000']);
            StoreStock::where('item_id', $this->otherItemId)->update(['quantity' => '0.000']);
        });
        $stockBefore = $this->stock();
        $balanceBefore = $this->inTenant($this->tenant, fn (): string => (string) Supplier::findOrFail($this->supplierId)->current_balance);

        $this->postJson('/api/v1/returns', [
            'return_type' => 'purchase_return', 'supplier_id' => $this->supplierId, 'purchase_id' => $purchaseId,
            'return_date' => now()->toDateString(),
            'items' => [
                ['purchase_item_id' => $lines[0], 'quantity' => '5.000'],
                ['purchase_item_id' => $lines[1], 'quantity' => '5.000'],
            ],
        ], $this->headers)->assertStatus(422);

        $this->assertSame($stockBefore, $this->stock());
        $this->inTenant($this->tenant, function () use ($balanceBefore): void {
            $this->assertSame(0, ReturnItem::query()->count());
            $this->assertSame($balanceBefore, (string) Supplier::findOrFail($this->supplierId)->current_balance);
        });
    }

    private function purchaseReturn(int $purchaseId, string $qty, ?int $supplierId = null): TestResponse
    {
        $lineId = (int) $this->inTenant($this->tenant, fn () => PurchaseItem::query()->where('purchase_id', $purchaseId)->orderBy('id')->value('id'));

        return $this->postJson('/api/v1/returns', [
            'return_type' => 'purchase_return', 'supplier_id' => $supplierId ?? $this->supplierId, 'purchase_id' => $purchaseId,
            'return_date' => now()->toDateString(), 'items' => [['purchase_item_id' => $lineId, 'quantity' => $qty]],
        ], $this->headers);
    }

    private function postReturn(int $invoiceId, int $lineId, string $qty, ?string $clientPrice = null, ?int $itemId = null, ?int $customerId = null): TestResponse
    {
        $line = ['invoice_item_id' => $lineId, 'quantity' => $qty];
        if ($clientPrice !== null) {
            $line['unit_price'] = $clientPrice;
        }
        if ($itemId !== null) {
            $line['item_id'] = $itemId;
        }

        return $this->postJson('/api/v1/returns', [
            'return_type' => 'sales_return', 'customer_id' => $customerId ?? $this->customerId, 'invoice_id' => $invoiceId,
            'return_date' => now()->toDateString(), 'items' => [$line],
        ], $this->headers);
    }

    /**
     * @return array{0: int, 1: int} invoice id, its line id
     */
    private function invoice(string $qty, string $price, string $discount = '0.000', ?int $storeId = null): array
    {
        return $this->inTenant($this->tenant, function () use ($qty, $price, $discount, $storeId): array {
            $this->actingAs(User::query()->orderBy('id')->firstOrFail());
            $invoice = app(InvoiceService::class)->confirmInvoice([
                'customer_id' => $this->customerId,
                'store_id' => $storeId ?? $this->storeId,
                'payment_type' => 'credit', 'payment_method' => 'cash', 'paid_amount' => '0.000',
                'discount_type' => 'fixed', 'discount_value' => $discount,
                'items' => [['item_id' => $this->itemId, 'quantity' => $qty, 'unit_price' => $price]],
            ]);

            return [(int) $invoice->id, (int) InvoiceItem::query()->where('invoice_id', $invoice->id)->value('id')];
        });
    }

    private function purchase(string $qty, string $cost, string $discount, ?int $storeId = null): int
    {
        return $this->inTenant($this->tenant, function () use ($qty, $cost, $discount, $storeId): int {
            $this->actingAs(User::query()->orderBy('id')->firstOrFail());
            $purchase = app(PurchaseService::class)->createPurchase([
                'supplier_id' => $this->supplierId, 'store_id' => $storeId ?? $this->storeId, 'paid_amount' => '0.000',
                'discount_amount' => $discount,
                'items' => [['item_id' => $this->itemId, 'quantity' => $qty, 'cost_price' => $cost]],
            ]);

            return (int) $purchase->id;
        });
    }

    private function makeItem(string $code, int $storeId): int
    {
        $item = Item::create([
            'name' => 'صنف '.$code, 'code' => $code, 'category' => 'coffee_beans', 'unit' => 'كجم',
            'cost_price' => '10.000', 'weighted_avg_cost' => '10.000', 'selling_price' => '20.000',
            'price_retail' => '20.000', 'price_wholesale' => '20.000',
            'current_stock' => '100.000', 'min_stock_level' => '1.000', 'is_active' => true,
        ]);
        StoreStock::create(['store_id' => $storeId, 'item_id' => $item->id, 'quantity' => '100.000']);

        return (int) $item->id;
    }

    private function customerBalance(): string
    {
        return $this->inTenant($this->tenant, fn (): string => (string) Customer::findOrFail($this->customerId)->current_balance);
    }

    private function stock(): string
    {
        return $this->inTenant($this->tenant, fn (): string => (string) Item::findOrFail($this->itemId)->current_stock);
    }
}
