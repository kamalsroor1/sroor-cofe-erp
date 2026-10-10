<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Enums\TenantStatus;
use App\Models\Tenant;
use App\Providers\TenancyServiceProvider;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Stancl\JobPipeline\JobPipeline;
use Stancl\Tenancy\Events\DatabaseDeleted;
use Stancl\Tenancy\Events\DeletingDatabase;
use Stancl\Tenancy\Events\TenantDeleted;
use Stancl\Tenancy\Jobs\DeleteDatabase;
use Tests\Concerns\SeedsCentralPlatformRoles;
use Tests\TenantTestCase;

/**
 * Regression for commit 606da74b, which stripped every $variable from
 * DeleteTenantAction and UpdateTenantDatabaseConfigAction (ParseError -> 500).
 *
 * Covers DELETE /api/v1/super-admin/tenants/{id} (disabled since OPS-11 / Q-B13: always 403,
 * nothing deleted, no DeleteDatabase job) and
 * POST /api/v1/super-admin/tenants/{id}/update-db-config.
 *
 * IDEN-1.8: operators are App\Models\CentralUser. Both routes demand a recent second factor
 * (RequireRecentTwoFactor, IDEN-1.12): without it the answer is 403
 * `central_auth.step_up_required` before the controller runs. Tenant users now get 401 (their
 * token is no central identity) where they used to get 403: intended, not weaker.
 *
 * The tenant here is a central row only (no database is created), so changing its DB
 * credentials can never touch a real database.
 */
class SuperAdminTenantMaintenanceApiTest extends TenantTestCase
{
    use SeedsCentralPlatformRoles;

    private Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedCentralPlatformRoles();

        $id = 'maint-'.Str::lower(Str::random(8));
        $this->tenant = Tenant::query()->create([
            'id' => $id,
            'name' => 'مستأجر الصيانة',
            'slug' => $id,
            'email' => $id.'@sroor.test',
            'status' => TenantStatus::Active->value,
            // No database for this row: stancl's CreateDatabase stops the pipeline on this flag.
            'tenancy_create_database' => false,
        ]);
        $this->tenant->domains()->create(['domain' => $id.'.sroor.test']);

