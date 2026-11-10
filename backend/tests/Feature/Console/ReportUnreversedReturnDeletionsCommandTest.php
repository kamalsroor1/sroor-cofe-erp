<?php

declare(strict_types=1);

namespace Tests\Feature\Console;

use App\Models\Customer;
use App\Models\Item;
use App\Models\ReturnDocument;
use App\Models\StockMovement;
use App\Models\StoreStock;
use App\Models\Supplier;
use App\Models\Tenant;
use Illuminate\Support\Facades\Artisan;
use Tests\TenantTestCase;

/**
 * BE-02 follow-up: returns:report-unreversed-deletions finds returns that were soft-deleted before
 * DeleteReturnAction reversed anything and (with --fix) reverses each exactly once.
 */
final class ReportUnreversedReturnDeletionsCommandTest extends TenantTestCase
{
    private const COMMAND = 'returns:report-unreversed-deletions';

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
            $customer = Customer::create(['name' => 'عميل', 'phone' => '01000007103', 'price_tier' => 'retail', 'is_active' => true]);
            $supplier = Supplier::create(['name' => 'مورد', 'phone' => '01234567890', 'current_balance' => '0.000', 'is_active' => true]);
            $item = Item::create([
                'name' => 'بن', 'code' => 'BN-9', 'category' => 'coffee_beans', 'unit' => 'كجم',
                'cost_price' => '350.000', 'weighted_avg_cost' => '350.000', 'selling_price' => '480.000',
                'price_retail' => '480.000', 'price_wholesale' => '440.000',
                'current_stock' => '100.000', 'min_stock_level' => '15.000', 'is_active' => true,
            ]);
            StoreStock::create(['store_id' => $this->storeId, 'item_id' => $item->id, 'quantity' => '100.000']);

