<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Http\Middleware\EnsureCentralContext;
use App\Models\CashShift;
use App\Models\Item;
use App\Models\Store;
use App\Models\StoreStock;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\Route;
use Tests\TenantTestCase;

/**
 * STOR-1 security fix: ApiTokenAuth used to copy any numeric X-Store-Id into the session, so a
 * cashier of store A could read store B's stock, open shift and pos settings by sending
 * `X-Store-Id: B`. The header is now access-checked: a forbidden store is a 403.
 */
final class StoreHeaderTrustTest extends TenantTestCase
{
    /**
     * Store B with one item stocked at 7.250 and an open shift; the item has no stock row in A.
     *
     * @return array{store_b: int, item: int, shift_b: int}
     */
    private function seedStoreB(Tenant $tenant): array
    {
        return $this->inTenant($tenant, function (): array {
            $storeB = Store::query()->create([
                'name' => 'فرع ب',
                'code' => 'BR-B',
                'type' => 'retail',
                'is_main' => false,
                'is_active' => true,
            ]);

            $item = Item::query()->create([
                'code' => 'TRUST-1',
                'name' => 'صنف الثقة',
                'category' => 'general',
                'unit' => 'كجم',
                'cost_price' => '10.000',
                'selling_price' => '20.000',
                'current_stock' => '0.000',
                'min_stock_level' => '100.000',
                'is_active' => true,
                'is_weighted' => true,
            ]);

            StoreStock::query()->create([
                'store_id' => $storeB->id,
                'item_id' => $item->id,
                'quantity' => '7.250',
                'min_stock' => '0.000',
            ]);

            $shift = CashShift::query()->create([
                'user_id' => User::query()->orderBy('id')->value('id'),
                'store_id' => $storeB->id,
                'shift_number' => 'SH-B-1',
                'status' => 'open',
                'opened_at' => now(),
                'opening_cash_balance' => '0.000',
            ]);

            return ['store_b' => (int) $storeB->id, 'item' => (int) $item->id, 'shift_b' => (int) $shift->id];
        });
    }

    /**
     * @return array<string, string>
     */
    private function headersWithoutStore(Tenant $tenant, User $user): array
    {
        $headers = $this->tenantHeaders($tenant, $user);
        unset($headers['X-Store-Id']);

        return $headers;
    }

    public function test_cashier_of_store_a_gets_403_on_pos_bootstrap_for_store_b(): void
    {
        $tenant = $this->createTenant();
        $seed = $this->seedStoreB($tenant);
        $cashier = $this->createTenantUser($tenant, 'cashier');

        $response = $this->getJson('/api/v1/pos/bootstrap', $this->tenantHeaders($tenant, $cashier, $seed['store_b']))
            ->assertStatus(403)
            ->assertJsonPath('success', false);

        $this->assertArrayNotHasKey('data', $response->json());
        $this->assertStringNotContainsString('SH-B-1', (string) $response->getContent());
    }

    public function test_cashier_of_store_a_reads_only_store_a_on_pos_bootstrap(): void
    {
        $tenant = $this->createTenant();
        $mainId = (int) $this->tenantStore($tenant)->id;
        $seed = $this->seedStoreB($tenant);
        $cashier = $this->createTenantUser($tenant, 'cashier');

        foreach ([$this->tenantHeaders($tenant, $cashier, $mainId), $this->headersWithoutStore($tenant, $cashier)] as $headers) {
            $response = $this->getJson('/api/v1/pos/bootstrap', $headers)
                ->assertOk()
                ->assertJsonPath('data.active_store.id', $mainId)
                ->assertJsonPath('data.active_shift', null)
                ->assertJsonPath('data.pos_settings.store_id', $mainId);

            $item = collect($response->json('data.items'))->firstWhere('id', $seed['item']);
            $this->assertNotNull($item);
            $this->assertEquals(0, $item['current_stock'], 'store A must not see store B stock');
        }
    }

    public function test_admin_with_all_stores_access_still_reads_store_b(): void
    {
        $tenant = $this->createTenant();
        $seed = $this->seedStoreB($tenant);

        $response = $this->getJson('/api/v1/pos/bootstrap', $this->tenantHeaders($tenant, null, $seed['store_b']))
            ->assertOk()
            ->assertJsonPath('data.active_store.id', $seed['store_b'])
            ->assertJsonPath('data.active_shift.id', $seed['shift_b']);

        $item = collect($response->json('data.items'))->firstWhere('id', $seed['item']);
        $this->assertEquals(7.25, $item['current_stock']);
    }

    public function test_cashier_assigned_to_store_b_may_read_it(): void
    {
        $tenant = $this->createTenant();
        $seed = $this->seedStoreB($tenant);
        $cashier = $this->createTenantUser($tenant, 'cashier');
        $this->inTenant($tenant, fn () => User::query()->findOrFail($cashier->id)->stores()->attach($seed['store_b']));

        $this->getJson('/api/v1/pos/bootstrap', $this->tenantHeaders($tenant, $cashier, $seed['store_b']))
            ->assertOk()
            ->assertJsonPath('data.active_store.id', $seed['store_b'])
            ->assertJsonPath('data.active_shift.id', $seed['shift_b']);
    }

    public function test_cashier_of_store_a_gets_403_on_low_stock_for_store_b(): void
    {
        $tenant = $this->createTenant();
        $mainId = (int) $this->tenantStore($tenant)->id;
        $seed = $this->seedStoreB($tenant);
        $cashier = $this->createTenantUser($tenant, 'cashier');

        $this->getJson('/api/v1/items/low-stock', $this->tenantHeaders($tenant, $cashier, $seed['store_b']))
            ->assertStatus(403)
            ->assertJsonPath('success', false);

        $this->getJson('/api/v1/items/low-stock', $this->tenantHeaders($tenant, $cashier, $mainId))
            ->assertOk();

        $this->getJson('/api/v1/items/low-stock', $this->tenantHeaders($tenant, null, $seed['store_b']))
            ->assertOk();
    }

    public function test_cashier_cannot_claim_all_stores_or_send_a_malformed_store_header(): void
    {
        $tenant = $this->createTenant();
        $cashier = $this->createTenantUser($tenant, 'cashier');

        $this->getJson('/api/v1/pos/bootstrap', $this->tenantHeaders($tenant, $cashier, 0))->assertStatus(403);

        $headers = $this->tenantHeaders($tenant, $cashier);
        $headers['X-Store-Id'] = 'all';
        $this->getJson('/api/v1/pos/bootstrap', $headers)->assertStatus(403);
    }

    public function test_store_header_check_is_skipped_on_central_routes_only(): void
    {
        // The central control plane has no `stores` table; ApiTokenAuth recognises it by
        // EnsureCentralContext in the route's middleware, so this must stay true.
        $central = Route::getRoutes()->getByName('api.super_admin.dashboard');
        $this->assertNotNull($central);
        $this->assertContains(EnsureCentralContext::class, $central->gatherMiddleware());

        $tenantRoute = Route::getRoutes()->getByName('api.pos.bootstrap');
        $this->assertNotNull($tenantRoute);
        $this->assertNotContains(EnsureCentralContext::class, $tenantRoute->gatherMiddleware());
    }
}
