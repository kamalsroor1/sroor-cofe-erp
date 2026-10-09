<?php

declare(strict_types=1);

namespace Tests\Feature\Central;

use App\Enums\CentralPermission;
use App\Models\CentralPersonalAccessToken;
use App\Models\CentralUser;
use App\Models\User;
use Database\Seeders\CentralPermissionsSeeder;
use Illuminate\Support\Str;
use Tests\TenantTestCase;

/**
 * IDEN-1.3: central and tenant tokens never cross.
 *  - a CentralUser token is 401 on tenant routes (ApiTokenAuth only reads Sanctum's tables);
 *  - a tenant token (or a legacy central App\Models\User Sanctum token) is 401 on the
 *    central routes (AuthenticateCentral only reads central_personal_access_tokens and only
 *    accepts a CentralUser owner).
 */
final class CentralTokenIsolationApiTest extends TenantTestCase
{
    private const CENTRAL_ME = '/api/v1/super-admin/auth/me';

    private const CENTRAL_LOGOUT = '/api/v1/super-admin/auth/logout';

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

    /** A Phase 0 operator: App\Models\User in the central `users` table with the legacy web-guard role. */
    private function legacyCentralSuperAdmin(): User
    {
        $user = new User;
        $user->forceFill([
            'name' => 'legacy operator',
            'email' => 'legacy-'.Str::lower(Str::random(8)).'@central.test',
            'password' => 'not-used',
            'is_active' => true,
        ])->save();
        $user->assignRole('super_admin');

        return $user;
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
    }

    public function test_tenant_token_is_401_on_central_routes(): void
    {
        $tenant = $this->createTenant();
        $tenantToken = $this->tenantToken($tenant);

        $bare = ['Accept' => 'application/json', 'Authorization' => 'Bearer '.$tenantToken];

        $this->withHeaders($bare)->getJson(self::CENTRAL_ME)->assertStatus(401);
        $this->withHeaders($bare)->postJson(self::CENTRAL_LOGOUT)->assertStatus(401);

        // Selecting the (existing) tenant does not help: central routes never resolve tenancy.
        $withTenant = array_merge($bare, ['X-Tenant' => (string) $tenant->getTenantKey()]);
        $this->withHeaders($withTenant)->getJson(self::CENTRAL_ME)->assertStatus(401);

        // The tenant token itself is untouched and still valid in its tenant.
        $this->withHeaders($this->tenantHeaders($tenant))->getJson('/api/v1/auth/me')->assertOk();
    }

    public function test_legacy_central_sanctum_token_is_401_on_central_routes(): void
    {
        $legacy = $this->legacyCentralSuperAdmin();
        $token = $legacy->createToken('legacy-super-admin', ['*'], now()->addHour())->plainTextToken;

        // Sanity: that token still drives the Phase 0 control plane until IDEN-1.4.
        $this->withHeaders(['Accept' => 'application/json', 'Authorization' => 'Bearer '.$token])
            ->getJson('/api/v1/super-admin/dashboard')
            ->assertOk();

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
