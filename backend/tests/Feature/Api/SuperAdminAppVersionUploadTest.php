<?php

declare(strict_types=1);

namespace Tests\Feature\Api;

use App\Models\AppVersion;
use App\Models\Store;
use App\Models\User;
use Database\Seeders\PermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use Symfony\Component\HttpFoundation\Response;
use Tests\Concerns\SeedsCentralPlatformRoles;
use Tests\TestCase;

/**
 * W1 hardening note 5: release binaries (APK / EXE / IPA) uploaded by the super admin are
 * stored under a server-generated name. Neither the client's file name nor its extension
 * is used, and re-uploading a version never overwrites the binary of another release.
 */
final class SuperAdminAppVersionUploadTest extends TestCase
{
    use RefreshDatabase;
    use SeedsCentralPlatformRoles;

    private string $token = '';

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
        $this->seed(PermissionsSeeder::class);
        $this->seedCentralPlatformRoles();

        $store = Store::create(['name' => 'الفرع الرئيسي', 'code' => 'MAIN', 'is_main' => true, 'is_active' => true]);
        $user = User::factory()->create(['phone' => '01000000721', 'is_active' => true, 'default_store_id' => $store->id]);
        $user->assignRole('super_admin');
        $this->token = $user->createToken('t')->plainTextToken;
    }

    public function test_android_release_is_stored_under_a_generated_name(): void
    {
        $response = $this->upload('android', '2.4.0', 240, UploadedFile::fake()->create('Kamal phone build.php', 64));

        $response->assertCreated();
        $version = AppVersion::query()->findOrFail($response->json('version.id'));

        $this->assertMatchesRegularExpression('#^apks/android/[0-9a-z]{26}\.apk$#', (string) $version->apk_path);
        $this->assertStringNotContainsStringIgnoringCase('kamal', (string) $version->apk_path);
        Storage::disk('public')->assertExists((string) $version->apk_path);

        // The download name is built from validated fields, never from the client's file.
        $this->assertStringEndsWith('-v240.apk', (string) $version->apk_filename);
        $this->assertStringNotContainsStringIgnoringCase('kamal', (string) $version->apk_filename);
    }

    public function test_extension_follows_the_platform_not_the_client_file(): void
    {
        $windows = $this->upload('windows', '3.0.0', 300, UploadedFile::fake()->create('setup.zip', 64));
        $ios = $this->upload('ios', '3.0.0', 301, UploadedFile::fake()->create('build.html', 64));

        $this->assertMatchesRegularExpression('#^apks/windows/[0-9a-z]{26}\.exe$#', (string) $windows->assertCreated()->json('version.apk_path'));
        $this->assertMatchesRegularExpression('#^apks/ios/[0-9a-z]{26}\.ipa$#', (string) $ios->assertCreated()->json('version.apk_path'));
    }

    public function test_re_uploading_the_same_version_keeps_both_binaries(): void
    {
        $first = (string) $this->upload('android', '2.5.0', 250, UploadedFile::fake()->create('a.apk', 32))->assertCreated()->json('version.apk_path');
        $second = (string) $this->upload('android', '2.5.0', 251, UploadedFile::fake()->create('a.apk', 48))->assertCreated()->json('version.apk_path');

        $this->assertNotSame($first, $second);
        Storage::disk('public')->assertExists($first);
        Storage::disk('public')->assertExists($second);
    }

    /**
     * @return TestResponse<Response>
     */
    private function upload(string $platform, string $versionName, int $versionCode, UploadedFile $file): TestResponse
    {
        return $this->withHeader('Authorization', 'Bearer '.$this->token)
            ->post('/api/v1/super-admin/app-versions', [
                'platform' => $platform,
                'version_name' => $versionName,
                'version_code' => $versionCode,
                'release_notes_ar' => 'ملاحظات',
                'apk_file' => $file,
            ], ['Accept' => 'application/json']);
    }
}
