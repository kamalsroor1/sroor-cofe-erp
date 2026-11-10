<?php

declare(strict_types=1);

namespace Tests\Feature\Landing;

use App\Models\SignupRequest;
use Database\Seeders\PlansAndFeaturesSeeder;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Queue;
use Tests\TenantTestCase;

/**
 * PRD-08: Legal draft documents (`resources/legal/`), public legal API (`GET /api/v1/public/legal/{document}`),
 * and explicit consent enforcement + persistence on `POST /api/v1/public/signup-requests`.
 */
final class PublicLegalAndConsentTest extends TenantTestCase
{
    private const SIGNUP_URL = '/api/v1/public/signup-requests';

    protected function setUp(): void
    {
        parent::setUp();

        $this->endTenancy();
        $this->seed(PlansAndFeaturesSeeder::class);
    }

    public function test_legal_markdown_drafts_exist_with_mandatory_draft_banner_and_company_placeholders(): void
    {
        $legalDir = resource_path('legal');

        $files = [
            'terms-of-service.ar.md',
            'terms-of-service.en.md',
            'privacy-policy.ar.md',
            'privacy-policy.en.md',
            'data-processing-agreement.ar.md',
            'data-processing-agreement.en.md',
        ];

        foreach ($files as $filename) {
            $fullPath = $legalDir.DIRECTORY_SEPARATOR.$filename;
            $this->assertTrue(File::isFile($fullPath), "Missing legal draft file: {$filename}");

            $content = (string) File::get($fullPath);
            $lines = preg_split('/\r?\n/', ltrim($content)) ?: [];
            $firstLine = $lines[0] ?? '';

            // 1. First line must carry the mandatory draft notice
            $this->assertStringContainsString('مسودة — تحتاج مراجعة قانونية قبل النشر', $firstLine, "First line of {$filename} must contain the mandatory draft notice.");

            // 2. Placeholders must be used instead of invented company details
            $this->assertStringContainsString('{{COMPANY_NAME}}', $content, "{$filename} must use {{COMPANY_NAME}} placeholder.");
            $this->assertStringContainsString('{{COMPANY_LEGAL_ADDRESS}}', $content, "{$filename} must use {{COMPANY_LEGAL_ADDRESS}} placeholder.");
            $this->assertStringContainsString('{{COMMERCIAL_REGISTER_NO}}', $content, "{$filename} must use {{COMMERCIAL_REGISTER_NO}} placeholder.");
            $this->assertStringContainsString('{{TAX_REGISTRATION_NO}}', $content, "{$filename} must use {{TAX_REGISTRATION_NO}} placeholder.");
            $this->assertStringContainsString('{{DPO_EMAIL}}', $content, "{$filename} must use {{DPO_EMAIL}} placeholder.");
        }

        // Privacy policy must reference Egyptian Law 151 of 2020 and lifecycle/backup retention
        $privacyAr = (string) File::get($legalDir.DIRECTORY_SEPARATOR.'privacy-policy.ar.md');
        $this->assertStringContainsString('151 لسنة 2020', $privacyAr);
        $this->assertStringContainsString('AES-256', $privacyAr);
        $this->assertStringContainsString('90', $privacyAr);

        // DPA must define Data Controller and Data Processor roles
        $dpaAr = (string) File::get($legalDir.DIRECTORY_SEPARATOR.'data-processing-agreement.ar.md');
        $this->assertStringContainsString('المتحكم في البيانات', $dpaAr);
        $this->assertStringContainsString('معالج البيانات', $dpaAr);
    }

    public function test_public_legal_endpoints_return_200_without_authentication_in_ar_and_en(): void
    {
        foreach (['terms', 'privacy', 'dpa'] as $doc) {
            $arResponse = $this->getJson("/api/v1/public/legal/{$doc}", ['X-Locale' => 'ar'])
                ->assertOk()
                ->assertJsonPath('data.document', $doc)
                ->assertJsonPath('data.locale', 'ar')
                ->assertJsonPath('data.is_draft', true)
                ->assertJsonPath('data.version', config('legal.version'));

            $this->assertStringContainsString('مسودة — تحتاج مراجعة قانونية قبل النشر', (string) $arResponse->json('data.markdown'));

            $enResponse = $this->getJson("/api/v1/public/legal/{$doc}", ['X-Locale' => 'en'])
                ->assertOk()
                ->assertJsonPath('data.document', $doc)
                ->assertJsonPath('data.locale', 'en')
                ->assertJsonPath('data.is_draft', true)
                ->assertJsonPath('data.version', config('legal.version'));

            $this->assertStringContainsString('DRAFT — Requires Legal Review Before Publication', (string) $enResponse->json('data.markdown'));
        }

        // Unknown legal document slug is rejected with 404
        $this->getJson('/api/v1/public/legal/unknown-doc')->assertNotFound();
    }

