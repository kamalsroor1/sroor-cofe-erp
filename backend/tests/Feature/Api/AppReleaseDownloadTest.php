<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Models\AppVersion;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Tests\Concerns\IsolatesReleaseDisks;
use Tests\Concerns\SeedsCentralPlatformRoles;
use Tests\TenantTestCase;

/**
 * APP-3 extension (W2 batch 5): GET /api/v1/app/download-apk serves release binaries from the
 * PRIVATE release disk only, version-specific, with integrity headers.
 */
final class AppReleaseDownloadTest extends TenantTestCase
{
    use IsolatesReleaseDisks;
    use SeedsCentralPlatformRoles;

    protected function setUp(): void
    {
        parent::setUp();

        AppVersion::query()->delete();
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function release(string $platform, int $code, array $attributes = [], string $disk = AppVersion::RELEASE_DISK): AppVersion
    {
        $content = 'BINARY-'.$platform.'-'.$code;
        $path = 'apks/'.$platform.'/'.strtolower((string) Str::ulid()).($platform === 'windows' ? '.exe' : '.apk');
        Storage::disk($disk)->put($path, $content);

        return AppVersion::query()->create($attributes + [
            'platform' => $platform,
            'version_name' => '1.0.'.$code,
            'version_code' => $code,
            'release_notes_ar' => 'ملاحظات',
            'apk_path' => $path,
            'apk_filename' => 'erp-v'.$code.($platform === 'windows' ? '.exe' : '.apk'),
            'apk_size_bytes' => strlen($content),
            'apk_checksum' => hash('sha256', $content),
            'is_active' => true,
            'download_count' => 0,
            'published_at' => now(),
        ]);
    }

    public function test_a_specific_version_is_served_with_integrity_headers(): void
    {
        $old = $this->release('android', 7);
        $this->release('android', 9);

        $response = $this->get('/api/v1/app/download-apk?platform=android&version_code=7')->assertOk();

        $response->assertHeader('Content-Type', 'application/vnd.android.package-archive')
            ->assertHeader('X-Checksum-Sha256', hash('sha256', 'BINARY-android-7'))
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('Content-Length', (string) strlen('BINARY-android-7'));
        $this->assertStringStartsWith('attachment;', (string) $response->headers->get('Content-Disposition'));
        $this->assertStringContainsString('erp-v7.apk', (string) $response->headers->get('Content-Disposition'));
        $this->assertStringContainsString('no-cache', (string) $response->headers->get('Cache-Control'));
        $this->assertStringContainsString('private', (string) $response->headers->get('Cache-Control'));

        $base = $response->baseResponse;
        $this->assertInstanceOf(BinaryFileResponse::class, $base);
        $this->assertSame('BINARY-android-7', file_get_contents($base->getFile()->getPathname()));
        $this->assertSame(1, (int) $old->fresh()?->download_count);
    }

    public function test_downloads_have_a_tight_hourly_limit_per_ip_shared_by_both_routes(): void
    {
        config(['rate_limits.app_download.per_hour' => 3]);
        $this->release('android', 7);

        $this->get('/api/v1/app/download-apk?platform=android')->assertOk();
        $this->get('/api/v1/app/download-latest-apk?platform=android')->assertOk();
        $this->get('/api/v1/app/download-apk?platform=android&version_code=7')->assertOk();

        // Fourth download within the hour, on either route: 429 (the bucket is per IP, not per route).
        $this->get('/api/v1/app/download-latest-apk?platform=android')->assertStatus(429);
        $this->get('/api/v1/app/download-apk?platform=android')->assertStatus(429);

        // Another client IP has its own bucket.
        $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.77'])
            ->get('/api/v1/app/download-apk?platform=android')
            ->assertOk();

        // The update check is not counted against the download bucket.
        $this->getJson('/api/v1/app/check-update?platform=android&version_code=1')->assertOk();

        // After an hour the bucket refills.
        $this->travel(61)->minutes();
        $this->get('/api/v1/app/download-apk?platform=android')->assertOk();
    }

    public function test_an_empty_env_value_keeps_the_default_download_limit(): void
    {
        $previous = getenv('RATE_LIMIT_APP_DOWNLOAD_HOURLY');
        putenv('RATE_LIMIT_APP_DOWNLOAD_HOURLY=');
        $_ENV['RATE_LIMIT_APP_DOWNLOAD_HOURLY'] = '';
        $_SERVER['RATE_LIMIT_APP_DOWNLOAD_HOURLY'] = '';

        try {
            $config = require config_path('rate_limits.php');
            $this->assertSame(10, $config['app_download']['per_hour']);
        } finally {
            putenv($previous === false ? 'RATE_LIMIT_APP_DOWNLOAD_HOURLY' : 'RATE_LIMIT_APP_DOWNLOAD_HOURLY='.$previous);
            unset($_ENV['RATE_LIMIT_APP_DOWNLOAD_HOURLY'], $_SERVER['RATE_LIMIT_APP_DOWNLOAD_HOURLY']);
        }
    }

    public function test_without_version_code_the_latest_advertised_release_is_served(): void
    {
        $this->release('android', 7);
        $this->release('android', 9, ['apk_checksum' => null]); // never advertised, never served as "latest"

        $this->get('/api/v1/app/download-apk?platform=android')
            ->assertOk()
            ->assertHeader('X-Checksum-Sha256', hash('sha256', 'BINARY-android-7'));
    }

    public function test_windows_installer_is_served_as_a_portable_executable(): void
    {
        $this->release('windows', 1002003);

        $this->get('/api/v1/app/download-latest-apk?platform=windows&version_code=1002003')
            ->assertOk()
            ->assertHeader('Content-Type', 'application/vnd.microsoft.portable-executable')
            ->assertHeader('X-Checksum-Sha256', hash('sha256', 'BINARY-windows-1002003'));
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function unservable(): array
    {
        return [
            'inactive release' => ['inactive'],
            'release without checksum' => ['no_checksum'],
            'unknown version code' => ['unknown'],
            'version of another platform' => ['other_platform'],
            'binary missing on disk' => ['missing_file'],
        ];
    }

    #[DataProvider('unservable')]
    public function test_unservable_releases_are_404_with_a_translated_message(string $case): void
    {
        $code = 7;
        match ($case) {
            'inactive' => $this->release('android', $code, ['is_active' => false]),
            'no_checksum' => $this->release('android', $code, ['apk_checksum' => null]),
            'unknown' => $this->release('android', 8),
            'other_platform' => $this->release('windows', $code),
            'missing_file' => $this->release('android', $code, ['apk_path' => 'apks/android/gone.apk']),
        };

        $this->getJson('/api/v1/app/download-apk?platform=android&version_code='.$code)
            ->assertNotFound()
            ->assertJson(['message' => __('app_update.file_not_available')]);

        $this->assertSame(0, (int) AppVersion::query()->sum('download_count'));
    }

    public function test_a_legacy_public_binary_is_served_until_relocated(): void
    {
        $this->release('android', 7, [], AppVersion::LEGACY_RELEASE_DISK);

        $this->get('/api/v1/app/download-apk?platform=android&version_code=7')
            ->assertOk()
            ->assertHeader('X-Checksum-Sha256', hash('sha256', 'BINARY-android-7'));
    }

    public function test_deleting_a_release_removes_its_binary_from_every_release_disk(): void
    {
        $this->seedCentralPlatformRoles();
        $private = $this->release('android', 7);
        $legacy = $this->release('android', 8, [], AppVersion::LEGACY_RELEASE_DISK);
        $headers = $this->steppedUpCentralHeaders($this->centralSuperAdmin());

        $this->deleteJson('/api/v1/super-admin/app-versions/'.$private->getKey(), [], $headers)->assertOk();
        $this->deleteJson('/api/v1/super-admin/app-versions/'.$legacy->getKey(), [], $headers)->assertOk();

        Storage::disk(AppVersion::RELEASE_DISK)->assertMissing((string) $private->apk_path);
        Storage::disk(AppVersion::LEGACY_RELEASE_DISK)->assertMissing((string) $legacy->apk_path);
    }

    public function test_the_release_disk_is_private_central_and_never_web_served(): void
    {
        $root = str_replace('\\', '/', (string) config('filesystems.disks.'.AppVersion::RELEASE_DISK.'.root'));

        $this->assertStringEndsWith('storage/app/private/releases', $root);
        $this->assertStringNotContainsString('/public', $root);
        $this->assertSame('private', config('filesystems.disks.'.AppVersion::RELEASE_DISK.'.visibility'));
        $this->assertFalse((bool) config('filesystems.disks.'.AppVersion::RELEASE_DISK.'.serve'));
        $this->assertNotContains(AppVersion::RELEASE_DISK, (array) config('tenancy.filesystem.disks'));
        $this->assertNotContains(AppVersion::LEGACY_RELEASE_DISK, (array) config('tenancy.filesystem.disks'));
    }

    public function test_apache_denies_release_binaries_under_public(): void
    {
        $htaccess = (string) file_get_contents(public_path('.htaccess'));

        $this->assertStringContainsString('\.(apk|exe|ipa)$', $htaccess);
    }
}
