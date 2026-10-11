<?php

declare(strict_types=1);

namespace Tests\Feature\Central;

use App\Enums\CentralAuditEvent;
use App\Models\CentralAuditLog;
use App\Models\PlatformSetting;
use App\Models\Setting;
use App\Models\Tenant;
use App\Services\Platform\PlatformUnitsCatalog;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\Concerns\SeedsCentralPlatformRoles;
use Tests\TenantTestCase;

/**
 * BRND-2: the platform unit catalog (GET/POST /api/v1/super-admin/units, PlatformUnitsCatalog,
 * central cache key) and the per-tenant units (POST /tenants/{id}/update-units, now one
 * central transaction: a failing tenant write is a 422 that saves nothing), plus the central
 * data migration of the legacy `global_system_units` row.
 */
final class PlatformUnitsApiTest extends TenantTestCase
{
    use SeedsCentralPlatformRoles;

    private const MIGRATION = 'database/migrations/2026_10_10_400200_move_legacy_global_system_units_to_platform_settings.php';

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedCentralPlatformRoles();
    }

    // ------------------------------------------------------------------ catalog

    public function test_catalog_write_requires_a_recent_second_factor(): void
    {
        $this->postJson('/api/v1/super-admin/units', ['units' => ['كجم']], $this->centralHeaders($this->centralSuperAdmin()))
            ->assertForbidden()
            ->assertJsonPath('error_code', 'central_auth.step_up_required');

        $this->assertSame(0, PlatformSetting::query()->count());
    }

    public function test_catalog_defaults_then_saves_dedupes_and_is_audited(): void
    {
        $operator = $this->centralSuperAdmin();
        $headers = $this->steppedUpCentralHeaders($operator);

        $this->getJson('/api/v1/super-admin/units', $headers)
            ->assertOk()
            ->assertJsonPath('units', PlatformUnitsCatalog::parse(PlatformUnitsCatalog::DEFAULT_UNITS));

        $this->postJson('/api/v1/super-admin/units', ['units' => [' كجم ', 'جرام', 'كجم', '0.250 كجم']], $headers)
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('units', ['كجم', 'جرام', '0.250 كجم']);

        // The cached read is invalidated by the save.
        $this->getJson('/api/v1/super-admin/units', $headers)->assertOk()->assertJsonPath('units', ['كجم', 'جرام', '0.250 كجم']);
        $this->assertDatabaseHas('platform_settings', [
            'key' => PlatformUnitsCatalog::KEY,
            'value' => 'كجم,جرام,0.250 كجم',
            'updated_by' => $operator->getKey(),
        ]);

        $audit = CentralAuditLog::query()->where('event', CentralAuditEvent::PlatformUnitsUpdated->value)->sole();
        $this->assertSame(['كجم', 'جرام', '0.250 كجم'], $audit->properties['units'] ?? null);
    }

    public function test_a_unit_with_a_comma_or_markup_is_422(): void
    {
        $headers = $this->steppedUpCentralHeaders($this->centralSuperAdmin());

        foreach ([['كجم,جرام'], ['<b>كجم</b>'], [''], array_fill(0, 101, 'x')] as $units) {
            $this->postJson('/api/v1/super-admin/units', ['units' => $units], $headers)->assertStatus(422);
        }

        $this->assertSame(0, PlatformSetting::query()->count());
    }

    public function test_a_direct_central_write_is_visible_to_cached_reads_in_central_and_tenant_context(): void
    {
        $tenant = $this->createTenant();
        $catalog = app(PlatformUnitsCatalog::class);

        // Warm the cache in both contexts.
        $this->assertSame(PlatformUnitsCatalog::parse(PlatformUnitsCatalog::DEFAULT_UNITS), $catalog->all());
        $this->inTenant($tenant, fn () => app(PlatformUnitsCatalog::class)->all());

        PlatformSetting::query()->create(['key' => PlatformUnitsCatalog::KEY, 'value' => 'لتر,مل', 'type' => 'string']);

        $this->assertSame(['لتر', 'مل'], $catalog->all());
        $this->assertSame(['لتر', 'مل'], $this->inTenant($tenant, fn () => app(PlatformUnitsCatalog::class)->all()));
        $this->assertSame(PlatformUnitsCatalog::cacheKey(), $this->inTenant($tenant, fn () => PlatformUnitsCatalog::cacheKey()));
    }

    public function test_support_reads_but_cannot_write_and_tokens_outside_the_control_plane_are_401(): void
    {
        $support = $this->centralHeaders($this->centralSupport());
        $this->getJson('/api/v1/super-admin/units', $support)->assertOk();
        $this->postJson('/api/v1/super-admin/units', ['units' => ['كجم']], $support)->assertForbidden();

        $tenant = $this->createTenant();
        $tenantBearer = ['Accept' => 'application/json', 'Authorization' => 'Bearer '.$this->tenantToken($tenant)];
        $this->getJson('/api/v1/super-admin/units')->assertUnauthorized();
        $this->postJson('/api/v1/super-admin/units', ['units' => ['كجم']], $tenantBearer)->assertUnauthorized();
        $this->getJson($this->tenantUrl($tenant, '/api/v1/super-admin/units'), $support)->assertNotFound();

        $this->assertSame(0, PlatformSetting::query()->count());
    }

    // ------------------------------------------------------------------ tenant units

    public function test_tenant_units_are_saved_centrally_and_in_the_tenant_database(): void
    {
        $tenant = $this->createTenant();
        $other = $this->createTenant();
        $id = (string) $tenant->getTenantKey();

        $this->postJson("/api/v1/super-admin/tenants/{$id}/update-units", ['units' => ['كجم', 'جرام', 'كجم']], $this->steppedUpCentralHeaders($this->centralSuperAdmin()))
            ->assertOk()
            ->assertJsonPath('allowed_units', ['كجم', 'جرام']);

        $fresh = Tenant::query()->findOrFail($id);
        $this->assertSame(['كجم', 'جرام'], $fresh->getAttribute('allowed_units'));
        $this->assertSame('كجم,جرام', $this->inTenant($tenant, fn () => Setting::query()->where('key', 'inventory_units')->value('value')));

        // Isolation: the other tenant is untouched.
        $this->assertNull($this->inTenant($other, fn () => Setting::query()->where('key', 'inventory_units')->value('value')));

        $audit = CentralAuditLog::query()->where('event', CentralAuditEvent::TenantUnitsUpdated->value)->sole();
        $this->assertSame($id, $audit->tenant_id);
    }

    public function test_a_failing_tenant_write_is_a_422_and_saves_nothing(): void
    {
        $tenant = $this->createTenant();
        $id = (string) $tenant->getTenantKey();
        $this->inTenant($tenant, fn () => Schema::drop('settings'));

        $this->postJson("/api/v1/super-admin/tenants/{$id}/update-units", ['units' => ['كجم']], $this->steppedUpCentralHeaders($this->centralSuperAdmin()))
            ->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', __('super.units_save_failed'));

        $this->assertNull(Tenant::query()->findOrFail($id)->getAttribute('allowed_units'));
        $this->assertSame(0, CentralAuditLog::query()->where('event', CentralAuditEvent::TenantUnitsUpdated->value)->count());
        $this->assertFalse(tenancy()->initialized, 'the tenant context must always end');
    }

    public function test_tenant_units_validation_unknown_tenant_and_step_up(): void
    {
        $tenant = $this->createTenant();
        $id = (string) $tenant->getTenantKey();
        $steppedUp = $this->steppedUpCentralHeaders($this->centralSuperAdmin());

        $this->postJson("/api/v1/super-admin/tenants/{$id}/update-units", ['units' => 'كجم'], $steppedUp)->assertStatus(422)->assertJsonValidationErrors(['units']);
        $this->postJson("/api/v1/super-admin/tenants/{$id}/update-units", ['units' => ['كجم,جرام']], $steppedUp)->assertStatus(422)->assertJsonValidationErrors(['units.0']);
        $this->postJson('/api/v1/super-admin/tenants/no-such-tenant/update-units', ['units' => ['كجم']], $steppedUp)
            ->assertNotFound()
            ->assertJsonPath('message', __('super.tenant_not_found'));

        $this->postJson("/api/v1/super-admin/tenants/{$id}/update-units", ['units' => ['كجم']], $this->centralHeaders($this->centralSuperAdmin()))
            ->assertForbidden()
            ->assertJsonPath('error_code', 'central_auth.step_up_required');

        $this->assertNull($this->inTenant($tenant, fn () => Setting::query()->where('key', 'inventory_units')->value('value')));
    }

    // ------------------------------------------------------------------ data migration

    public function test_migration_copies_the_legacy_row_once_never_overwrites_and_down_removes_only_its_row(): void
    {
        $migration = require base_path(self::MIGRATION);

        // No legacy table: nothing happens.
        $migration->up();
        $this->assertSame(0, PlatformSetting::query()->count());

        Schema::create('settings', function (Blueprint $table): void {
            $table->id();
            $table->string('key');
            $table->text('value')->nullable();
            $table->softDeletes();
        });
        DB::table('settings')->insert(['key' => PlatformUnitsCatalog::KEY, 'value' => 'كجم,جرام']);

        $migration->up();
        $migration->up();
        $this->assertSame(1, PlatformSetting::query()->where('key', PlatformUnitsCatalog::KEY)->count());
        $this->assertDatabaseHas('platform_settings', ['key' => PlatformUnitsCatalog::KEY, 'value' => 'كجم,جرام', 'updated_by' => null]);
        $this->assertSame(['كجم', 'جرام'], app(PlatformUnitsCatalog::class)->all());

        $migration->down();
        $this->assertSame(0, PlatformSetting::query()->count());

        // An operator-owned row is never overwritten nor removed.
        $operator = $this->centralSuperAdmin();
        PlatformSetting::query()->create(['key' => PlatformUnitsCatalog::KEY, 'value' => 'لتر', 'type' => 'string', 'updated_by' => $operator->getKey()]);
        $migration->up();
        $migration->down();
        $this->assertDatabaseHas('platform_settings', ['key' => PlatformUnitsCatalog::KEY, 'value' => 'لتر']);
    }
}
