<?php

declare(strict_types=1);

namespace Tests\Feature\Help;

use App\Enums\PlatformSettingKey;
use App\Services\Branding\PlatformBranding;
use Tests\TenantTestCase;

final class HelpCenterArticlesTest extends TenantTestCase
{
    /** @var list<string> */
    private const EXPECTED_FILES = [
        '01-getting-started.md',
        '02-items.md',
        '03-pos.md',
        '04-returns.md',
        '05-purchases.md',
        '06-treasury-shifts.md',
        '07-reports.md',
        '08-users-roles.md',
        '09-plans-billing.md',
    ];

    public function test_arabic_and_english_help_articles_have_complete_parity_and_headings(): void
    {
        $arFiles = array_map('basename', glob(resource_path('help/ar/*.md')) ?: []);
        $enFiles = array_map('basename', glob(resource_path('help/en/*.md')) ?: []);

        sort($arFiles);
        sort($enFiles);

        $this->assertSame(self::EXPECTED_FILES, $arFiles);
        $this->assertSame(self::EXPECTED_FILES, $enFiles);

        foreach (self::EXPECTED_FILES as $filename) {
            foreach (['ar', 'en'] as $locale) {
                $content = (string) file_get_contents(resource_path("help/{$locale}/{$filename}"));
                $this->assertNotEmpty(trim($content), "Help article {$locale}/{$filename} must not be empty.");
                $this->assertMatchesRegularExpression(
                    '/^#\s+.+/m',
                    $content,
                    "Help article {$locale}/{$filename} must contain a top-level # heading.",
                );
                $this->assertMatchesRegularExpression(
                    '/^##\s+.+/m',
                    $content,
                    "Help article {$locale}/{$filename} must contain at least one ## section.",
                );
            }
        }
    }

    public function test_system_context_exposes_central_platform_support_phone_for_help_center(): void
    {
        app(PlatformBranding::class)->update([
            PlatformSettingKey::SupportPhone->value => '+201000000000',
        ]);

        $tenant = $this->createTenant();
        $this->tenantAdmin($tenant);

        $this->getJson('/api/v1/system/context', $this->tenantGuestHeaders($tenant))
            ->assertStatus(401);

        $this->getJson('/api/v1/system/context', $this->tenantHeaders($tenant))
            ->assertOk()
            ->assertJsonPath('data.branding.platform.support_phone', '+201000000000');
    }
}
