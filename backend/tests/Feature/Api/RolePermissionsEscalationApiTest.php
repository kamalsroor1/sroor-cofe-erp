<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Models\ActivityLog;
use App\Models\Tenant;
use App\Models\User;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TenantTestCase;

/** SEC-04: a roles.manage delegate cannot escalate privileges through the role-permission editor. */
class RolePermissionsEscalationApiTest extends TenantTestCase
{
    protected Tenant $tenant;

    protected User $manager;

    protected int $cashierRoleId;

    protected int $accountantRoleId;

    protected int $adminRoleId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = $this->createTenant();
        $this->inTenant($this->tenant, function (): void {
            Permission::findOrCreate('zz.held', 'web');
            Permission::findOrCreate('zz.other_held', 'web');
            Permission::findOrCreate('zz.not_held', 'web');
        });
        // The manager holds `accountant` as a role plus direct permissions.
        $this->manager = $this->createTenantUser($this->tenant, 'accountant', ['roles.manage', 'zz.held', 'zz.other_held']);
        [$this->cashierRoleId, $this->accountantRoleId, $this->adminRoleId] = $this->inTenant(
            $this->tenant,
            fn (): array => [
                (int) Role::findByName('cashier')->id,
                (int) Role::findByName('accountant')->id,
                (int) Role::findByName('admin')->id,
            ]
        );
    }

    private function managerHeaders(): array
    {
        return $this->tenantHeaders($this->tenant, $this->manager);
    }

    private function putPerms(int $roleId, array $permissions, ?array $headers = null)
    {
        return $this->putJson("/api/v1/roles/{$roleId}/permissions", ['permissions' => $permissions], $headers ?? $this->managerHeaders());
    }

    /** @return list<string> */
    private function perms(int $roleId): array
    {
        return $this->inTenant($this->tenant, function () use ($roleId): array {
            app(PermissionRegistrar::class)->forgetCachedPermissions();
            $names = Role::findById($roleId)->permissions()->pluck('name')->all();
            sort($names);

            return $names;
        });
    }

    private function setPerms(int $roleId, array $names): void
    {
        $this->inTenant($this->tenant, function () use ($roleId, $names): void {
            Role::findById($roleId)->syncPermissions($names);
            app(PermissionRegistrar::class)->forgetCachedPermissions();
        });
    }

    public function test_admin_can_grant_any_permission(): void
    {
        $this->putPerms($this->cashierRoleId, ['pos.access', 'zz.not_held'], $this->tenantHeaders($this->tenant))
            ->assertOk();

        $this->assertSame(['pos.access', 'zz.not_held'], $this->perms($this->cashierRoleId));
    }

    public function test_manager_can_grant_a_permission_they_hold_and_it_is_audited(): void
    {
        $this->setPerms($this->cashierRoleId, ['pos.access']);

        $this->putPerms($this->cashierRoleId, ['pos.access', 'zz.held'])->assertOk();

        $this->assertSame(['pos.access', 'zz.held'], $this->perms($this->cashierRoleId));

        $log = $this->inTenant($this->tenant, fn () => ActivityLog::query()->where('module', 'roles')->where('action', 'permissions_updated')->latest('id')->first());
        $this->assertNotNull($log);
        $this->assertSame(['zz.held'], $log->properties['added']);
        $this->assertSame([], $log->properties['removed']);
    }

    public function test_manager_cannot_grant_a_permission_they_do_not_hold(): void
    {
        $this->setPerms($this->cashierRoleId, ['pos.access']);

        $this->putPerms($this->cashierRoleId, ['pos.access', 'zz.not_held'])
            ->assertStatus(403)
            ->assertJson(['success' => false, 'message' => __('roles.cannot_grant_unheld_permission')]);

        $this->assertSame(['pos.access'], $this->perms($this->cashierRoleId));
    }

    public function test_manager_cannot_edit_a_role_they_hold(): void
    {
        $before = $this->perms($this->accountantRoleId);

        $this->putPerms($this->accountantRoleId, ['zz.held'])
            ->assertStatus(403)
            ->assertJson(['success' => false, 'message' => __('roles.cannot_edit_own_role')]);

        $this->assertSame($before, $this->perms($this->accountantRoleId));
    }

    public function test_manager_cannot_edit_the_admin_role(): void
    {
        $before = $this->perms($this->adminRoleId);

        $this->putPerms($this->adminRoleId, [])
            ->assertStatus(403)
            ->assertJson(['message' => __('roles.cannot_edit_admin_role')]);

        $this->assertSame($before, $this->perms($this->adminRoleId));
    }

    public function test_manager_can_remove_permissions_they_do_not_hold(): void
    {
        $this->setPerms($this->cashierRoleId, ['pos.access', 'zz.not_held']);

        $this->putPerms($this->cashierRoleId, ['pos.access'])->assertOk();

        $this->assertSame(['pos.access'], $this->perms($this->cashierRoleId));
    }

    public function test_manager_can_keep_an_existing_unheld_permission_while_adding_a_held_one(): void
    {
        $this->setPerms($this->cashierRoleId, ['zz.not_held']);

        $this->putPerms($this->cashierRoleId, ['zz.not_held', 'zz.held'])->assertOk();

        $this->assertSame(['zz.held', 'zz.not_held'], $this->perms($this->cashierRoleId));
    }

    public function test_unauthenticated_request_is_rejected(): void
    {
        $this->putJson("/api/v1/roles/{$this->cashierRoleId}/permissions", ['permissions' => []], $this->tenantGuestHeaders($this->tenant))
            ->assertStatus(401);
    }

    public function test_user_without_roles_manage_is_forbidden(): void
    {
        $user = $this->createTenantUser($this->tenant, 'cashier');
        $before = $this->perms($this->cashierRoleId);

        $this->putPerms($this->accountantRoleId, ['zz.held'], $this->tenantHeaders($this->tenant, $user))->assertStatus(403);
        $this->assertSame($before, $this->perms($this->cashierRoleId));
    }

    public function test_invalid_and_super_admin_permission_names_are_rejected(): void
    {
        $this->putPerms($this->cashierRoleId, ['no_such_permission'])->assertStatus(422);
        $this->putPerms($this->cashierRoleId, ['super_admin.tenants.manage'])->assertStatus(422);
    }

    private function makeRole(string $name, array $permissions): void
    {
        $this->inTenant($this->tenant, function () use ($name, $permissions): void {
            Role::findOrCreate($name, 'web')->syncPermissions($permissions);
            app(PermissionRegistrar::class)->forgetCachedPermissions();
        });
    }

    private function userPayload(string $role, string $phone): array
    {
        return ['name' => 'Delegate Made', 'phone' => $phone, 'password' => 'secret123', 'role' => $role, 'is_active' => true];
    }

    private function userExists(string $phone): bool
    {
        return $this->inTenant($this->tenant, fn (): bool => User::query()->where('phone', $phone)->exists());
    }

    /** @return list<string> */
    private function roleNamesOf(int $userId): array
    {
        return $this->inTenant($this->tenant, fn (): array => User::findOrFail($userId)->getRoleNames()->all());
    }

    public function test_delegate_cannot_create_user_with_role_holding_unheld_permissions(): void
    {
        $this->makeRole('zz_unheld_role', ['zz.held', 'zz.not_held']);

        $this->postJson('/api/v1/users', $this->userPayload('zz_unheld_role', '01000009001'), $this->managerHeaders())
            ->assertStatus(403)
            ->assertJson(['success' => false, 'message' => __('roles.cannot_grant_unheld_permission')]);

        $this->assertFalse($this->userExists('01000009001'));
    }

    public function test_delegate_can_create_user_with_role_whose_permissions_are_all_held(): void
    {
        $this->makeRole('zz_held_role', ['zz.held', 'zz.other_held']);

        $this->postJson('/api/v1/users', $this->userPayload('zz_held_role', '01000009002'), $this->managerHeaders())
            ->assertStatus(201);

        $this->assertTrue($this->userExists('01000009002'));
    }

    public function test_admin_can_create_user_with_any_role(): void
    {
        $this->makeRole('zz_unheld_role', ['zz.not_held']);

        $this->postJson('/api/v1/users', $this->userPayload('zz_unheld_role', '01000009003'), $this->tenantHeaders($this->tenant))
            ->assertStatus(201);
    }

    public function test_delegate_cannot_move_another_user_to_role_with_unheld_permissions(): void
    {
        $this->makeRole('zz_unheld_role', ['zz.not_held']);
        $target = $this->createTenantUser($this->tenant, 'cashier');

        $this->putJson("/api/v1/users/{$target->id}", [
            'name' => $target->name, 'phone' => $target->phone, 'email' => $target->email,
            'role' => 'zz_unheld_role', 'is_active' => true,
        ], $this->managerHeaders())
            ->assertStatus(403)
            ->assertJson(['success' => false, 'message' => __('roles.cannot_grant_unheld_permission')]);

        $this->assertSame(['cashier'], $this->roleNamesOf($target->id));
    }

    public function test_delegate_can_move_another_user_to_role_with_held_permissions_and_admin_can_to_any(): void
    {
        $this->makeRole('zz_held_role', ['zz.held']);
        $this->makeRole('zz_unheld_role', ['zz.not_held']);
        $target = $this->createTenantUser($this->tenant, 'cashier');
        $payload = fn (string $role): array => [
            'name' => $target->name, 'phone' => $target->phone, 'email' => $target->email,
            'role' => $role, 'is_active' => true,
        ];

        $this->putJson("/api/v1/users/{$target->id}", $payload('zz_held_role'), $this->managerHeaders())->assertOk();
        $this->assertSame(['zz_held_role'], $this->roleNamesOf($target->id));

        $this->putJson("/api/v1/users/{$target->id}", $payload('zz_unheld_role'), $this->tenantHeaders($this->tenant))->assertOk();
        $this->assertSame(['zz_unheld_role'], $this->roleNamesOf($target->id));
    }

    public function test_unrelated_authorization_failures_keep_default_shape(): void
    {
        $user = $this->createTenantUser($this->tenant, 'cashier');

        $this->putJson("/api/v1/roles/{$this->cashierRoleId}/permissions", ['permissions' => []], $this->tenantHeaders($this->tenant, $user))
            ->assertStatus(403);
    }

    public function test_no_activity_log_when_permissions_unchanged(): void
    {
        $this->setPerms($this->cashierRoleId, ['pos.access']);

        $this->putPerms($this->cashierRoleId, ['pos.access'])->assertOk();

        $count = $this->inTenant($this->tenant, fn () => ActivityLog::query()->where('module', 'roles')->where('action', 'permissions_updated')->count());
        $this->assertSame(0, $count);
    }
}
