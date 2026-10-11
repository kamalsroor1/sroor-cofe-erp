<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Models\AppVersion;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\Concerns\IsolatesReleaseDisks;
use Tests\TenantTestCase;

/**
 * APP-3 extension (W2 batch 5): the GET /api/v1/app/check-update contract consumed by the
 * Android updater (useAppUpdate) and the desktop updater (APP-7).
 */
final class AppUpdateCheckContractTest extends TenantTestCase
{
    use IsolatesReleaseDisks;

    private const URL = '/api/v1/app/check-update?platform=android&version_code=5&version_name=1.0.5';

    protected function setUp(): void
    {
        parent::setUp();

        AppVersion::query()->delete();
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function release(int $code, array $attributes = [], ?string $content = null, bool $storeFile = true): AppVersion
    {
        $content ??= 'BINARY-'.$code;
        $platform = (string) ($attributes['platform'] ?? 'android');
        $path = 'apks/'.$platform.'/'.strtolower((string) Str::ulid()).'.apk';
        if ($storeFile) {
            Storage::disk(AppVersion::RELEASE_DISK)->put($path, $content);
        }

        return AppVersion::query()->create($attributes + [
            'platform' => $platform,
            'version_name' => '1.0.'.$code,
            'version_code' => $code,
            'min_version_code' => 1,
            'is_force_update' => false,
            'release_notes_ar' => "سطر أول\nسطر تاني",
            'apk_path' => $path,
            'apk_filename' => 'erp-v'.$code.'.apk',
            'apk_size_bytes' => strlen($content),
            'apk_checksum' => hash('sha256', $content),
            'is_active' => true,
            'published_at' => now(),
        ]);
    }

    public function test_full_contract_for_an_optional_update(): void
    {
        $previous = $this->release(8);
        // apk_size_bytes is what the client is told (set at upload); the fake file just has to exist.
        $latest = $this->release(10, ['apk_size_bytes' => 19_293_798]);

        $response = $this->getJson(self::URL)->assertOk();

        $response->assertJsonStructure([
            'success', 'has_update', 'force_update', 'current_app_version', 'current_version_code',
            'latest_version', 'latest_version_code', 'min_supported_version_code',
            'download_url', 'manual_download_url', 'file_size', 'file_size_bytes',
            'checksum', 'checksum_algorithm', 'release_notes', 'release_notes_ar', 'published_at',
            'title', 'message',
            'rollback' => ['version', 'version_code', 'download_url', 'checksum', 'file_size_bytes'],
        ]);

        $response->assertJsonPath('success', true)
            ->assertJsonPath('has_update', true)
            ->assertJsonPath('force_update', false)
            ->assertJsonPath('current_app_version', '1.0.5')
            ->assertJsonPath('current_version_code', 5)
            ->assertJsonPath('latest_version', '1.0.10')
            ->assertJsonPath('latest_version_code', 10)
            ->assertJsonPath('min_supported_version_code', 1)
            ->assertJsonPath('checksum', $latest->apk_checksum)
            ->assertJsonPath('checksum_algorithm', 'sha256')
            ->assertJsonPath('file_size_bytes', 19_293_798)
            ->assertJsonPath('file_size', __('app_releases.size.megabytes', ['size' => '18.3']))
            ->assertJsonPath('release_notes', ['سطر أول', 'سطر تاني'])
            ->assertJsonPath('manual_download_url', rtrim((string) config('app.url'), '/').'/download')
            ->assertJsonPath('title', __('app_releases.check.title_optional'))
            ->assertJsonPath('message', __('app_releases.check.message_available', ['version' => '1.0.10']))
            ->assertJsonPath('rollback.version', '1.0.8')
            ->assertJsonPath('rollback.version_code', 8)
            ->assertJsonPath('rollback.checksum', $previous->apk_checksum);

        // Version-specific download URLs (never "whatever is latest" for a checksum-pinned file).
        $this->assertStringContainsString('/api/v1/app/download-apk?', (string) $response->json('download_url'));
        $this->assertStringContainsString('version_code=10', (string) $response->json('download_url'));
        $this->assertStringContainsString('version_code=8', (string) $response->json('rollback.download_url'));
        $this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}[+-]\d{2}:\d{2}$/', (string) $response->json('published_at'));
    }

    public function test_texts_are_translated_and_follow_x_locale(): void
    {
        $this->release(10, ['release_notes_en' => "First line\nSecond line"]);

        $this->getJson(self::URL, ['X-Locale' => 'en'])
            ->assertOk()
            ->assertJsonPath('title', __('app_releases.check.title_optional', [], 'en'))
            ->assertJsonPath('message', __('app_releases.check.message_available', ['version' => '1.0.10'], 'en'))
            ->assertJsonPath('release_notes', ['First line', 'Second line']);

        $ar = $this->getJson(self::URL, ['X-Locale' => 'ar'])->assertOk();
        $ar->assertJsonPath('title', __('app_releases.check.title_optional', [], 'ar'))
            ->assertJsonPath('release_notes', ['سطر أول', 'سطر تاني']);
        $this->assertNotSame($ar->json('title'), __('app_releases.check.title_optional', [], 'en'));
    }

    public function test_english_notes_fall_back_to_arabic_when_missing(): void
    {
        $this->release(10);

        $this->getJson(self::URL, ['X-Locale' => 'en'])->assertJsonPath('release_notes', ['سطر أول', 'سطر تاني']);
    }

