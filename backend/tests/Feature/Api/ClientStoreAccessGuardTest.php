<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Models\CashShift;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Item;
use App\Models\StockTransfer;
use App\Models\Store;
use App\Models\StoreStock;
use App\Models\Tenant;
use App\Models\User;
use Tests\TenantTestCase;

/**
 * W2 batch 2 security (interim until STOR-2): ApiTokenAuth only verified the X-Store-Id header,
 * so a branch id sent in the body / query (`store_id`, `from_store_id`) was trusted as is. A
 * cashier of branch A could sell from branch B's stock, a storekeeper of A could move B's stock,
 * and anyone with reports.view could read B's figures. Every client store id is now access-checked
 * (App\Support\ClientStoreGuard / StoreStockTransferRequest): 403 `store_access_denied`.
 *
 * Also covers the SPA lockout fix: a stale X-Store-Id (store the user lost) is a 403 with
 * error_code on data routes, but /auth/me, /stores and logout ignore it so the app can recover.
 */
final class ClientStoreAccessGuardTest extends TenantTestCase
{
    /**
     * Branch B with one item: 8.500 in B, 3.000 in the main store A.
     *
     * @return array{store_b: int, item: int, customer: int}
     */
    private function seedBranchB(Tenant $tenant): array
    {
        $mainId = (int) $this->tenantStore($tenant)->id;

        return $this->inTenant($tenant, function () use ($mainId): array {
            $storeB = Store::query()->create([
                'name' => 'فرع ب',
                'code' => 'BR-B',
                'type' => 'retail',
                'is_main' => false,
                'is_active' => true,
            ]);

            $item = Item::query()->create([
                'code' => 'GUARD-1',
                'name' => 'صنف الحماية',
                'unit' => 'كجم',
                'cost_price' => '10.000',
                'selling_price' => '20.000',
                'current_stock' => '11.500',
                'is_active' => true,
            ]);

            StoreStock::query()->create(['store_id' => $storeB->id, 'item_id' => $item->id, 'quantity' => '8.500']);
            StoreStock::query()->create(['store_id' => $mainId, 'item_id' => $item->id, 'quantity' => '3.000']);

            $customer = Customer::query()->create([
                'name' => 'عميل الفروع',
                'phone' => '01000007611',
                'current_balance' => '0.000',
                'is_active' => true,
            ]);

            return ['store_b' => (int) $storeB->id, 'item' => (int) $item->id, 'customer' => (int) $customer->id];
        });
    }

    private function stockOf(Tenant $tenant, int $storeId, int $itemId): string
    {
        return (string) $this->inTenant($tenant, fn () => StoreStock::query()
            ->where('store_id', $storeId)->where('item_id', $itemId)->value('quantity'));
    }

    // ------------------------------------------------------------------
    // 1. Stock transfers: transfers.create no longer means "any branch"
    // ------------------------------------------------------------------

    public function test_storekeeper_of_store_a_cannot_transfer_store_b_stock(): void
    {
        $tenant = $this->createTenant();
        $mainId = (int) $this->tenantStore($tenant)->id;
        $seed = $this->seedBranchB($tenant);
        $storekeeper = $this->createTenantUser($tenant, 'storekeeper');

        $this->postJson('/api/v1/transfers', [
            'from_store_id' => $seed['store_b'],
            'to_store_id' => $mainId,
            'transfer_date' => now()->toDateString(),
            'items' => [['item_id' => $seed['item'], 'quantity' => '2.000']],
        ], $this->tenantHeaders($tenant, $storekeeper))
            ->assertStatus(403)
            ->assertJsonPath('success', false)
            ->assertJsonPath('error_code', 'store_access_denied')
            ->assertJsonPath('message', __('common.store_access_denied'));

        $this->assertSame('8.500', $this->stockOf($tenant, $seed['store_b'], $seed['item']));
        $this->assertSame('3.000', $this->stockOf($tenant, $mainId, $seed['item']));
        $this->assertSame(0, $this->inTenant($tenant, fn (): int => StockTransfer::query()->count()));
    }

    public function test_storekeeper_of_store_a_cannot_push_stock_into_an_unassigned_store(): void
    {
        $tenant = $this->createTenant();
        $mainId = (int) $this->tenantStore($tenant)->id;
        $seed = $this->seedBranchB($tenant);
        $storekeeper = $this->createTenantUser($tenant, 'storekeeper');

        $this->postJson('/api/v1/transfers', [
            'from_store_id' => $mainId,
            'to_store_id' => $seed['store_b'],
            'transfer_date' => now()->toDateString(),
            'items' => [['item_id' => $seed['item'], 'quantity' => '1.000']],
        ], $this->tenantHeaders($tenant, $storekeeper))->assertStatus(403);

        $this->assertSame('3.000', $this->stockOf($tenant, $mainId, $seed['item']));
    }

