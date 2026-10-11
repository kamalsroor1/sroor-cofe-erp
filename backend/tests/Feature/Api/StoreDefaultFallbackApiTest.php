<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Models\Item;
use App\Models\Store;
use App\Models\StoreStock;
use App\Models\Tenant;
use App\Models\User;
use Tests\TenantTestCase;

/**
 * W2 3F: single-store endpoints called WITHOUT X-Store-Id / store_id fall back to the user's own
 * store (ActiveStore::defaultFor via ClientStoreGuard::concreteOrDefault), never to a hardcoded
 * store id 1. A user without any usable store gets the translated 403, never another branch's
 * (or every branch's) figures.
 *
 * Covers TreasuryController::summary, ItemController::lowStock and StoreController::stocks.
 */
final class StoreDefaultFallbackApiTest extends TenantTestCase
{
    private Tenant $tenant;

    private Store $mainStore;

    private Store $branch;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = $this->createTenant();
        $this->mainStore = $this->tenantStore($this->tenant);
        $this->branch = $this->inTenant($this->tenant, static fn (): Store => Store::query()->create([
            'name' => 'فرع المعادي',
            'code' => 'BR-MAADI',
            'type' => 'retail',
            'is_main' => false,
            'is_active' => true,
        ]));
    }

    /**
     * Bearer + X-Tenant, deliberately WITHOUT X-Store-Id.
     *
     * @return array<string, string>
     */
    private function headersWithoutStore(?User $user = null): array
    {
        $headers = $this->tenantHeaders($this->tenant, $user);
        unset($headers['X-Store-Id']);

        return $headers;
    }

    public function test_treasury_summary_without_store_uses_the_users_default_store(): void
    {
        $user = $this->createTenantUser($this->tenant, null, ['daily_journal.view'], ['default_store_id' => $this->branch->id]);

        $this->getJson('/api/v1/treasury/summary', $this->headersWithoutStore($user))
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('store_id', $this->branch->id);
    }

    public function test_treasury_summary_without_store_uses_the_first_assigned_store(): void
    {
        $user = $this->createTenantUser($this->tenant, null, ['daily_journal.view'], ['default_store_id' => null]);
        $this->inTenant($this->tenant, fn () => $user->stores()->attach($this->branch->id));

        $this->getJson('/api/v1/treasury/summary', $this->headersWithoutStore($user))
            ->assertOk()
            ->assertJsonPath('store_id', $this->branch->id);
    }

    public function test_admin_without_any_own_store_falls_back_to_the_main_store(): void
    {
        $admin = $this->createTenantUser($this->tenant, 'admin', [], ['default_store_id' => null]);

        $this->getJson('/api/v1/treasury/summary', $this->headersWithoutStore($admin))
            ->assertOk()
            ->assertJsonPath('store_id', $this->mainStore->id);
    }

    public function test_user_without_any_usable_store_gets_403_instead_of_another_branch(): void
    {
        $user = $this->createTenantUser($this->tenant, null, ['daily_journal.view', 'items.view'], ['default_store_id' => null]);

        foreach (['/api/v1/treasury/summary', '/api/v1/items/low-stock', '/api/v1/stores/stocks'] as $uri) {
            $this->getJson($uri, $this->headersWithoutStore($user))
                ->assertStatus(403)
                ->assertJsonPath('success', false)
                ->assertJsonPath('error_code', 'store_access_denied')
                ->assertJsonPath('message', __('common.store_access_denied'));
        }
    }

    public function test_low_stock_without_store_measures_the_users_own_store(): void
    {
        $item = $this->inTenant($this->tenant, function (): Item {
            $item = Item::query()->create([
                'name' => 'بن كولومبي',
                'code' => 'COF-COL-3F',
                'category' => 'بن حبوب',
                'unit' => 'كجم',
                'cost_price' => '300.000',
                'selling_price' => '450.000',
                'current_stock' => '5.000', // below the minimum: the radar lists the item, the row shows the store's own stock
                'min_stock_level' => '10.000',
                'is_active' => true,
            ]);
            // Plenty in the main store, short in the branch.
            StoreStock::query()->create(['store_id' => $this->mainStore->id, 'item_id' => $item->id, 'quantity' => '20.000']);
            StoreStock::query()->create(['store_id' => $this->branch->id, 'item_id' => $item->id, 'quantity' => '0.250']);

            return $item;
        });

        $user = $this->createTenantUser($this->tenant, null, ['items.view'], ['default_store_id' => $this->branch->id]);

        $row = collect($this->getJson('/api/v1/items/low-stock', $this->headersWithoutStore($user))
            ->assertOk()
            ->json('low_items'))->firstWhere('id', $item->id);

        $this->assertIsArray($row);
        $this->assertSame('0.250', $row['current_stock']);
        $this->assertSame('9.750', $row['deficit']);
    }

    public function test_guest_is_401(): void
    {
        $headers = $this->tenantGuestHeaders($this->tenant);
        unset($headers['X-Store-Id']);

        $this->getJson('/api/v1/treasury/summary', $headers)->assertStatus(401);
        $this->getJson('/api/v1/items/low-stock', $headers)->assertStatus(401);
    }
}
