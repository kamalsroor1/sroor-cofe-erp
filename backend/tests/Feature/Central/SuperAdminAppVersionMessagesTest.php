<?php

declare(strict_types=1);

namespace Tests\Feature\Central;

use App\Models\AppVersion;
use Tests\Concerns\SeedsCentralPlatformRoles;
use Tests\TenantTestCase;

/**
 * W2-B3 lane 3L: SuperAdminAppVersionController answered toggle-active / destroy with hardcoded
 * Arabic. The messages now come from lang/{ar,en}/super.php.
 */
final class SuperAdminAppVersionMessagesTest extends TenantTestCase
{
    use SeedsCentralPlatformRoles;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedCentralPlatformRoles();
    }

    private function appVersion(): AppVersion
    {
        return AppVersion::query()->create([
            'platform' => 'android',
            'version_name' => '9.9.'.random_int(1, 999),
            'version_code' => random_int(10000, 99999),
            'release_notes_ar' => 'ملاحظات',
            'is_active' => true,
        ]);
    }

    public function test_toggle_active_returns_the_translated_message(): void
    {
        $version = $this->appVersion();
        $headers = $this->steppedUpCentralHeaders($this->centralSuperAdmin());

        $this->patchJson('/api/v1/super-admin/app-versions/'.$version->getKey().'/toggle-active', [], $headers)
            ->assertOk()
            ->assertJsonPath('message', __('super.version_status_toggled'))
            ->assertJsonPath('is_active', false);

        $this->assertFalse((bool) $version->fresh()?->is_active);
    }

    public function test_destroy_returns_the_translated_message(): void
    {
        $version = $this->appVersion();
        $headers = $this->steppedUpCentralHeaders($this->centralSuperAdmin());

        $this->deleteJson('/api/v1/super-admin/app-versions/'.$version->getKey(), [], $headers)
            ->assertOk()
            ->assertJsonPath('message', __('super.version_deleted_success'));

        $this->assertNull(AppVersion::query()->find($version->getKey()));
    }

    public function test_the_keys_exist_in_both_locales(): void
    {
        foreach (['super.version_status_toggled', 'super.version_deleted_success', 'super.release_published_success'] as $key) {
            foreach (['ar', 'en'] as $locale) {
                $this->assertNotSame($key, __($key, [], $locale), $key.' ['.$locale.']');
            }
        }
    }
}
