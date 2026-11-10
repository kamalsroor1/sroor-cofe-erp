<?php

declare(strict_types=1);

namespace App\Actions\Landing;

use App\Services\Branding\PlatformBranding;
use Illuminate\Support\Facades\File;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\ServiceUnavailableHttpException;

/**
 * PRD-08: load a public legal document (`terms`, `privacy`, `dpa`) from `resources/legal/` in the
 * active locale (`ar` or `en`), with the version and company details substituted from `config/legal.php`,
 * along with the public platform brand details for the marketing shell.
 *
 * While `legal.is_draft` is true the draft banner stays and unfilled placeholders remain visible. A
 * non-draft document is never served with an unfilled placeholder (503 instead).
 */
final class GetPublicLegalDocumentAction
{
    private const DOCUMENTS = [
        'terms' => 'terms-of-service',
        'privacy' => 'privacy-policy',
        'dpa' => 'data-processing-agreement',
    ];

    /** Company identity that must be filled before a document may stop being a draft. */
    private const CORE_PLACEHOLDERS = [
        'COMPANY_NAME',
        'COMPANY_LEGAL_ADDRESS',
        'COMMERCIAL_REGISTER_NO',
        'TAX_REGISTRATION_NO',
        'EFFECTIVE_DATE',
    ];

    public function __construct(
        private readonly PlatformBranding $branding,
    ) {}

    /**
     * @return array{
     *     document: string,
     *     locale: string,
     *     version: string,
     *     is_draft: bool,
     *     markdown: string,
     *     brand: array{name: string, subtitle: string, support_phone: string, support_email: string, website_url: string}
     * }
     */
    public function execute(string $document, string $locale): array
    {
        $slug = self::DOCUMENTS[$document] ?? null;
        if ($slug === null) {
            throw new NotFoundHttpException;
        }

        $normalizedLocale = $locale === 'en' ? 'en' : 'ar';
        $path = $this->resolvePath($slug, $normalizedLocale);

        if (! File::isFile($path)) {
            throw new NotFoundHttpException;
        }

        $brand = $this->branding->get();
        $isDraft = (bool) config('legal.is_draft', true);
        $version = trim((string) config('legal.version'));
        $markdown = $this->render((string) File::get($path), $version, $isDraft, $brand->supportEmail, $brand->supportPhone);

        return [
            'document' => $document,
            'locale' => $normalizedLocale,
            'version' => $version,
            'is_draft' => $isDraft,
            'markdown' => $markdown,
            'brand' => [
                'name' => $brand->name,
                'subtitle' => $brand->subtitle,
                'support_phone' => $brand->supportPhone,
                'support_email' => $brand->supportEmail,
                'website_url' => $brand->websiteUrl,
            ],
        ];
    }

    private function render(string $markdown, string $version, bool $isDraft, string $brandEmail, string $brandPhone): string
    {
        /** @var array<string, string> $configured */
        $configured = (array) config('legal.placeholders', []);
        $values = array_map(static fn (mixed $value): string => trim((string) $value), $configured);

        foreach (['SUPPORT_EMAIL' => $brandEmail, 'SUPPORT_PHONE' => $brandPhone] as $token => $fallback) {
            if (($values[$token] ?? '') === '') {
                $values[$token] = trim($fallback);
            }
        }
        $values['LEGAL_VERSION'] = $version;

        if (! $isDraft) {
            preg_match_all('/\{\{([A-Z_]+)\}\}/', $markdown, $found);
            $required = array_unique([...self::CORE_PLACEHOLDERS, ...$found[1]]);

            foreach ($required as $token) {
                if (($values[$token] ?? '') === '') {
                    throw new ServiceUnavailableHttpException(null, (string) __('landing.legal.not_ready'));
                }
            }

            // The "draft, needs legal review" banner is the first line of every source file.
            $markdown = (string) preg_replace('/\A\s*>[^\n]*\n+/u', '', $markdown);
        }

        $replacements = [];
        foreach ($values as $token => $value) {
            if ($value !== '') {
                $replacements['{{'.$token.'}}'] = $value;
            }
        }

        return strtr($markdown, $replacements);
    }

    private function resolvePath(string $slug, string $locale): string
    {
        return resource_path('legal'.DIRECTORY_SEPARATOR."{$slug}.{$locale}.md");
    }
}
