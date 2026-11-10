<?php

declare(strict_types=1);

namespace Tests\Feature\Landing;

use App\Models\SignupRequest;
use Database\Seeders\PlansAndFeaturesSeeder;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Queue;
use Tests\TenantTestCase;

/**
 * PRD-08: Legal draft documents (`docs/legal/`), public legal API (`GET /api/v1/public/legal/{document}`),
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
        $legalDir = dirname(base_path()).DIRECTORY_SEPARATOR.'docs'.DIRECTORY_SEPARATOR.'legal';

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
                ->assertJsonPath('data.version', config('signup.legal_version'));

            $this->assertStringContainsString('مسودة — تحتاج مراجعة قانونية قبل النشر', (string) $arResponse->json('data.markdown'));

            $enResponse = $this->getJson("/api/v1/public/legal/{$doc}", ['X-Locale' => 'en'])
                ->assertOk()
                ->assertJsonPath('data.document', $doc)
                ->assertJsonPath('data.locale', 'en')
                ->assertJsonPath('data.is_draft', true)
                ->assertJsonPath('data.version', config('signup.legal_version'));

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
        config(['signup.legal_version' => '2026-11-draft-v1']);

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
}