        // Never let a DeleteDatabase pipeline touch a real database.
        Event::fake([TenantDeleted::class, DeletingDatabase::class, DatabaseDeleted::class]);
    }

    private function deleteUri(): string
    {
        return "/api/v1/super-admin/tenants/{$this->tenant->id}";
    }

    private function dbConfigUri(): string
    {
        return "/api/v1/super-admin/tenants/{$this->tenant->id}/update-db-config";
    }

    private function freshTenant(): Tenant
    {
        return Tenant::query()->findOrFail($this->tenant->id);
    }

    /** @return array<string, string> */
    private function tenantUserBearer(string $role): array
    {
        $tenant = $this->createTenant();
        $user = $role === 'admin' ? $this->tenantAdmin($tenant) : $this->createTenantUser($tenant, $role);

        return ['Accept' => 'application/json', 'Authorization' => 'Bearer '.$this->tenantToken($tenant, $user)];
    }

    private function assertTenantUntouched(): void
    {
        $this->assertDatabaseHas('tenants', ['id' => $this->tenant->id]);
        $this->assertDatabaseHas('domains', ['tenant_id' => $this->tenant->id]);
        $this->assertNull($this->freshTenant()->tenancy_db_name);
        Event::assertNotDispatched(TenantDeleted::class);
    }

    // ---------------------------------------------------------------- delete

    public function test_super_admin_delete_is_disabled_and_deletes_nothing(): void
    {
        Bus::fake();
        Queue::fake();

        $this->deleteJson($this->deleteUri(), [], $this->steppedUpCentralHeaders($this->centralSuperAdmin()))
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

    public function test_delete_without_a_recent_second_factor_is_step_up_required(): void
    {
        $operator = $this->centralSuperAdmin();

        $this->deleteJson($this->deleteUri(), [], $this->centralHeaders($operator))
            ->assertStatus(403)
            ->assertJsonPath('error_code', 'central_auth.step_up_required');

        // A proof older than central.step_up_ttl_minutes no longer counts.
        $stale = now()->subMinutes((int) config('central.step_up_ttl_minutes', 15) + 1);
        $this->deleteJson($this->deleteUri(), [], $this->steppedUpCentralHeaders($operator, $stale))
            ->assertStatus(403)
            ->assertJsonPath('error_code', 'central_auth.step_up_required');

        $this->assertTenantUntouched();
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
        $this->postJson("/api/v1/super-admin/tenants/{$this->tenant->id}/toggle-status", [
            'status' => TenantStatus::Suspended->value,
            'reason' => 'violation',
        ], $this->steppedUpCentralHeaders($this->centralSuperAdmin()))
            ->assertStatus(200)
            ->assertJson(['success' => true]);

        $this->assertSame(TenantStatus::Suspended->value, $this->freshTenant()->status);
        $this->assertTenantUntouched();
    }

    public function test_guest_cannot_delete_tenant(): void
    {
        $this->deleteJson($this->deleteUri())->assertStatus(401);
        $this->assertTenantUntouched();
    }

    /** Formerly 403 (see class docblock). */
    public function test_tenant_users_cannot_delete_tenant(): void
    {
        foreach (['cashier', 'admin'] as $role) {
            $this->deleteJson($this->deleteUri(), [], $this->tenantUserBearer($role))->assertStatus(401);
        }

        $this->assertTenantUntouched();
    }

    public function test_support_cannot_delete_tenant_even_with_a_recent_second_factor(): void
    {
        $this->deleteJson($this->deleteUri(), [], $this->steppedUpCentralHeaders($this->centralSupport()))
            ->assertStatus(403)
            ->assertJsonMissingPath('error_code');

        $this->assertTenantUntouched();
    }

    public function test_delete_from_tenant_host_is_404_even_for_super_admin(): void
    {
        $host = $this->createTenant();

        $this->deleteJson($this->tenantUrl($host, $this->deleteUri()), [], $this->steppedUpCentralHeaders($this->centralSuperAdmin()))
            ->assertStatus(404);

        $this->assertTenantUntouched();
    }

    // ------------------------------------------------------- update-db-config

    public function test_super_admin_can_update_tenant_db_config(): void
    {
        $this->postJson($this->dbConfigUri(), [
            'tenancy_db_name' => 'tenant_maint_db',
            'tenancy_db_username' => 'maint_user',
            'tenancy_db_password' => 'not-a-real-secret',
        ], $this->steppedUpCentralHeaders($this->centralSuperAdmin()))
            ->assertStatus(200)
            ->assertJson(['success' => true])
            ->assertJsonMissing(['tenancy_db_password' => 'not-a-real-secret']);

        $fresh = $this->freshTenant();
        $this->assertSame('tenant_maint_db', $fresh->tenancy_db_name);
        $this->assertSame('maint_user', $fresh->tenancy_db_username);
        $this->assertSame('not-a-real-secret', $fresh->tenancy_db_password);
    }

    public function test_update_db_config_only_changes_submitted_keys(): void
    {
        $this->tenant->update(['tenancy_db_name' => 'old_db', 'tenancy_db_username' => 'old_user']);

        $this->postJson($this->dbConfigUri(), ['tenancy_db_name' => 'tenant_new_db'], $this->steppedUpCentralHeaders($this->centralSuperAdmin()))
            ->assertStatus(200);

        $fresh = $this->freshTenant();
        $this->assertSame('tenant_new_db', $fresh->tenancy_db_name);
        $this->assertSame('old_user', $fresh->tenancy_db_username);
    }

    public function test_update_db_config_validates_input(): void
    {
        $this->postJson($this->dbConfigUri(), ['tenancy_db_name' => str_repeat('x', 101)], $this->steppedUpCentralHeaders($this->centralSuperAdmin()))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['tenancy_db_name']);

        $this->assertTenantUntouched();
    }

    public function test_update_db_config_without_a_recent_second_factor_is_step_up_required(): void
    {
        $this->postJson($this->dbConfigUri(), ['tenancy_db_name' => 'hijack'], $this->centralHeaders($this->centralSuperAdmin()))
            ->assertStatus(403)
            ->assertJsonPath('error_code', 'central_auth.step_up_required');

        $this->assertTenantUntouched();
    }

    public function test_update_db_config_of_unknown_tenant_is_404(): void
    {
        $this->postJson('/api/v1/super-admin/tenants/no-such-tenant/update-db-config', ['tenancy_db_name' => 'x'], $this->steppedUpCentralHeaders($this->centralSuperAdmin()))
            ->assertStatus(404)
            ->assertJsonPath('message', __('super.tenant_not_found'));
    }

    public function test_guest_cannot_update_db_config(): void
    {
        $this->postJson($this->dbConfigUri(), ['tenancy_db_name' => 'hijack'])->assertStatus(401);
        $this->assertTenantUntouched();
    }

    /** Formerly 403 (see class docblock). */
    public function test_tenant_users_cannot_update_db_config(): void
    {
        foreach (['cashier', 'admin'] as $role) {
            $this->postJson($this->dbConfigUri(), ['tenancy_db_name' => 'hijack'], $this->tenantUserBearer($role))->assertStatus(401);
        }

        $this->assertTenantUntouched();
    }

    public function test_support_cannot_update_db_config(): void
    {
        $this->postJson($this->dbConfigUri(), ['tenancy_db_name' => 'hijack'], $this->steppedUpCentralHeaders($this->centralSupport()))
            ->assertStatus(403);

        $this->assertTenantUntouched();
    }

    public function test_update_db_config_from_tenant_host_is_404_even_for_super_admin(): void
    {
        $host = $this->createTenant();

        $this->postJson($this->tenantUrl($host, $this->dbConfigUri()), ['tenancy_db_name' => 'hijack'], $this->steppedUpCentralHeaders($this->centralSuperAdmin()))
            ->assertStatus(404);

        $this->assertTenantUntouched();
    }
}
