<?php

declare(strict_types=1);

namespace Tests\Feature\Stock;

use App\Actions\Items\CreateItemAction;
use App\DTOs\Items\ItemDTO;
use App\Exceptions\Stock\InsufficientStockException;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Item;
use App\Models\Setting;
use App\Models\StockMovement;
use App\Models\Store;
use App\Models\StoreStock;
use App\Models\Tenant;
use App\Services\InvoiceService;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Tests\TenantTestCase;

/**
 * BE-04: the (store, item) grid of store_stocks is always complete, and selling from a store
 * with a missing row is checked against zero instead of silently skipping the branch check.
 */
final class StoreStockCompletenessTest extends TenantTestCase
{
    private Tenant $tenant;

    /** @var array<string, string> */
    private array $adminHeaders;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = $this->createTenant();
        $this->tenantAdmin($this->tenant);
        $this->adminHeaders = $this->tenantHeaders($this->tenant);
    }

    private function makeItem(string $code, string $stock = '100.000'): Item
    {
        return Item::create([
            'code' => $code,
            'name' => 'صنف '.$code,
            'unit' => 'قطعة',
            'current_stock' => $stock,
            'cost_price' => '10.000',
            'weighted_avg_cost' => '10.000',
            'selling_price' => '20.000',
            'min_stock_level' => '5.000',
            'is_active' => true,
        ]);
    }

    private function makeBranch(string $code = 'BR-01'): Store
    {
        return Store::create([
            'name' => 'فرع '.$code, 'code' => $code, 'type' => 'retail_shop',
            'is_active' => true, 'is_main' => false,
        ]);
    }

    private function sell(Item $item, Store $store, string $qty): Invoice
    {
        $customer = Customer::create(['name' => 'عميل', 'is_active' => true]);

        return app(InvoiceService::class)->confirmInvoice([
            'customer_id' => $customer->id,
            'payment_type' => 'cash',
            'store_id' => $store->id,
            'items' => [['item_id' => $item->id, 'quantity' => $qty, 'unit_price' => '20.000']],
        ]);
    }

    public function test_creating_a_store_via_api_seeds_zero_rows_for_every_item(): void
    {
        $this->inTenant($this->tenant, function (): void {
            $this->makeItem('A');
            $this->makeItem('B');
        });

        $response = $this->postJson('/api/v1/stores', [
            'name' => 'فرع جديد', 'code' => 'NEW-01', 'type' => 'retail_shop', 'is_active' => true,
        ], $this->adminHeaders);
        $response->assertStatus(201);
        $storeId = (int) $response->json('data.id');

        $this->inTenant($this->tenant, function () use ($storeId): void {
            $rows = StoreStock::where('store_id', $storeId)->get();
            $this->assertCount(2, $rows);
            $this->assertSame(['0.000', '0.000'], $rows->pluck('quantity')->map(fn ($q) => (string) $q)->all());
        });
    }

    public function test_unauthenticated_or_unauthorized_cannot_create_a_store(): void
    {
        $this->postJson('/api/v1/stores', ['name' => 'x', 'code' => 'X', 'type' => 'retail_shop'], $this->tenantGuestHeaders($this->tenant))
            ->assertStatus(401);

        $user = $this->createTenantUser($this->tenant, attributes: ['name' => 'بدون صلاحيات']);
        $this->postJson('/api/v1/stores', ['name' => 'x', 'code' => 'X', 'type' => 'retail_shop'], $this->tenantHeaders($this->tenant, $user))
            ->assertStatus(403);
    }

    public function test_creating_an_item_seeds_zero_rows_for_every_store_only_in_its_tenant(): void
    {
        $other = $this->createTenant();

        $itemId = $this->inTenant($this->tenant, function (): int {
            $this->makeBranch('BR-A');
            $dto = ItemDTO::fromArray([
                'name' => 'صنف جديد', 'unit' => 'قطعة', 'cost_price' => '5.000',
                'selling_price' => '9.000', 'min_stock_level' => '3.000', 'is_active' => true,
            ]);

            $item = app(CreateItemAction::class)->execute($dto);
            $this->assertSame(Store::count(), StoreStock::where('item_id', $item->id)->count());
            $this->assertSame(0, StoreStock::where('item_id', $item->id)->where('quantity', '!=', 0)->count());

            return (int) $item->id;
        });

        $this->inTenant($other, fn () => $this->assertSame(0, StoreStock::where('item_id', $itemId)->count()));
    }

    public function test_sale_from_branch_without_row_is_rejected_and_nothing_persists(): void
    {
        $this->inTenant($this->tenant, function (): void {
            $item = $this->makeItem('S1');
            $branch = $this->makeBranch();
            StoreStock::where('store_id', $branch->id)->forceDelete(); // legacy: branch with no rows

            try {
                $this->sell($item, $branch, '30.000');
                $this->fail('Expected insufficient stock');
            } catch (InsufficientStockException $e) {
                $this->assertSame(422, $e->status());
                $this->assertStringContainsString($item->name, $e->getMessage());
            }

            $this->assertSame('100.000', (string) $item->fresh()->current_stock);
            $this->assertSame(0, Invoice::count());
            $this->assertSame(0, StockMovement::where('item_id', $item->id)->count());
            $this->assertSame(0, StoreStock::where('store_id', $branch->id)->count());
        });
    }

    public function test_sale_from_branch_without_row_goes_negative_when_allowed(): void
    {
        $this->inTenant($this->tenant, function (): void {
            Setting::set('allow_negative_stock', '1');
            $item = $this->makeItem('S2');
            $branch = $this->makeBranch('BR-02');
            StoreStock::where('store_id', $branch->id)->forceDelete();

            $this->sell($item, $branch, '30.000');

            $this->assertSame('-30.000', (string) StoreStock::where('store_id', $branch->id)->where('item_id', $item->id)->firstOrFail()->quantity);
            $this->assertSame('70.000', (string) $item->fresh()->current_stock);
        });
    }

    public function test_sale_within_branch_stock_still_works(): void
    {
        $this->inTenant($this->tenant, function (): void {
            $item = $this->makeItem('S3');
            $branch = $this->makeBranch('BR-03');
            StoreStock::create(['store_id' => $branch->id, 'item_id' => $item->id, 'quantity' => '40.000']);

            $this->sell($item, $branch, '30.000');
            $this->assertSame('10.000', (string) StoreStock::where('store_id', $branch->id)->where('item_id', $item->id)->firstOrFail()->quantity);

            $this->expectException(InsufficientStockException::class);
            $this->sell($item, $branch, '11.000');
        });
    }

    public function test_backfill_migration_adds_only_missing_rows_and_is_idempotent(): void
    {
        $this->inTenant($this->tenant, function (): void {
            $a = $this->makeItem('M1');
            $b = $this->makeItem('M2');
            $branch = $this->makeBranch('BR-M');
            $main = Store::where('is_main', true)->firstOrFail();

            StoreStock::query()->forceDelete();
            DB::table('store_stocks')->insert([
                'store_id' => $main->id, 'item_id' => $a->id, 'quantity' => '7.000', 'min_stock' => '1.000',
                'created_at' => now(), 'updated_at' => now(),
            ]);

            $migration = require database_path('migrations/tenant/2026_11_10_100000_backfill_missing_store_stocks.php');
            $this->assertInstanceOf(Migration::class, $migration);
            $migration->up();
            $migration->up();

            $this->assertSame(4, StoreStock::count());
            // 7 existing + 93 residual of current_stock 100 goes to the main row
            $this->assertSame('100.000', (string) StoreStock::where('store_id', $main->id)->where('item_id', $a->id)->firstOrFail()->quantity);
            $this->assertSame('0.000', (string) StoreStock::where('store_id', $branch->id)->where('item_id', $b->id)->firstOrFail()->quantity);
        });
    }

    private function runBackfill(): void
    {
        $migration = require database_path('migrations/tenant/2026_11_10_100000_backfill_missing_store_stocks.php');
        $migration->up();
    }

    private function qty(Store $store, Item $item): string
    {
        return (string) StoreStock::where('store_id', $store->id)->where('item_id', $item->id)->firstOrFail()->quantity;
    }

    public function test_backfill_assigns_existing_stock_residual_to_main_store_and_is_idempotent(): void
    {
        $this->inTenant($this->tenant, function (): void {
            $item = $this->makeItem('R1', '10.000');
            $branch = $this->makeBranch('BR-R');
            $main = Store::where('is_main', true)->firstOrFail();
            StoreStock::query()->forceDelete();

            $this->runBackfill();
            $this->assertSame('10.000', $this->qty($main, $item));
            $this->assertSame('0.000', $this->qty($branch, $item));

            $this->runBackfill();
            $this->assertSame('10.000', $this->qty($main, $item));
            $this->assertSame('0.000', $this->qty($branch, $item));
        });
    }

    public function test_backfill_gives_main_the_remainder_of_a_partial_allocation(): void
    {
        $this->inTenant($this->tenant, function (): void {
            $item = $this->makeItem('R2', '10.000');
            $branch = $this->makeBranch('BR-P');
            $main = Store::where('is_main', true)->firstOrFail();
            StoreStock::query()->forceDelete();
            StoreStock::create(['store_id' => $branch->id, 'item_id' => $item->id, 'quantity' => '4.000']);

            $this->runBackfill();
            $this->assertSame('6.000', $this->qty($main, $item));
            $this->assertSame('4.000', $this->qty($branch, $item));

            $this->runBackfill();
            $this->assertSame('6.000', $this->qty($main, $item));
        });
    }

    public function test_backfill_adds_residual_to_existing_main_row_and_leaves_over_allocated_items(): void
    {
        $this->inTenant($this->tenant, function (): void {
            $a = $this->makeItem('R3', '10.000');
            $b = $this->makeItem('R4', '5.000');
            $main = Store::where('is_main', true)->firstOrFail();
            $branch = $this->makeBranch('BR-O');
            StoreStock::query()->forceDelete();
            StoreStock::create(['store_id' => $main->id, 'item_id' => $a->id, 'quantity' => '3.000']);
            StoreStock::create(['store_id' => $branch->id, 'item_id' => $b->id, 'quantity' => '9.000']);

            $this->runBackfill();
            $this->assertSame('10.000', $this->qty($main, $a));
            $this->assertSame('9.000', $this->qty($branch, $b));
            $this->assertSame('0.000', $this->qty($main, $b));
        });
    }

    public function test_zero_quantity_deduction_with_soft_deleted_row_does_not_hit_unique_index(): void
    {
        $this->inTenant($this->tenant, function (): void {
            $item = $this->makeItem('Z1');
            $branch = $this->makeBranch('BR-Z');
            StoreStock::create(['store_id' => $branch->id, 'item_id' => $item->id, 'quantity' => '0.000'])->delete();

            $this->sell($item, $branch, '0.000');
            $this->assertSame(1, StoreStock::withTrashed()->where('store_id', $branch->id)->where('item_id', $item->id)->count());
        });
    }
}
