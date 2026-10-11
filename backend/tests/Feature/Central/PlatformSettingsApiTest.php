<?php

declare(strict_types=1);

namespace Tests\Feature\Central;

use App\Enums\CentralAuditEvent;
use App\Enums\PlatformAssetSlot;
use App\Models\CentralAuditLog;
use App\Models\CentralUser;
use App\Models\PlatformSetting;
use App\Services\Branding\PlatformBranding;
use GdImage;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\Concerns\SeedsCentralPlatformRoles;
use Tests\TenantTestCase;

/**
 * BRND-2: GET/PUT /api/v1/super-admin/platform-settings and
 * POST/DELETE /api/v1/super-admin/platform-settings/assets/{slot}.
 *
 *  - strict partial update (unknown keys and empty bodies are 422; null resets to config);
 *  - writes need super_admin.settings.manage AND a recent second factor; support reads only;
 *  - guests, tenant tokens → 401; a tenant host → 404 (EnsureCentralContext);
 *  - assets: ImageSanitizer (no SVG, payload stripped) → central_public branding/<uuid>.<ext>,
 *    old file deleted after commit, new file deleted if the DB write fails, audited, 10/min.
 */
final class PlatformSettingsApiTest extends TenantTestCase
{
    use SeedsCentralPlatformRoles;

    private const URL = '/api/v1/super-admin/platform-settings';

