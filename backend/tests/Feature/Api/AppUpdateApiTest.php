<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Models\AppVersion;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Tests\TenantTestCase;

/**
 * Public, central app-update endpoints. QA-4: on the tenant harness base class the central
 * database holds only central tables, and the tenant cases below prove that a request
 * carrying a tenant (X-Tenant) still reads and writes the CENTRAL app_versions table.
 */
class AppUpdateApiTest extends TenantTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        AppVersion::query()->delete();
    }

    public function test_check_version_returns_no_update_when_app_is_current(): void
    {
        AppVersion::create([
            'platform' => 'android',
            'version_name' => '1.0.0',
            'version_code' => 1,
            'min_version_code' => 1,
            'is_force_update' => false,
            'release_notes_ar' => 'الإصدار الأولي المستقر',
            'is_active' => true,
            'published_at' => now(),
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
            'platform' => 'android',
            'version_name' => '1.1.0',
            'version_code' => 2,
            'min_version_code' => 1,
            'is_force_update' => false,
            'release_notes_ar' => "تحسينات في الأداء والطباعة\nإصلاح ألوان الأكشن بار",
            'is_active' => true,
            'published_at' => now(),
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
            'platform' => 'android',
            'version_name' => '2.0.0',
            'version_code' => 10,
            'min_version_code' => 5,
            'is_force_update' => true,
            'release_notes_ar' => 'ترقية أمنية كبرى إلزامية',
            'is_active' => true,
            'published_at' => now(),
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
            'platform' => 'android',
            'version_name' => '2.0.0-beta',
            'version_code' => 99,
            'min_version_code' => 1,
            'is_force_update' => false,
            'release_notes_ar' => 'نسخة تجريبية مغلقة',
            'is_active' => false,
            'published_at' => now(),
        ]);

        $response = $this->getJson('/api/v1/app/check-update?platform=android&version_code=1&version_name=1.0.0');

        $response->assertStatus(200)
            ->assertJsonPath('has_update', false);
    }

    public function test_platform_isolation(): void
    {
        AppVersion::create([
            'platform' => 'windows',
            'version_name' => '3.0.0',
            'version_code' => 30,
            'release_notes_ar' => 'تحديث خاص بنظام ويندوز',
            'is_active' => true,
            'published_at' => now(),
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

    public function test_check_version_exposes_sha256_checksum_for_windows_release(): void
    {
        $checksum = hash('sha256', 'FAKE_WINDOWS_INSTALLER');

        AppVersion::create([
            'platform' => 'windows',
            'version_name' => '3.1.0',
            'version_code' => 31,
            'release_notes_ar' => 'تحديث ويندوز موقع',
            'apk_path' => 'apks/sroor-erp-setup-3.1.0.exe',
            'apk_filename' => 'sroor-erp-setup-3.1.0.exe',
            'apk_size_bytes' => 22,
            'apk_checksum' => $checksum,
            'is_active' => true,
            'published_at' => now(),
        ]);

        $response = $this->getJson('/api/v1/app/check-update?platform=windows&version_code=1&version_name=1.0.0');

        $response->assertStatus(200)
            ->assertJsonPath('has_update', true)
            ->assertJsonPath('checksum', $checksum);

        $this->assertMatchesRegularExpression('/^[a-f0-9]{64}$/', (string) $response->json('checksum'));
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
            'platform' => 'android',
            'version_name' => '1.1.0',
            'version_code' => 2,
            'release_notes_ar' => 'إصدار للتنزيل',
            'apk_path' => 'apks/sroor-erp-v1.1.apk',
            'apk_filename' => 'sroor-erp-v1.1.apk',
            'is_active' => true,
            'download_count' => 0,
            'published_at' => now(),
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

    /**
     * Run $callback with a legacy fallback binary present at $path, creating a tiny
     * placeholder only when the file does not already exist (so the regression is
     * deterministic on CI) and removing only what this test created.
     */
    private function withLegacyFallbackFile(string $path, callable $callback): void
    {
        $created = false;

        if (! file_exists($path)) {
            file_put_contents($path, 'LEGACY_FALLBACK_PLACEHOLDER');
            $created = true;
        }

        try {
            $callback();
        } finally {
            if ($created && file_exists($path)) {
                unlink($path);
            }
        }
    }

    public function test_download_windows_returns_404_when_no_release_even_if_legacy_fallback_files_exist(): void
    {
        Storage::fake('public');
        $this->assertSame(0, AppVersion::query()->count());

        $this->withLegacyFallbackFile(public_path('desktop-setup.exe'), function (): void {
            $this->getJson('/api/v1/app/download-apk?platform=windows')
                ->assertStatus(404)
                ->assertJson(['message' => __('app_update.file_not_available')]);
        });
    }

    public function test_download_ios_never_serves_android_release(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('apks/sroor-erp-v1.1.apk', 'FAKE_APK_CONTENT');

        AppVersion::create([
            'platform' => 'android',
            'version_name' => '1.1.0',
            'version_code' => 2,
            'release_notes_ar' => 'إصدار أندرويد فقط',
            'apk_path' => 'apks/sroor-erp-v1.1.apk',
            'apk_filename' => 'sroor-erp-v1.1.apk',
            'is_active' => true,
            'download_count' => 0,
            'published_at' => now(),
        ]);

        $this->withLegacyFallbackFile(public_path('app.apk'), function (): void {
            $this->getJson('/api/v1/app/download-apk?platform=ios')
                ->assertStatus(404)
                ->assertJson(['message' => __('app_update.file_not_available')]);
        });

        // The android release must not have been counted as downloaded by an iOS request.
        $this->assertSame(0, (int) AppVersion::query()->where('platform', 'android')->value('download_count'));
    }

    public function test_download_404_message_is_translated_not_a_hardcoded_literal(): void
    {
        Storage::fake('public');

        $this->assertNotSame('app_update.file_not_available', __('app_update.file_not_available'), 'Missing lang key app_update.file_not_available.');
        app()->setLocale('en');
        $this->assertNotSame('app_update.file_not_available', __('app_update.file_not_available'), 'Missing en lang key app_update.file_not_available.');
    }

    public function test_check_version_inside_a_tenant_request_reads_the_central_release_table(): void
    {
        $tenant = $this->createTenant();

        AppVersion::create([
            'platform' => 'android',
            'version_name' => '1.4.0',
            'version_code' => 14,
            'min_version_code' => 1,
            'is_force_update' => false,
            'release_notes_ar' => 'إصدار مركزي واحد لكل المحلات',
            'is_active' => true,
            'published_at' => now(),
        ]);

        $this->getJson('/api/v1/app/check-update?platform=android&version_code=1&version_name=1.0.0', ['X-Tenant' => (string) $tenant->getTenantKey()])
            ->assertStatus(200)
            ->assertJsonPath('has_update', true)
            ->assertJsonPath('latest_version', '1.4.0');

        // The tenant database never gets (or needs) an app_versions table.
        $this->assertFalse($this->inTenant($tenant, fn (): bool => Schema::hasTable('app_versions')));
    }

    /**
     * The client journey on a tenant host: check-update returns download_url built from the
     * request host, and the app then downloads from that URL. The binary is a central
     * release (central public disk), so it must be served whichever tenant asks.
     */
    public function test_download_url_returned_on_a_tenant_host_serves_the_central_release(): void
    {
        Storage::fake('public');
        Storage::fake(AppVersion::CENTRAL_RELEASE_DISK);
        Storage::disk(AppVersion::CENTRAL_RELEASE_DISK)->put('apks/sroor-erp-v1.5.apk', 'FAKE_APK_CONTENT');
        $tenantA = $this->createTenant();
        $tenantB = $this->createTenant();

        $release = AppVersion::create([
            'platform' => 'android',
            'version_name' => '1.5.0',
            'version_code' => 15,
            'release_notes_ar' => 'إصدار للتنزيل',
            'apk_path' => 'apks/sroor-erp-v1.5.apk',
            'apk_filename' => 'sroor-erp-v1.5.apk',
            'is_active' => true,
            'download_count' => 0,
            'published_at' => now(),
        ]);

        foreach ([$tenantA, $tenantB] as $tenant) {
            $downloadUrl = (string) $this->getJson($this->tenantUrl($tenant, '/api/v1/app/check-update?platform=android&version_code=1&version_name=1.0.0'))
                ->assertStatus(200)
                ->assertJsonPath('has_update', true)
                ->json('download_url');
            $this->assertStringStartsWith($this->tenantUrl($tenant, '/'), $downloadUrl);

            $this->get($downloadUrl)
                ->assertStatus(200)
                ->assertHeader('Content-Type', 'application/vnd.android.package-archive');
        }

        $this->assertSame(2, (int) $release->fresh()->download_count, 'Both tenants must count on the one central release row.');
    }
}