    public function test_storekeeper_assigned_to_both_stores_and_admin_may_transfer(): void
    {
        $tenant = $this->createTenant();
        $mainId = (int) $this->tenantStore($tenant)->id;
        $seed = $this->seedBranchB($tenant);
        $storekeeper = $this->createTenantUser($tenant, 'storekeeper');
        $this->inTenant($tenant, fn () => User::query()->findOrFail($storekeeper->id)->stores()->attach($seed['store_b']));

        $payload = [
            'from_store_id' => $seed['store_b'],
            'to_store_id' => $mainId,
            'transfer_date' => now()->toDateString(),
            'items' => [['item_id' => $seed['item'], 'quantity' => '2.250']],
        ];

        $this->postJson('/api/v1/transfers', $payload, $this->tenantHeaders($tenant, $storekeeper))->assertStatus(201);
        $this->postJson('/api/v1/transfers', $payload, $this->tenantHeaders($tenant))->assertStatus(201);

        $this->assertSame('4.000', $this->stockOf($tenant, $seed['store_b'], $seed['item']));
        $this->assertSame('7.500', $this->stockOf($tenant, $mainId, $seed['item']));
    }

    // ------------------------------------------------------------------
    // 2. Body / query store_id on POS, reports, store stocks
    // ------------------------------------------------------------------

    public function test_cashier_of_store_a_cannot_sell_from_store_b_with_a_body_store_id(): void
    {
        $tenant = $this->createTenant();
        $seed = $this->seedBranchB($tenant);
        $cashier = $this->createTenantUser($tenant, 'cashier');

        foreach ([$this->tenantHeaders($tenant, $cashier), $this->headersWithoutStore($tenant, $cashier)] as $headers) {
            $this->postJson('/api/v1/pos/checkout', [
                'store_id' => $seed['store_b'],
                'customer_id' => $seed['customer'],
                'payment_type' => 'cash',
                'payment_method' => 'cash',
                'items' => [['item_id' => $seed['item'], 'quantity' => '1.000', 'unit_price' => '20.000']],
            ], $headers)
                ->assertStatus(403)
                ->assertJsonPath('error_code', 'store_access_denied');
        }

        $this->assertSame('8.500', $this->stockOf($tenant, $seed['store_b'], $seed['item']));
        $this->assertSame(0, $this->inTenant($tenant, fn (): int => Invoice::query()->count()));
    }

    public function test_pos_checkout_with_the_own_store_in_body_and_header_still_sells_from_it(): void
    {
        $tenant = $this->createTenant();
        $mainId = (int) $this->tenantStore($tenant)->id;
        $seed = $this->seedBranchB($tenant);
        $cashier = $this->createTenantUser($tenant, 'cashier');

        $this->postJson('/api/v1/pos/checkout', [
            'store_id' => $mainId,
            'customer_id' => $seed['customer'],
            'payment_type' => 'cash',
            'payment_method' => 'cash',
            'items' => [['item_id' => $seed['item'], 'quantity' => '0.250', 'unit_price' => '20.000']],
        ], $this->tenantHeaders($tenant, $cashier))->assertStatus(201);

        $this->assertSame('2.750', $this->stockOf($tenant, $mainId, $seed['item']));
        $this->assertSame('8.500', $this->stockOf($tenant, $seed['store_b'], $seed['item']));
    }

    public function test_report_with_a_query_store_id_of_another_store_is_forbidden(): void
    {
        $tenant = $this->createTenant();
        $mainId = (int) $this->tenantStore($tenant)->id;
        $seed = $this->seedBranchB($tenant);
        $viewer = $this->createTenantUser($tenant, null, ['reports.view']);

        $this->getJson('/api/v1/reports/summary?store_id='.$seed['store_b'], $this->tenantHeaders($tenant, $viewer))
            ->assertStatus(403)
            ->assertJsonPath('error_code', 'store_access_denied');
        $this->getJson('/api/v1/reports/summary?store_id=all', $this->tenantHeaders($tenant, $viewer))
            ->assertStatus(403);
        $this->getJson('/api/v1/reports/items/'.$seed['item'].'/card?store_id='.$seed['store_b'], $this->tenantHeaders($tenant, $viewer))
            ->assertStatus(403);

        $this->getJson('/api/v1/reports/summary?store_id='.$mainId, $this->tenantHeaders($tenant, $viewer))->assertOk();
        $this->getJson('/api/v1/reports/summary?store_id='.$seed['store_b'], $this->tenantHeaders($tenant))->assertOk();
    }