            return [$customer->id, $supplier->id, $item->id];
        });
    }

    public function test_dry_run_lists_the_legacy_deletion_but_changes_nothing(): void
    {
        $id = $this->legacyDeletedSalesReturn('7.250', '20.000');
        $number = $this->inTenant($this->tenant, fn (): string => (string) ReturnDocument::withTrashed()->findOrFail($id)->return_number);
        $this->assertSame('107.250', $this->stock());
        $this->assertSame('-145.000', $this->customerBalance());

        $this->assertSame(0, Artisan::call(self::COMMAND, ['--tenant' => $this->tenant->id]));
        $output = Artisan::output();

        $this->assertStringContainsString($number, $output);
        $this->assertStringContainsString('-7.250', $output, 'The stock effect of the reversal is listed.');
        $this->assertSame('107.250', $this->stock());
        $this->assertSame('107.250', $this->storeStock());
        $this->assertSame('-145.000', $this->customerBalance());
        $this->assertSame(0, $this->reversalMovements());
        $this->inTenant($this->tenant, fn () => $this->assertSoftDeleted('returns', ['id' => $id]));
    }

    public function test_fix_reverses_a_sales_return_exactly_once(): void
    {
        $id = $this->legacyDeletedSalesReturn('7.250', '20.000');

        $this->assertSame(0, Artisan::call(self::COMMAND, ['--tenant' => $this->tenant->id, '--fix' => true]));

        $this->assertSame('100.000', $this->stock());
        $this->assertSame('100.000', $this->storeStock());
        $this->assertSame('0.000', $this->customerBalance());
        $this->assertSame(1, $this->reversalMovements());
        $this->inTenant($this->tenant, function () use ($id): void {
            $movement = StockMovement::query()->where('movement_type', 'sales_return_cancel_out')->sole();
            $this->assertSame('7.250', (string) $movement->quantity);
            $this->assertSame($id, (int) $movement->source_id);
            $this->assertSame($this->storeId, (int) $movement->store_id);
        });

        // A second run finds nothing to do.
        $this->assertSame(0, Artisan::call(self::COMMAND, ['--tenant' => $this->tenant->id, '--fix' => true]));
        $this->assertSame('100.000', $this->stock());
        $this->assertSame('100.000', $this->storeStock());
        $this->assertSame(1, $this->reversalMovements());
    }

    public function test_fix_reverses_a_purchase_return_including_the_average_cost(): void
    {
        $id = (int) $this->postJson('/api/v1/returns', [
            'return_type' => 'purchase_return', 'supplier_id' => $this->supplierId, 'return_date' => now()->toDateString(),
            'items' => [['item_id' => $this->itemId, 'quantity' => '10.000', 'unit_price' => '400.000']],
        ], $this->headers)->assertCreated()->json('data.id');
        $this->inTenant($this->tenant, fn () => ReturnDocument::query()->whereKey($id)->delete());
        $this->assertSame('90.000', $this->stock());

        Artisan::call(self::COMMAND, ['--tenant' => $this->tenant->id, '--fix' => true]);

        $this->assertSame('100.000', $this->stock());
        $this->assertSame('100.000', $this->storeStock());
        $this->assertSame('0.000', $this->supplierBalance());
        $this->assertSame('350.000', $this->wac());
        $this->assertSame(1, $this->reversalMovements());
    }

    public function test_returns_already_reversed_when_deleted_are_untouched(): void
    {
        $id = (int) $this->postJson('/api/v1/returns', [
            'return_type' => 'sales_return', 'customer_id' => $this->customerId, 'return_date' => now()->toDateString(),
            'items' => [['item_id' => $this->itemId, 'quantity' => '5.000', 'unit_price' => '20.000']],
        ], $this->headers)->assertCreated()->json('data.id');
        $this->deleteJson('/api/v1/returns/'.$id, [], $this->headers)->assertOk();
        $number = $this->inTenant($this->tenant, fn (): string => (string) ReturnDocument::withTrashed()->findOrFail($id)->return_number);
        $this->assertSame('100.000', $this->stock());
        $this->assertSame(1, $this->reversalMovements());

        $this->assertSame(0, Artisan::call(self::COMMAND, ['--tenant' => $this->tenant->id, '--fix' => true]));

        $this->assertStringNotContainsString($number, Artisan::output());
        $this->assertSame('100.000', $this->stock());
        $this->assertSame('100.000', $this->storeStock());
        $this->assertSame(1, $this->reversalMovements());
    }

    public function test_live_returns_are_not_listed_or_touched(): void
    {
        $this->postJson('/api/v1/returns', [
            'return_type' => 'sales_return', 'customer_id' => $this->customerId, 'return_date' => now()->toDateString(),
            'items' => [['item_id' => $this->itemId, 'quantity' => '5.000', 'unit_price' => '20.000']],
        ], $this->headers)->assertCreated();

        Artisan::call(self::COMMAND, ['--tenant' => $this->tenant->id, '--fix' => true]);

        $this->assertSame('105.000', $this->stock());
        $this->assertSame(0, $this->reversalMovements());
    }

    public function test_a_return_that_would_make_stock_negative_is_skipped_and_the_others_still_reversed(): void
    {
        $blocked = $this->legacyDeletedSalesReturn('10.000', '20.000');
        $this->inTenant($this->tenant, function (): void {
            Item::whereKey($this->itemId)->update(['current_stock' => '4.000']);
            StoreStock::where('item_id', $this->itemId)->update(['quantity' => '4.000']);
        });
        // The purchase return puts stock back, so it is reversible regardless.
        $purchaseId = (int) $this->postJson('/api/v1/returns', [
            'return_type' => 'purchase_return', 'supplier_id' => $this->supplierId, 'return_date' => now()->toDateString(),
            'items' => [['item_id' => $this->itemId, 'quantity' => '2.000', 'unit_price' => '400.000']],
        ], $this->headers)->assertCreated()->json('data.id');
        $this->inTenant($this->tenant, fn () => ReturnDocument::query()->whereKey($purchaseId)->delete());
        $this->assertSame('2.000', $this->stock());

        // Dry-run flags the blocked one without writing.
        Artisan::call(self::COMMAND, ['--tenant' => $this->tenant->id]);
        $dryRun = Artisan::output();
        $this->assertStringContainsString(explode(':', (string) __('console.unreversed_return_deletions.status_blocked'))[0], $dryRun, 'The blocked return is flagged in the dry run.');
        $this->assertStringContainsString((string) __('console.unreversed_return_deletions.status_would_reverse'), $dryRun);
        $this->assertSame('2.000', $this->stock());
        $this->assertSame(0, $this->reversalMovements());

        $this->assertSame(0, Artisan::call(self::COMMAND, ['--tenant' => $this->tenant->id, '--fix' => true]));

        // Purchase return reversed (+2), sales return skipped (would need -10 from 4).
        $this->assertSame('4.000', $this->stock());
        $this->assertSame('4.000', $this->storeStock());
        $this->assertSame(1, $this->reversalMovements());
        $this->inTenant($this->tenant, function () use ($blocked): void {
            $this->assertSame(0, StockMovement::query()->where('source_id', $blocked)->where('movement_type', 'sales_return_cancel_out')->count());
            $this->assertSoftDeleted('returns', ['id' => $blocked]);
        });
    }

    public function test_unknown_tenant_fails(): void
    {
        $this->assertSame(1, Artisan::call(self::COMMAND, ['--tenant' => 'nope']));
    }

    /** A sales return as the old DeleteReturnAction left it: soft-deleted, nothing reversed. */
    private function legacyDeletedSalesReturn(string $qty, string $price): int
    {
        $id = (int) $this->postJson('/api/v1/returns', [
            'return_type' => 'sales_return', 'customer_id' => $this->customerId, 'return_date' => now()->toDateString(),
            'items' => [['item_id' => $this->itemId, 'quantity' => $qty, 'unit_price' => $price]],
        ], $this->headers)->assertCreated()->json('data.id');

        $this->inTenant($this->tenant, fn () => ReturnDocument::query()->whereKey($id)->delete());

        return $id;
    }

    private function reversalMovements(): int
    {
        return $this->inTenant($this->tenant, fn (): int => StockMovement::query()
            ->whereIn('movement_type', ['sales_return_cancel_out', 'purchase_return_cancel_in'])->count());
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
