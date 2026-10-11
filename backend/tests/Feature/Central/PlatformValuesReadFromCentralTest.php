<?php

declare(strict_types=1);

namespace Tests\Feature\Central;

use App\Actions\SuperAdmin\GetPlatformSystemUnitsAction;
use App\Models\PlatformSetting;
use App\Models\Setting;
use App\Services\Branding\PlatformBranding;
use Illuminate\Support\Facades\Cache;
use Tests\Concerns\SeedsCentralPlatformRoles;
use Tests\TenantTestCase;

/**
 * W2-B3 lane 3L: three platform-level values were still read through the tenant `Setting`
 * model (a leftover `settings` table row, or nothing, depending on the context):
 *  - GET /api/v1/super-admin/tenants/{id} -> global_units (central context);
 *  - GET /api/v1/system/context -> system.platform_name (tenant context);
 *  - GET /manifest.json -> name / short_name / description.
 * They now come from the CENTRAL platform_settings table (GetPlatformSystemUnitsAction,
 * PlatformBranding); the response shapes are unchanged.
 */
final class PlatformValuesReadFromCentralTest extends TenantTestCase
{
    use SeedsCentralPlatformRoles;

    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();
        $this->seedCentralPlatformRoles();
    }

    public function test_tenant_details_global_units_come_from_platform_settings(): void
    {
        $tenant = $this->createTenant();
        $this->endTenancy();

        // The old code read the tenant `settings` model here (central context: no such table,
        // so it always served the built-in catalog and ignored the super-admin's list).
        PlatformSetting::query()->create(['key' => GetPlatformSystemUnitsAction::KEY, 'value' => 'كجم, جرام ,لتر', 'type' => 'string']);

        $this->getJson('/api/v1/super-admin/tenants/'.$tenant->getTenantKey(), $this->centralHeaders($this->centralSuperAdmin()))
            ->assertOk()
            ->assertJsonPath('data.global_units', ['كجم', 'جرام', 'لتر'])
            ->assertJsonStructure(['data' => ['tenant', 'stats', 'allowed_units', 'global_units', 'features', 'grouped_features', 'plans']]);

        $this->assertFalse(tenancy()->initialized, 'The request must not stay inside the tenant.');
    }

    public function test_tenant_details_fall_back_to_the_built_in_unit_catalog(): void
    {
        $tenant = $this->createTenant();
        $this->endTenancy();

        $this->getJson('/api/v1/super-admin/tenants/'.$tenant->getTenantKey(), $this->centralHeaders($this->centralSuperAdmin()))
            ->assertOk()
            ->assertJsonPath('data.global_units', explode(',', GetPlatformSystemUnitsAction::DEFAULT_UNITS));
    }

    public function test_system_context_platform_name_ignores_a_tenant_settings_row(): void
    {
        $tenant = $this->createTenant();
        $this->inTenant($tenant, static function (): void {
            Setting::set('platform_name', 'Tenant Spoofed Platform');
        });
        $this->endTenancy();
        $this->app->make(PlatformBranding::class)->update(['name' => 'منصة مركزية']);

        $this->getJson('/api/v1/system/context', $this->tenantHeaders($tenant))
            ->assertOk()
            ->assertJsonPath('data.system.platform_name', 'منصة مركزية');
    }

    public function test_manifest_uses_the_platform_branding(): void
    {
        $this->app->make(PlatformBranding::class)->update(['name' => 'Central Brand', 'subtitle' => 'Central subtitle']);

        $response = $this->get('/manifest.json')->assertOk();

        $this->assertStringStartsWith('Central Brand | ', (string) $response->json('name'));
        $this->assertSame('Central Brand', $response->json('short_name'));
        $this->assertSame('Central subtitle', $response->json('description'));
    }
}
