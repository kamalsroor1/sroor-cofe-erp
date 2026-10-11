<?php

declare(strict_types=1);

namespace Tests\Feature\Branding;

use App\Models\Setting;
use App\Models\Tenant;
use App\Models\TenantBrandProfile;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Tests\TenantTestCase;

/**
 * BRND-5: shop (tenant) branding management.
 *
 *  - POST/DELETE /api/v1/settings/branding/logo/{light|dark} (settings.manage): the logo is
 *    sanitized and stored through the tenant's TenantBrandProfile → tenant `media` table and
 *    tenant-suffixed disk. Nothing is written to public/ (shared by every tenant).
 *  - Legacy POST /api/v1/settings with logo_*_file now really stores the logo (it was ignored).
 *  - New settings keys: receipt_header_lines, receipt_footer_text, validated system_theme_color.
 *  - /system/context carries the `branding` block (platform + tenant) with the back-compat keys.
 */
#[Group('harness')]
final class TenantBrandingApiTest extends TenantTestCase
{
    private Tenant $tenant;

    /** @var array<string, string|false> public/logo* file => md5 before the test */
    private array $publicLogosBefore = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = $this->createTenant();
        $this->publicLogosBefore = $this->publicLogoFingerprint();
    }

    protected function tearDown(): void
    {
        // Every test: the shared public/logo* files are never written or added.
        $this->assertSame($this->publicLogosBefore, $this->publicLogoFingerprint(), 'public/logo* must never change.');

        parent::tearDown();
    }

    // ── upload ───────────────────────────────────────────────────────────

    public function test_admin_uploads_a_light_logo_into_the_tenant_db_and_tenant_disk(): void
    {
        $response = $this->post('/api/v1/settings/branding/logo/light', [
            'file' => UploadedFile::fake()->image('../../evil name.png', 300, 120),
        ], $this->tenantHeaders($this->tenant));

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', __('branding.logo_uploaded'))
            ->assertJsonPath('data.logos.dark', null);

        $url = (string) $response->json('data.logos.light');
        $this->assertStringStartsWith('http://'.$this->tenantDomain($this->tenant).'/api/v1/branding/logo/light?v=', $url);

        $stored = $this->inTenant($this->tenant, function (): array {
            $media = Media::query()->where('collection_name', 'logo_light')->sole();

            return [
                'model_type' => $media->model_type,
                'disk' => $media->disk,
                'file_name' => $media->file_name,
                'path' => $media->getPath(),
                'sha256' => $media->getCustomProperty('sha256'),
                'width' => $media->getCustomProperty('width'),
            ];
        });

        $this->assertSame(TenantBrandProfile::class, $stored['model_type']);
        $this->assertSame('public', $stored['disk']);
        $this->assertMatchesRegularExpression('/^[0-9a-z]{26}\.png$/', $stored['file_name'], 'Server-generated name, never the client name.');
        $this->assertFileExists($stored['path']);
        $this->assertStringContainsString($this->tenantDirName($this->tenant), $stored['path']);
        $this->assertSame(hash('sha256', (string) file_get_contents($stored['path'])), $stored['sha256']);
        $this->assertSame(300, $stored['width']);
        $this->assertStringEndsWith('?v='.substr((string) $stored['sha256'], 0, 16), $url);

        // Never in the central DB.
        $this->assertSame(0, DB::connection($this->centralConnectionName())->table('media')->count());
    }

    public function test_replacing_a_logo_keeps_only_the_new_media_and_removes_the_old_file(): void
    {
        $headers = $this->tenantHeaders($this->tenant);

        $this->post('/api/v1/settings/branding/logo/dark', ['file' => UploadedFile::fake()->image('a.png', 100, 100)], $headers)->assertOk();
        $oldPath = $this->inTenant($this->tenant, fn (): string => Media::query()->where('collection_name', 'logo_dark')->sole()->getPath());

        $this->post('/api/v1/settings/branding/logo/dark', ['file' => UploadedFile::fake()->image('b.jpg', 120, 80)], $headers)
            ->assertOk()
            ->assertJsonPath('data.logos.light', null);

        $media = $this->inTenant($this->tenant, fn () => Media::query()->where('collection_name', 'logo_dark')->get());

        $this->assertCount(1, $media);
        $this->assertStringEndsWith('.jpg', (string) $media->first()?->file_name);
        $this->assertFileDoesNotExist($oldPath);
        $this->assertSame(1, $this->inTenant($this->tenant, fn (): int => TenantBrandProfile::query()->count()));
    }

    public function test_delete_removes_the_logo_and_is_idempotent(): void
    {
        $headers = $this->tenantHeaders($this->tenant);
        $this->post('/api/v1/settings/branding/logo/light', ['file' => UploadedFile::fake()->image('a.png', 100, 100)], $headers)->assertOk();
        $path = $this->inTenant($this->tenant, fn (): string => Media::query()->sole()->getPath());

        $this->deleteJson('/api/v1/settings/branding/logo/light', [], $headers)
            ->assertOk()
            ->assertJsonPath('message', __('branding.logo_deleted'))
            ->assertJsonPath('data.logos.light', null);

        $this->assertFileDoesNotExist($path);
        $this->assertSame(0, $this->inTenant($this->tenant, fn (): int => Media::query()->count()));

        $this->deleteJson('/api/v1/settings/branding/logo/light', [], $headers)->assertOk();
    }

    public function test_guest_gets_401(): void
    {
        $guest = $this->tenantGuestHeaders($this->tenant);

        $this->post('/api/v1/settings/branding/logo/light', ['file' => UploadedFile::fake()->image('a.png', 100, 100)], $guest)->assertStatus(401);
        $this->deleteJson('/api/v1/settings/branding/logo/light', [], $guest)->assertStatus(401);
    }

    public function test_user_without_settings_manage_gets_403_and_nothing_is_stored(): void
    {
        $cashier = $this->createTenantUser($this->tenant, 'cashier');
        $headers = $this->tenantHeaders($this->tenant, $cashier);

        $this->post('/api/v1/settings/branding/logo/light', ['file' => UploadedFile::fake()->image('a.png', 100, 100)], $headers)->assertStatus(403);
        $this->deleteJson('/api/v1/settings/branding/logo/light', [], $headers)->assertStatus(403);

        $this->assertSame(0, $this->inTenant($this->tenant, fn (): int => Media::query()->count()));
    }

    public function test_unknown_variant_is_404(): void
    {
        $this->post('/api/v1/settings/branding/logo/favicon', ['file' => UploadedFile::fake()->image('a.png', 100, 100)], $this->tenantHeaders($this->tenant))
            ->assertNotFound();
    }

    /**
     * @return array<string, array{0: \Closure(): (UploadedFile|null), 1: string}>
     */
    public static function invalidUploads(): array
    {
        return [
            'missing file' => [static fn (): ?UploadedFile => null, 'file'],
            'svg' => [static fn (): UploadedFile => UploadedFile::fake()->createWithContent('logo.svg', '<svg xmlns="http://www.w3.org/2000/svg" onload="alert(1)"></svg>'), 'file'],
            'svg disguised as png' => [static fn (): UploadedFile => UploadedFile::fake()->createWithContent('logo.png', '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>'), 'file'],
            'gif' => [static fn (): UploadedFile => UploadedFile::fake()->image('logo.gif', 100, 100), 'file'],
            'too small' => [static fn (): UploadedFile => UploadedFile::fake()->image('logo.png', 32, 32), 'file'],
            'too large in pixels' => [static fn (): UploadedFile => UploadedFile::fake()->image('logo.png', 2100, 64), 'file'],
            'not an image' => [static fn (): UploadedFile => UploadedFile::fake()->createWithContent('logo.png', '<?php echo 1;'), 'file'],
        ];
    }

    #[DataProvider('invalidUploads')]
    public function test_invalid_upload_is_422_and_nothing_is_stored(\Closure $file, string $field): void
    {
        $upload = $file();

        $this->post('/api/v1/settings/branding/logo/light', $upload !== null ? ['file' => $upload] : [], $this->tenantHeaders($this->tenant) + ['Accept' => 'application/json'])
            ->assertStatus(422)
            ->assertJsonValidationErrors([$field]);

        $this->assertSame(0, $this->inTenant($this->tenant, fn (): int => Media::query()->count()));
    }

    // ── legacy settings form ─────────────────────────────────────────────

    public function test_legacy_settings_form_stores_both_logos_per_tenant(): void
    {
        $this->post('/api/v1/settings', [
            'company_name' => 'محمصة الاختبار',
            'logo_light_file' => UploadedFile::fake()->image('light.png', 200, 100),
            'logo_dark_file' => UploadedFile::fake()->image('dark.webp', 200, 100),
        ], $this->tenantHeaders($this->tenant))->assertOk()->assertJsonPath('success', true);

        $collections = $this->inTenant($this->tenant, fn (): array => Media::query()->orderBy('collection_name')->pluck('collection_name')->all());
        $this->assertSame(['logo_dark', 'logo_light'], $collections);
        $this->assertSame('محمصة الاختبار', $this->inTenant($this->tenant, fn (): ?string => Setting::query()->where('key', 'company_name')->value('value')));

        $settings = $this->getJson('/api/v1/settings', $this->tenantHeaders($this->tenant))->assertOk()->json('settings');
        $this->assertStringContainsString('/api/v1/branding/logo/light?v=', (string) $settings['logo_light_url']);
        $this->assertStringContainsString('/api/v1/branding/logo/dark?v=', (string) $settings['logo_dark_url']);
        $this->assertArrayNotHasKey('logo_file', $settings);
    }

    public function test_legacy_logo_file_field_means_the_light_logo(): void
    {
        $this->post('/api/v1/settings', [
            'company_name' => 'محل',
            'logo_file' => UploadedFile::fake()->image('logo.png', 200, 100),
        ], $this->tenantHeaders($this->tenant))->assertOk();

        $this->assertSame(['logo_light'], $this->inTenant($this->tenant, fn (): array => Media::query()->pluck('collection_name')->all()));
    }

    public function test_legacy_settings_form_with_a_bad_logo_is_422_and_saves_nothing(): void
    {
        $this->inTenant($this->tenant, fn () => Setting::set('company_name', 'الاسم القديم'));

        $this->post('/api/v1/settings', [
            'company_name' => 'اسم جديد',
            'logo_light_file' => UploadedFile::fake()->createWithContent('logo.png', '<svg xmlns="http://www.w3.org/2000/svg"></svg>'),
        ], $this->tenantHeaders($this->tenant) + ['Accept' => 'application/json'])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['logo_light_file']);

        $this->assertSame('الاسم القديم', $this->inTenant($this->tenant, fn (): ?string => Setting::query()->where('key', 'company_name')->value('value')));
        $this->assertSame(0, $this->inTenant($this->tenant, fn (): int => Media::query()->count()));
    }

    public function test_legacy_settings_form_replaces_the_previous_logo_only_after_the_settings_are_saved(): void
    {
        $headers = $this->tenantHeaders($this->tenant);
        $this->post('/api/v1/settings/branding/logo/dark', ['file' => UploadedFile::fake()->image('old.png', 100, 100)], $headers)->assertOk();
        $oldPath = $this->inTenant($this->tenant, fn (): string => Media::query()->where('collection_name', 'logo_dark')->sole()->getPath());

        $this->post('/api/v1/settings', [
            'company_name' => 'اسم جديد',
            'logo_dark_file' => UploadedFile::fake()->image('new.jpg', 120, 80),
        ], $headers)->assertOk();

        $media = $this->inTenant($this->tenant, fn () => Media::query()->where('collection_name', 'logo_dark')->get());
        $this->assertCount(1, $media);
        $this->assertStringEndsWith('.jpg', (string) $media->first()?->file_name);
        $this->assertFileDoesNotExist($oldPath);
        $this->assertSame('اسم جديد', $this->inTenant($this->tenant, fn (): ?string => Setting::query()->where('key', 'company_name')->value('value')));
    }

    public function test_a_failed_settings_save_deletes_the_new_logo_and_keeps_the_old_one(): void
    {
        $headers = $this->tenantHeaders($this->tenant);
        $this->post('/api/v1/settings/branding/logo/dark', ['file' => UploadedFile::fake()->image('old.png', 100, 100)], $headers)->assertOk();
        [$oldId, $oldPath] = $this->inTenant($this->tenant, function (): array {
            $media = Media::query()->where('collection_name', 'logo_dark')->sole();

            return [(int) $media->getKey(), $media->getPath()];
        });
        $filesBefore = $this->tenantMediaFiles();

        // The settings write fails after the logo was stored.
        $this->inTenant($this->tenant, fn () => Schema::drop('settings'));

        $this->post('/api/v1/settings', [
            'company_name' => 'اسم جديد',
            'logo_dark_file' => UploadedFile::fake()->image('new.jpg', 120, 80),
            'logo_light_file' => UploadedFile::fake()->image('light.png', 120, 80),
        ], $headers + ['Accept' => 'application/json'])
            ->assertStatus(500)
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', __('settings.settings_save_failed'));

        $remaining = $this->inTenant($this->tenant, fn (): array => Media::query()->pluck('id')->map(static fn ($id): int => (int) $id)->all());
        $this->assertSame([$oldId], $remaining, 'Only the previous logo is left; the new media rows are gone.');
        $this->assertFileExists($oldPath);
        $this->assertSame($filesBefore, $this->tenantMediaFiles(), 'No new logo file is left on the tenant disk.');
    }

    public function test_a_failed_logo_upload_saves_no_settings(): void
    {
        $this->inTenant($this->tenant, function (): void {
            Setting::set('company_name', 'الاسم القديم');
            // Storing the (valid) logo fails.
            Schema::drop('media');
        });

        $this->post('/api/v1/settings', [
            'company_name' => 'اسم جديد',
            'logo_light_file' => UploadedFile::fake()->image('light.png', 120, 80),
        ], $this->tenantHeaders($this->tenant) + ['Accept' => 'application/json'])
            ->assertStatus(500)
            ->assertJsonPath('message', __('settings.settings_save_failed'));

        $this->assertSame('الاسم القديم', $this->inTenant($this->tenant, fn (): ?string => Setting::query()->where('key', 'company_name')->value('value')));
    }

    // ── receipt text & theme color ───────────────────────────────────────

    public function test_receipt_header_and_footer_are_saved_and_exposed(): void
    {
        $headers = $this->tenantHeaders($this->tenant);

        $this->postJson('/api/v1/settings', [
            'company_name' => 'محل',
            'receipt_header_lines' => "  فرع المعادي  \n\n شارع 9 - المعادي \nس.ت 12345",
            'receipt_footer_text' => 'شكرا لزيارتكم & نتمنى رؤيتكم "قريبا"',
            'system_theme_color' => '#12AB34',
        ], $headers)->assertOk();

        $this->assertSame(
            "فرع المعادي\nشارع 9 - المعادي\nس.ت 12345",
            $this->inTenant($this->tenant, fn (): ?string => Setting::query()->where('key', 'receipt_header_lines')->value('value')),
        );

        $this->getJson('/api/v1/settings', $headers)->assertOk()
            ->assertJsonPath('settings.receipt_header_lines', "فرع المعادي\nشارع 9 - المعادي\nس.ت 12345")
            ->assertJsonPath('settings.receipt_footer_text', 'شكرا لزيارتكم & نتمنى رؤيتكم "قريبا"')
            ->assertJsonPath('settings.system_theme_color', '#12AB34');

        $this->getJson('/api/v1/system/context', $headers)->assertOk()
            ->assertJsonPath('data.branding.tenant.receipt.header_lines', ['فرع المعادي', 'شارع 9 - المعادي', 'س.ت 12345'])
            ->assertJsonPath('data.branding.tenant.receipt.footer_text', 'شكرا لزيارتكم & نتمنى رؤيتكم "قريبا"')
            ->assertJsonPath('data.branding.tenant.theme_color', '#12ab34')
            ->assertJsonPath('data.system.system_theme_color', '#12ab34');
    }

    public function test_receipt_header_lines_accept_an_array(): void
    {
        $this->postJson('/api/v1/settings', [
            'company_name' => 'محل',
            'receipt_header_lines' => ['سطر 1', 'سطر 2'],
        ], $this->tenantHeaders($this->tenant))->assertOk();

        $this->assertSame("سطر 1\nسطر 2", $this->inTenant($this->tenant, fn (): ?string => Setting::query()->where('key', 'receipt_header_lines')->value('value')));
    }

    public function test_footer_falls_back_to_invoice_footer_note(): void
    {
        $this->postJson('/api/v1/settings', [
            'company_name' => 'محل',
            'invoice_footer_note' => 'ملاحظة الفاتورة القديمة',
        ], $this->tenantHeaders($this->tenant))->assertOk();

        $this->getJson('/api/v1/system/context', $this->tenantHeaders($this->tenant))
            ->assertOk()
            ->assertJsonPath('data.branding.tenant.receipt.footer_text', 'ملاحظة الفاتورة القديمة');
    }

    /**
     * @return array<string, array{0: array<string, mixed>, 1: string}>
     */
    public static function invalidBrandingSettings(): array
    {
        return [
            'seven header lines' => [['receipt_header_lines' => implode("\n", array_fill(0, 7, 'سطر'))], 'receipt_header_lines'],
            'header line over 80 chars' => [['receipt_header_lines' => str_repeat('ب', 81)], 'receipt_header_lines'],
            'markup in header' => [['receipt_header_lines' => '<b>عرض</b>'], 'receipt_header_lines'],
            'markup in footer' => [['receipt_footer_text' => '<script>alert(1)</script>'], 'receipt_footer_text'],
            'footer over 500 chars' => [['receipt_footer_text' => str_repeat('a', 501)], 'receipt_footer_text'],
            'unknown palette color' => [['system_theme_color' => 'red'], 'system_theme_color'],
            'short hex' => [['system_theme_color' => '#fff'], 'system_theme_color'],
            'css injection' => [['system_theme_color' => '#000;background:url(x)'], 'system_theme_color'],
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    #[DataProvider('invalidBrandingSettings')]
    public function test_invalid_branding_settings_are_422(array $payload, string $field): void
    {
        $this->postJson('/api/v1/settings', ['company_name' => 'محل'] + $payload, $this->tenantHeaders($this->tenant))
            ->assertStatus(422)
            ->assertJsonValidationErrors([$field]);
    }

    public function test_palette_theme_colors_are_accepted(): void
    {
        foreach (['amber', 'teal', 'indigo'] as $color) {
            $this->postJson('/api/v1/settings', ['company_name' => 'محل', 'system_theme_color' => $color], $this->tenantHeaders($this->tenant))
                ->assertOk();
        }
    }

    // ── system context ───────────────────────────────────────────────────

    public function test_system_context_branding_block_has_platform_tenant_and_back_compat_keys(): void
    {
        $this->inTenant($this->tenant, function (): void {
            Setting::set('telegram_bot_token', '123456:SECRET-TOKEN');
            Setting::set('commercial_register', 'CR-998877');
            Setting::set('company_name', 'محمصة النيل');
        });

        $response = $this->getJson('/api/v1/system/context', $this->tenantHeaders($this->tenant))->assertOk();

        $response->assertJsonStructure(['data' => ['branding' => [
            'platform' => ['name', 'short_name', 'subtitle', 'logos' => ['light', 'dark'], 'favicon', 'app_icon', 'primary_color', 'website_url', 'support_email', 'support_phone'],
            'tenant' => [
                'name', 'subtitle', 'logos' => ['light', 'dark'], 'theme_color', 'phone', 'address', 'invoice_color',
                'print' => ['show_logo', 'show_name', 'show_subtitle'],
                'receipt' => ['header_lines', 'footer_text'],
                'legal' => ['commercial_register', 'tax_registration_no'],
            ],
            'logo_light', 'logo_dark', 'logo',
        ]]]);

        $response->assertJsonPath('data.branding.tenant.name', 'محمصة النيل')
            ->assertJsonPath('data.branding.tenant.legal.commercial_register', 'CR-998877')
            ->assertJsonPath('data.tenant.legal.commercial_register', 'CR-998877')
            ->assertJsonPath('data.system.company_name', 'محمصة النيل');

        // Without a shop logo the back-compat keys point at the platform logo, never public/logo.png?v=.
        $this->assertSame($response->json('data.branding.platform.logos.light'), $response->json('data.branding.logo_light'));
        $this->assertStringNotContainsString('SECRET-TOKEN', (string) json_encode($response->json('data.branding')));
    }

    public function test_system_context_back_compat_keys_point_at_the_tenant_logo(): void
    {
        $headers = $this->tenantHeaders($this->tenant);
        $this->post('/api/v1/settings/branding/logo/light', ['file' => UploadedFile::fake()->image('a.png', 100, 100)], $headers)->assertOk();

        $response = $this->getJson('/api/v1/system/context', $headers)->assertOk();
        $light = (string) $response->json('data.branding.tenant.logos.light');

        $this->assertStringContainsString('/api/v1/branding/logo/light?v=', $light);
        $this->assertSame($light, $response->json('data.branding.logo_light'));
        $this->assertSame($light, $response->json('data.branding.logo'));
        // No dark logo: the dark key falls back to the light shop logo.
        $this->assertSame($light, $response->json('data.branding.logo_dark'));
    }

    // ── isolation ────────────────────────────────────────────────────────

    public function test_a_logo_uploaded_by_tenant_a_never_reaches_tenant_b(): void
    {
        $other = $this->createTenant();

        $this->post('/api/v1/settings/branding/logo/light', ['file' => UploadedFile::fake()->image('a.png', 100, 100)], $this->tenantHeaders($this->tenant))->assertOk();

        $this->assertSame(0, $this->inTenant($other, fn (): int => Media::query()->count()));
        $this->assertSame(0, $this->inTenant($other, fn (): int => TenantBrandProfile::query()->count()));

        $this->getJson('/api/v1/settings', $this->tenantHeaders($other))->assertOk()
            ->assertJsonPath('settings.logo_light_url', null);
        $this->getJson('/api/v1/system/context', $this->tenantHeaders($other))->assertOk()
            ->assertJsonPath('data.branding.tenant.logos.light', null);

        // Tenant B deleting "its" logo cannot touch tenant A's file.
        $this->deleteJson('/api/v1/settings/branding/logo/light', [], $this->tenantHeaders($other))->assertOk();
        $this->assertSame(1, $this->inTenant($this->tenant, fn (): int => Media::query()->count()));
        $path = $this->inTenant($this->tenant, fn (): string => Media::query()->sole()->getPath());
        $this->assertFileExists($path);
        $this->assertStringNotContainsString($this->tenantDirName($other), $path);
    }

    public function test_a_token_of_tenant_a_cannot_upload_into_tenant_b(): void
    {
        $other = $this->createTenant();
        $headers = $this->tenantHeaders($this->tenant);
        $headers['X-Tenant'] = (string) $other->getTenantKey();

        $this->post('/api/v1/settings/branding/logo/light', ['file' => UploadedFile::fake()->image('a.png', 100, 100)], $headers)
            ->assertStatus(401);

        $this->assertSame(0, $this->inTenant($other, fn (): int => Media::query()->count()));
    }

    // ── helpers ──────────────────────────────────────────────────────────

    /**
     * Every file under the tenant's media disk directory.
     *
     * @return list<string>
     */
    private function tenantMediaFiles(): array
    {
        return $this->inTenant($this->tenant, static function (): array {
            $files = Storage::disk('public')->allFiles();
            sort($files);

            return $files;
        });
    }

    private function tenantDirName(Tenant $tenant): string
    {
        return config('tenancy.filesystem.suffix_base', 'tenant').$tenant->getTenantKey();
    }

    /**
     * @return array<string, string|false>
     */
    private function publicLogoFingerprint(): array
    {
        $files = glob(public_path('logo*')) ?: [];
        sort($files);

        $out = [];
        foreach ($files as $file) {
            $out[$file] = is_file($file) ? md5_file($file) : false;
        }

        return $out;
    }
}
