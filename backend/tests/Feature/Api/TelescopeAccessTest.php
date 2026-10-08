<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Models\Store;
use App\Models\User;
use Database\Seeders\PermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Hash;
use Stancl\Tenancy\Events\CreatingDatabase;
use Stancl\Tenancy\Events\DatabaseCreated;
use Stancl\Tenancy\Events\DatabaseMigrated;
use Stancl\Tenancy\Events\MigratingDatabase;
use Stancl\Tenancy\Events\TenantCreated;
use Tests\Concerns\SeedsCentralPlatformRoles;
use Tests\TestCase;

/**
 * AUTH-4: the Telescope bridge must not accept a bearer token in the query string
 * (neither /telescope-access?token= nor the viewTelescope gate). Access is granted only
 * through a short-lived, single-use signed URL issued to a central super admin.
 */
class TelescopeAccessTest extends TestCase
{
    use RefreshDatabase;
    use SeedsCentralPlatformRoles;

    private const TENANT_HOST_URL = 'http://acme.tenant-host.test';

    private Store $store;

    protected function setUp(): void
    {
        parent::setUp();

        Event::fake([
            TenantCreated::class,
            CreatingDatabase::class,
            DatabaseCreated::class,
            MigratingDatabase::class,
            DatabaseMigrated::class,
        ]);

        $this->seed(PermissionsSeeder::class);
        $this->seedCentralPlatformRoles();

        $this->store = Store::create([
            'name' => 'الفرع الرئيسي',
            'code' => 'MAIN',
            'is_main' => true,
            'is_active' => true,
        ]);
    }

    private function makeUser(string $phone, string $role): User
    {
        $user = User::factory()->create([
            'name' => 'مستخدم '.$role,
            'phone' => $phone,
            'email' => $role.$phone.'@sroor.test',
            'password' => Hash::make('secret123'),
            'is_active' => true,
            'default_store_id' => $this->store->id,
        ]);
        $user->assignRole($role);

        return $user;
    }

    private function resetAuth(): void
    {
        $this->app['auth']->forgetGuards();
        $this->flushHeaders();
    }

    private function issueLink(User $super): string
    {
        $token = $super->createToken('sa')->plainTextToken;

        $url = $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/v1/super-admin/telescope-link')
            ->assertStatus(200)
            ->json('data.url');

        $this->assertIsString($url);
        $this->assertStringContainsString('/telescope-access', $url);
        $this->assertStringContainsString('signature=', $url);
        $this->assertStringContainsString('expires=', $url);

        // The issuing request authenticated the API guard in-process; drop it so the
        // follow-up proves the signed URL alone logs the browser in.
        $this->resetAuth();

        return $url;
    }

    public function test_query_token_on_telescope_access_is_rejected_without_login(): void
    {
        $super = $this->makeUser('01000004001', 'super_admin');
        $token = $super->createToken('sa')->plainTextToken;

        $this->get('/telescope-access?token='.urlencode($token))->assertStatus(403);

        $this->assertGuest('web');
    }

    public function test_plaintext_api_token_on_telescope_access_is_rejected(): void
    {
        $super = $this->makeUser('01000004002', 'super_admin');
        $super->forceFill(['api_token' => 'plain-telescope'])->save();

        $this->get('/telescope-access?token=plain-telescope')->assertStatus(403);

        $this->assertGuest('web');
    }

    public function test_super_admin_gets_signed_link_that_logs_in_once(): void
    {
        $super = $this->makeUser('01000004003', 'super_admin');
        $url = $this->issueLink($super);

        $this->get($url)->assertRedirect('/telescope');
        $this->assertAuthenticatedAs($super, 'web');

        $this->resetAuth();
        $this->app['auth']->guard('web')->logout();

        $this->get($url)->assertStatus(403);
        $this->assertGuest('web');
    }

    public function test_signed_link_expires_after_sixty_seconds(): void
    {
        $super = $this->makeUser('01000004004', 'super_admin');
        $url = $this->issueLink($super);

        $this->travel(61)->seconds();

        $this->get($url)->assertStatus(403);
        $this->assertGuest('web');
    }

    public function test_tampered_signed_link_is_rejected(): void
    {
        $super = $this->makeUser('01000004005', 'super_admin');
        $url = $this->issueLink($super);

        $tampered = (string) preg_replace('/([?&]n=)[^&]+/', '${1}forged-nonce', $url);
        $this->assertNotSame($url, $tampered, 'issued URL must carry the n nonce parameter');

        $this->get($tampered)->assertStatus(403);
        $this->assertGuest('web');
    }

    public function test_non_super_admin_cannot_issue_telescope_link(): void
    {
        $admin = $this->makeUser('01000004006', 'admin');
        $token = $admin->createToken('a')->plainTextToken;

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/v1/super-admin/telescope-link')
            ->assertStatus(403);
    }

    public function test_guest_cannot_issue_telescope_link(): void
    {
        $this->postJson('/api/v1/super-admin/telescope-link')->assertStatus(401);
    }

    public function test_telescope_link_on_tenant_host_is_404(): void
    {
        $super = $this->makeUser('01000004007', 'super_admin');
        $token = $super->createToken('sa')->plainTextToken;

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson(self::TENANT_HOST_URL.'/api/v1/super-admin/telescope-link')
            ->assertStatus(404);
    }

    public function test_view_telescope_gate_ignores_query_token(): void
    {
        $super = $this->makeUser('01000004008', 'super_admin');
        $token = $super->createToken('sa')->plainTextToken;

        $this->app->instance('request', Request::create('/telescope', 'GET', ['token' => $token]));

        $this->assertFalse(Gate::check('viewTelescope'), 'viewTelescope must not authenticate from ?token=');
        $this->assertGuest('web');
    }

    public function test_view_telescope_gate_allows_session_super_admin_only(): void
    {
        $super = $this->makeUser('01000004009', 'super_admin');
        $admin = $this->makeUser('01000004010', 'admin');

        $this->assertTrue(Gate::forUser($super)->check('viewTelescope'));
        $this->assertFalse(Gate::forUser($admin)->check('viewTelescope'));
    }

    /**
     * Gate::before (AppServiceProvider) answers true for any `admin` role before the
     * dashboard gates run, so a store admin passes viewTelescope / viewPulse.
     */
    public function test_view_pulse_gate_denies_store_admin(): void
    {
        $super = $this->makeUser('01000004011', 'super_admin');
        $admin = $this->makeUser('01000004012', 'admin');

        $this->assertTrue(Gate::forUser($super)->check('viewPulse'));
        $this->assertFalse(Gate::forUser($admin)->check('viewPulse'));
    }
}
