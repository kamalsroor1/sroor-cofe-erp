<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Models\AppVersion;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AppUpdateApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('migrate', ['--path' => 'database/migrations']);
        AppVersion::query()->delete();
    }

    public function test_check_version_returns_no_update_when_app_is_current(): void
    {
        AppVersion::create([
            'platform'         => 'android',
            'version_name'     => '1.0.0',
            'version_code'     => 1,
            'min_version_code' => 1,
            'is_force_update'  => false,
            'release_notes_ar' => 'الإصدار الأولي المستقر',
            'is_active'        => true,
            'published_at'     => now(),
        ]);

        $response = $this->getJson('/api/v1/app/check-update?platform=android&version_code=1&version_name=1.0.0');

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('has_update', false)
            ->assertJsonPath('force_update', false)
            ->assertJsonPath('current_app_version', '1.0.0');
    }

    public function test_check_version_returns_optional_update_when_newer_version_exists(): void
    {
        AppVersion::create([
            'platform'         => 'android',
            'version_name'     => '1.1.0',
            'version_code'     => 2,
            'min_version_code' => 1,
            'is_force_update'  => false,
            'release_notes_ar' => "تحسينات في الأداء والطباعة\nإصلاح ألوان الأكشن بار",
            'is_active'        => true,
            'published_at'     => now(),
        ]);

        $response = $this->getJson('/api/v1/app/version?platform=android&version_code=1&version_name=1.0.0');

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('has_update', true)
            ->assertJsonPath('force_update', false)
            ->assertJsonPath('latest_version', '1.1.0')
            ->assertJsonPath('latest_version_code', 2)
            ->assertJsonCount(2, 'release_notes');
    }

    public function test_check_version_returns_force_update_when_below_min_version(): void
    {
        AppVersion::create([
            'platform'         => 'android',
            'version_name'     => '2.0.0',
            'version_code'     => 10,
            'min_version_code' => 5,
            'is_force_update'  => true,
            'release_notes_ar' => 'ترقية أمنية كبرى إلزامية',
            'is_active'        => true,
            'published_at'     => now(),
        ]);

        $response = $this->getJson('/api/v1/app/check-update?platform=android&version_code=2&version_name=1.0.0');

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('has_update', true)
            ->assertJsonPath('force_update', true);
    }

    public function test_inactive_releases_are_ignored(): void
    {
        AppVersion::create([
            'platform'         => 'android',
            'version_name'     => '2.0.0-beta',
            'version_code'     => 99,
            'min_version_code' => 1,
            'is_force_update'  => false,
            'release_notes_ar' => 'نسخة تجريبية مغلقة',
            'is_active'        => false,
            'published_at'     => now(),
        ]);

        $response = $this->getJson('/api/v1/app/check-update?platform=android&version_code=1&version_name=1.0.0');

        $response->assertStatus(200)
            ->assertJsonPath('has_update', false);
    }

    public function test_platform_isolation(): void
    {
        AppVersion::create([
            'platform'         => 'windows',
            'version_name'     => '3.0.0',
            'version_code'     => 30,
            'release_notes_ar' => 'تحديث خاص بنظام ويندوز',
            'is_active'        => true,
            'published_at'     => now(),
        ]);

        // Query android, should not see windows version
        $response = $this->getJson('/api/v1/app/check-update?platform=android&version_code=1&version_name=1.0.0');

        $response->assertStatus(200)
            ->assertJsonPath('has_update', false);

        // Query windows, should see windows version
        $winResponse = $this->getJson('/api/v1/app/check-update?platform=windows&version_code=1&version_name=1.0.0');

        $winResponse->assertStatus(200)
            ->assertJsonPath('has_update', true)
            ->assertJsonPath('latest_version', '3.0.0');
    }

    public function test_validation_fails_on_invalid_platform_or_version_code(): void
    {
        $response = $this->getJson('/api/v1/app/check-update?platform=unsupported_os&version_code=-5');

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['platform', 'version_code']);
    }

    public function test_download_apk_serves_binary_when_file_exists(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('apks/sroor-erp-v1.1.apk', 'FAKE_APK_CONTENT');

        AppVersion::create([
            'platform'         => 'android',
            'version_name'     => '1.1.0',
            'version_code'     => 2,
            'release_notes_ar' => 'إصدار للتنزيل',
            'apk_path'         => 'apks/sroor-erp-v1.1.apk',
            'apk_filename'     => 'sroor-erp-v1.1.apk',
            'is_active'        => true,
            'download_count'   => 0,
            'published_at'     => now(),
        ]);

        $response = $this->get('/api/v1/app/download-apk?platform=android');

        $response->assertStatus(200)
            ->assertHeader('Content-Type', 'application/vnd.android.package-archive');
    }

    public function test_download_apk_throws_404_when_valid_platform_has_no_apk_file(): void
    {
        Storage::fake('public');

        $response = $this->getJson('/api/v1/app/download-apk?platform=ios');

        $response->assertStatus(404);
    }
}
