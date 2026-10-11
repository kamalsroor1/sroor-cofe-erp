<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Models\AppVersion;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use Symfony\Component\HttpFoundation\Response;
use Tests\Concerns\IsolatesReleaseDisks;
use Tests\Concerns\SeedsCentralPlatformRoles;
use Tests\Support\FakeReleaseBinaries;
use Tests\TenantTestCase;

/**
 * W1 hardening note 5: release binaries (APK / EXE) uploaded by the super admin are stored
 * under a server-generated name. Neither the client's file name nor its extension is used,
 * and re-uploading a version never overwrites the binary of another release.
 *
 * APP-3 extension (W2 batch 5): binaries go to the PRIVATE release disk
 * (AppVersion::RELEASE_DISK), never to the public disk; iOS is not a platform.
 *
 * IDEN-1.8: uploaded by an App\Models\CentralUser (super_admin.app_versions.manage); the
 * read-only `support` role and tenant tokens never upload.
 */
final class SuperAdminAppVersionUploadTest extends TenantTestCase
{
    use IsolatesReleaseDisks;
    use SeedsCentralPlatformRoles;

    /** @var array<string, string> */
    private array $headers = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedCentralPlatformRoles();
        // Uploading a release demands a recent second factor (security audit, W2 lane 3I).
        $this->headers = $this->steppedUpCentralHeaders($this->centralSuperAdmin());
    }

    protected function tearDown(): void
    {
        FakeReleaseBinaries::cleanup();

        parent::tearDown();
    }

    public function test_android_release_is_stored_under_a_generated_name(): void
    {
        // The client name carries a person's name and a .php suffix; only .apk passes the
        // extension rule, so the name is "Kamal phone build.php.apk".
        $response = $this->upload('android', '2.4.0', 240, FakeReleaseBinaries::upload(FakeReleaseBinaries::apk(), 'Kamal phone build.php.apk'));

        $response->assertCreated();
        $version = AppVersion::query()->findOrFail($response->json('version.id'));

        $this->assertMatchesRegularExpression('#^apks/android/[0-9a-z]{26}\.apk$#', (string) $version->apk_path);
        $this->assertStringNotContainsStringIgnoringCase('kamal', (string) $version->apk_path);
        Storage::disk(AppVersion::RELEASE_DISK)->assertExists((string) $version->apk_path);
        // Never web-reachable: nothing lands on the public disk.
        $this->assertSame([], Storage::disk('public')->allFiles());

        // The download name is built from validated fields, never from the client's file.
        $this->assertStringEndsWith('-v240.apk', (string) $version->apk_filename);
        $this->assertStringNotContainsStringIgnoringCase('kamal', (string) $version->apk_filename);
        $this->assertMatchesRegularExpression('/^[a-f0-9]{64}$/', (string) $version->apk_checksum);
        $this->assertTrue($version->apk_signing_block);
    }

    public function test_extension_follows_the_platform_not_the_client_file(): void
    {
        $windows = $this->upload('windows', '3.0.0', 3000000, FakeReleaseBinaries::upload(FakeReleaseBinaries::exe(), 'SETUP.EXE'));

        $this->assertMatchesRegularExpression('#^apks/windows/[0-9a-z]{26}\.exe$#', (string) $windows->assertCreated()->json('version.apk_path'));

        // iOS is not a platform (CTO Q7): rejected, nothing stored.
        $ios = $this->upload('ios', '3.0.0', 301, UploadedFile::fake()->create('build.ipa', 64));
        $ios->assertUnprocessable()->assertJsonValidationErrors(['platform']);
        $this->assertSame([], Storage::disk(AppVersion::RELEASE_DISK)->allFiles('apks/ios'));
    }

    public function test_re_uploading_the_same_version_keeps_both_binaries(): void
    {
        $first = (string) $this->upload('android', '2.5.0', 250, FakeReleaseBinaries::upload(FakeReleaseBinaries::apk(), 'a.apk'))->assertCreated()->json('version.apk_path');
        $second = (string) $this->upload('android', '2.5.0', 251, FakeReleaseBinaries::upload(FakeReleaseBinaries::apk(['signing' => 'v3']), 'a.apk'))->assertCreated()->json('version.apk_path');

        $this->assertNotSame($first, $second);
        Storage::disk(AppVersion::RELEASE_DISK)->assertExists($first);
        Storage::disk(AppVersion::RELEASE_DISK)->assertExists($second);
    }

    public function test_support_and_tenant_tokens_cannot_upload_a_release(): void
    {
        $support = $this->centralHeaders($this->centralSupport());
        $tenant = $this->createTenant();
        $tenantBearer = ['Accept' => 'application/json', 'Authorization' => 'Bearer '.$this->tenantToken($tenant)];

        $this->upload('android', '9.0.0', 900, FakeReleaseBinaries::upload(FakeReleaseBinaries::apk(), 'a.apk'), $support)->assertForbidden();
        // A tenant token is no central identity: 401 (was 403 before IDEN-1.4).
        $this->upload('android', '9.0.1', 901, FakeReleaseBinaries::upload(FakeReleaseBinaries::apk(), 'a.apk'), $tenantBearer)->assertUnauthorized();
        $this->upload('android', '9.0.2', 902, FakeReleaseBinaries::upload(FakeReleaseBinaries::apk(), 'a.apk'), ['Accept' => 'application/json'])->assertUnauthorized();

        $this->assertSame(0, AppVersion::query()->whereIn('version_code', [900, 901, 902])->count());
        $this->assertSame([], Storage::disk(AppVersion::RELEASE_DISK)->allFiles('apks'));
        $this->assertSame([], Storage::disk('public')->allFiles('apks'));

        // Support may still list releases (super_admin.app_versions.view).
        $this->getJson('/api/v1/super-admin/app-versions', $support)->assertOk();
    }

    /**
     * @param  array<string, string>|null  $headers
     * @return TestResponse<Response>
     */
    private function upload(string $platform, string $versionName, int $versionCode, UploadedFile $file, ?array $headers = null): TestResponse
    {
        return $this->post('/api/v1/super-admin/app-versions', [
            'platform' => $platform,
            'version_name' => $versionName,
            'version_code' => $versionCode,
            'release_notes_ar' => 'ملاحظات',
            'apk_file' => $file,
        ], ($headers ?? $this->headers) + ['Accept' => 'application/json']);
    }
}
