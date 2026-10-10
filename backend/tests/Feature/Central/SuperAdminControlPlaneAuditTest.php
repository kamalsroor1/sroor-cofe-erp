<?php

declare(strict_types=1);

namespace Tests\Feature\Central;

use App\Enums\CentralAuditEvent;
use App\Models\AppVersion;
use App\Models\CentralAuditLog;
use App\Models\CentralUser;
use App\Models\Plan;
use App\Models\Tenant;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Tests\Concerns\SeedsCentralPlatformRoles;
use Tests\TenantTestCase;

/**
 * W2-B3 lane 3G (security audit item B): every write of the legacy control plane leaves a
 * central audit row with the operator as causer, never a password, and the responses no
 * longer leak the raw Tenant model or the raw Artisan output.
 */
final class SuperAdminControlPlaneAuditTest extends TenantTestCase
{
    use SeedsCentralPlatformRoles;

    private const DB_PASSWORD = 'fixture-db-password-not-a-secret';

    private CentralUser $operator;

    /** @var array<string, string> */
    private array $headers;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedCentralPlatformRoles();
        $this->operator = $this->centralSuperAdmin();
        $this->headers = $this->steppedUpCentralHeaders($this->operator);
    }

    private function auditRow(CentralAuditEvent $event): CentralAuditLog
    {
        $log = CentralAuditLog::query()->where('event', $event->value)->sole();
        $this->assertSame(CentralUser::class, $log->causer_type);
        $this->assertSame((int) $this->operator->getKey(), (int) $log->causer_id);

        return $log;
    }

    public function test_platform_settings_and_system_units_are_audited(): void
    {
        $this->postJson('/api/v1/super-admin/settings', ['platform_name' => 'منصة سرور'], $this->headers)->assertOk();
        $settings = $this->auditRow(CentralAuditEvent::PlatformSettingsUpdated);
        $this->assertSame(['name'], $settings->properties['changed'] ?? null);

        $this->postJson('/api/v1/super-admin/units', ['units' => ['كجم', 'جرام']], $this->headers)->assertOk();
        $units = $this->auditRow(CentralAuditEvent::PlatformUnitsUpdated);
        $this->assertSame(['كجم', 'جرام'], $units->properties['units'] ?? null);
    }

    public function test_tenant_units_and_feature_override_are_audited(): void
    {
        $tenant = $this->createTenant();
        $id = (string) $tenant->getTenantKey();

        $this->postJson("/api/v1/super-admin/tenants/{$id}/update-units", ['units' => ['كجم']], $this->headers)
            ->assertOk()
            ->assertJsonPath('allowed_units', ['كجم']);
        $this->assertSame($id, $this->auditRow(CentralAuditEvent::TenantUnitsUpdated)->tenant_id);

        $this->postJson("/api/v1/super-admin/tenants/{$id}/override-feature", ['feature_key' => 'custom_branding'], $this->headers)
            ->assertOk();
        $override = $this->auditRow(CentralAuditEvent::TenantFeatureOverridden);
        $this->assertSame($id, $override->tenant_id);
        $this->assertSame('custom_branding', $override->properties['feature_key'] ?? null);
    }

    public function test_run_migrations_returns_a_status_only_and_is_audited(): void
    {
        $tenant = $this->createTenant();
        $id = (string) $tenant->getTenantKey();

        $response = $this->postJson("/api/v1/super-admin/tenants/{$id}/run-migrations", [], $this->headers)
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonMissingPath('output');
        $this->assertSame(['success', 'message'], array_keys((array) $response->json()));

        $log = $this->auditRow(CentralAuditEvent::TenantMigrationsRun);
        $this->assertSame($id, $log->tenant_id);
        $this->assertSame('succeeded', $log->properties['status'] ?? null);
    }

    public function test_db_config_change_is_audited_without_the_password(): void
    {
        $tenant = $this->createTenant();
        $id = (string) $tenant->getTenantKey();

        $response = $this->postJson("/api/v1/super-admin/tenants/{$id}/update-db-config", [
            'tenancy_db_name' => 'tenant_audit_target',
            'tenancy_db_username' => 'audit_user',
            'tenancy_db_password' => self::DB_PASSWORD,
        ], $this->headers)->assertOk();
        $this->assertStringNotContainsString(self::DB_PASSWORD, (string) $response->getContent());

        $log = $this->auditRow(CentralAuditEvent::TenantDbConfigUpdated);
        $this->assertSame($id, $log->tenant_id);
        $this->assertSame('tenant_audit_target', $log->properties['database'] ?? null);
        $this->assertEqualsCanonicalizing(['tenancy_db_name', 'tenancy_db_username', 'tenancy_db_password'], $log->properties['changed'] ?? []);
        $this->assertStringNotContainsString(self::DB_PASSWORD, (string) json_encode($log->properties));
        $this->assertStringNotContainsString('audit_user', (string) json_encode($log->properties));
    }

    public function test_plan_update_is_audited(): void
    {
        $plan = Plan::query()->create([
            'name' => 'باقة التجربة',
            'slug' => 'audit-plan-'.Str::lower(Str::random(6)),
            'price_monthly' => '100.000',
            'price_yearly' => '1000.000',
            'max_users' => 2,
            'max_stores' => 1,
            'max_items' => 100,
            'max_invoices_per_month' => 100,
            'is_active' => true,
            'is_popular' => false,
            'sort_order' => 9,
            'features' => [],
        ]);

        $this->putJson('/api/v1/super-admin/plans/'.$plan->id, [
            'name' => 'باقة التجربة المعدلة',
            'price_monthly' => '120.000',
            'price_yearly' => '1200.000',
            'max_users' => 3,
            'max_stores' => 1,
            'max_items' => 100,
            'max_invoices_per_month' => 100,
            'is_active' => true,
            'is_popular' => false,
            'features' => ['pos_offline' => true],
        ], $this->headers)->assertOk();

        $log = $this->auditRow(CentralAuditEvent::PlanUpdated);
        $this->assertSame((string) $plan->id, (string) $log->subject_id);
        $this->assertContains('price_monthly', $log->properties['changed'] ?? []);
    }

    public function test_app_version_store_toggle_and_destroy_are_audited(): void
    {
        $id = (int) $this->post('/api/v1/super-admin/app-versions', [
            'platform' => 'android',
            'version_name' => '7.1.0',
            'version_code' => 710,
            'release_notes_ar' => 'تحسينات',
        ], $this->headers)->assertCreated()->json('version.id');
        $this->assertSame('7.1.0', $this->auditRow(CentralAuditEvent::AppVersionCreated)->properties['version_name'] ?? null);

        $this->patchJson("/api/v1/super-admin/app-versions/{$id}/toggle-active", [], $this->headers)->assertOk();
        $this->auditRow(CentralAuditEvent::AppVersionToggled);

        $this->deleteJson("/api/v1/super-admin/app-versions/{$id}", [], $this->headers)->assertOk();
        $this->assertSame(710, $this->auditRow(CentralAuditEvent::AppVersionDeleted)->properties['version_code'] ?? null);
        $this->assertNull(AppVersion::query()->find($id));
    }

    public function test_store_tenant_returns_a_resource_without_db_credentials_and_is_audited(): void
    {
        $plan = Plan::query()->create([
            'name' => 'باقة أساسية',
            'slug' => 'basic-'.Str::lower(Str::random(6)),
            'price_monthly' => '0.000',
            'price_yearly' => '0.000',
            'is_active' => true,
            'is_popular' => false,
            'sort_order' => 1,
            'features' => [],
        ]);
        $slug = 'audit-shop-'.Str::lower(Str::random(6));

        try {
            $response = $this->postJson('/api/v1/super-admin/tenants', [
                'name' => 'محل المراجعة',
                'slug' => $slug,
                'email' => $slug.'@audit.test',
                'password' => 'secret1234',
                'plan_id' => $plan->id,
                'tenancy_db_password' => self::DB_PASSWORD,
            ], $this->headers)
                ->assertCreated()
                ->assertJsonPath('tenant.id', $slug)
                ->assertJsonMissingPath('tenant.tenancy_db_password')
                ->assertJsonMissingPath('tenant.tenancy_db_name')
                ->assertJsonMissingPath('tenant.data');
            $this->assertStringNotContainsString(self::DB_PASSWORD, (string) $response->getContent());

            $log = $this->auditRow(CentralAuditEvent::TenantCreated);
            $this->assertSame($slug, $log->tenant_id);
            $this->assertStringNotContainsString(self::DB_PASSWORD, (string) json_encode($log->properties));
            $this->assertStringNotContainsString('secret1234', (string) json_encode($log->properties));
        } finally {
            $this->dropProvisionedTenant($slug);
        }
    }

    private function dropProvisionedTenant(string $id): void
    {
        $this->endTenancy();
        $tenant = Tenant::query()->find($id);
        if (! $tenant instanceof Tenant) {
            return;
        }

        $manager = $tenant->database()->manager();
        $database = (string) $tenant->database()->getName();
        if ($manager->databaseExists($database)) {
            gc_collect_cycles();
            $manager->deleteDatabase($tenant);
        }

        $storage = storage_path().'/'.config('tenancy.filesystem.suffix_base', 'tenant').$id;
        if (is_dir($storage)) {
            File::deleteDirectory($storage);
        }
    }
}
