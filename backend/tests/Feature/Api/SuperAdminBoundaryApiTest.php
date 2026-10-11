<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Enums\CentralPermission;
use App\Enums\TenantStatus;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Spatie\Permission\Models\Role;
use Tests\Concerns\SeedsCentralPlatformRoles;
use Tests\TenantTestCase;

/**
 * P0-AUTH-3: super-admin is decided ONLY by central identity, never by a hardcoded
 * phone/email allowlist.
 *
 * IDEN-1.8: the central identity is App\Models\CentralUser (guard `central`). Tenant users
 * (any role, any phone, any email) are not central identities at all, so on the control plane
 * they now get 401 where they used to get 403 (authenticated by ApiTokenAuth, refused by the
 * gate). That is the intended IDEN-1.4 contract, not a weaker one. The tenant `auth/me` payload
 * no longer carries `is_super_admin` (IDEN-1.4).
 */
class SuperAdminBoundaryApiTest extends TenantTestCase
{
    use SeedsCentralPlatformRoles;

    /** Former allowlist (values removed from repo); PlatformSuperAdmin is role-based, so any phone keeps these assertions meaningful. */
    private const FORMER_ALLOWLIST = ['01000000901', '01000000902'];

    private Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedCentralPlatformRoles();
        $this->tenant = $this->createTenant();
    }

    private function makeUser(string $phone, ?string $role, ?string $email = null): User
    {
        return $this->createTenantUser($this->tenant, $role, [], [
            'name' => 'مستخدم '.$phone,
            'phone' => $phone,
            'email' => $email ?? ('u'.$phone.'@sroor.test'),
        ]);
    }

    /** @return array<string, string> Bearer only: the control plane never carries tenant context. */
    private function bearer(User $user): array
    {
        return ['Accept' => 'application/json', 'Authorization' => 'Bearer '.$this->tenantToken($this->tenant, $user)];
    }

    private function tenantStatus(): string
    {
        return (string) Tenant::query()->findOrFail($this->tenant->getTenantKey())->status;
    }

    public function test_allowlisted_phone_without_role_is_not_super_admin(): void
    {
        $user = $this->makeUser(self::FORMER_ALLOWLIST[0], 'cashier');

        $this->getJson('/api/v1/super-admin/dashboard', $this->bearer($user))->assertStatus(401);

        $this->getJson('/api/v1/auth/me', $this->tenantHeaders($this->tenant, $user))
            ->assertStatus(200)
            ->assertJsonMissingPath('data.user.is_super_admin');
    }

    public function test_second_allowlisted_phone_without_any_role_is_not_super_admin(): void
    {
        $user = $this->makeUser(self::FORMER_ALLOWLIST[1], null);

        $this->getJson('/api/v1/super-admin/tenants', $this->bearer($user))->assertStatus(401);
    }

    public function test_cashier_changing_phone_to_former_allowlist_does_not_escalate(): void
    {
        $cashier = $this->makeUser('01099999999', 'cashier');
        $tenantHeaders = $this->tenantHeaders($this->tenant, $cashier);
        $bearer = ['Accept' => 'application/json', 'Authorization' => $tenantHeaders['Authorization']];

        $this->putJson('/api/v1/profile', [
            'name' => 'كاشير يحاول التصعيد',
            'phone' => self::FORMER_ALLOWLIST[1],
            'email' => 'cashier-escalate@sroor.test',
            'theme_preference' => 'dark',
        ], $tenantHeaders)->assertStatus(200);

        $this->assertSame(
            self::FORMER_ALLOWLIST[1],
            $this->inTenant($this->tenant, static fn (): ?string => User::query()->whereKey($cashier->id)->value('phone')),
        );

        $this->getJson('/api/v1/super-admin/tenants', $bearer)->assertStatus(401);

        $this->getJson('/api/v1/auth/me', $tenantHeaders)
            ->assertStatus(200)
            ->assertJsonMissingPath('data.user.is_super_admin');
    }

    public function test_tenant_admin_role_cannot_toggle_tenant_status_or_override_feature(): void
    {
        $headers = $this->bearer($this->makeUser('01000007011', 'admin'));
        $id = (string) $this->tenant->getTenantKey();

        $this->postJson("/api/v1/super-admin/tenants/{$id}/toggle-status", [
            'status' => TenantStatus::Suspended->value,
            'reason' => 'other',
        ], $headers)->assertStatus(401);

        $this->postJson("/api/v1/super-admin/tenants/{$id}/override-feature", [
            'feature_key' => 'custom_branding',
        ], $headers)->assertStatus(401);

        $this->assertSame(TenantStatus::Active->value, $this->tenantStatus());
        $this->assertNotContains('custom_branding', (array) Tenant::query()->findOrFail($id)->enabled_features);
    }

    public function test_tenant_admin_with_former_allowlisted_phone_cannot_toggle_tenant_status(): void
    {
        // Real tenant DBs commonly have their owner on a former allowlisted phone with the admin role.
        $admin = $this->makeUser(self::FORMER_ALLOWLIST[0], 'admin');

        $this->postJson("/api/v1/super-admin/tenants/{$this->tenant->getTenantKey()}/toggle-status", [
            'status' => TenantStatus::Suspended->value,
            'reason' => 'other',
        ], $this->bearer($admin))->assertStatus(401);

        $this->assertSame(TenantStatus::Active->value, $this->tenantStatus());
    }

    public function test_tenant_user_with_a_super_admin_role_in_its_tenant_db_is_refused(): void
    {
        // A tenant DB seeded before P0-AUTH-4 may still hold a web-guard `super_admin` role.
        $this->inTenant($this->tenant, fn (): Role => $this->webRole('super_admin'));
        $user = $this->makeUser('01000007012', 'super_admin');

        $this->getJson('/api/v1/super-admin/dashboard', $this->bearer($user))->assertStatus(401);
        $this->postJson("/api/v1/super-admin/tenants/{$this->tenant->getTenantKey()}/toggle-status", [
            'status' => TenantStatus::Suspended->value,
            'reason' => 'other',
        ], $this->bearer($user))->assertStatus(401);

        $this->assertSame(TenantStatus::Active->value, $this->tenantStatus());
    }

    public function test_platform_super_admin_still_allowed(): void
    {
        $super = $this->centralSuperAdmin(['email' => 'platform@sroor.test']);
        $headers = $this->centralHeaders($super);

        $this->getJson('/api/v1/super-admin/dashboard', $headers)
            ->assertStatus(200)
            ->assertJson(['success' => true]);

        $this->getJson('/api/v1/super-admin/tenants', $headers)->assertStatus(200);

        $this->getJson('/api/v1/super-admin/auth/me', $headers)
            ->assertStatus(200)
            ->assertJsonPath('data.email', 'platform@sroor.test')
            ->assertJsonPath('data.roles', [CentralPermission::ROLE_SUPER_ADMIN]);
    }

    public function test_pulse_and_super_admin_gates_ignore_former_allowlist(): void
    {
        foreach (self::FORMER_ALLOWLIST as $phone) {
            $user = $this->makeUser($phone, 'cashier');

            // Tenant users are only ever authorised inside their tenant.
            $this->inTenant($this->tenant, function () use ($user, $phone): void {
                $this->assertTrue(Gate::forUser($user)->allows('pos.access'), 'fixture: the gate does see the cashier role');
                $this->assertFalse(Gate::forUser($user)->allows('viewPulse'), "viewPulse must not be granted by phone {$phone}");
                $this->assertFalse(Gate::forUser($user)->allows('super_admin.access'), "super_admin.access must not be granted by phone {$phone}");
                foreach (CentralPermission::cases() as $permission) {
                    $this->assertFalse(Gate::forUser($user)->allows($permission->value), "{$permission->value} must not be granted by phone {$phone}");
                }
            });
        }

        $super = $this->centralSuperAdmin();
        $this->assertTrue(Gate::forUser($super)->allows('viewPulse'));
        $this->assertTrue(Gate::forUser($super)->allows(CentralPermission::TenantsManage->value));
        // The Phase 0 umbrella ability is not a CentralPermission: nobody holds it any more.
        $this->assertFalse(Gate::forUser($super)->allows('super_admin.access'));
    }

    public function test_telescope_access_bridge_rejects_former_allowlisted_phone(): void
    {
        $token = $this->tenantToken($this->tenant, $this->makeUser(self::FORMER_ALLOWLIST[0], 'cashier'));

        $this->get('/telescope-access?token='.urlencode($token))->assertStatus(403);
        $this->assertGuest('web');
    }

    public function test_telescope_access_bridge_rejects_tenant_admin_role(): void
    {
        $token = $this->tenantToken($this->tenant, $this->makeUser('01000007023', 'admin'));

        $this->get('/telescope-access?token='.urlencode($token))->assertStatus(403);
        $this->assertGuest('web');
    }

    public function test_telescope_access_bridge_rejects_company_email_domain(): void
    {
        $token = $this->tenantToken($this->tenant, $this->makeUser('01000007024', 'cashier', 'someone@baraa-solutions.com'));

        $this->get('/telescope-access?token='.urlencode($token))->assertStatus(403);
        $this->assertGuest('web');
    }
}
