<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Models\Category;
use App\Models\Item;
use App\Models\PosQuickKey;
use App\Models\Store;
use App\Models\Tenant;
use App\Models\User;
use Tests\TenantTestCase;

/**
 * POSB-6: GET /api/v1/pos/quick-keys (active store) and
 * PUT /api/v1/stores/{store}/pos/quick-keys (full replace), plus /pos/bootstrap `quick_keys`.
 */
final class PosQuickKeysApiTest extends TenantTestCase
{
    private const LIST_URL = '/api/v1/pos/quick-keys';

    private function url(int $storeId): string
    {
        return '/api/v1/stores/'.$storeId.'/pos/quick-keys';
    }

    private function createStore(Tenant $tenant, string $code): int
    {
        return $this->inTenant($tenant, fn (): int => (int) Store::query()->create([
            'name' => 'فرع '.$code,
            'code' => $code,
            'type' => 'retail',
            'is_main' => false,
            'is_active' => true,
        ])->id);
    }

    private function createItem(Tenant $tenant, string $code, bool $weighted = false, bool $active = true): int
    {
        return $this->inTenant($tenant, fn (): int => (int) Item::query()->create([
            'code' => $code,
            'name' => 'صنف '.$code,
            'category' => 'general',
            'unit' => $weighted ? 'كجم' : 'قطعة',
            'cost_price' => '10.000',
            'selling_price' => '32.500',
            'current_stock' => '0.000',
            'min_stock_level' => '0.000',
            'is_active' => $active,
            'is_weighted' => $weighted,
        ])->id);
    }

    private function createCategory(Tenant $tenant, string $name, bool $active = true): int
    {
        return $this->inTenant($tenant, fn (): int => (int) Category::query()->create([
            'name' => $name,
            'icon' => 'x',
            'color' => '#64748B',
            'color_light' => '#f1f5f9',
            'sort_order' => 1,
            'is_active' => $active,
        ])->id);
    }

    private function keyCount(Tenant $tenant, ?int $storeId = null): int
    {
        return $this->inTenant($tenant, fn (): int => PosQuickKey::query()
            ->when($storeId !== null, fn ($q) => $q->where('store_id', $storeId))
            ->count());
    }

    /**
     * @return array<string, mixed>
     */
    private function itemKey(int $itemId, int $page = 1, int $position = 0, array $extra = []): array
    {
        return array_merge(['page' => $page, 'position' => $position, 'type' => 'item', 'item_id' => $itemId], $extra);
    }

    /**
     * @return array<string, mixed>
     */
    private function categoryKey(int $categoryId, int $page = 1, int $position = 1, array $extra = []): array
    {
        return array_merge(['page' => $page, 'position' => $position, 'type' => 'category', 'category_id' => $categoryId], $extra);
    }

    public function test_admin_replaces_and_reads_the_layout(): void
    {
        $tenant = $this->createTenant();
        $storeId = (int) $this->tenantStore($tenant)->id;
        $sugar = $this->createItem($tenant, 'SUGAR', weighted: true);
        $salt = $this->createItem($tenant, 'SALT');
        $grocery = $this->createCategory($tenant, 'بقالة');
        $headers = $this->tenantHeaders($tenant);

        $response = $this->putJson($this->url($storeId), ['keys' => [
            $this->itemKey($salt, page: 2, position: 0),
            $this->categoryKey($grocery, page: 1, position: 5, extra: ['label' => 'بقالة سريعة', 'color' => 'violet']),
            $this->itemKey($sugar, page: 1, position: 0, extra: ['color' => 'sky']),
        ]], $headers);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('meta.store_id', $storeId)
            ->assertJsonPath('meta.max_keys', 300)
            ->assertJsonCount(3, 'data')
            // Ordered by page, then position.
            ->assertJsonPath('data.0.page', 1)
            ->assertJsonPath('data.0.position', 0)
            ->assertJsonPath('data.0.type', 'item')
            ->assertJsonPath('data.0.color', 'sky')
            ->assertJsonPath('data.0.item.id', $sugar)
            ->assertJsonPath('data.0.item.price', '32.500')
            ->assertJsonPath('data.0.item.is_weighted', true)
            ->assertJsonPath('data.0.category', null)
            ->assertJsonPath('data.1.type', 'category')
            ->assertJsonPath('data.1.label', 'بقالة سريعة')
            ->assertJsonPath('data.1.category.id', $grocery)
            ->assertJsonPath('data.1.item', null)
            ->assertJsonPath('data.2.page', 2)
            ->assertJsonPath('data.2.item.is_weighted', false)
            ->assertJsonPath('data.2.color', null);
        $this->assertIsString($response->json('message'));

        $this->getJson(self::LIST_URL, $headers)
            ->assertOk()
            ->assertJsonCount(3, 'data')
            ->assertJsonPath('data.0.item.id', $sugar)
            ->assertJsonPath('meta.colors', ['slate', 'sky', 'emerald', 'amber', 'rose', 'violet', 'indigo', 'teal']);

        // Full replace: the second save leaves exactly its own keys.
        $this->putJson($this->url($storeId), ['keys' => [$this->itemKey($salt, page: 3, position: 59)]], $headers)
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.page', 3)
            ->assertJsonPath('data.0.position', 59);
        $this->assertSame(1, $this->keyCount($tenant, $storeId));

        // Empty list clears the layout.
        $this->putJson($this->url($storeId), ['keys' => []], $headers)->assertOk()->assertJsonCount(0, 'data');
        $this->assertSame(0, $this->keyCount($tenant));
    }

