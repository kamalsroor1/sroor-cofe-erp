<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Models\Plan;
use App\Models\Store;
use App\Models\Tenant;
use App\Models\User;
use App\Providers\TenancyServiceProvider;
use Database\Seeders\PermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Queue;
use Stancl\JobPipeline\JobPipeline;
use Stancl\Tenancy\Events\CreatingDatabase;
use Stancl\Tenancy\Events\DatabaseCreated;
use Stancl\Tenancy\Events\DatabaseDeleted;
use Stancl\Tenancy\Events\DatabaseMigrated;
use Stancl\Tenancy\Events\DeletingDatabase;
use Stancl\Tenancy\Events\MigratingDatabase;
use Stancl\Tenancy\Events\TenantCreated;
use Stancl\Tenancy\Events\TenantDeleted;
use Stancl\Tenancy\Jobs\DeleteDatabase;
use Tests\Concerns\SeedsCentralPlatformRoles;
use Tests\TestCase;

/**
 * Regression for commit 606da74b, which stripped every $variable from
 * DeleteTenantAction and UpdateTenantDatabaseConfigAction (ParseError -> 500).
 *
 * Covers DELETE /api/v1/super-admin/tenants/{id} (disabled since OPS-11 / Q-B13: always 403,
 * nothing deleted, no DeleteDatabase job) and
 * POST /api/v1/super-admin/tenants/{id}/update-db-config.
 */
class SuperAdminTenantMaintenanceApiTest extends TestCase
{
    use RefreshDatabase;
    use SeedsCentralPlatformRoles;

    private const TENANT_HOST_URL = 'http://acme.tenant-host.test';

    protected Store $store;

    protected Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();

        // Never let the DeleteDatabase / CreateDatabase job pipelines touch a real database.
        Event::fake([
            TenantCreated::class,
            CreatingDatabase::class,
            DatabaseCreated::class,
            MigratingDatabase::class,
            DatabaseMigrated::class,
            TenantDeleted::class,
            DeletingDatabase::class,
            DatabaseDeleted::class,
        ]);

        $this->seed(PermissionsSeeder::class);
        $this->seedCentralPlatformRoles();

        $this->store = Store::create([
            'name' => 'الفرع الرئيسي',
            'code' => 'MAIN',
            'is_main' => true,
            'is_active' => true,
        ]);

        $plan = Plan::create([
            'name' => 'باقة أساسية',
            'slug' => 'basic-maintenance',
            'price_monthly' => '100.000',
            'price_yearly' => '1000.000',
            'max_users' => 5,
            'max_stores' => 1,
            'max_items' => 100,
            'max_invoices_per_month' => 1000,
            'is_active' => true,
            'is_popular' => false,
            'sort_order' => 1,
            'features' => [],
        ]);

        $this->tenant = Tenant::create([
            'id' => 'maint-tenant',
            'name' => 'مستأجر الصيانة',
            'slug' => 'maint-tenant',
            'email' => 'maint@sroor.test',
            'plan_id' => $plan->id,
            'status' => 'active',
        ]);
        $this->tenant->domains()->create(['domain' => 'maint.sroor.test']);
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

    /** @return array<string, string> */
    private function bearer(User $user): array
    {
        return ['Authorization' => 'Bearer '.$user->createToken('t')->plainTextToken];
    }

    private function deleteUri(): string
    {
        return "/api/v1/super-admin/tenants/{$this->tenant->id}";
    }

    private function dbConfigUri(): string
    {
        return "/api/v1/super-admin/tenants/{$this->tenant->id}/update-db-config";
    }

    private function assertTenantUntouched(): void
    {
        $this->assertDatabaseHas('tenants', ['id' => $this->tenant->id]);
        $this->assertDatabaseHas('domains', ['tenant_id' => $this->tenant->id]);
        $this->assertNull($this->tenant->fresh()->tenancy_db_name);
        Event::assertNotDispatched(TenantDeleted::class);
    }

    // ---------------------------------------------------------------- delete

    public function test_super_admin_delete_is_disabled_and_deletes_nothing(): void
    {
        Bus::fake();
        Queue::fake();
        $super = $this->makeUser('01000007032', 'super_admin');

        $this->withHeaders($this->bearer($super))
            ->deleteJson($this->deleteUri())
            ->assertStatus(403)
            ->assertJson([
                'success' => false,
                'message' => __('super.tenant_delete_disabled'),
            ]);

        $this->assertTenantUntouched();
        $this->assertSame(1, $this->tenant->domains()->count());
        Event::assertNotDispatched(DeletingDatabase::class);
        Event::assertNotDispatched(DatabaseDeleted::class);
        Bus::assertNotDispatched(DeleteDatabase::class);
        Queue::assertNotPushed(DeleteDatabase::class);
    }

