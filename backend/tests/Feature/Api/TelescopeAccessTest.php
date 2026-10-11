<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Models\CentralUser;
use App\Models\Tenant;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Tests\Concerns\SeedsCentralPlatformRoles;
use Tests\TenantTestCase;

/**
 * AUTH-4: the Telescope bridge must not accept a bearer token in the query string
 * (neither /telescope-access?token= nor the viewTelescope gate). Access is granted only
 * through a short-lived, single-use signed URL issued to a central super admin.
 *
 * IDEN-1.8: the central super admin is an App\Models\CentralUser (IDEN-1.4 removed the legacy
 * App\Models\User branch). The link is issued on the control plane (Bearer central token,
 * can:super_admin.monitoring.view) and redeems into the operator's own session guard,
 * `central_web` (IDEN-1.1 / IDEN-1.7): never the tenant-side `web` guard, and never a `users`
 * row that merely shares the operator's id.
 *
 * Contract changes (intended, not weaker): a tenant token asking for a link is 401 (was 403);
 * a legacy `users`-table super admin no longer passes viewTelescope.
 */
class TelescopeAccessTest extends TenantTestCase
{
    use SeedsCentralPlatformRoles;

    private Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedCentralPlatformRoles();
        $this->tenant = $this->createTenant();
    }

    private function resetAuth(): void
    {
        $this->app['auth']->forgetGuards();
        $this->flushHeaders();
    }

    private function issueLink(CentralUser $super): string
    {
        $url = $this->postJson('/api/v1/super-admin/telescope-link', [], $this->centralHeaders($super))
            ->assertStatus(200)
            ->json('data.url');

        $this->assertIsString($url);
        $this->assertStringContainsString('/telescope-access', $url);
        $this->assertStringContainsString('signature=', $url);
        $this->assertStringContainsString('expires=', $url);

        // The issuing request authenticated the central guard in-process; drop it so the
        // follow-up proves the signed URL alone logs the browser in.
        $this->resetAuth();

        return $url;
    }

    private function assertNobodyLoggedIn(): void
    {
        $this->assertGuest('web');
        $this->assertGuest('central_web');
    }

    public function test_query_token_on_telescope_access_is_rejected_without_login(): void
    {
        $centralToken = $this->centralSuperAdmin()->createToken('sa')->plainTextToken;
        $tenantToken = $this->tenantToken($this->tenant);

        $this->get('/telescope-access?token='.urlencode($centralToken))->assertStatus(403);
        $this->get('/telescope-access?token='.urlencode($tenantToken))->assertStatus(403);

        $this->assertNobodyLoggedIn();
    }

    public function test_plaintext_api_token_on_telescope_access_is_rejected(): void
    {
        $this->get('/telescope-access?token=plain-telescope')->assertStatus(403);
        $this->get('/telescope-access?n=forged-nonce')->assertStatus(403);

        $this->assertNobodyLoggedIn();
    }

    public function test_super_admin_gets_signed_link_that_logs_in_once(): void
    {
        $super = $this->centralSuperAdmin();
        // A legacy `users` row sharing the operator's id must never be the one logged in.
        $twin = $this->legacyUsersTableSuperAdmin(['id' => $super->id]);
        $this->assertSame((int) $super->id, (int) $twin->id, 'fixture: same id in both tables');

        $url = $this->issueLink($super);

        $this->get($url)->assertRedirect('/telescope');
        $this->assertAuthenticatedAs($super, 'central_web');
        $this->assertGuest('web');

        $this->resetAuth();
        $this->app['auth']->guard('central_web')->logout();

        $this->get($url)->assertStatus(403);
        $this->assertNobodyLoggedIn();
    }

    public function test_signed_link_expires_after_sixty_seconds(): void
    {
        $url = $this->issueLink($this->centralSuperAdmin());

        $this->travel(61)->seconds();

        $this->get($url)->assertStatus(403);
        $this->assertNobodyLoggedIn();
    }

    public function test_tampered_signed_link_is_rejected(): void
    {
        $url = $this->issueLink($this->centralSuperAdmin());

        $tampered = (string) preg_replace('/([?&]n=)[^&]+/', '${1}forged-nonce', $url);
        $this->assertNotSame($url, $tampered, 'issued URL must carry the n nonce parameter');

        $this->get($tampered)->assertStatus(403);
        $this->assertNobodyLoggedIn();
    }

    public function test_non_super_admin_cannot_issue_telescope_link(): void
    {
        // Monitoring is not a support ability (raw cross-tenant data), nor a role-less one.
        foreach ([$this->centralSupport(), $this->centralOperator(null)] as $operator) {
            $this->postJson('/api/v1/super-admin/telescope-link', [], $this->centralHeaders($operator))
                ->assertStatus(403);
        }
    }

    public function test_tenant_token_cannot_issue_telescope_link(): void
    {
        // Was 403 (tenant admin authenticated by ApiTokenAuth); a tenant token is no central identity.
        $bearer = ['Accept' => 'application/json', 'Authorization' => 'Bearer '.$this->tenantToken($this->tenant)];

        $this->postJson('/api/v1/super-admin/telescope-link', [], $bearer)->assertStatus(401);
    }

    public function test_guest_cannot_issue_telescope_link(): void
    {
        $this->postJson('/api/v1/super-admin/telescope-link')->assertStatus(401);
    }

    public function test_telescope_link_on_tenant_host_is_404(): void
    {
        $this->postJson($this->tenantUrl($this->tenant, '/api/v1/super-admin/telescope-link'), [], $this->centralHeaders($this->centralSuperAdmin()))
            ->assertStatus(404);
    }

    public function test_view_telescope_gate_ignores_query_token(): void
    {
        $token = $this->centralSuperAdmin()->createToken('sa')->plainTextToken;

        $this->app->instance('request', Request::create('/telescope', 'GET', ['token' => $token]));

        $this->assertFalse(Gate::check('viewTelescope'), 'viewTelescope must not authenticate from ?token=');
        $this->assertNobodyLoggedIn();
    }

    public function test_view_telescope_gate_allows_central_super_admin_only(): void
    {
        $this->assertTrue(Gate::forUser($this->centralSuperAdmin())->check('viewTelescope'));
        $this->assertFalse(Gate::forUser($this->centralSupport())->check('viewTelescope'));
        // IDEN-1.4: the legacy `users` super admin no longer passes (it did before).
        $this->assertFalse(Gate::forUser($this->legacyUsersTableSuperAdmin())->check('viewTelescope'));

        $admin = $this->tenantAdmin($this->tenant);
        $this->assertFalse($this->inTenant($this->tenant, static fn (): bool => Gate::forUser($admin)->check('viewTelescope')));
    }

    /**
     * Gate::before (AppServiceProvider) answers true for any `admin` role before the
     * dashboard gates run, so a store admin must be stopped explicitly for viewTelescope / viewPulse.
     */
    public function test_view_pulse_gate_denies_store_admin(): void
    {
        $this->assertTrue(Gate::forUser($this->centralSuperAdmin())->check('viewPulse'));
        $this->assertFalse(Gate::forUser($this->legacyUsersTableSuperAdmin())->check('viewPulse'));

        $admin = $this->tenantAdmin($this->tenant);
        $this->assertFalse($this->inTenant($this->tenant, static fn (): bool => Gate::forUser($admin)->check('viewPulse')));
    }
}
