<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Models\Item;
use App\Models\ReturnDocument;
use App\Models\Tenant;
use App\Models\User;
use Tests\TenantTestCase;

class TrashApiTest extends TenantTestCase
{
    protected Tenant $tenant;

    protected User $adminUser;

    /** @var array<string, string> */
    protected array $adminHeaders;

    protected User $unauthorizedUser;

    /** @var array<string, string> */
    protected array $unauthorizedHeaders;

    protected int $storeId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = $this->createTenant();
        $this->storeId = (int) $this->tenantStore($this->tenant)->id;
        $this->adminUser = $this->tenantAdmin($this->tenant);
        $this->adminHeaders = $this->tenantHeaders($this->tenant);

        $this->unauthorizedUser = $this->createTenantUser($this->tenant, attributes: ['name' => 'مستخدم بدون صلاحيات']);
        $this->unauthorizedHeaders = $this->tenantHeaders($this->tenant, $this->unauthorizedUser);
    }

    public function test_unauthenticated_request_is_rejected(): void
    {
        $response = $this->getJson('/api/v1/trash', $this->tenantGuestHeaders($this->tenant));
        $response->assertStatus(401);
    }

    public function test_unauthorized_user_cannot_access_trash(): void
    {
        $response = $this->getJson('/api/v1/trash', $this->unauthorizedHeaders);

        $response->assertStatus(403);
    }

    public function test_can_list_trashed_records_and_counts(): void
    {
        $this->inTenant($this->tenant, fn () => $this->makeTrashedItem('صنف محذوف', 'DEL-01'));

        $response = $this->getJson('/api/v1/trash?tab=items', $this->adminHeaders);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'tab',
                'data',
                'counts' => ['items', 'customers', 'suppliers'],
                'pagination',
            ])
            ->assertJson([
                'success' => true,
                'tab' => 'items',
                'counts' => [
                    'items' => 1,
                ],
            ]);
    }

    public function test_trashed_returns_show_the_return_total_amount(): void
    {
        $this->inTenant($this->tenant, function (): void {
            $return = ReturnDocument::create([
                'return_number' => 'RET-TRASH-01',
                'return_type' => 'sales_return',
                'user_id' => $this->adminUser->id,
                'store_id' => $this->storeId,
                'total_amount' => '150.500',
                'return_date' => now()->toDateString(),
            ]);
            $return->delete();
        });

        $response = $this->getJson('/api/v1/trash?tab=returns', $this->adminHeaders);

        $response->assertStatus(200)
            ->assertJsonPath('data.0.title', 'RET-TRASH-01');
        $this->assertStringStartsWith('150.500', $response->json('data.0.subtitle'));
    }

    public function test_can_restore_trashed_item(): void
    {
        $itemId = $this->inTenant($this->tenant, function (): int {
            $id = $this->makeTrashedItem('صنف للاسترجاع', 'REST-01');
            $this->assertSoftDeleted('items', ['id' => $id]);

            return $id;
        });

        $response = $this->postJson("/api/v1/trash/items/{$itemId}/restore", [], $this->adminHeaders);

        $response->assertStatus(200)
            ->assertJson(['success' => true]);

        $this->inTenant($this->tenant, fn () => $this->assertNotSoftDeleted('items', ['id' => $itemId]));
    }

    public function test_can_force_delete_trashed_item(): void
    {
        $itemId = $this->inTenant($this->tenant, fn (): int => $this->makeTrashedItem('صنف للحذف النهائي', 'FORCE-01'));

        $response = $this->deleteJson("/api/v1/trash/items/{$itemId}/force", [], $this->adminHeaders);

        $response->assertStatus(200)
            ->assertJson(['success' => true]);

        $this->inTenant($this->tenant, fn () => $this->assertDatabaseMissing('items', ['id' => $itemId]));
    }

    public function test_invalid_trash_type_returns_error(): void
    {
        $response = $this->postJson('/api/v1/trash/invalid_type/999/restore', [], $this->adminHeaders);

        $response->assertStatus(422);
    }

    public function test_trash_of_another_tenant_is_invisible_and_cannot_be_restored_or_purged(): void
    {
        $other = $this->createTenant();
        $foreignId = $this->inTenant($other, fn (): int => $this->makeTrashedItem('صنف محذوف لمستأجر آخر', 'DEL-OTHER'));

        $this->getJson('/api/v1/trash?tab=items', $this->adminHeaders)
            ->assertOk()
            ->assertJsonPath('counts.items', 0)
            ->assertJsonCount(0, 'data');

        // Not found in this tenant's DB. (Today the controller maps that to 422, not 404.)
        $restore = $this->postJson("/api/v1/trash/items/{$foreignId}/restore", [], $this->adminHeaders);
        $this->assertContains($restore->status(), [404, 422]);
        $restore->assertJsonPath('success', false);

        $purge = $this->deleteJson("/api/v1/trash/items/{$foreignId}/force", [], $this->adminHeaders);
        $this->assertContains($purge->status(), [404, 422]);
        $purge->assertJsonPath('success', false);

        $this->inTenant($other, fn () => $this->assertSoftDeleted('items', ['id' => $foreignId]));
    }

    /** Runs inside the tenant. */
    private function makeTrashedItem(string $name, string $code): int
    {
        $item = Item::create([
            'name' => $name,
            'code' => $code,
            'category' => 'coffee_beans',
            'cost_price' => '100.000',
            'selling_price' => '150.000',
            'current_stock' => '0.000',
            'min_stock_level' => '5.000',
            'is_active' => true,
        ]);
        $item->delete();

        return $item->id;
    }
}