    public function test_forced_update_by_flag_and_by_minimum_version(): void
    {
        $this->release(10, ['is_force_update' => true]);

        $this->getJson(self::URL)->assertOk()
            ->assertJsonPath('force_update', true)
            ->assertJsonPath('title', __('app_releases.check.title_force'))
            ->assertJsonPath('message', __('app_releases.check.message_force', ['version' => '1.0.10']));

        AppVersion::query()->delete();
        $this->release(11, ['min_version_code' => 6]);

        $this->getJson(self::URL)->assertJsonPath('force_update', true)->assertJsonPath('min_supported_version_code', 6);
        $this->getJson('/api/v1/app/check-update?platform=android&version_code=6')->assertJsonPath('force_update', false);
    }

    public function test_a_release_without_checksum_is_never_advertised(): void
    {
        $this->release(10);
        $this->release(11, ['apk_checksum' => null, 'is_force_update' => true]);
        $this->release(12, ['apk_checksum' => 'not-a-sha256', 'is_force_update' => true]);

        $this->getJson(self::URL)->assertOk()
            ->assertJsonPath('has_update', true)
            ->assertJsonPath('latest_version_code', 10)
            ->assertJsonPath('force_update', false);
    }

    public function test_a_release_whose_binary_is_missing_is_never_advertised(): void
    {
        $this->release(10);
        $this->release(11, ['is_force_update' => true], storeFile: false);
        $this->release(12, ['apk_path' => null, 'is_force_update' => true]);
        $this->release(13, ['apk_size_bytes' => 0, 'is_force_update' => true]);

        $this->getJson(self::URL)->assertOk()
            ->assertJsonPath('latest_version_code', 10)
            ->assertJsonPath('force_update', false);
    }

    public function test_no_advertisable_release_means_no_update_and_no_download_fields(): void
    {
        $this->release(10, ['apk_checksum' => null]);

        $this->getJson(self::URL)->assertOk()
            ->assertJsonPath('has_update', false)
            ->assertJsonPath('force_update', false)
            ->assertJsonPath('latest_version', '1.0.5')
            ->assertJsonPath('latest_version_code', 5)
            ->assertJsonPath('download_url', null)
            ->assertJsonPath('checksum', null)
            ->assertJsonPath('file_size', null)
            ->assertJsonPath('file_size_bytes', null)
            ->assertJsonPath('release_notes', [])
            ->assertJsonPath('published_at', null)
            ->assertJsonPath('rollback', null)
            ->assertJsonPath('title', __('app_releases.check.title_up_to_date'))
            ->assertJsonPath('message', __('app_releases.check.message_up_to_date'));
    }

    public function test_up_to_date_client_gets_no_update_but_the_current_release_data(): void
    {
        $this->release(5);

        $this->getJson(self::URL)->assertOk()
            ->assertJsonPath('has_update', false)
            ->assertJsonPath('latest_version_code', 5)
            ->assertJsonPath('rollback', null)
            ->assertJsonPath('title', __('app_releases.check.title_up_to_date'));
    }

    public function test_rollback_skips_inactive_and_unpublishable_releases(): void
    {
        $this->release(6);
        $this->release(7, ['is_active' => false]);
        $this->release(8, ['apk_checksum' => null]);
        $this->release(9);

        $this->getJson(self::URL)
            ->assertJsonPath('latest_version_code', 9)
            ->assertJsonPath('rollback.version_code', 6);
    }

    public function test_a_legacy_public_binary_is_still_advertised_until_relocated(): void
    {
        $release = $this->release(10, storeFile: false);
        Storage::disk(AppVersion::LEGACY_RELEASE_DISK)->put((string) $release->apk_path, 'BINARY-10');

        $this->getJson(self::URL)->assertJsonPath('latest_version_code', 10);
    }

    public function test_no_hardcoded_texts_remain_in_the_controller(): void
    {
        $source = (string) file_get_contents(app_path('Http/Controllers/Api/AppUpdateController.php'));

        $this->assertDoesNotMatchRegularExpression('/\p{Arabic}/u', $source);
        $this->assertStringNotContainsString('18.5 MB', $source);
    }

    /** Desktop builds before APP-7 send version_code=0: they must be offered the update, not a 422. */
    public function test_legacy_desktop_version_code_zero_is_offered_the_update(): void
    {
        $this->release(1002003, ['platform' => 'windows', 'version_name' => '1.2.3']);

        $this->getJson('/api/v1/app/check-update?platform=windows&version_code=0')
            ->assertOk()
            ->assertJsonPath('has_update', true)
            ->assertJsonPath('latest_version_code', 1002003);
    }

    /** N1: version_code 0 or missing is UNKNOWN: the update is offered but never forced. */
    public function test_an_unknown_version_code_is_never_forced(): void
    {
        $this->release(10, ['is_force_update' => true, 'min_version_code' => 8]);

        foreach (['&version_code=0', ''] as $query) {
            $this->getJson('/api/v1/app/check-update?platform=android'.$query)
                ->assertOk()
                ->assertJsonPath('has_update', true)
                ->assertJsonPath('force_update', false)
                ->assertJsonPath('latest_version_code', 10);
        }

        // A known older build is still forced.
        $this->getJson('/api/v1/app/check-update?platform=android&version_code=7')
            ->assertJsonPath('force_update', true);
    }

    public function test_ios_and_unknown_platforms_are_422(): void
    {
        $this->getJson('/api/v1/app/check-update?platform=ios&version_code=1')
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['platform']);
    }
}
