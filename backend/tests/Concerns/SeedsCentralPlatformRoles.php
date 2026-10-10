<?php

declare(strict_types=1);

namespace Tests\Concerns;

use App\Enums\CentralPermission;
use App\Models\CentralPersonalAccessToken;
use App\Models\CentralUser;
use App\Models\User;
use Carbon\CarbonInterface;
use Database\Seeders\CentralPermissionsSeeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use RuntimeException;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * IDEN-1.8: central (control-plane) identity for the super-admin tests.
 *
 * Platform operators are App\Models\CentralUser (`central_users`, guard `central`) with the
 * CentralPermissionsSeeder roles `super_admin` (everything) and `support` (read-only). The
 * Phase 0 operator (an App\Models\User in the central `users` table with a `web`-guard
 * `super_admin` role) no longer reaches /api/v1/super-admin/* (IDEN-1.4); it is kept here only
 * so tests can prove that refusal.
 *
 * Use from a test extending Tests\TenantTestCase (needs InteractsWithTenants:
 * centralSuperAdmin(), centralHeaders(), endTenancy()).
 */
trait SeedsCentralPlatformRoles
{
    /** Platform console host used by tests that turn IDEN-1.11 admin-host enforcement on. */
    protected static string $centralAdminHost = 'admin.platform-harness.test';

    /** Seed the central-guard roles/permissions (idempotent) and return the central super_admin role. */
    protected function seedCentralPlatformRoles(): Role
    {
        $this->endTenancy();

        $this->seed(CentralPermissionsSeeder::class);
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return $this->centralRole(CentralPermission::ROLE_SUPER_ADMIN);
    }

    /** A role seeded by CentralPermissionsSeeder on the `central` guard. */
    protected function centralRole(string $name): Role
    {
        return Role::query()
            ->where('name', $name)
            ->where('guard_name', CentralPermission::GUARD)
            ->firstOrFail();
    }

    /** A `web`-guard role in the CURRENT context's database (created when missing). */
    protected function webRole(string $name): Role
    {
        $role = Role::query()->firstOrCreate(['name' => $name, 'guard_name' => 'web']);
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return $role;
    }

    /**
     * An active platform operator with the given central role (null = no role at all).
     *
     * @param  array<string, mixed>  $attributes
     */
    protected function centralOperator(?string $role = CentralPermission::ROLE_SUPER_ADMIN, array $attributes = []): CentralUser
    {
        $this->seedCentralPlatformRoles();

        $user = CentralUser::factory()->create(array_merge([
            'email' => 'operator-'.Str::lower(Str::random(10)).'@central.harness.test',
        ], $attributes));

        if ($role !== null) {
            $user->assignRole($this->centralRole($role));
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return $user->refresh();
    }

    /** The read-only `support` operator. */
    protected function centralSupport(): CentralUser
    {
        return $this->centralOperator(CentralPermission::ROLE_SUPPORT);
    }

    /**
     * Bearer headers whose central token carries a second-factor proof (IDEN-1.12 step-up),
     * as if the operator had just called POST /auth/step-up. $verifiedAt defaults to now;
     * pass an older time to model a stale proof.
     *
     * @return array<string, string>
     */
    protected function steppedUpCentralHeaders(CentralUser $user, ?CarbonInterface $verifiedAt = null): array
    {
        $this->endTenancy();

        $issued = $user->createToken('harness-central-step-up');
        $token = $issued->accessToken;

        if (! $token instanceof CentralPersonalAccessToken) {
            throw new RuntimeException('Harness: a central user must issue CentralPersonalAccessToken rows.');
        }

        $token->forceFill(['two_factor_verified_at' => $verifiedAt ?? now()])->save();

        return [
            'Accept' => 'application/json',
            'Authorization' => 'Bearer '.$issued->plainTextToken,
        ];
    }

    /** Turn IDEN-1.11 admin-host enforcement on for this test (CENTRAL_ADMIN_DOMAINS). */
    protected function useCentralAdminHost(): void
    {
        config(['central.admin_domains' => [static::$centralAdminHost]]);
    }

    /** Absolute URL on the platform console host, e.g. centralAdminUrl('/api/v1/super-admin/dashboard'). */
    protected function centralAdminUrl(string $path): string
    {
        return 'http://'.static::$centralAdminHost.'/'.ltrim($path, '/');
    }

    /**
     * A Phase 0 operator: App\Models\User in the CENTRAL `users` table holding the legacy
     * `web`-guard `super_admin` role. Must be refused everywhere on the control plane.
     *
     * @param  array<string, mixed>  $attributes  e.g. ['id' => $operator->id] for id-collision tests
     */
    protected function legacyUsersTableSuperAdmin(array $attributes = []): User
    {
        $this->endTenancy();

        $role = $this->webRole(CentralPermission::ROLE_SUPER_ADMIN);

        $user = new User;
        $user->forceFill(array_merge([
            'name' => 'مشغل قديم '.Str::lower(Str::random(6)),
            'email' => 'legacy-'.Str::lower(Str::random(10)).'@central.harness.test',
            'password' => Hash::make('password'),
            'is_active' => true,
        ], $attributes))->save();
        $user->assignRole($role);
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        return $user;
    }
}