    private const MANAGED_PATH = '/^branding\/[0-9a-f-]{36}\.(png|jpg|webp)$/';

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedCentralPlatformRoles();
        Storage::fake(PlatformAssetSlot::DISK);
        app()->setLocale('en');
    }

    // ------------------------------------------------------------------ GET

    public function test_super_admin_and_support_read_the_effective_settings(): void
    {
        foreach ([$this->centralSuperAdmin(), $this->centralSupport()] as $operator) {
            $this->getJson(self::URL, $this->centralHeaders($operator))
                ->assertOk()
                ->assertJsonPath('success', true)
                ->assertJsonStructure(['data' => [
                    'name', 'short_name', 'subtitle', 'legal_name', 'primary_color', 'support_email',
                    'support_phone', 'website_url', 'powered_by_enabled',
                    'assets' => [
                        'logo_light' => ['url', 'custom'], 'logo_dark' => ['url', 'custom'],
                        'favicon' => ['url', 'custom'], 'app_icon' => ['url', 'custom'],
                    ],
                ]])
                ->assertJsonPath('data.assets.logo_light.custom', false);
        }
    }

    public function test_operator_without_a_role_is_403_on_read(): void
    {
        $this->getJson(self::URL, $this->centralHeaders($this->centralOperator(null)))->assertForbidden();
    }

    // ------------------------------------------------------------------ PUT

    public function test_put_updates_only_the_sent_keys_resets_nulls_and_is_audited(): void
    {
        $operator = $this->centralSuperAdmin();
        $headers = $this->steppedUpCentralHeaders($operator);

        $this->putJson(self::URL, ['subtitle' => 'Subtitle before', 'support_email' => 'help@platform.test'], $headers)->assertOk();

        $response = $this->putJson(self::URL, [
            'name' => 'منصة التجزئة',
            'primary_color' => '#AA22CC',
            'powered_by_enabled' => false,
            'subtitle' => null,
        ], $headers)
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', __('super.platform_settings_saved_success'))
            ->assertJsonPath('data.name', 'منصة التجزئة')
            ->assertJsonPath('data.primary_color', '#aa22cc')
            ->assertJsonPath('data.powered_by_enabled', false)
            // Absent => unchanged.
            ->assertJsonPath('data.support_email', 'help@platform.test');

        // null => back to the config default (row value cleared).
        $this->assertSame((string) config('branding.subtitle'), $response->json('data.subtitle'));
        $this->assertDatabaseHas('platform_settings', ['key' => 'subtitle', 'value' => null, 'updated_by' => $operator->getKey()]);
        $this->assertDatabaseHas('platform_settings', ['key' => 'name', 'value' => 'منصة التجزئة']);
        $this->assertDatabaseHas('platform_settings', ['key' => 'powered_by_enabled', 'value' => '0']);

        $audit = CentralAuditLog::query()->where('event', CentralAuditEvent::PlatformSettingsUpdated->value)->latest('id')->firstOrFail();
        $this->assertSame(CentralUser::class, $audit->causer_type);
        $this->assertSame((int) $operator->getKey(), (int) $audit->causer_id);
        $this->assertEqualsCanonicalizing(['name', 'primary_color', 'powered_by_enabled', 'subtitle'], $audit->properties['changed'] ?? []);

        // A fresh read (new token) sees the saved values.
        $this->getJson(self::URL, $this->centralHeaders($this->centralSupport()))
            ->assertOk()
            ->assertJsonPath('data.name', 'منصة التجزئة');
    }

    public function test_a_rename_is_visible_inside_a_tenant_context_immediately(): void
    {
        $tenant = $this->createTenant();
        $this->inTenant($tenant, fn () => app(PlatformBranding::class)->get()->name);

        $this->putJson(self::URL, ['name' => 'اسم جديد للمنصة'], $this->steppedUpCentralHeaders($this->centralSuperAdmin()))
            ->assertOk();

        $this->assertSame('اسم جديد للمنصة', $this->inTenant($tenant, fn () => app(PlatformBranding::class)->get()->name));
    }

    /**
     * @return array<string, array{0: array<string, mixed>, 1: list<string>}>
     */
    public static function invalidPayloads(): array
    {
        return [
            'empty body' => [[], ['settings']],
            'unknown key' => [['name' => 'ok', 'telegram_token' => 'x'], ['telegram_token']],
            'asset key is not editable here' => [['logo_light' => 'branding/x.png'], ['logo_light']],
            'blank name' => [['name' => ''], ['name']],
            'name with markup' => [['name' => '<b>x</b>'], ['name']],
            'name too long' => [['name' => str_repeat('a', 101)], ['name']],
            'short name too long' => [['short_name' => str_repeat('a', 31)], ['short_name']],
            'bad color' => [['primary_color' => 'red'], ['primary_color']],
            'short hex color' => [['primary_color' => '#fff'], ['primary_color']],
            'bad email' => [['support_email' => 'not-an-email'], ['support_email']],
            'bad phone' => [['support_phone' => 'call me'], ['support_phone']],
            'bad url' => [['website_url' => 'javascript:alert(1)'], ['website_url']],
            'bad boolean' => [['powered_by_enabled' => 'maybe'], ['powered_by_enabled']],
            'null boolean' => [['powered_by_enabled' => null], ['powered_by_enabled']],
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  list<string>  $errors
     */
    #[DataProvider('invalidPayloads')]
    public function test_put_rejects_invalid_payloads_without_writing(array $payload, array $errors): void
    {
        $this->putJson(self::URL, $payload, $this->steppedUpCentralHeaders($this->centralSuperAdmin()))
            ->assertStatus(422)
            ->assertJsonValidationErrors($errors);

        $this->assertSame(0, PlatformSetting::query()->count());
        $this->assertSame(0, CentralAuditLog::query()->where('event', CentralAuditEvent::PlatformSettingsUpdated->value)->count());
    }

    public function test_unknown_key_message_is_translated(): void
    {
        app()->setLocale('ar');

        $this->putJson(self::URL, ['foo' => 'bar'], $this->steppedUpCentralHeaders($this->centralSuperAdmin()) + ['Accept-Language' => 'ar'])
            ->assertStatus(422)
            ->assertJsonPath('errors.foo.0', __('super.platform_branding.unknown_field'));
    }

    // ------------------------------------------------------------------ auth matrix

    /**
     * @return array<string, array{0: string, 1: string}>
     */
    public static function writeRequests(): array
    {
        return [
            'put settings' => ['PUT', self::URL],
            'upload asset' => ['POST', self::URL.'/assets/logo_light'],
            'delete asset' => ['DELETE', self::URL.'/assets/logo_light'],
        ];
    }

    #[DataProvider('writeRequests')]
    public function test_writes_without_a_recent_second_factor_are_403_step_up_required(string $method, string $uri): void
    {
        $this->json($method, $uri, ['name' => 'x'], $this->centralHeaders($this->centralSuperAdmin()))
            ->assertForbidden()
            ->assertJsonPath('error_code', 'central_auth.step_up_required');

        $this->json($method, $uri, ['name' => 'x'], $this->steppedUpCentralHeaders($this->centralSuperAdmin(), now()->subHour()))
            ->assertForbidden()
            ->assertJsonPath('error_code', 'central_auth.step_up_required');

        $this->assertSame(0, PlatformSetting::query()->count());
    }

    #[DataProvider('writeRequests')]
    public function test_support_gets_a_plain_403_on_writes(string $method, string $uri): void
    {
        $this->json($method, $uri, ['name' => 'x'], $this->steppedUpCentralHeaders($this->centralSupport()))
            ->assertForbidden()
            ->assertJsonMissingPath('error_code');

        $this->assertSame(0, PlatformSetting::query()->count());
    }

    public function test_guest_and_tenant_tokens_are_401(): void
    {
        $tenant = $this->createTenant();
        $tenantBearer = ['Accept' => 'application/json', 'Authorization' => 'Bearer '.$this->tenantToken($tenant)];

        $this->getJson(self::URL)->assertUnauthorized();
        $this->putJson(self::URL, ['name' => 'x'])->assertUnauthorized();
        $this->postJson(self::URL.'/assets/logo_light')->assertUnauthorized();

        $this->getJson(self::URL, $tenantBearer)->assertUnauthorized();
        $this->putJson(self::URL, ['name' => 'x'], $tenantBearer)->assertUnauthorized();
        $this->deleteJson(self::URL.'/assets/logo_light', [], $tenantBearer)->assertUnauthorized();

        $this->assertSame(0, PlatformSetting::query()->count());
    }

    public function test_tenant_host_is_404_even_for_an_operator(): void
    {
        $tenant = $this->createTenant();
        $headers = $this->steppedUpCentralHeaders($this->centralSuperAdmin());

        $this->getJson($this->tenantUrl($tenant, self::URL), $headers)->assertNotFound();
        $this->putJson($this->tenantUrl($tenant, self::URL), ['name' => 'x'], $headers)->assertNotFound();
        $this->call('POST', $this->tenantUrl($tenant, self::URL.'/assets/logo_light'), [], [], ['file' => $this->pngUpload(128, 64)], $this->server($headers))
            ->assertNotFound();

        $this->assertSame(0, PlatformSetting::query()->count());
    }

    // ------------------------------------------------------------------ assets

    public function test_upload_sanitizes_the_image_and_stores_it_on_the_central_disk(): void
    {
        $operator = $this->centralSuperAdmin();
        $payload = '<?php echo "pwned"; ?>';

        $response = $this->upload('logo_light', $this->pngUpload(128, 64, $payload, 'evil.php.png'), $this->steppedUpCentralHeaders($operator))
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', __('super.platform_branding.asset_uploaded'))
            ->assertJsonPath('data.assets.logo_light.custom', true);

        $path = (string) PlatformSetting::query()->where('key', 'logo_light')->value('value');
        $this->assertMatchesRegularExpression(self::MANAGED_PATH, $path);
        $this->assertStringEndsWith('.png', $path);
        $this->assertStringNotContainsString('evil', $path);

        $disk = Storage::disk(PlatformAssetSlot::DISK);
        $disk->assertExists($path);
        $stored = (string) $disk->get($path);
        $this->assertSame("\x89PNG\r\n\x1a\n", substr($stored, 0, 8));
        $this->assertStringNotContainsString('pwned', $stored, 'appended payload must be stripped by the re-encode');
        $this->assertStringContainsString(basename($path), (string) $response->json('data.assets.logo_light.url'));

        $audit = CentralAuditLog::query()->where('event', CentralAuditEvent::PlatformAssetUploaded->value)->sole();
        $this->assertSame((int) $operator->getKey(), (int) $audit->causer_id);
        $this->assertSame('logo_light', $audit->properties['slot'] ?? null);
        $this->assertSame($path, $audit->properties['path'] ?? null);
        $this->assertSame(hash('sha256', $stored), $audit->properties['sha256'] ?? null);
    }

    public function test_replacing_an_asset_deletes_the_previous_file_after_commit(): void
    {
        $headers = $this->steppedUpCentralHeaders($this->centralSuperAdmin());
        $disk = Storage::disk(PlatformAssetSlot::DISK);

        $this->upload('logo_dark', $this->pngUpload(128, 64), $headers)->assertOk();
        $first = (string) PlatformSetting::query()->where('key', 'logo_dark')->value('value');

        $this->upload('logo_dark', $this->pngUpload(256, 128), $headers)->assertOk();
        $second = (string) PlatformSetting::query()->where('key', 'logo_dark')->value('value');

        $this->assertNotSame($first, $second);
        $disk->assertMissing($first);
        $disk->assertExists($second);
        $this->assertCount(1, $disk->allFiles());
    }

    /**
     * @return array<string, array{0: string, 1: string}>
     */
    public static function rejectedUploads(): array
    {
        return [
            'svg logo' => ['logo_light', 'svg'],
            'svg renamed to png' => ['logo_dark', 'svg-as-png'],
            'gif logo' => ['logo_light', 'gif'],
            'jpeg favicon' => ['favicon', 'jpeg-square'],
            'non-square favicon' => ['favicon', 'png-wide'],
            'too small app icon' => ['app_icon', 'png-tiny-square'],
            'not an image' => ['logo_light', 'text'],
        ];
    }

    #[DataProvider('rejectedUploads')]
    public function test_rejected_uploads_are_422_and_write_nothing(string $slot, string $kind): void
    {
        $this->upload($slot, $this->badUpload($kind), $this->steppedUpCentralHeaders($this->centralSuperAdmin()))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['file']);

        $this->assertSame([], Storage::disk(PlatformAssetSlot::DISK)->allFiles());
        $this->assertSame(0, PlatformSetting::query()->count());
        $this->assertSame(0, CentralAuditLog::query()->where('event', CentralAuditEvent::PlatformAssetUploaded->value)->count());
    }

    public function test_svg_rejection_message_is_translated(): void
    {
        $this->upload('logo_light', $this->badUpload('svg'), $this->steppedUpCentralHeaders($this->centralSuperAdmin()))
            ->assertStatus(422)
            ->assertJsonPath('errors.file.0', __('media.svg_forbidden'));
    }

    public function test_missing_file_is_422(): void
    {
        $this->postJson(self::URL.'/assets/favicon', [], $this->steppedUpCentralHeaders($this->centralSuperAdmin()))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['file']);
    }

    public function test_unknown_slot_is_404(): void
    {
        $headers = $this->steppedUpCentralHeaders($this->centralSuperAdmin());

        $this->upload('splash', $this->pngUpload(128, 64), $headers)->assertNotFound();
        $this->deleteJson(self::URL.'/assets/splash', [], $headers)->assertNotFound();
    }

    public function test_a_failed_db_write_deletes_the_new_file(): void
    {
        $headers = $this->steppedUpCentralHeaders($this->centralSuperAdmin());

        PlatformSetting::saving(static function (): void {
            throw new RuntimeException('simulated central write failure');
        });

        $this->upload('app_icon', $this->pngUpload(256, 256), $headers)->assertStatus(500);

        $this->assertSame([], Storage::disk(PlatformAssetSlot::DISK)->allFiles());
        $this->assertSame(0, PlatformSetting::query()->count());
        $this->assertSame(0, CentralAuditLog::query()->where('event', CentralAuditEvent::PlatformAssetUploaded->value)->count());
    }

    public function test_delete_removes_the_file_resets_the_slot_and_is_audited(): void
    {
        $operator = $this->centralSuperAdmin();
        $headers = $this->steppedUpCentralHeaders($operator);
        $disk = Storage::disk(PlatformAssetSlot::DISK);

        $this->upload('favicon', $this->pngUpload(64, 64), $headers)->assertOk();
        $path = (string) PlatformSetting::query()->where('key', 'favicon')->value('value');
        $disk->assertExists($path);

        $this->deleteJson(self::URL.'/assets/favicon', [], $headers)
            ->assertOk()
            ->assertJsonPath('message', __('super.platform_branding.asset_deleted'))
            ->assertJsonPath('data.assets.favicon.custom', false);

        $disk->assertMissing($path);
        $this->assertDatabaseHas('platform_settings', ['key' => 'favicon', 'value' => null]);

        $audit = CentralAuditLog::query()->where('event', CentralAuditEvent::PlatformAssetDeleted->value)->sole();
        $this->assertSame((int) $operator->getKey(), (int) $audit->causer_id);
        $this->assertSame($path, $audit->properties['path'] ?? null);

        // Idempotent: deleting an empty slot changes nothing and is not audited again.
        $this->deleteJson(self::URL.'/assets/favicon', [], $headers)->assertOk();
        $this->assertSame(1, CentralAuditLog::query()->where('event', CentralAuditEvent::PlatformAssetDeleted->value)->count());
    }

    public function test_delete_never_removes_a_file_this_feature_did_not_write(): void
    {
        $disk = Storage::disk(PlatformAssetSlot::DISK);
        $disk->put('keep/me.png', 'x');
        PlatformSetting::query()->create(['key' => 'logo_light', 'value' => 'keep/me.png', 'type' => 'asset']);

        $this->deleteJson(self::URL.'/assets/logo_light', [], $this->steppedUpCentralHeaders($this->centralSuperAdmin()))->assertOk();

        $disk->assertExists('keep/me.png');
        $this->assertDatabaseHas('platform_settings', ['key' => 'logo_light', 'value' => null]);
    }

    public function test_asset_endpoints_are_throttled_to_ten_per_minute(): void
    {
        $headers = $this->steppedUpCentralHeaders($this->centralSuperAdmin());

        for ($i = 0; $i < 10; $i++) {
            $this->deleteJson(self::URL.'/assets/logo_light', [], $headers)->assertOk();
        }

        $this->deleteJson(self::URL.'/assets/logo_light', [], $headers)->assertStatus(429);
    }

    // ------------------------------------------------------------------ helpers

    /**
     * @param  array<string, string>  $headers
     */
    private function upload(string $slot, UploadedFile $file, array $headers): TestResponse
    {
        return $this->call('POST', self::URL.'/assets/'.$slot, [], [], ['file' => $file], $this->server($headers));
    }

    /**
     * @param  array<string, string>  $headers
     * @return array<string, string>
     */
    private function server(array $headers): array
    {
        $server = [];
        foreach ($headers as $name => $value) {
            $server['HTTP_'.strtoupper(str_replace('-', '_', $name))] = $value;
        }

        return $server;
    }

    private function pngUpload(int $width, int $height, string $append = '', string $name = 'logo.png'): UploadedFile
    {
        return UploadedFile::fake()->createWithContent($name, $this->capture(fn (GdImage $image) => imagepng($image), $width, $height).$append);
    }

    private function badUpload(string $kind): UploadedFile
    {
        $svg = '<svg xmlns="http://www.w3.org/2000/svg" width="100" height="100"><script>alert(1)</script></svg>';

        return match ($kind) {
            'svg' => UploadedFile::fake()->createWithContent('logo.svg', '<?xml version="1.0"?>'.$svg),
            'svg-as-png' => UploadedFile::fake()->createWithContent('logo.png', $svg),
            'gif' => UploadedFile::fake()->createWithContent('logo.png', $this->capture(fn (GdImage $image) => imagegif($image), 100, 100)),
            'jpeg-square' => UploadedFile::fake()->createWithContent('favicon.png', $this->capture(fn (GdImage $image) => imagejpeg($image), 64, 64)),
            'png-wide' => $this->pngUpload(128, 64, '', 'favicon.png'),
            'png-tiny-square' => $this->pngUpload(64, 64, '', 'icon.png'),
            default => UploadedFile::fake()->createWithContent('logo.png', 'just some text, not an image'),
        };
    }

    /**
     * @param  callable(GdImage): bool  $writer
     */
    private function capture(callable $writer, int $width, int $height): string
    {
        $image = imagecreatetruecolor($width, $height);
        if (! $image instanceof GdImage) {
            throw new RuntimeException('GD could not allocate a test image.');
        }

        imagefill($image, 0, 0, (int) imagecolorallocate($image, 200, 30, 30));

        ob_start();
        $writer($image);

        return (string) ob_get_clean();
    }
}
