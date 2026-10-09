<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Models\Category;
use App\Models\Item;
use App\Models\Tenant;
use App\Models\User;
use Tests\TenantTestCase;

class CategoryApiTest extends TenantTestCase
{
    protected Tenant $tenant;

    protected User $adminUser;

    /** @var array<string, string> */
    protected array $adminHeaders;

    protected User $unauthorizedUser;

    /** @var array<string, string> */
    protected array $unauthorizedHeaders;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = $this->createTenant();
        $this->adminUser = $this->tenantAdmin($this->tenant);
        $this->adminHeaders = $this->tenantHeaders($this->tenant);

        // No role, no permissions.
        $this->unauthorizedUser = $this->createTenantUser($this->tenant, attributes: ['name' => 'مستخدم بدون صلاحية']);
        $this->unauthorizedHeaders = $this->tenantHeaders($this->tenant, $this->unauthorizedUser);
    }

    public function test_unauthenticated_request_is_rejected(): void
    {
        $response = $this->getJson('/api/v1/categories', $this->tenantGuestHeaders($this->tenant));
        $response->assertStatus(401);
    }

    public function test_unauthorized_user_cannot_create_category(): void
    {
        $response = $this->postJson('/api/v1/categories', [
            'name' => 'فئة ممنوعة',
            'icon' => '🚫',
        ], $this->unauthorizedHeaders);

        $response->assertStatus(403);
        $this->assertSame(0, $this->inTenant($this->tenant, fn (): int => Category::query()->count()));
    }

    public function test_can_list_and_create_categories(): void
    {
        $response = $this->postJson('/api/v1/categories', [
            'name' => 'مشروبات ساخنة',
            'icon' => '☕',
            'sort_order' => 1,
            'is_active' => true,
        ], $this->adminHeaders);

        $response->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.name', 'مشروبات ساخنة')
            ->assertJsonPath('data.icon', '☕');

        $this->inTenant($this->tenant, fn () => $this->assertDatabaseHas('categories', [
            'name' => 'مشروبات ساخنة',
            'icon' => '☕',
        ]));

        $listResponse = $this->getJson('/api/v1/categories', $this->adminHeaders);

        $listResponse->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonCount(1, 'data');
    }

    public function test_create_category_fails_validation_on_missing_name(): void
    {
        $response = $this->postJson('/api/v1/categories', [
            'icon' => '☕',
        ], $this->adminHeaders);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['name']);
    }

    public function test_can_update_and_delete_category(): void
    {
        [$catId, $itemId] = $this->inTenant($this->tenant, function (): array {
            $cat = Category::create([
                'name' => 'حلويات',
                'icon' => '🍰',
                'sort_order' => 2,
                'is_active' => true,
            ]);

            $item = Item::create([
                'name' => 'تشيز كيك لوتس',
                'code' => 'CAKE-001',
                'category_id' => $cat->id,
                'unit' => 'قطعة',
                'cost_price' => '30.000',
                'selling_price' => '65.000',
                'is_active' => true,
            ]);

            return [$cat->id, $item->id];
        });

        $updateResponse = $this->putJson("/api/v1/categories/{$catId}", [
            'name' => 'حلويات ومعجنات فاخرة',
            'icon' => '🥐',
        ], $this->adminHeaders);

        $updateResponse->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.name', 'حلويات ومعجنات فاخرة')
            ->assertJsonPath('data.icon', '🥐');

        $deleteResponse = $this->deleteJson("/api/v1/categories/{$catId}", [], $this->adminHeaders);

        $deleteResponse->assertStatus(200)
            ->assertJsonPath('success', true);

        $this->inTenant($this->tenant, function () use ($catId, $itemId): void {
            $this->assertSoftDeleted('categories', ['id' => $catId]);

            // Item's category_id should be safely nullified
            $this->assertNull(Item::find($itemId)->category_id);
        });
    }

    public function test_unauthorized_user_cannot_delete_category(): void
    {
        $catId = $this->inTenant($this->tenant, fn (): int => Category::create([
            'name' => 'قسم سري',
            'icon' => '🔒',
            'is_active' => true,
        ])->id);

        $response = $this->deleteJson("/api/v1/categories/{$catId}", [], $this->unauthorizedHeaders);

        $response->assertStatus(403);
        $this->inTenant($this->tenant, fn () => $this->assertNotSoftDeleted('categories', ['id' => $catId]));
    }

    public function test_categories_of_another_tenant_are_invisible_and_immutable(): void
    {
        $other = $this->createTenant();
        $foreignId = $this->inTenant($other, fn (): int => Category::create([
            'name' => 'قسم مستأجر آخر',
            'icon' => '🏷️',
            'is_active' => true,
        ])->id);

        $this->getJson('/api/v1/categories', $this->adminHeaders)
            ->assertOk()
            ->assertJsonCount(0, 'data')
            ->assertJsonMissing(['name' => 'قسم مستأجر آخر']);

        $this->putJson("/api/v1/categories/{$foreignId}", ['name' => 'اختراق'], $this->adminHeaders)->assertNotFound();
        $this->deleteJson("/api/v1/categories/{$foreignId}", [], $this->adminHeaders)->assertNotFound();

        $this->inTenant($other, fn () => $this->assertDatabaseHas('categories', [
            'id' => $foreignId,
            'name' => 'قسم مستأجر آخر',
            'deleted_at' => null,
        ]));
    }
}
