<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Models\Store;
use App\Models\Tenant;
use App\Models\User;
use Tests\TenantTestCase;

/**
 * W2-B3 lane 3G: POST /api/v1/stores/switch decides access by role/permission
 * (ActiveStore::canAccess: admin role, stores.manage, or an assigned/default store),
 * never by the magic "user id 1 is an admin" rule.
 */
final class StoreSwitchAccessTest extends TenantTestCase
{
    private const URL = '/api/v1/stores/switch';

    private Tenant $tenant;

    private int $branchId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = $this->createTenant();
        $this->branchId = $this->inTenant($this->tenant, static fn (): int => (int) Store::query()->create([
            'name' => 'فرع المعادي',
            'code' => 'MAADI-01',
            'type' => 'retail_shop',
            'is_main' => false,
            'is_active' => true,
        ])->getKey());
    }

    public function test_user_id_1_without_role_or_assignment_is_403(): void
    {
        // `id` is not fillable: re-key a fresh, role-less user to 1 (the harness hands out
        // offset ids, so 1 is free in the tenant DB).
        $created = $this->createTenantUser($this->tenant);
        $userOne = $this->inTenant($this->tenant, function () use ($created): User {
            User::query()->whereKey($created->getKey())->update(['id' => 1]);
            $user = User::query()->findOrFail(1);
            $this->assertFalse($user->hasRole('admin'));
            $this->assertFalse($user->can('stores.manage'));

            return $user;
        });
        $this->assertSame(1, (int) $userOne->getKey());

        $this->postJson(self::URL, ['store_id' => $this->branchId], $this->tenantHeaders($this->tenant, $userOne))
            ->assertStatus(403)
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', __('common.store_access_denied'));

        $this->assertNotSame(
            $this->branchId,
            $this->inTenant($this->tenant, static fn (): int => (int) User::query()->findOrFail(1)->default_store_id),
        );
    }

    public function test_admin_role_can_switch_to_any_store(): void
    {
        $this->postJson(self::URL, ['store_id' => $this->branchId], $this->tenantHeaders($this->tenant))
            ->assertOk()
            ->assertJsonPath('active_store.id', $this->branchId);
    }

    public function test_stores_manage_permission_can_switch_to_any_store(): void
    {
        $manager = $this->createTenantUser($this->tenant, permissions: ['stores.manage']);

        $this->postJson(self::URL, ['store_id' => $this->branchId], $this->tenantHeaders($this->tenant, $manager))
            ->assertOk()
            ->assertJsonPath('active_store.id', $this->branchId);
    }

    public function test_assigned_user_can_switch_to_the_assigned_store(): void
    {
        $cashier = $this->createTenantUser($this->tenant);
        $this->inTenant($this->tenant, fn () => User::query()->findOrFail($cashier->getKey())->stores()->attach($this->branchId));

        $this->postJson(self::URL, ['store_id' => $this->branchId], $this->tenantHeaders($this->tenant, $cashier))
            ->assertOk()
            ->assertJsonPath('active_store.id', $this->branchId);
    }

    public function test_guest_is_401(): void
    {
        $this->postJson(self::URL, ['store_id' => $this->branchId], $this->tenantGuestHeaders($this->tenant))
            ->assertUnauthorized();
    }
}
