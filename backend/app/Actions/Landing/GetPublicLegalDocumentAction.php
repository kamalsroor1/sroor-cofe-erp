<?php

declare(strict_types=1);

namespace App\Actions\Landing;

use App\Services\Branding\PlatformBranding;
use Illuminate\Support\Facades\File;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * PRD-08: load a public legal draft document (`terms`, `privacy`, `dpa`) from `docs/legal/`
 * in the active locale (`ar` or `en`), along with version metadata and public platform brand
 * details for the marketing shell.
 */
final class GetPublicLegalDocumentAction
{
    private const DOCUMENTS = [
        'terms' => 'terms-of-service',
        'privacy' => 'privacy-policy',
        'dpa' => 'data-processing-agreement',
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

        $markdown = (string) File::get($path);
        $version = trim((string) config('signup.legal_version', '2026-11-draft-v1'));
        $brand = $this->branding->get();

        return [
            'document' => $document,
            'locale' => $normalizedLocale,
            'version' => $version !== '' ? $version : '2026-11-draft-v1',
            'is_draft' => true,
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

    private function resolvePath(string $slug, string $locale): string
    {
        return dirname(base_path()).DIRECTORY_SEPARATOR.'docs'.DIRECTORY_SEPARATOR.'legal'.DIRECTORY_SEPARATOR."{$slug}.{$locale}.md";
    }
}
