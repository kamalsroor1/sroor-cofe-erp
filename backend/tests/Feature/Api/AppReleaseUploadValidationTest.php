<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Models\AppVersion;
use App\Support\AppReleases\ReleaseBinaryReport;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Symfony\Component\HttpFoundation\Response;
use Tests\Concerns\IsolatesReleaseDisks;
use Tests\Concerns\SeedsCentralPlatformRoles;
use Tests\Support\FakeReleaseBinaries;
use Tests\TenantTestCase;

/**
 * APP-3 extension (W2 batch 5): POST /api/v1/super-admin/app-versions validates the release
 * strictly (StoreAppVersionRequest + ReleaseBinaryInspector) and never leaves a stored binary
 * without its row (CreateAppVersionAction).
 */
final class AppReleaseUploadValidationTest extends TenantTestCase
{
    use IsolatesReleaseDisks;
    use SeedsCentralPlatformRoles;

    /** @var array<string, string> */
    private array $headers = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedCentralPlatformRoles();
        $this->headers = $this->steppedUpCentralHeaders($this->centralSuperAdmin()) + ['Accept' => 'application/json'];
    }

    protected function tearDown(): void
    {
        AppVersion::flushEventListeners();
        FakeReleaseBinaries::cleanup();

        parent::tearDown();
    }

    public function test_a_signed_apk_is_published_with_its_integrity_columns(): void
    {
        $path = FakeReleaseBinaries::apk();
        $checksum = hash_file('sha256', $path);

        $response = $this->upload(['apk_file' => FakeReleaseBinaries::upload($path, 'release.apk')])->assertCreated();

        $version = AppVersion::query()->findOrFail($response->json('version.id'));
        $this->assertSame('android', $version->platform);
        $this->assertSame($checksum, $version->apk_checksum);
        $this->assertSame(filesize($path), $version->apk_size_bytes);
        $this->assertTrue($version->apk_signing_block);
        $this->assertFalse($version->authenticode_present);
        $this->assertNotNull($version->binary_mime);
        $this->assertSame(1, $version->min_version_code, 'Omitted min_version_code must not force every installed build.');
        Storage::disk(AppVersion::RELEASE_DISK)->assertExists((string) $version->apk_path);
        $this->assertSame([], Storage::disk('public')->allFiles());
    }

    public function test_a_windows_installer_needs_the_semver_code_and_records_authenticode(): void
    {
        $file = FakeReleaseBinaries::upload(FakeReleaseBinaries::exe(['authenticode' => true]), 'Setup.exe');

        $response = $this->upload([
            'platform' => 'windows',
            'version_name' => '1.4.2',
            'version_code' => 1004002,
            'apk_file' => $file,
        ])->assertCreated();

        $version = AppVersion::query()->findOrFail($response->json('version.id'));
        $this->assertTrue($version->authenticode_present);
        $this->assertFalse($version->apk_signing_block);
        $this->assertStringEndsWith('.exe', (string) $version->apk_path);
    }

    public function test_windows_code_that_does_not_match_the_name_is_rejected(): void
    {
        $this->upload([
            'platform' => 'windows',
            'version_name' => '1.4.2',
            'version_code' => 142,
            'apk_file' => FakeReleaseBinaries::upload(FakeReleaseBinaries::exe(), 'Setup.exe'),
        ])->assertUnprocessable()
            ->assertJsonPath('errors.version_code.0', __('app_releases.windows_code_mismatch', ['name' => '1.4.2', 'code' => 1004002]));

        $this->assertNothingStored();
    }

    /**
     * @return array<string, array{0: array<string, mixed>, 1: string}>
     */
    public static function invalidFields(): array
    {
        return [
            'ios platform' => [['platform' => 'ios'], 'platform'],
            'unknown platform' => [['platform' => 'linux'], 'platform'],
            'two-part name' => [['version_name' => '1.2'], 'version_name'],
            'pre-release name' => [['version_name' => '1.2.3-beta'], 'version_name'],
            'four-digit part' => [['version_name' => '1.2000.3'], 'version_name'],
            'code zero' => [['version_code' => 0], 'version_code'],
            'code above int32' => [['version_code' => 2147483648], 'version_code'],
            'code not integer' => [['version_code' => '12abc'], 'version_code'],
            'min above code' => [['version_code' => 50, 'min_version_code' => 51], 'min_version_code'],
            'notes too long' => [['release_notes_ar' => 'x'], 'release_notes_ar'], // replaced in the test
            'english notes too long' => [['release_notes_en' => 'x'], 'release_notes_en'], // replaced in the test
            'missing notes' => [['release_notes_ar' => ''], 'release_notes_ar'],
            'missing file' => [['apk_file' => null], 'apk_file'],
            'zip extension' => [['apk_file' => 'zip'], 'apk_file'],
            'exe for android' => [['apk_file' => 'exe'], 'apk_file'],
        ];
    }

    /**
     * @param  array<string, mixed>  $override
     */
    #[DataProvider('invalidFields')]
    public function test_invalid_fields_are_422_and_nothing_is_stored(array $override, string $field): void
    {
        foreach (['release_notes_ar', 'release_notes_en'] as $notes) {
            if (($override[$notes] ?? null) === 'x') {
                $override[$notes] = str_repeat('ن', 5001);
            }
        }
        if (($override['apk_file'] ?? null) === 'zip') {
            $override['apk_file'] = FakeReleaseBinaries::upload(FakeReleaseBinaries::apk(), 'release.zip');
        } elseif (($override['apk_file'] ?? null) === 'exe') {
            $override['apk_file'] = FakeReleaseBinaries::upload(FakeReleaseBinaries::exe(), 'release.exe');
        }

        $this->upload($override)->assertUnprocessable()->assertJsonValidationErrors([$field]);

        $this->assertNothingStored();
    }

    /**
     * @return array<string, array{0: string, 1: string}>
     */
    public static function invalidBinaries(): array
    {
        return [
            'unsigned apk' => ['unsigned', ReleaseBinaryReport::SIGNING_BLOCK_MISSING],
            'apk without manifest' => ['no_manifest', ReleaseBinaryReport::MANIFEST_MISSING],
            'zip-slip apk' => ['zip_slip', ReleaseBinaryReport::UNSAFE_ENTRY],
            'text file named apk' => ['text', ReleaseBinaryReport::MIME_NOT_ALLOWED],
        ];
    }

    #[DataProvider('invalidBinaries')]
    public function test_invalid_android_binaries_are_422_with_the_reason(string $kind, string $failure): void
    {
        $path = match ($kind) {
            'unsigned' => FakeReleaseBinaries::apk(['signing' => 'none']),
            'no_manifest' => FakeReleaseBinaries::apk(['manifest' => false]),
            'zip_slip' => FakeReleaseBinaries::apk(['entries' => ['../../../public/index.php' => '<?php echo 1;']]),
            default => FakeReleaseBinaries::plain('plain text pretending to be an apk'),
        };

        $this->upload(['apk_file' => FakeReleaseBinaries::upload((string) $path, 'release.apk')])
            ->assertUnprocessable()
            ->assertJsonPath('errors.apk_file.0', __('app_releases.invalid_binary'))
            ->assertJsonPath('errors.apk_file.1', __('app_releases.binary_errors.'.$failure));

        $this->assertNothingStored();
    }

    public function test_a_dll_is_not_a_windows_installer(): void
    {
        $this->upload([
            'platform' => 'windows',
            'version_name' => '2.0.0',
            'version_code' => 2000000,
            'apk_file' => FakeReleaseBinaries::upload(FakeReleaseBinaries::exe(['dll' => true]), 'evil.exe'),
        ])->assertUnprocessable()
            ->assertJsonPath('errors.apk_file.1', __('app_releases.binary_errors.'.ReleaseBinaryReport::IS_DLL));

        $this->assertNothingStored();
    }

    public function test_version_code_must_be_newer_than_every_release_of_the_platform(): void
    {
        $this->existingRelease('android', 300);
        $this->existingRelease('windows', 5000000); // another platform never blocks android

        $this->upload(['version_code' => 299])
            ->assertUnprocessable()
            ->assertJsonPath('errors.version_code.0', __('app_releases.version_not_newer', [
                'platform' => __('app_releases.platforms.android'),
                'max' => 300,
            ]));

        $this->upload(['version_code' => 300])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['version_code']);

        $this->assertSame(1, AppVersion::query()->where('platform', 'android')->count());
        $this->assertSame([], Storage::disk(AppVersion::RELEASE_DISK)->allFiles());

        $this->upload(['version_code' => 301])->assertCreated();
    }

    public function test_the_stored_binary_is_deleted_when_the_database_write_fails(): void
    {
        AppVersion::creating(static function (): void {
            throw new RuntimeException('simulated database failure');
        });

        $this->upload()->assertStatus(500);

        $this->assertSame(0, AppVersion::query()->count());
        $this->assertSame([], Storage::disk(AppVersion::RELEASE_DISK)->allFiles(), 'No orphan binary may survive a failed insert.');
    }

    /** A parallel upload that wins the race hits unique(platform, version_code): 422, file removed. */
    public function test_a_lost_race_on_the_unique_index_is_422_and_leaves_no_file(): void
    {
        AppVersion::creating(static function (AppVersion $version): void {
            DB::connection($version->getConnectionName())->table('app_versions')->insert([
                'platform' => $version->platform,
                'version_name' => $version->version_name,
                'version_code' => $version->version_code,
                'min_version_code' => 1,
                'is_force_update' => false,
                'release_notes_ar' => 'سباق',
                'apk_size_bytes' => 0,
                'download_count' => 0,
                'is_active' => false,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        });

        $this->upload()
            ->assertUnprocessable()
            ->assertJsonPath('errors.version_code.0', __('app_releases.version_code_taken', ['platform' => __('app_releases.platforms.android')]));

        $this->assertSame([], Storage::disk(AppVersion::RELEASE_DISK)->allFiles());
    }

    public function test_messages_follow_the_request_locale(): void
    {
        $this->upload(['platform' => 'ios'], $this->headers + ['X-Locale' => 'en'])
            ->assertUnprocessable()
            ->assertJsonPath('errors.platform.0', __('app_releases.platform_not_supported', [], 'en'));
    }

    private function assertNothingStored(): void
    {
        $this->assertSame([], Storage::disk(AppVersion::RELEASE_DISK)->allFiles());
        $this->assertSame([], Storage::disk('public')->allFiles());
        $this->assertSame(0, AppVersion::query()->where('release_notes_ar', 'ملاحظات الإصدار')->count());
    }

    private function existingRelease(string $platform, int $code): void
    {
        AppVersion::query()->create([
            'platform' => $platform,
            'version_name' => '1.0.0',
            'version_code' => $code,
            'release_notes_ar' => 'قديم',
            'is_active' => true,
        ]);
    }

    /**
     * @param  array<string, mixed>  $override  a null value removes the field
     * @param  array<string, string>|null  $headers
     * @return TestResponse<Response>
     */
    private function upload(array $override = [], ?array $headers = null): TestResponse
    {
        $payload = array_merge([
            'platform' => 'android',
            'version_name' => '2.0.0',
            'version_code' => 200,
            'release_notes_ar' => 'ملاحظات الإصدار',
            'apk_file' => FakeReleaseBinaries::upload(FakeReleaseBinaries::apk(), 'release.apk'),
        ], $override);

        $payload = array_filter($payload, static fn (mixed $value): bool => $value !== null);

        return $this->post('/api/v1/super-admin/app-versions', $payload, $headers ?? $this->headers);
    }
}
