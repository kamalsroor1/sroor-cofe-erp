<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Models\Plan;
use App\Models\Store;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\PermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Stancl\Tenancy\Events\CreatingDatabase;
use Stancl\Tenancy\Events\DatabaseCreated;
use Stancl\Tenancy\Events\DatabaseMigrated;
use Stancl\Tenancy\Events\MigratingDatabase;
use Stancl\Tenancy\Events\TenantCreated;
use Tests\Concerns\SeedsCentralPlatformRoles;
use Tests\TestCase;

/**
 * P0-AUTH-3: super-admin is decided ONLY by central identity (super_admin role),
 * never by a hardcoded phone/email allowlist.
 */
class SuperAdminBoundaryApiTest extends TestCase
{
    use RefreshDatabase;
    use SeedsCentralPlatformRoles;

    /** Former allowlist (values removed from repo); PlatformSuperAdmin is role-based, so any phone keeps these assertions meaningful. */
    private const FORMER_ALLOWLIST = ['01000000901', '01000000902'];

    protected Store $store;

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

    private function makeUser(string $phone, ?string $role, ?string $email = null): User
    {
        $user = User::factory()->create([
            'name' => 'مستخدم '.$phone,
            'phone' => $phone,
            'email' => $email ?? ('u'.$phone.'@sroor.test'),
            'password' => Hash::make('secret123'),
            'is_active' => true,
            'default_store_id' => $this->store->id,
        ]);

        if ($role !== null) {
            $user->assignRole($role);
        }

        return $user;
    }

    /** @return array<string, string> */
    private function bearer(User $user): array
    {
        return ['Authorization' => 'Bearer '.$user->createToken('t')->plainTextToken];
    }

    private function makeTenant(): Tenant
    {
        $suffix = Str::lower(Str::random(8));

        $plan = Plan::create([
            'name' => 'باقة أساسية',
            'slug' => 'basic-'.$suffix,
            'price_monthly' => '100.000',
            'price_yearly' => '1000.000',
            'max_users' => 5,
            'max_stores' => 1,
            'max_items' => 100,
            'max_invoices_per_month' => 1000,
            'is_active' => true,
            'is_popular' => false,
            'sort_order' => 1,
            'features' => ['coffee_blender' => false],
        ]);

        return Tenant::create([
            'id' => 'boundary-'.$suffix,
            'name' => 'مستأجر اختبار',
            'slug' => 'boundary-'.$suffix,
            'email' => 'tenant-'.$suffix.'@sroor.test',
            'plan_id' => $plan->id,
            'status' => 'active',
        ]);
    }

    public function test_allowlisted_phone_without_role_is_not_super_admin(): void
    {
        $user = $this->makeUser(self::FORMER_ALLOWLIST[0], 'cashier');
        $headers = $this->bearer($user);

        $this->withHeaders($headers)
            ->getJson('/api/v1/super-admin/dashboard')
            ->assertStatus(403);

        $this->withHeaders($headers)
            ->getJson('/api/v1/auth/me')
            ->assertStatus(200)
            ->assertJsonPath('data.user.is_super_admin', false);
    }

    public function test_second_allowlisted_phone_without_any_role_is_not_super_admin(): void
    {
        $user = $this->makeUser(self::FORMER_ALLOWLIST[1], null);

        $this->withHeaders($this->bearer($user))
            ->getJson('/api/v1/super-admin/tenants')
            ->assertStatus(403);
    }

    public function test_cashier_changing_phone_to_former_allowlist_does_not_escalate(): void
    {
        $cashier = $this->makeUser('01099999999', 'cashier');
        $headers = $this->bearer($cashier);

        $this->withHeaders($headers)
            ->putJson('/api/v1/profile', [
                'name' => 'كاشير يحاول التصعيد',
                'phone' => self::FORMER_ALLOWLIST[1],
                'email' => 'cashier-escalate@sroor.test',
                'theme_preference' => 'dark',
            ])
            ->assertStatus(200);

        $this->assertDatabaseHas('users', ['id' => $cashier->id, 'phone' => self::FORMER_ALLOWLIST[1]]);

        $this->withHeaders($headers)
            ->getJson('/api/v1/super-admin/tenants')
            ->assertStatus(403);

        $this->withHeaders($headers)
            ->getJson('/api/v1/auth/me')
            ->assertStatus(200)
            ->assertJsonPath('data.user.is_super_admin', false);
    }