    public function test_validation_rejects_bad_layouts_and_keeps_the_saved_one(): void
    {
        $tenant = $this->createTenant();
        $storeId = (int) $this->tenantStore($tenant)->id;
        $item = $this->createItem($tenant, 'OK');
        $inactiveItem = $this->createItem($tenant, 'OFF', active: false);
        $deletedItem = $this->createItem($tenant, 'DEL');
        $this->inTenant($tenant, fn () => Item::query()->findOrFail($deletedItem)->delete());
        $category = $this->createCategory($tenant, 'ألبان');
        $inactiveCategory = $this->createCategory($tenant, 'مقفولة', active: false);
        $headers = $this->tenantHeaders($tenant);

        $this->putJson($this->url($storeId), ['keys' => [$this->itemKey($item)]], $headers)->assertOk();

        $tooMany = [];
        for ($i = 0; $i < 301; $i++) {
            $tooMany[] = $this->itemKey($item, page: intdiv($i, 60) + 1, position: $i % 60);
        }

        $cases = [
            'keys missing' => [[], 'keys'],
            'keys not array' => [['keys' => 'x'], 'keys'],
            'more than 300 keys' => [['keys' => $tooMany], 'keys'],
            'duplicate position' => [['keys' => [$this->itemKey($item, 2, 7), $this->categoryKey($category, 2, 7)]], 'keys.1.position'],
            'page zero' => [['keys' => [$this->itemKey($item, 0, 0)]], 'keys.0.page'],
            'page six' => [['keys' => [$this->itemKey($item, 6, 0)]], 'keys.0.page'],
            'position 60' => [['keys' => [$this->itemKey($item, 1, 60)]], 'keys.0.position'],
            'unknown type' => [['keys' => [['page' => 1, 'position' => 0, 'type' => 'action']]], 'keys.0.type'],
            'free hex colour' => [['keys' => [$this->itemKey($item, extra: ['color' => '#ff0000'])]], 'keys.0.color'],
            'label over 30 chars' => [['keys' => [$this->itemKey($item, extra: ['label' => str_repeat('ب', 31)])]], 'keys.0.label'],
            'item key without item' => [['keys' => [['page' => 1, 'position' => 0, 'type' => 'item']]], 'keys.0.item_id'],
            'category key without category' => [['keys' => [['page' => 1, 'position' => 0, 'type' => 'category']]], 'keys.0.category_id'],
            'item key with category id' => [['keys' => [$this->itemKey($item, extra: ['category_id' => $category])]], 'keys.0.category_id'],
            'missing item' => [['keys' => [$this->itemKey(999999)]], 'keys.0.item_id'],
            'inactive item' => [['keys' => [$this->itemKey($inactiveItem)]], 'keys.0.item_id'],
            'soft-deleted item' => [['keys' => [$this->itemKey($deletedItem)]], 'keys.0.item_id'],
            'inactive category' => [['keys' => [$this->categoryKey($inactiveCategory)]], 'keys.0.category_id'],
        ];

        foreach ($cases as [$payload, $errorKey]) {
            $this->putJson($this->url($storeId), $payload, $headers)
                ->assertStatus(422)
                ->assertJsonValidationErrors([$errorKey]);
        }

        // The layout saved before the rejected attempts is untouched.
        $this->assertSame(1, $this->keyCount($tenant, $storeId));
        $this->assertSame($item, $this->inTenant($tenant, fn (): int => (int) PosQuickKey::query()->value('item_id')));
    }

