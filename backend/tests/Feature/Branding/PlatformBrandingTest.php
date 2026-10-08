<?php

declare(strict_types=1);

namespace Tests\Feature\Branding;

use App\DTOs\Branding\PlatformBrandingDTO;
use App\Enums\PlatformSettingKey;
use App\Models\PlatformSetting;
use App\Services\Branding\PlatformBranding;
use App\Support\TenantCache;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TenantTestCase;

/**
 * BRND-1: platform branding has ONE source of truth.
 *
 * config/branding.php (env) is the fallback, the CENTRAL `platform_settings` table
 * overrides it, and the result is cached under an explicit central key so a tenant
 * request and a central request read the same entry.
 */
final class PlatformBrandingTest extends TenantTestCase
{
    private const LEGACY_MIGRATION = 'migrations/2026_10_10_400010_move_legacy_platform_settings_to_platform_settings_table.php';

    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();
        config([
            'branding.name' => 'Config Platform',
            'branding.short_name' => 'CfgP',
            'branding.subtitle' => 'Config subtitle',
            'branding.support_email' => 'support@example.test',
            'branding.primary_color' => '#112233',
            'branding.powered_by_enabled' => true,
        ]);
    }

    /** Run the data migration file's up() or down() against the central connection. */
    private function runLegacyMigration(string $direction): void
    {
        $migration = require database_path(self::LEGACY_MIGRATION);
        $this->assertInstanceOf(Migration::class, $migration);
        $this->assertTrue(method_exists($migration, $direction));

        $migration->{$direction}();
    }

    private function branding(): PlatformBranding
    {
        return $this->app->make(PlatformBranding::class);
    }

    public function test_defaults_come_from_config_when_nothing_is_stored(): void
    {
        $dto = $this->branding()->get();

        $this->assertInstanceOf(PlatformBrandingDTO::class, $dto);
        $this->assertSame('Config Platform', $dto->name);
        $this->assertSame('CfgP', $dto->shortName);
        $this->assertSame('Config subtitle', $dto->subtitle);
        $this->assertSame('support@example.test', $dto->supportEmail);
        $this->assertSame('#112233', $dto->primaryColor);
        $this->assertTrue($dto->poweredByEnabled);
        $this->assertSame('Config Platform', $dto->toArray()['name']);
    }

    public function test_stored_values_override_config_with_their_types(): void
    {
        $this->branding()->update([
            'name' => 'منصة التجزئة',
            'subtitle' => '',
            'powered_by_enabled' => false,
            'primary_color' => '#AbCdEf',
        ]);

        $dto = $this->branding()->get();

        $this->assertSame('منصة التجزئة', $dto->name);
        $this->assertSame('', $dto->subtitle, 'An explicitly blank optional value is kept.');
        $this->assertFalse($dto->poweredByEnabled);
        $this->assertSame('#abcdef', $dto->primaryColor);
        $this->assertSame('CfgP', $dto->shortName, 'Keys never stored keep the config value.');

        $row = PlatformSetting::query()->where('key', 'powered_by_enabled')->firstOrFail();
        $this->assertSame('bool', $row->type);
        $this->assertSame('0', $row->value);
    }

    public function test_blank_or_invalid_values_fall_back_to_config(): void
    {
        PlatformSetting::query()->create(['key' => 'name', 'value' => '   ', 'type' => 'string']);
        PlatformSetting::query()->create(['key' => 'primary_color', 'value' => 'red;}body{', 'type' => 'color']);

        $dto = $this->branding()->get();

        $this->assertSame('Config Platform', $dto->name, 'The platform name can never be blank.');
        $this->assertSame('#112233', $dto->primaryColor, 'A non-hex color is never served.');
    }

    public function test_unknown_keys_are_rejected_by_update(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->branding()->update(['telegram_token' => 'x']);
    }

    public function test_a_save_invalidates_the_cached_branding_immediately(): void
    {
        $this->assertSame('Config Platform', $this->branding()->get()->name);
        $versionBefore = TenantCache::centralVersion(PlatformBranding::CACHE_NAMESPACE);

        // A direct model write (not only update()) must invalidate too.
        PlatformSetting::query()->create(['key' => 'name', 'value' => 'Renamed', 'type' => 'string']);

        $this->assertNotSame($versionBefore, TenantCache::centralVersion(PlatformBranding::CACHE_NAMESPACE));
        $this->assertSame('Renamed', $this->branding()->get()->name);

        PlatformSetting::query()->where('key', 'name')->firstOrFail()->delete();
        $this->assertSame('Config Platform', $this->branding()->get()->name);
    }

    public function test_cache_entry_is_shared_between_central_and_tenant_context(): void
    {
        $tenant = $this->createTenant();

        $this->branding()->get(); // prime the cache in central context

        $this->inTenant($tenant, function (): void {
            $this->assertSame('Config Platform', $this->branding()->get()->name);
            // Saved from inside a tenant request: still the central table and the central key.
            $this->branding()->update(['name' => 'From tenant context']);
        });

        $this->assertSame('From tenant context', $this->branding()->get()->name);
        $this->assertSame(1, PlatformSetting::query()->count());
    }

    public function test_model_is_pinned_to_the_central_connection_under_tenancy(): void
    {
        $tenant = $this->createTenant();
        $central = $this->centralConnectionName();

        $this->inTenant($tenant, function () use ($central): void {
            $this->assertSame($central, (new PlatformSetting)->getConnectionName());
            $this->assertFalse(Schema::hasTable('platform_settings'), 'platform_settings must not exist in a tenant DB.');
            PlatformSetting::query()->create(['key' => 'subtitle', 'value' => 'x', 'type' => 'string']);
        });

        $this->assertTrue(Schema::connection($central)->hasTable('platform_settings'));
        $this->assertSame('x', PlatformSetting::query()->where('key', 'subtitle')->value('value'));
    }

    public function test_falls_back_to_config_without_caching_when_the_table_is_missing(): void
    {
        Schema::drop('platform_settings');

        $this->assertSame('Config Platform', $this->branding()->get()->name);
        $this->assertFalse(
            Cache::has(PlatformBranding::cacheKey()),
            'A failed read must not be cached, or the real values stay hidden after migrating.',
        );
    }

    public function test_every_known_key_has_a_type(): void
    {
        foreach (PlatformSettingKey::cases() as $key) {
            $this->assertContains($key->type(), ['string', 'bool', 'color', 'asset']);
        }
    }

    public function test_legacy_central_settings_are_copied_once_without_overwriting(): void
    {
        Schema::create('settings', function ($table): void {
            $table->id();
            $table->string('key')->unique();
            $table->text('value')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
        DB::table('settings')->insert([
            ['key' => 'platform_name', 'value' => 'Legacy Name', 'deleted_at' => null],
            ['key' => 'platform_subtitle', 'value' => 'Legacy subtitle', 'deleted_at' => null],
            ['key' => 'support_phone', 'value' => '01000000501', 'deleted_at' => null],
            ['key' => 'support_email', 'value' => 'old@example.test', 'deleted_at' => now()],
            ['key' => 'company_name', 'value' => 'not platform data', 'deleted_at' => null],
        ]);
        PlatformSetting::query()->create(['key' => 'subtitle', 'value' => 'Already set', 'type' => 'string']);

        $this->runLegacyMigration('up');
        $this->runLegacyMigration('up'); // idempotent

        $stored = PlatformSetting::query()->pluck('value', 'key')->all();
        $this->assertSame('Legacy Name', $stored['name']);
        $this->assertSame('Already set', $stored['subtitle'], 'Existing platform settings are never overwritten.');
        $this->assertSame('01000000501', $stored['support_phone']);
        $this->assertArrayNotHasKey('support_email', $stored, 'Soft-deleted legacy rows are ignored.');
        $this->assertCount(3, $stored);
        $this->assertSame(1, DB::table('settings')->where('key', 'platform_name')->count(), 'Legacy rows are left in place.');

        $this->runLegacyMigration('down');
        $this->assertSame(['subtitle' => 'Already set'], PlatformSetting::query()->pluck('value', 'key')->all());
    }

    public function test_legacy_migration_is_a_no_op_without_a_central_settings_table(): void
    {
        $this->assertFalse(Schema::hasTable('settings'));

        $this->runLegacyMigration('up');

        $this->assertSame(0, PlatformSetting::query()->count());
    }
}