    public function test_public_spa_routes_serve_terms_privacy_and_dpa_without_authentication(): void
    {
        $this->withoutVite();

        foreach (['/terms', '/privacy', '/dpa'] as $path) {
            $this->get($path)->assertOk();
        }
    }

    public function test_signup_request_without_terms_consent_is_rejected_with_422(): void
    {
        Queue::fake();

        $basePayload = [
            'business_name' => 'محمصة النور',
            'contact_name' => 'كريم حسن',
            'phone' => '01012345678',
            'business_type' => 'retail',
        ];

        // Missing terms_accepted
        $this->postJson(self::SIGNUP_URL, $basePayload, ['X-Locale' => 'ar'])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['terms_accepted'])
            ->assertJsonPath('errors.terms_accepted.0', __('signup.validation.terms_required', [], 'ar'));

        // Explicit false terms_accepted
        $this->postJson(self::SIGNUP_URL, array_merge($basePayload, ['terms_accepted' => false]), ['X-Locale' => 'en'])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['terms_accepted'])
            ->assertJsonPath('errors.terms_accepted.0', __('signup.validation.terms_required', [], 'en'));

        $this->assertSame(0, SignupRequest::query()->count());
    }

    public function test_signup_request_with_terms_consent_persists_timestamp_version_and_ip_in_central_db(): void
    {
        Queue::fake();
        config(['legal.version' => '2026-11-draft-v1']);

        $this->withServerVariables(['REMOTE_ADDR' => '198.51.100.42'])
            ->postJson(self::SIGNUP_URL, [
                'business_name' => 'محمصة الموافقة القانونية',
                'contact_name' => 'محمود سعيد',
                'phone' => '01098765432',
                'business_type' => 'weighted',
                'terms_accepted' => true,
            ])
            ->assertCreated();

        $signup = SignupRequest::query()->sole();
        $this->assertSame(config('tenancy.database.central_connection'), $signup->getConnectionName());
        $this->assertNotNull($signup->terms_accepted_at);
        $this->assertSame('2026-11-draft-v1', $signup->terms_version);
        $this->assertSame('198.51.100.42', $signup->ip);
    }

    public function test_version_and_company_details_are_rendered_from_config(): void
    {
        config([
            'legal.version' => '2027-01-v2',
            'legal.placeholders.COMPANY_NAME' => 'Acme Coffee LLC',
        ]);

        $markdown = (string) $this->getJson('/api/v1/public/legal/terms', ['X-Locale' => 'en'])
            ->assertOk()
            ->assertJsonPath('data.version', '2027-01-v2')
            ->json('data.markdown');

        $this->assertStringContainsString('2027-01-v2', $markdown);
        $this->assertStringContainsString('Acme Coffee LLC', $markdown);
        $this->assertStringNotContainsString('{{COMPANY_NAME}}', $markdown);
        $this->assertStringNotContainsString('{{LEGAL_VERSION}}', $markdown);
        // Still a draft by default: unfilled placeholders stay visible.
        $this->assertStringContainsString('{{TAX_REGISTRATION_NO}}', $markdown);
    }

    public function test_non_draft_document_with_unfilled_placeholders_is_not_served(): void
    {
        config(['legal.is_draft' => false]);

        foreach (['terms', 'privacy', 'dpa'] as $doc) {
            $this->getJson("/api/v1/public/legal/{$doc}", ['X-Locale' => 'en'])->assertStatus(503);
        }
    }

    public function test_non_draft_document_is_served_without_banner_once_every_placeholder_is_filled(): void
    {
        config([
            'legal.is_draft' => false,
            'legal.placeholders' => [
                'COMPANY_NAME' => 'Acme Coffee LLC',
                'COMPANY_LEGAL_ADDRESS' => '1 Nile St, Cairo',
                'COMMERCIAL_REGISTER_NO' => 'CR-1',
                'TAX_REGISTRATION_NO' => 'TX-1',
                'EFFECTIVE_DATE' => '2027-01-01',
                'DPO_EMAIL' => 'dpo@example.test',
                'HOSTING_REGION' => 'EU',
                'SUPPORT_EMAIL' => 'help@example.test',
                'SUPPORT_PHONE' => '01000000000',
            ],
        ]);

        foreach (['terms', 'privacy', 'dpa'] as $doc) {
            foreach (['ar', 'en'] as $locale) {
                $response = $this->getJson("/api/v1/public/legal/{$doc}", ['X-Locale' => $locale])
                    ->assertOk()
                    ->assertJsonPath('data.is_draft', false);

                $markdown = (string) $response->json('data.markdown');
                $this->assertDoesNotMatchRegularExpression('/\{\{[A-Z_]+\}\}/', $markdown);
                $this->assertStringNotContainsString('DRAFT', $markdown);
                $this->assertStringNotContainsString('مسودة', $markdown);
            }
        }
    }
}
