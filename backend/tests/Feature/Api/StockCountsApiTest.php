<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Enums\StockCountStatus;
use App\Models\Category;
use App\Models\Item;
use App\Models\StockCount;
use App\Models\StockMovement;
use App\Models\Store;
use App\Models\StoreStock;
use App\Models\Tenant;
use App\Services\DocumentNumberService;
use App\Services\StockService;
use App\Support\TenantClock;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;
use RuntimeException;
use Tests\TenantTestCase;

/**
 * SHOP-02: stocktake sessions (start / count / variance / post / cancel).
 */
class StockCountsApiTest extends TenantTestCase
{
    private Tenant $tenant;

    private int $mainStoreId;

    private int $branchStoreId;

    /** @var array<string, int> */
    private array $items;

    private int $categoryId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = $this->createTenant();
        $this->mainStoreId = (int) $this->tenantStore($this->tenant)->id;

        [$this->branchStoreId, $this->items, $this->categoryId] = $this->inTenant($this->tenant, fn (): array => $this->seedFixtures($this->mainStoreId));
    }

    // ---------------------------------------------------------------- authn / authz

    public function test_guest_gets_401(): void
    {
        $this->getJson('/api/v1/stock-counts', $this->tenantGuestHeaders($this->tenant))->assertStatus(401);
        $this->postJson('/api/v1/stock-counts', [], $this->tenantGuestHeaders($this->tenant))->assertStatus(401);
    }

    public function test_user_without_stock_count_permission_gets_403(): void
    {
        $count = $this->startCount();
        $headers = $this->tenantHeaders($this->tenant, $this->createTenantUser($this->tenant, permissions: ['items.view']));

        $this->getJson('/api/v1/stock-counts', $headers)->assertStatus(403);
        $this->postJson('/api/v1/stock-counts', [], $headers)->assertStatus(403);
        $this->getJson("/api/v1/stock-counts/{$count}", $headers)->assertStatus(403);
        $this->putJson("/api/v1/stock-counts/{$count}/lines", ['lines' => [['item_id' => $this->items['a'], 'counted_qty' => '1']]], $headers)->assertStatus(403);
        $this->postJson("/api/v1/stock-counts/{$count}/post", [], $headers)->assertStatus(403);
        $this->postJson("/api/v1/stock-counts/{$count}/cancel", [], $headers)->assertStatus(403);
    }

    public function test_storekeeper_role_holds_stock_count(): void
    {
        $headers = $this->tenantHeaders($this->tenant, $this->createTenantUser($this->tenant, role: 'storekeeper'));

        $this->getJson('/api/v1/stock-counts', $headers)->assertOk();
    }

    // ---------------------------------------------------------------- start

    public function test_start_snapshots_every_active_item_of_the_active_store(): void
    {
        $response = $this->postJson('/api/v1/stock-counts', ['notes' => 'جرد آخر الشهر'], $this->tenantHeaders($this->tenant))
            ->assertCreated()
            ->assertJsonPath('data.status', 'draft')
            ->assertJsonPath('data.store_id', $this->mainStoreId)
            ->assertJsonPath('data.lines_count', 3);

        $lines = collect($response->json('data.lines'))->keyBy('item_id');
        $this->assertSame('100.000', $lines[$this->items['a']]['system_qty']);
        $this->assertSame('50.000', $lines[$this->items['b']]['system_qty']);
        $this->assertSame('0.000', $lines[$this->items['c']]['system_qty']);
        $this->assertFalse($lines->has($this->items['inactive']));
        $this->assertStringStartsWith('CNT-', (string) $response->json('data.count_number'));
    }

    public function test_start_limited_to_a_category(): void
    {
        $this->postJson('/api/v1/stock-counts', ['category_id' => $this->categoryId], $this->tenantHeaders($this->tenant))
            ->assertCreated()
            ->assertJsonPath('data.lines_count', 1)
            ->assertJsonPath('data.lines.0.item_id', $this->items['a']);
    }

    public function test_only_one_open_count_per_store(): void
    {
        $this->startCount();

        $arResponse = $this->postJson('/api/v1/stock-counts', [], [
            ...$this->tenantHeaders($this->tenant),
            'X-Locale' => 'ar',
        ]);
        $arResponse->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonPath('error_code', 'stock_counts.already_open');
        $this->assertNotSame('stock_counts.already_open', $arResponse->json('message'));
        $this->assertSame(__('stock_counts.already_open', [], 'ar'), $arResponse->json('message'));

        $enResponse = $this->postJson('/api/v1/stock-counts', [], [
            ...$this->tenantHeaders($this->tenant),
            'X-Locale' => 'en',
        ]);
        $enResponse->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonPath('error_code', 'stock_counts.already_open');
        $this->assertNotSame('stock_counts.already_open', $enResponse->json('message'));
        $this->assertSame(__('stock_counts.already_open', [], 'en'), $enResponse->json('message'));

        // Another branch may count at the same time.
        $this->postJson('/api/v1/stock-counts', [], $this->tenantHeaders($this->tenant, store: $this->branchStoreId))
            ->assertCreated()
            ->assertJsonPath('data.store_id', $this->branchStoreId);
    }

    public function test_start_validation_errors(): void
    {
        $this->postJson('/api/v1/stock-counts', ['category_id' => 999999, 'is_blind' => 'maybe'], $this->tenantHeaders($this->tenant))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['category_id', 'is_blind']);
    }

    // ---------------------------------------------------------------- counting

    public function test_save_lines_validation_errors(): void
    {
        $count = $this->startCount();

        $this->putJson("/api/v1/stock-counts/{$count}/lines", [], $this->tenantHeaders($this->tenant))
            ->assertStatus(422)->assertJsonValidationErrors(['lines']);

        $this->putJson("/api/v1/stock-counts/{$count}/lines", ['lines' => [
            ['item_id' => $this->items['a'], 'counted_qty' => '-1'],
            ['item_id' => 999999, 'counted_qty' => '1'],
            ['item_id' => $this->items['b'], 'counted_qty' => '1.2345'],
        ]], $this->tenantHeaders($this->tenant))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['lines.0.counted_qty', 'lines.1.item_id', 'lines.2.counted_qty']);
    }

    public function test_saving_counts_moves_status_to_counting_and_does_not_touch_stock(): void
    {
        $count = $this->startCount();

        $this->saveCounts($count, [$this->items['a'] => '97.5'])
            ->assertOk()
            ->assertJsonPath('data.status', 'counting')
            ->assertJsonPath('data.counted_lines_count', 1);

        $this->assertSame('100.000', $this->storeQty($this->mainStoreId, $this->items['a']));
        $this->assertSame(0, $this->movementCount());

        // Clearing the only count goes back to draft.
        $this->putJson("/api/v1/stock-counts/{$count}/lines", ['lines' => [['item_id' => $this->items['a'], 'counted_qty' => null]]], $this->tenantHeaders($this->tenant))
            ->assertOk()
            ->assertJsonPath('data.status', 'draft');
    }

    public function test_an_item_outside_the_snapshot_is_added_when_counted(): void
    {
        $count = $this->startCountWith(['category_id' => $this->categoryId]);

        $this->saveCounts($count, [$this->items['b'] => '48'])
            ->assertOk()
            ->assertJsonPath('data.lines_count', 2);

        $this->postJson("/api/v1/stock-counts/{$count}/post", [], $this->tenantHeaders($this->tenant))->assertOk();
        $this->assertSame('48.000', $this->storeQty($this->mainStoreId, $this->items['b']));
    }

    // ---------------------------------------------------------------- posting

    public function test_post_applies_surplus_and_shortage_with_movements(): void
    {
        $count = $this->startCount();
        $this->saveCounts($count, [$this->items['a'] => '95', $this->items['b'] => '52.250', $this->items['c'] => '0']);

        $response = $this->postJson("/api/v1/stock-counts/{$count}/post", [], $this->tenantHeaders($this->tenant))
            ->assertOk()
            ->assertJsonPath('data.status', 'posted')
            ->assertJsonPath('data.is_open', false);

        $lines = collect($response->json('data.lines'))->keyBy('item_id');
        $this->assertSame('-5.000', $lines[$this->items['a']]['difference']);
        $this->assertSame('2.250', $lines[$this->items['b']]['difference']);
        $this->assertSame('0.000', $lines[$this->items['c']]['difference']);

        $this->assertSame('95.000', $this->storeQty($this->mainStoreId, $this->items['a']));
        $this->assertSame('52.250', $this->storeQty($this->mainStoreId, $this->items['b']));
        $this->assertSame('0.000', $this->storeQty($this->mainStoreId, $this->items['c']));

        $this->inTenant($this->tenant, function () use ($count): void {
            $this->assertSame('95.000', (string) Item::query()->whereKey($this->items['a'])->value('current_stock'));
            $this->assertSame('52.250', (string) Item::query()->whereKey($this->items['b'])->value('current_stock'));

            $movements = StockMovement::query()->where('source_type', StockCount::class)->where('source_id', $count)->get()->keyBy('item_id');
            $this->assertCount(2, $movements);
            $this->assertSame('stock_count_out', $movements[$this->items['a']]->movement_type);
            $this->assertSame('5.000', (string) $movements[$this->items['a']]->quantity);
            $this->assertSame('100.000', (string) $movements[$this->items['a']]->stock_before);
            $this->assertSame('95.000', (string) $movements[$this->items['a']]->stock_after);
            $this->assertSame('stock_count_in', $movements[$this->items['b']]->movement_type);
            $this->assertSame('2.250', (string) $movements[$this->items['b']]->quantity);
            $this->assertSame($this->mainStoreId, (int) $movements[$this->items['b']]->store_id);
            $this->assertNull(StockCount::query()->whereKey($count)->value('open_store_id'));
        });
    }

    public function test_posting_twice_and_editing_after_posting_are_refused(): void
    {
        $count = $this->startCount();
        $this->saveCounts($count, [$this->items['a'] => '90']);
        $this->postJson("/api/v1/stock-counts/{$count}/post", [], $this->tenantHeaders($this->tenant))->assertOk();

        // INV-235: double-post in Arabic & English.
        $doublePostAr = $this->postJson("/api/v1/stock-counts/{$count}/post", [], [
            ...$this->tenantHeaders($this->tenant),
            'X-Locale' => 'ar',
        ]);
        $doublePostAr->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonPath('error_code', 'stock_counts.already_posted');
        $this->assertNotSame('stock_counts.already_posted', $doublePostAr->json('message'));
        $this->assertSame(__('stock_counts.already_posted', [], 'ar'), $doublePostAr->json('message'));

        $doublePostEn = $this->postJson("/api/v1/stock-counts/{$count}/post", [], [
            ...$this->tenantHeaders($this->tenant),
            'X-Locale' => 'en',
        ]);
        $doublePostEn->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonPath('error_code', 'stock_counts.already_posted');
        $this->assertNotSame('stock_counts.already_posted', $doublePostEn->json('message'));
        $this->assertSame(__('stock_counts.already_posted', [], 'en'), $doublePostEn->json('message'));

        // INV-236: editing lines after posting in Arabic & English.
        $editPostedAr = $this->putJson("/api/v1/stock-counts/{$count}/lines", ['lines' => [['item_id' => $this->items['a'], 'counted_qty' => '1']]], [
            ...$this->tenantHeaders($this->tenant),
            'X-Locale' => 'ar',
        ]);
        $editPostedAr->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonPath('error_code', 'stock_counts.not_open');
        $this->assertNotSame('stock_counts.not_open', $editPostedAr->json('message'));
        $this->assertSame(__('stock_counts.not_open', [], 'ar'), $editPostedAr->json('message'));

        $editPostedEn = $this->putJson("/api/v1/stock-counts/{$count}/lines", ['lines' => [['item_id' => $this->items['a'], 'counted_qty' => '1']]], [
            ...$this->tenantHeaders($this->tenant),
            'X-Locale' => 'en',
        ]);
        $editPostedEn->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonPath('error_code', 'stock_counts.not_open');
        $this->assertNotSame('stock_counts.not_open', $editPostedEn->json('message'));
        $this->assertSame(__('stock_counts.not_open', [], 'en'), $editPostedEn->json('message'));

        $this->postJson("/api/v1/stock-counts/{$count}/cancel", [], $this->tenantHeaders($this->tenant))
            ->assertStatus(422)
            ->assertJsonPath('error_code', 'stock_counts.not_open');

        $this->assertSame('90.000', $this->storeQty($this->mainStoreId, $this->items['a']));
        $this->assertSame(1, $this->movementCount());

        // INV-236: editing lines on a cancelled count also returns translated stock_counts.not_open.
        $cancelledCount = $this->startCount();
        $this->postJson("/api/v1/stock-counts/{$cancelledCount}/cancel", [], $this->tenantHeaders($this->tenant))->assertOk();

        $editCancelledAr = $this->putJson("/api/v1/stock-counts/{$cancelledCount}/lines", ['lines' => [['item_id' => $this->items['a'], 'counted_qty' => '2']]], [
            ...$this->tenantHeaders($this->tenant),
            'X-Locale' => 'ar',
        ]);
        $editCancelledAr->assertStatus(422)
            ->assertJsonPath('error_code', 'stock_counts.not_open')
            ->assertJsonPath('message', __('stock_counts.not_open', [], 'ar'));
    }

    public function test_a_sale_between_snapshot_and_post_is_absorbed(): void
    {
        $count = $this->startCount();
        $this->saveCounts($count, [$this->items['a'] => '96']);

        // A sale of 3 after the snapshot (100) and before posting: live balance 97.
        $this->inTenant($this->tenant, function (): void {
            DB::transaction(function (): void {
                $item = Item::query()->findOrFail($this->items['a']);
                app(StockService::class)->deductStock($item, '3.000', $item, 'TEST-SALE-1', 'sales_out', null, $this->mainStoreId);
            });
        });
        $this->assertSame('97.000', $this->storeQty($this->mainStoreId, $this->items['a']));

        $response = $this->postJson("/api/v1/stock-counts/{$count}/post", [], $this->tenantHeaders($this->tenant))->assertOk();

        $line = collect($response->json('data.lines'))->firstWhere('item_id', $this->items['a']);
        $this->assertSame('100.000', $line['system_qty']);
        $this->assertSame('97.000', $line['system_qty_at_post']);
        $this->assertSame('-1.000', $line['difference']);
        $this->assertSame('96.000', $this->storeQty($this->mainStoreId, $this->items['a']));
        $this->inTenant($this->tenant, fn () => $this->assertSame('96.000', (string) Item::query()->whereKey($this->items['a'])->value('current_stock')));
    }

    public function test_uncounted_lines_are_skipped_unless_zero_uncounted(): void
    {
        $count = $this->startCount();
        $this->saveCounts($count, [$this->items['a'] => '100']);

        $this->postJson("/api/v1/stock-counts/{$count}/post", ['zero_uncounted' => true], $this->tenantHeaders($this->tenant))->assertOk();

        $this->assertSame('100.000', $this->storeQty($this->mainStoreId, $this->items['a']));
        $this->assertSame('0.000', $this->storeQty($this->mainStoreId, $this->items['b']));

        $second = $this->startCount();
        $this->postJson("/api/v1/stock-counts/{$second}/post", [], $this->tenantHeaders($this->tenant))
            ->assertStatus(422)
            ->assertJsonPath('error_code', 'stock_counts.nothing_counted');
    }

    public function test_a_failure_in_the_middle_of_posting_rolls_everything_back(): void
    {
        $count = $this->startCount();
        $this->saveCounts($count, [$this->items['a'] => '90', $this->items['b'] => '40', $this->items['c'] => '5']);

        $this->app->instance(StockService::class, new class(app(TenantClock::class), app(DocumentNumberService::class)) extends StockService
        {
            private int $calls = 0;

            public function applyCountedQuantity(int $itemId, int $storeId, string $countedQuantity, Model $source, string $documentNumber, string $notes, ?int $actorId = null): array
            {
                if (++$this->calls === 2) {
                    throw new RuntimeException('Simulated failure in the middle of posting.');
                }

                return parent::applyCountedQuantity($itemId, $storeId, $countedQuantity, $source, $documentNumber, $notes, $actorId);
            }
        });

        $this->postJson("/api/v1/stock-counts/{$count}/post", [], $this->tenantHeaders($this->tenant))->assertStatus(500);

        $this->assertSame('100.000', $this->storeQty($this->mainStoreId, $this->items['a']));
        $this->assertSame('50.000', $this->storeQty($this->mainStoreId, $this->items['b']));
        $this->assertSame(0, $this->movementCount());
        $this->inTenant($this->tenant, function () use ($count): void {
            $this->assertSame('100.000', (string) Item::query()->whereKey($this->items['a'])->value('current_stock'));
            $this->assertSame(StockCountStatus::Counting, StockCount::query()->whereKey($count)->value('status'));
            $this->assertSame($this->mainStoreId, (int) StockCount::query()->whereKey($count)->value('open_store_id'));
        });
    }

    // ---------------------------------------------------------------- blind / variance / cancel / list

    public function test_blind_count_hides_system_quantity_until_posted(): void
    {
        $count = $this->startCountWith(['is_blind' => true]);
        $this->saveCounts($count, [$this->items['a'] => '98']);

        $response = $this->getJson("/api/v1/stock-counts/{$count}", $this->tenantHeaders($this->tenant))
            ->assertOk()
            ->assertJsonPath('data.is_blind', true);
        $line = collect($response->json('data.lines'))->firstWhere('item_id', $this->items['a']);
        $this->assertNull($line['system_qty']);
        $this->assertNull($line['difference']);

        $this->postJson("/api/v1/stock-counts/{$count}/post", [], $this->tenantHeaders($this->tenant))->assertOk();
        $line = collect($this->getJson("/api/v1/stock-counts/{$count}", $this->tenantHeaders($this->tenant))->json('data.lines'))->firstWhere('item_id', $this->items['a']);
        $this->assertSame('100.000', $line['system_qty']);
        $this->assertSame('-2.000', $line['difference']);
    }

    public function test_variance_report_lists_differences_and_values(): void
    {
        $count = $this->startCount();
        $this->saveCounts($count, [$this->items['a'] => '98', $this->items['b'] => '51', $this->items['c'] => '0']);

        $this->getJson("/api/v1/stock-counts/{$count}/variance", $this->tenantHeaders($this->tenant))
            ->assertOk()
            ->assertJsonCount(2, 'data.lines')
            ->assertJsonPath('data.totals.counted_count', 3)
            ->assertJsonPath('data.totals.matched_count', 1)
            ->assertJsonPath('data.totals.surplus_count', 1)
            ->assertJsonPath('data.totals.shortage_count', 1)
            // a: -2 x 420 = -840 ; b: +1 x 850 = 850
            ->assertJsonPath('data.totals.shortage_value', '840.000')
            ->assertJsonPath('data.totals.surplus_value', '850.000')
            ->assertJsonPath('data.totals.net_value', '10.000');
    }

    public function test_variance_report_hides_cost_without_view_cost(): void
    {
        $count = $this->startCount();
        $this->saveCounts($count, [$this->items['a'] => '98']);
        $headers = $this->tenantHeaders($this->tenant, $this->createTenantUser($this->tenant, permissions: ['stock.count']));

        $this->getJson("/api/v1/stock-counts/{$count}/variance", $headers)
            ->assertOk()
            ->assertJsonPath('data.lines.0.difference', '-2.000')
            ->assertJsonPath('data.lines.0.difference_value', null)
            ->assertJsonPath('data.totals.net_value', null);
    }

    public function test_cancel_frees_the_store_without_touching_stock(): void
    {
        $count = $this->startCount();
        $this->saveCounts($count, [$this->items['a'] => '1']);

        $this->postJson("/api/v1/stock-counts/{$count}/cancel", [], $this->tenantHeaders($this->tenant))
            ->assertOk()
            ->assertJsonPath('data.status', 'cancelled');

        $this->assertSame('100.000', $this->storeQty($this->mainStoreId, $this->items['a']));
        $this->assertSame(0, $this->movementCount());
        $this->startCount();
    }

    public function test_list_is_scoped_to_the_active_store_and_filterable(): void
    {
        $mainCount = $this->startCount();
        $this->postJson('/api/v1/stock-counts', [], $this->tenantHeaders($this->tenant, store: $this->branchStoreId))->assertCreated();

        $this->getJson('/api/v1/stock-counts', $this->tenantHeaders($this->tenant))
            ->assertOk()
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.id', $mainCount)
            ->assertJsonPath('data.0.lines_count', 3)
            ->assertJsonPath('data.0.counted_lines_count', 0);

        $this->getJson('/api/v1/stock-counts?status=posted', $this->tenantHeaders($this->tenant))
            ->assertOk()
            ->assertJsonPath('meta.total', 0);
    }

    // ---------------------------------------------------------------- isolation

    public function test_a_count_of_a_store_the_user_cannot_access_is_not_found(): void
    {
        $branchCount = (int) $this->postJson('/api/v1/stock-counts', [], $this->tenantHeaders($this->tenant, store: $this->branchStoreId))
            ->assertCreated()->json('data.id');

        // Holds stock.count, but only for the main store.
        $clerk = $this->createTenantUser($this->tenant, permissions: ['stock.count']);
        $headers = $this->tenantHeaders($this->tenant, $clerk);

        $this->getJson("/api/v1/stock-counts/{$branchCount}", $headers)->assertNotFound();
        $this->getJson("/api/v1/stock-counts/{$branchCount}/variance", $headers)->assertNotFound();
        $this->putJson("/api/v1/stock-counts/{$branchCount}/lines", ['lines' => [['item_id' => $this->items['a'], 'counted_qty' => '1']]], $headers)->assertNotFound();
        $this->postJson("/api/v1/stock-counts/{$branchCount}/post", [], $headers)->assertNotFound();
        $this->postJson("/api/v1/stock-counts/{$branchCount}/cancel", [], $headers)->assertNotFound();

        // Starting a count in the other branch through the header is refused by store scoping.
        $this->postJson('/api/v1/stock-counts', [], $this->tenantHeaders($this->tenant, $clerk, $this->branchStoreId))->assertForbidden();
        $this->getJson('/api/v1/stock-counts', $headers)->assertOk()->assertJsonPath('meta.total', 0);
    }

    public function test_counts_of_another_tenant_are_unreachable(): void
    {
        $other = $this->createTenant();
        $this->inTenant($other, function (): void {
            Item::query()->create(['name' => 'صنف تاني', 'code' => 'OTHER-1', 'unit' => 'قطعة', 'cost_price' => '1.000', 'selling_price' => '2.000', 'current_stock' => '0.000', 'is_active' => true]);
        });
        $foreign = (int) $this->postJson('/api/v1/stock-counts', [], $this->tenantHeaders($other))->assertCreated()->json('data.id');

        $headers = $this->tenantHeaders($this->tenant);
        $this->getJson('/api/v1/stock-counts', $headers)->assertOk()->assertJsonPath('meta.total', 0);
        $this->getJson("/api/v1/stock-counts/{$foreign}", $headers)->assertNotFound();
        $this->postJson("/api/v1/stock-counts/{$foreign}/post", [], $headers)->assertNotFound();

        $this->inTenant($other, fn () => $this->assertSame(StockCountStatus::Draft, StockCount::query()->whereKey($foreign)->value('status')));
    }

    // ---------------------------------------------------------------- helpers

    private function startCount(): int
    {
        return $this->startCountWith([]);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function startCountWith(array $payload): int
    {
        return (int) $this->postJson('/api/v1/stock-counts', $payload, $this->tenantHeaders($this->tenant))
            ->assertCreated()
            ->json('data.id');
    }

    /**
     * @param  array<int, string>  $counts  item_id => counted qty
     */
    private function saveCounts(int $countId, array $counts): TestResponse
    {
        $lines = [];
        foreach ($counts as $itemId => $qty) {
            $lines[] = ['item_id' => $itemId, 'counted_qty' => $qty];
        }

        return $this->putJson("/api/v1/stock-counts/{$countId}/lines", ['lines' => $lines], $this->tenantHeaders($this->tenant));
    }

    private function storeQty(int $storeId, int $itemId): string
    {
        return $this->inTenant(
            $this->tenant,
            fn (): string => (string) StoreStock::query()->where('store_id', $storeId)->where('item_id', $itemId)->value('quantity'),
        );
    }

    private function movementCount(): int
    {
        return $this->inTenant($this->tenant, fn (): int => StockMovement::query()->whereIn('movement_type', ['stock_count_in', 'stock_count_out'])->count());
    }

    /**
     * Inside the tenant: a second branch, a category, three active items (a in the category)
     * and one inactive item, stocked in the main store.
     *
     * @return array{0: int, 1: array<string, int>, 2: int}
     */
    private function seedFixtures(int $mainStoreId): array
    {
        $branch = Store::query()->create(['name' => 'فرع المعادي', 'code' => 'BR-MAADI', 'type' => 'retail', 'is_main' => false, 'is_active' => true]);
        $category = Category::query()->create(['name' => 'بن']);

        $make = function (string $code, string $cost, string $qty, bool $active = true, ?int $categoryId = null) use ($mainStoreId): int {
            $item = Item::query()->create([
                'name' => 'صنف '.$code,
                'code' => $code,
                'unit' => 'كجم',
                'category_id' => $categoryId,
                'cost_price' => $cost,
                'selling_price' => bcmul($cost, '1.5', 3),
                'current_stock' => $qty,
                'min_stock_level' => '1.000',
                'is_active' => $active,
            ]);

            StoreStock::query()->updateOrCreate(['store_id' => $mainStoreId, 'item_id' => $item->id], ['quantity' => $qty]);

            return (int) $item->id;
        };

        return [(int) $branch->id, [
            'a' => $make('CNT-A', '420.000', '100.000', categoryId: (int) $category->id),
            'b' => $make('CNT-B', '850.000', '50.000'),
            'c' => $make('CNT-C', '10.000', '0.000'),
            'inactive' => $make('CNT-X', '5.000', '7.000', active: false),
        ], (int) $category->id];
    }
}