    public function test_requires_authentication(): void
    {
        $tenant = $this->createTenant();
        $storeId = (int) $this->tenantStore($tenant)->id;
        $item = $this->createItem($tenant, 'A1');
        $headers = ['Accept' => 'application/json', 'X-Tenant' => (string) $tenant->getTenantKey()];

        $this->getJson(self::LIST_URL, $headers)->assertStatus(401);
        $this->putJson($this->url($storeId), ['keys' => [$this->itemKey($item)]], $headers)->assertStatus(401);
        $this->putJson($this->url(999999), ['keys' => []], $headers)->assertStatus(401);

        $this->assertSame(0, $this->keyCount($tenant));
    }

    public function test_permissions_are_enforced(): void
    {
        $tenant = $this->createTenant();
        $storeId = (int) $this->tenantStore($tenant)->id;
        $item = $this->createItem($tenant, 'A1');
        $cashier = $this->createTenantUser($tenant, 'cashier');
        $nobody = $this->createTenantUser($tenant);

        // Cashier (pos.access, no settings.manage) reads but cannot edit.
        $this->getJson(self::LIST_URL, $this->tenantHeaders($tenant, $cashier))->assertOk();
        $this->putJson($this->url($storeId), ['keys' => [$this->itemKey($item)]], $this->tenantHeaders($tenant, $cashier))
            ->assertStatus(403)
            ->assertJsonPath('success', false);

        // No pos.access at all.
        $this->getJson(self::LIST_URL, $this->tenantHeaders($tenant, $nobody))->assertStatus(403);

        $this->assertSame(0, $this->keyCount($tenant));
    }

    public function test_branch_a_cannot_read_or_edit_branch_b(): void
    {
        $tenant = $this->createTenant();
        $storeA = $this->createStore($tenant, 'A-01');
        $storeB = $this->createStore($tenant, 'B-01');
        $item = $this->createItem($tenant, 'A1');

        // Admin seeds branch B's layout.
        $this->putJson($this->url($storeB), ['keys' => [$this->itemKey($item, 4, 4)]], $this->tenantHeaders($tenant, null, $storeB))->assertOk();

        /** @var User $manager */
        $manager = $this->createTenantUser($tenant, 'cashier', ['settings.manage'], ['default_store_id' => $storeA]);
        $this->inTenant($tenant, fn () => User::query()->findOrFail($manager->id)->stores()->sync([$storeA]));

        $headersA = $this->tenantHeaders($tenant, $manager, $storeA);
        $headersB = $this->tenantHeaders($tenant, $manager, $storeB);

        $this->putJson($this->url($storeA), ['keys' => [$this->itemKey($item)]], $headersA)->assertOk();
        $this->getJson(self::LIST_URL, $headersA)
            ->assertOk()
            ->assertJsonPath('meta.store_id', $storeA)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.page', 1);

        // Neither by URL nor by X-Store-Id.
        $this->putJson($this->url($storeB), ['keys' => []], $headersA)->assertStatus(403);
        $this->putJson($this->url($storeB), ['keys' => []], $headersB)->assertStatus(403);
        $this->getJson(self::LIST_URL, $headersB)->assertStatus(403);

        $this->assertSame(1, $this->keyCount($tenant, $storeB));
        $this->assertSame(4, $this->inTenant($tenant, fn (): int => (int) PosQuickKey::query()->where('store_id', $storeB)->value('page')));
    }

    public function test_missing_or_soft_deleted_store_is_404(): void
    {
        $tenant = $this->createTenant();
        $deleted = $this->createStore($tenant, 'DEL-01');
        $this->inTenant($tenant, fn () => Store::query()->findOrFail($deleted)->delete());
        $headers = $this->tenantHeaders($tenant);

        $this->putJson($this->url(999999), ['keys' => []], $headers)->assertStatus(404);
        $this->putJson($this->url($deleted), ['keys' => []], $headers)->assertStatus(404);
    }