    public function test_delete_disabled_message_exists_in_arabic_and_english(): void
    {
        $this->assertSame('حذف المستأجر متوقف مؤقتًا — استخدم الإيقاف', __('super.tenant_delete_disabled', [], 'ar'));
        $this->assertSame('Tenant deletion is temporarily disabled — suspend the tenant instead', __('super.tenant_delete_disabled', [], 'en'));
    }

    public function test_tenant_deleted_pipeline_never_drops_a_database(): void
    {
        $provider = new TenancyServiceProvider($this->app);

        foreach ($provider->events() as $event => $listeners) {
            foreach ($listeners as $listener) {
                if ($listener instanceof JobPipeline) {
                    $this->assertNotContains(DeleteDatabase::class, $listener->jobs, "DeleteDatabase is wired to {$event}");
                }
            }
        }
    }

    public function test_super_admin_can_still_suspend_tenant(): void
    {
        $super = $this->makeUser('01000007043', 'super_admin');

        $this->withHeaders($this->bearer($super))
            ->postJson("/api/v1/super-admin/tenants/{$this->tenant->id}/toggle-status", ['status' => 'suspended'])
            ->assertStatus(200)
            ->assertJson(['success' => true]);

        $this->assertSame('suspended', $this->tenant->fresh()->status);
        $this->assertTenantUntouched();
    }

    public function test_guest_cannot_delete_tenant(): void
    {
        $this->deleteJson($this->deleteUri())->assertStatus(401);
        $this->assertTenantUntouched();
    }

    public function test_tenant_users_cannot_delete_tenant(): void
    {
        foreach (['cashier' => '01000007033', 'admin' => '01000007034'] as $role => $phone) {
            $this->withHeaders($this->bearer($this->makeUser($phone, $role)))
                ->deleteJson($this->deleteUri())
                ->assertStatus(403);
        }

        $this->assertTenantUntouched();
    }

    public function test_delete_from_tenant_host_is_404_even_for_super_admin(): void
    {
        $super = $this->makeUser('01000007035', 'super_admin');

        $this->withHeaders($this->bearer($super))
            ->deleteJson(self::TENANT_HOST_URL.$this->deleteUri())
            ->assertStatus(404);

        $this->assertTenantUntouched();
    }

    // ------------------------------------------------------- update-db-config

    public function test_super_admin_can_update_tenant_db_config(): void
    {
        $super = $this->makeUser('01000007036', 'super_admin');

        $this->withHeaders($this->bearer($super))
            ->postJson($this->dbConfigUri(), [
                'tenancy_db_name' => 'tenant_maint_db',
                'tenancy_db_username' => 'maint_user',
                'tenancy_db_password' => 'not-a-real-secret',
            ])
            ->assertStatus(200)
            ->assertJson(['success' => true])
            ->assertJsonMissing(['tenancy_db_password' => 'not-a-real-secret']);

        $fresh = $this->tenant->fresh();
        $this->assertSame('tenant_maint_db', $fresh->tenancy_db_name);
        $this->assertSame('maint_user', $fresh->tenancy_db_username);
        $this->assertSame('not-a-real-secret', $fresh->tenancy_db_password);
    }

    public function test_update_db_config_only_changes_submitted_keys(): void
    {
        $this->tenant->update(['tenancy_db_name' => 'old_db', 'tenancy_db_username' => 'old_user']);
        $super = $this->makeUser('01000007037', 'super_admin');

        $this->withHeaders($this->bearer($super))
            ->postJson($this->dbConfigUri(), ['tenancy_db_name' => 'new_db'])
            ->assertStatus(200);

        $fresh = $this->tenant->fresh();
        $this->assertSame('new_db', $fresh->tenancy_db_name);
        $this->assertSame('old_user', $fresh->tenancy_db_username);
    }

    public function test_update_db_config_validates_input(): void
    {
        $super = $this->makeUser('01000007038', 'super_admin');

        $this->withHeaders($this->bearer($super))
            ->postJson($this->dbConfigUri(), ['tenancy_db_name' => str_repeat('x', 101)])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['tenancy_db_name']);
    }

    public function test_guest_cannot_update_db_config(): void
    {
        $this->postJson($this->dbConfigUri(), ['tenancy_db_name' => 'hijack'])->assertStatus(401);
        $this->assertTenantUntouched();
    }

    public function test_tenant_users_cannot_update_db_config(): void
    {
        foreach (['cashier' => '01000007039', 'admin' => '01000007040'] as $role => $phone) {
            $this->withHeaders($this->bearer($this->makeUser($phone, $role)))
                ->postJson($this->dbConfigUri(), ['tenancy_db_name' => 'hijack'])
                ->assertStatus(403);
        }

        $this->assertTenantUntouched();
    }

    public function test_update_db_config_from_tenant_host_is_404_even_for_super_admin(): void
    {
        $super = $this->makeUser('01000007041', 'super_admin');

        $this->withHeaders($this->bearer($super))
            ->postJson(self::TENANT_HOST_URL.$this->dbConfigUri(), ['tenancy_db_name' => 'hijack'])
            ->assertStatus(404);

        $this->assertTenantUntouched();
    }
}