    public function test_tenant_admin_role_cannot_toggle_tenant_status_or_override_feature(): void
    {
        $tenant = $this->makeTenant();
        $admin = $this->makeUser('01000007011', 'admin');
        $headers = $this->bearer($admin);

        $this->withHeaders($headers)
            ->postJson("/api/v1/super-admin/tenants/{$tenant->id}/toggle-status", [
                'status' => 'suspended',
                'extend_days' => 0,
            ])
            ->assertStatus(403);

        $this->withHeaders($headers)
            ->postJson("/api/v1/super-admin/tenants/{$tenant->id}/override-feature", [
                'feature_key' => 'custom_branding',
            ])
            ->assertStatus(403);

        $this->assertSame('active', $tenant->fresh()->status);
    }

    public function test_tenant_admin_with_former_allowlisted_phone_cannot_toggle_tenant_status(): void
    {
        // Real tenant DBs commonly have their owner on a former allowlisted phone with the admin role.
        $tenant = $this->makeTenant();
        $admin = $this->makeUser(self::FORMER_ALLOWLIST[0], 'admin');

        $this->withHeaders($this->bearer($admin))
            ->postJson("/api/v1/super-admin/tenants/{$tenant->id}/toggle-status", [
                'status' => 'suspended',
                'extend_days' => 0,
            ])
            ->assertStatus(403);

        $this->assertSame('active', $tenant->fresh()->status);
    }

    public function test_platform_super_admin_still_allowed(): void
    {
        $super = $this->makeUser('01000000001', 'super_admin', 'platform@sroor.test');
        $headers = $this->bearer($super);

        $this->withHeaders($headers)
            ->getJson('/api/v1/super-admin/dashboard')
            ->assertStatus(200)
            ->assertJson(['success' => true]);

        $this->withHeaders($headers)
            ->getJson('/api/v1/super-admin/tenants')
            ->assertStatus(200);

        $this->withHeaders($headers)
            ->getJson('/api/v1/auth/me')
            ->assertStatus(200)
            ->assertJsonPath('data.user.is_super_admin', true);
    }

    public function test_pulse_and_super_admin_gates_ignore_former_allowlist(): void
    {
        foreach (self::FORMER_ALLOWLIST as $phone) {
            $user = $this->makeUser($phone, 'cashier');
            $this->assertFalse(Gate::forUser($user)->allows('viewPulse'), "viewPulse must not be granted by phone {$phone}");
            $this->assertFalse(Gate::forUser($user)->allows('super_admin.access'), "super_admin.access must not be granted by phone {$phone}");
        }

        $super = $this->makeUser('01000000002', 'super_admin', 'platform2@sroor.test');
        $this->assertTrue(Gate::forUser($super)->allows('viewPulse'));
        $this->assertTrue(Gate::forUser($super)->allows('super_admin.access'));
    }

    public function test_telescope_access_bridge_rejects_former_allowlisted_phone(): void
    {
        $user = $this->makeUser(self::FORMER_ALLOWLIST[0], 'cashier');
        $token = $user->createToken('t')->plainTextToken;

        $this->get('/telescope-access?token='.urlencode($token))
            ->assertStatus(403);
    }

    public function test_telescope_access_bridge_rejects_tenant_admin_role(): void
    {
        $admin = $this->makeUser('01000007023', 'admin');
        $token = $admin->createToken('t')->plainTextToken;

        $this->get('/telescope-access?token='.urlencode($token))
            ->assertStatus(403);
    }

    public function test_telescope_access_bridge_rejects_company_email_domain(): void
    {
        $user = $this->makeUser('01000007024', 'cashier', 'someone@baraa-solutions.com');
        $token = $user->createToken('t')->plainTextToken;

        $this->get('/telescope-access?token='.urlencode($token))
            ->assertStatus(403);
    }
}
