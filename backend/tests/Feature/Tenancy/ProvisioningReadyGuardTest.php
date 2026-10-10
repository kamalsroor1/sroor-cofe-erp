<?php

declare(strict_types=1);

namespace Tests\Feature\Tenancy;

use App\Actions\SuperAdmin\ImpersonateTenantAction;
use App\Enums\CentralAuditEvent;
use App\Enums\TenantProvisioningStatus;
use App\Exceptions\TenantProvisioningException;
use App\Models\CentralAuditLog;
use App\Models\Setting;
use App\Models\Tenant;
use App\Services\TenantProvisionerService;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use Stancl\Tenancy\Database\Models\ImpersonationToken;
use Tests\Concerns\SeedsCentralPlatformRoles;
use Tests\TenantTestCase;

/**
 * W2 batch 4 review (B1, S1): super-admin operations that need (or would re-point) the
 * tenant database answer 409 `provisioning.workspace_not_ready` while the tenant is not
 * `ready`, and change nothing:
 *  - POST /tenants/{id}/update-db-config (would point the running job at another DB/account);
 *  - POST /tenants/{id}/run-migrations (the job migrates; a failed tenant has no DB);
 *  - POST /tenants/{id}/update-units (no tenant DB to write);
 *  - ImpersonateTenantAction (legacy, unrouted).
 * A ready tenant next to it is unaffected (isolation).
 */
final class ProvisioningReadyGuardTest extends TenantTestCase
{
    use SeedsCentralPlatformRoles;

    /** @return array<string, array{TenantProvisioningStatus}> */
    public static function notReady(): array
    {
        return [
            'pending' => [TenantProvisioningStatus::Pending],
            'running' => [TenantProvisioningStatus::Running],
            'failed' => [TenantProvisioningStatus::Failed],
        ];
    }

    #[DataProvider('notReady')]
    public function test_db_config_update_is_409_and_changes_nothing(TenantProvisioningStatus $status): void
    {
        $tenant = $this->unprovisionedTenant($status, ['tenancy_db_name' => 'tenant_qa_original']);
        $id = (string) $tenant->getTenantKey();
        $dataBefore = $this->rawData($id);

        $this->postJson("/api/v1/super-admin/tenants/{$id}/update-db-config", [
            'tenancy_db_name' => 'tenant_qa_foreign',
            'tenancy_db_username' => 'sroor_app',
            'tenancy_db_password' => 'shared-secret',
        ], $this->steppedUpCentralHeaders($this->centralSuperAdmin()))
            ->assertStatus(409)
            ->assertJsonPath('success', false)
            ->assertJsonPath('error_code', 'provisioning.workspace_not_ready')
            ->assertJsonPath('provisioning_status', $status->value)
            ->assertJsonPath('message', __('provisioning.requires_ready_workspace', ['status' => $status->label()]));

        $this->assertSame($dataBefore, $this->rawData($id));
        $this->assertSame(0, CentralAuditLog::query()->where('event', CentralAuditEvent::TenantDbConfigUpdated->value)->count());
    }

    public function test_db_config_update_still_works_on_a_ready_tenant(): void
    {
        $this->unprovisionedTenant(TenantProvisioningStatus::Running);
        $ready = $this->createTenant();

        $this->postJson("/api/v1/super-admin/tenants/{$ready->getTenantKey()}/update-db-config", [
            'tenancy_db_username' => 'ready_user',
        ], $this->steppedUpCentralHeaders($this->centralSuperAdmin()))->assertOk();

        $this->assertSame('ready_user', Tenant::query()->findOrFail($ready->getTenantKey())->tenancy_db_username);
    }

    #[DataProvider('notReady')]
    public function test_run_migrations_is_409_and_runs_nothing(TenantProvisioningStatus $status): void
    {
        $tenant = $this->unprovisionedTenant($status);

        $this->postJson("/api/v1/super-admin/tenants/{$tenant->getTenantKey()}/run-migrations", [], $this->steppedUpCentralHeaders($this->centralSuperAdmin()))
            ->assertStatus(409)
            ->assertJsonPath('error_code', 'provisioning.workspace_not_ready');

        $this->assertSame(0, CentralAuditLog::query()->where('event', CentralAuditEvent::TenantMigrationsRun->value)->count());
        $this->assertFalse($tenant->database()->manager()->databaseExists((string) $tenant->database()->getName()), 'No database was created by a migrate run.');
    }

    #[DataProvider('notReady')]
    public function test_tenant_units_update_is_409_and_saves_nothing(TenantProvisioningStatus $status): void
    {
        $tenant = $this->unprovisionedTenant($status);
        $id = (string) $tenant->getTenantKey();

        $this->postJson("/api/v1/super-admin/tenants/{$id}/update-units", ['units' => ['كجم']], $this->steppedUpCentralHeaders($this->centralSuperAdmin()))
            ->assertStatus(409)
            ->assertJsonPath('error_code', 'provisioning.workspace_not_ready');

        $this->assertNull(Tenant::query()->findOrFail($id)->getAttribute('allowed_units'));
        $this->assertSame(0, CentralAuditLog::query()->where('event', CentralAuditEvent::TenantUnitsUpdated->value)->count());
        $this->assertFalse(tenancy()->initialized);
    }

    public function test_tenant_units_update_still_works_on_a_ready_tenant(): void
    {
        $this->unprovisionedTenant(TenantProvisioningStatus::Pending);
        $ready = $this->createTenant();

        $this->postJson("/api/v1/super-admin/tenants/{$ready->getTenantKey()}/update-units", ['units' => ['كجم']], $this->steppedUpCentralHeaders($this->centralSuperAdmin()))
            ->assertOk();

        $this->assertSame('كجم', $this->inTenant($ready, fn () => Setting::query()->where('key', 'inventory_units')->value('value')));
    }

    #[DataProvider('notReady')]
    public function test_impersonation_of_a_tenant_that_is_not_ready_is_refused(TenantProvisioningStatus $status): void
    {
        $tenant = $this->unprovisionedTenant($status);

        try {
            app(ImpersonateTenantAction::class)->execute((string) $tenant->getTenantKey());
            $this->fail('Impersonating a tenant that is not ready must be refused.');
        } catch (TenantProvisioningException $e) {
            $this->assertSame(409, $e->getStatusCode());
            $this->assertSame('provisioning.workspace_not_ready', $e->errorCode());
        }

        $this->assertSame(0, ImpersonationToken::query()->count());
        $this->assertFalse(tenancy()->initialized);
    }

    // ------------------------------------------------------------------ helpers

    /**
     * A central tenant row in a non-ready provisioning state, WITHOUT a database.
     *
     * @param  array<string, mixed>  $attributes
     */
    private function unprovisionedTenant(TenantProvisioningStatus $status, array $attributes = []): Tenant
    {
        $id = 'qaguard'.Str::lower(Str::random(10));

        Tenant::query()->create(array_merge([
            'id' => $id,
            'name' => 'Guard '.$id,
            'slug' => $id,
            'email' => $id.'@harness.test',
            'status' => 'trial',
            'trial_ends_at' => now()->addDays(14),
            'enabled_features' => [],
            'provisioning_status' => $status,
            'tenancy_create_database' => false,
            'tenancy_'.TenantProvisionerService::SEED_KEY => 'sealed',
        ], $attributes));

        return Tenant::query()->findOrFail($id);
    }

    private function rawData(string $id): string
    {
        return (string) Tenant::query()->toBase()->where('id', $id)->value('data');
    }
}