    public function test_inactive_or_deleted_targets_are_dropped_silently(): void
    {
        $tenant = $this->createTenant();
        $storeId = (int) $this->tenantStore($tenant)->id;
        $keep = $this->createItem($tenant, 'KEEP');
        $deactivate = $this->createItem($tenant, 'OFF');
        $delete = $this->createItem($tenant, 'GONE');
        $category = $this->createCategory($tenant, 'منظفات');
        $headers = $this->tenantHeaders($tenant);

        $this->putJson($this->url($storeId), ['keys' => [
            $this->itemKey($keep, 1, 0),
            $this->itemKey($deactivate, 1, 1),
            $this->itemKey($delete, 1, 2),
            $this->categoryKey($category, 1, 3),
        ]], $headers)->assertOk()->assertJsonCount(4, 'data');

        $this->inTenant($tenant, function () use ($deactivate, $delete, $category): void {
            Item::query()->findOrFail($deactivate)->update(['is_active' => false]);
            Item::query()->findOrFail($delete)->delete();
            Category::query()->findOrFail($category)->delete();
        });

        $this->getJson(self::LIST_URL, $headers)
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.item.id', $keep);

        $this->getJson('/api/v1/pos/bootstrap', $headers)
            ->assertOk()
            ->assertJsonCount(1, 'data.quick_keys')
            ->assertJsonPath('data.quick_keys.0.item.id', $keep);

        // Rows stay (the item may be re-activated); only the read drops them.
        $this->assertSame(4, $this->keyCount($tenant, $storeId));
    }

    public function test_keys_are_isolated_between_tenants(): void
    {
        $tenantA = $this->createTenant();
        $tenantB = $this->createTenant();
        $storeA = (int) $this->tenantStore($tenantA)->id;
        $storeB = (int) $this->tenantStore($tenantB)->id;

        $itemA = $this->createItem($tenantA, 'ONLY-A');
        $this->putJson($this->url($storeA), ['keys' => [$this->itemKey($itemA)]], $this->tenantHeaders($tenantA))->assertOk();

        // Tenant B has no item with tenant A's id: it cannot point a key at it.
        $this->assertFalse($this->inTenant($tenantB, fn (): bool => Item::query()->whereKey($itemA)->exists()));
        $this->putJson($this->url($storeB), ['keys' => [$this->itemKey($itemA)]], $this->tenantHeaders($tenantB))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['keys.0.item_id']);

        // Tenant B sees none of tenant A's keys (same numeric store id on both sides).
        $this->getJson(self::LIST_URL, $this->tenantHeaders($tenantB))->assertOk()->assertJsonCount(0, 'data');
        $this->getJson('/api/v1/pos/bootstrap', $this->tenantHeaders($tenantB))->assertOk()->assertJsonCount(0, 'data.quick_keys');

        $this->assertSame(0, $this->keyCount($tenantB));
        $this->assertSame(1, $this->keyCount($tenantA));
    }

    public function test_bootstrap_includes_keys_of_the_active_store_only(): void
    {
        $tenant = $this->createTenant();
        $mainId = (int) $this->tenantStore($tenant)->id;
        $otherId = $this->createStore($tenant, 'OTHER-01');
        $itemMain = $this->createItem($tenant, 'MAIN-ITEM', weighted: true);
        $itemOther = $this->createItem($tenant, 'OTHER-ITEM');

        $this->putJson($this->url($mainId), ['keys' => [$this->itemKey($itemMain)]], $this->tenantHeaders($tenant))->assertOk();
        $this->putJson($this->url($otherId), ['keys' => [$this->itemKey($itemOther, 5, 59)]], $this->tenantHeaders($tenant))->assertOk();

        $this->getJson('/api/v1/pos/bootstrap', $this->tenantHeaders($tenant, null, $mainId))
            ->assertOk()
            ->assertJsonCount(1, 'data.quick_keys')
            ->assertJsonPath('data.quick_keys.0.item.id', $itemMain)
            ->assertJsonPath('data.quick_keys.0.item.price', '32.500')
            ->assertJsonPath('data.quick_keys.0.item.is_weighted', true);

        $this->getJson('/api/v1/pos/bootstrap', $this->tenantHeaders($tenant, null, $otherId))
            ->assertOk()
            ->assertJsonPath('data.quick_keys.0.item.id', $itemOther)
            ->assertJsonPath('data.quick_keys.0.page', 5);

        // A cashier sending another branch's X-Store-Id is refused outright (STOR-1): nothing
        // from that branch, keys included, is returned.
        $cashier = $this->createTenantUser($tenant, 'cashier');
        $this->getJson('/api/v1/pos/bootstrap', $this->tenantHeaders($tenant, $cashier, $otherId))
            ->assertForbidden()
            ->assertJsonMissingPath('data.quick_keys');
    }
}