    public function test_store_stocks_of_another_store_are_forbidden_and_all_is_never_store_zero(): void
    {
        $tenant = $this->createTenant();
        $mainId = (int) $this->tenantStore($tenant)->id;
        $seed = $this->seedBranchB($tenant);
        $storekeeper = $this->createTenantUser($tenant, 'storekeeper');

        $this->getJson('/api/v1/stores/stocks?store_id='.$seed['store_b'], $this->tenantHeaders($tenant, $storekeeper))
            ->assertStatus(403)
            ->assertJsonPath('error_code', 'store_access_denied');

        $own = $this->getJson('/api/v1/stores/stocks?store_id='.$mainId, $this->tenantHeaders($tenant, $storekeeper))->assertOk();
        $this->assertSame([3], array_map('intval', array_column($own->json('data'), 'quantity')), 'only store A stock (3.000) is listed');

        // Admin picking branch B on the stores screen: the query value is the filter.
        $b = $this->getJson('/api/v1/stores/stocks?store_id='.$seed['store_b'], $this->tenantHeaders($tenant))->assertOk();
        $this->assertCount(1, $b->json('data'));

        // `all` is not a store: 422, never (int) 'all' = store 0.
        $this->getJson('/api/v1/stores/stocks?store_id=all', $this->tenantHeaders($tenant))
            ->assertStatus(422)
            ->assertJsonValidationErrors('store_id');
    }

    public function test_item_movements_with_another_store_id_are_forbidden(): void
    {
        $tenant = $this->createTenant();
        $seed = $this->seedBranchB($tenant);
        $viewer = $this->createTenantUser($tenant, null, ['items.view']);

        $this->getJson('/api/v1/items/'.$seed['item'].'/movements?store_id='.$seed['store_b'], $this->tenantHeaders($tenant, $viewer))
            ->assertStatus(403)
            ->assertJsonPath('error_code', 'store_access_denied');
    }

    public function test_opening_a_shift_on_all_stores_is_rejected_and_creates_nothing(): void
    {
        $tenant = $this->createTenant();

        $headers = $this->tenantHeaders($tenant);
        $headers['X-Store-Id'] = 'all';

        $this->postJson('/api/v1/shifts/open', ['opening_cash_balance' => '100.000'], $headers)
            ->assertStatus(422)
            ->assertJsonValidationErrors('store_id');

        $this->assertSame(0, $this->inTenant($tenant, fn (): int => CashShift::query()->count()));
    }

    public function test_body_store_id_all_on_a_write_is_rejected_for_a_user_who_cannot_view_all(): void
    {
        $tenant = $this->createTenant();
        $cashier = $this->createTenantUser($tenant, 'cashier');

        $this->postJson('/api/v1/shifts/open', ['opening_cash_balance' => '100.000', 'store_id' => 'all'], $this->tenantHeaders($tenant, $cashier))
            ->assertStatus(403)
            ->assertJsonPath('error_code', 'store_access_denied');

        $this->assertSame(0, $this->inTenant($tenant, fn (): int => CashShift::query()->count()));
    }

    // ------------------------------------------------------------------
    // A. Lockout recovery: a stale X-Store-Id
    // ------------------------------------------------------------------

    public function test_user_who_lost_store_b_gets_store_access_denied_on_data_routes_but_can_recover(): void
    {
        $tenant = $this->createTenant();
        $mainId = (int) $this->tenantStore($tenant)->id;
        $seed = $this->seedBranchB($tenant);
        $cashier = $this->createTenantUser($tenant, 'cashier');
        $this->inTenant($tenant, function () use ($seed, $cashier): void {
            CashShift::query()->create([
                'user_id' => $cashier->id,
                'store_id' => $seed['store_b'],
                'shift_number' => 'SH-B-STALE',
                'status' => 'open',
                'opened_at' => now(),
                'opening_cash_balance' => '0.000',
            ]);
        });

        // The SPA still sends the branch it had before the user was unassigned from it.
        $stale = $this->tenantHeaders($tenant, $cashier, $seed['store_b']);

        $this->getJson('/api/v1/items/low-stock', $stale)
            ->assertStatus(403)
            ->assertJsonPath('error_code', 'store_access_denied');

        $me = $this->getJson('/api/v1/auth/me', $stale)->assertOk();
        $this->assertNotSame($seed['store_b'], $me->json('data.store.id') ?? $me->json('store.id'));
        $this->assertStringNotContainsString('SH-B-STALE', (string) $me->getContent());

        $stores = $this->getJson('/api/v1/stores', $stale)->assertOk();
        $this->assertSame($mainId, $stores->json('active_store.id'));
        $this->assertNotContains($seed['store_b'], array_column($stores->json('stores'), 'id'));

        $this->postJson('/api/v1/stores/switch', ['store_id' => $mainId], $stale)->assertOk();
        $this->postJson('/api/v1/stores/switch', ['store_id' => $seed['store_b']], $stale)->assertStatus(403);
        $this->postJson('/api/v1/auth/logout', [], $stale)->assertOk();
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
}
