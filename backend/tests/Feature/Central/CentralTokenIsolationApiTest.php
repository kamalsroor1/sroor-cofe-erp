<?php

declare(strict_types=1);

namespace Tests\Feature\Central;

use App\Enums\CentralPermission;
use App\Models\CentralPersonalAccessToken;
use App\Models\CentralUser;
use App\Models\User;
use Database\Seeders\CentralPermissionsSeeder;
use Illuminate\Support\Str;
use Tests\Concerns\SeedsCentralPlatformRoles;
use Tests\TenantTestCase;

/**
 * IDEN-1.3: central and tenant tokens never cross.
 *  - a CentralUser token is 401 on tenant routes (ApiTokenAuth only reads Sanctum's tables);
 *  - a tenant token (or a legacy central App\Models\User Sanctum token) is 401 on the
 *    central routes (AuthenticateCentral only reads central_personal_access_tokens and only
 *    accepts a CentralUser owner).
 *
 * IDEN-1.8: the same holds for the whole /api/v1/super-admin/* control plane moved by IDEN-1.4.
 * Intended changes: a tenant token used to be 403 there (ApiTokenAuth + gate) and is now 401;
 * a tenant token WITH X-Tenant is 404 (EnsureCentralContext runs before authentication).
 */
final class CentralTokenIsolationApiTest extends TenantTestCase
{
    use SeedsCentralPlatformRoles;

    private const CENTRAL_ME = '/api/v1/super-admin/auth/me';

    private const CENTRAL_LOGOUT = '/api/v1/super-admin/auth/logout';

    /** Control-plane reads moved to routes/central.php by IDEN-1.4. */
    private const CONTROL_PLANE_READS = [
        '/api/v1/super-admin/dashboard',
        '/api/v1/super-admin/tenants',
        '/api/v1/super-admin/plans',
        '/api/v1/super-admin/settings',
        '/api/v1/super-admin/app-versions',
    ];

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(CentralPermissionsSeeder::class);
    }

    private function operatorToken(): string
    {
        $operator = CentralUser::factory()->create();
        $operator->assignRole(CentralPermission::ROLE_SUPER_ADMIN);

        return $operator->createToken('ops')->plainTextToken;
    }

    /**
     * A Phase 0 operator: App\Models\User in the central `users` table with the legacy web-guard
     * role (created by the helper: CentralPermissionsSeeder no longer seeds that role).
     */
    private function legacyCentralSuperAdmin(): User
    {
        return $this->legacyUsersTableSuperAdmin();
    }

    public function test_central_token_is_401_on_tenant_routes(): void
    {
        $tenant = $this->createTenant();
        $centralToken = $this->operatorToken();

        // Sanity: the tenant's own token works on the same route.
        $this->withHeaders($this->tenantHeaders($tenant))->getJson('/api/v1/auth/me')->assertOk();

        $headers = array_merge($this->tenantHeaders($tenant), ['Authorization' => 'Bearer '.$centralToken]);

        $this->withHeaders($headers)->getJson('/api/v1/auth/me')->assertStatus(401);
        $this->withHeaders($headers)->getJson('/api/v1/customers')->assertStatus(401);
        $this->withHeaders($headers)->getJson($this->tenantUrl($tenant, '/api/v1/auth/me'))->assertStatus(401);

        $this->assertSame(1, CentralPersonalAccessToken::query()->count(), 'Rejection must not consume the central token.');

        // Sanity the other way: the same central token does work on the control plane (absolute
        // central URL: a relative one would reuse the tenant host of the previous request; and
        // without the X-Tenant header that withHeaders() kept from the calls above).
        $this->flushHeaders();
        $this->withHeaders(['Accept' => 'application/json', 'Authorization' => 'Bearer '.$centralToken])
            ->getJson('http://localhost/api/v1/super-admin/dashboard')
            ->assertOk();
    }

    public function test_tenant_token_is_401_on_central_routes(): void
    {
        $tenant = $this->createTenant();
        $tenantToken = $this->tenantToken($tenant);

        $bare = ['Accept' => 'application/json', 'Authorization' => 'Bearer '.$tenantToken];

        $this->withHeaders($bare)->getJson(self::CENTRAL_ME)->assertStatus(401);
        $this->withHeaders($bare)->postJson(self::CENTRAL_LOGOUT)->assertStatus(401);

        // IDEN-1.4: the whole control plane refuses the tenant token the same way.
        foreach (self::CONTROL_PLANE_READS as $uri) {
            $this->withHeaders($bare)->getJson($uri)->assertStatus(401);
        }
        $this->withHeaders($bare)->postJson('/api/v1/super-admin/tenants', [])->assertStatus(401);

        // Selecting the (existing) tenant does not help: central routes never resolve tenancy.
        // Since IDEN-1.4 EnsureCentralContext refuses any tenant identifier with 404 BEFORE
        // authentication (was 401 from the authenticator): the control plane is invisible.
        $withTenant = array_merge($bare, ['X-Tenant' => (string) $tenant->getTenantKey()]);
        $this->withHeaders($withTenant)->getJson(self::CENTRAL_ME)->assertStatus(404);
        $this->withHeaders($withTenant)->getJson('/api/v1/super-admin/dashboard')->assertStatus(404);

        // The tenant token itself is untouched and still valid in its tenant.
        $this->withHeaders($this->tenantHeaders($tenant))->getJson('/api/v1/auth/me')->assertOk();
    }

    public function test_legacy_central_sanctum_token_is_401_on_central_routes(): void
    {
        $legacy = $this->legacyCentralSuperAdmin();
        $token = $legacy->createToken('legacy-super-admin', ['*'], now()->addHour())->plainTextToken;

        // IDEN-1.4: that token no longer drives the control plane either (it was 200 before).
        foreach (self::CONTROL_PLANE_READS as $uri) {
            $this->withHeaders(['Accept' => 'application/json', 'Authorization' => 'Bearer '.$token])
                ->getJson($uri)
                ->assertStatus(401);
        }

        $this->withHeaders(['Accept' => 'application/json', 'Authorization' => 'Bearer '.$token])
            ->getJson(self::CENTRAL_ME)
            ->assertStatus(401);
        $this->withHeaders(['Accept' => 'application/json', 'Authorization' => 'Bearer '.Str::after($token, '|')])
            ->getJson(self::CENTRAL_ME)
            ->assertStatus(401);
    }

    public function test_central_token_row_owned_by_a_non_central_model_is_401(): void
    {
        $legacy = $this->legacyCentralSuperAdmin();
        $plain = Str::random(40);
        $token = new CentralPersonalAccessToken;
        $token->forceFill([
            'tokenable_type' => $legacy->getMorphClass(),
            'tokenable_id' => $legacy->getKey(),
            'name' => 'forged',
            'token' => hash('sha256', $plain),
            'abilities' => ['central:*'],
            'expires_at' => now()->addHour(),
        ])->save();

        $this->withHeaders(['Accept' => 'application/json', 'Authorization' => 'Bearer '.$token->getKey().'|'.$plain])
            ->getJson(self::CENTRAL_ME)
            ->assertStatus(401);
    }
}
