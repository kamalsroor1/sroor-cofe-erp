<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\DTOs\Branding\PlatformBrandingDTO;
use App\DTOs\Branding\TenantBrandingDTO;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

/**
 * BRND-3: public branding payload (GET /api/v1/branding, before login).
 *
 * ALLOWLIST ONLY: every key is listed here on purpose. Never spread a DTO or a settings
 * array into this payload (telegram, legal ids, contact data and flags stay out). The
 * authenticated, fuller shop block for /system/context is tenantBlock().
 *
 * @property array{platform: PlatformBrandingDTO, tenant: TenantBrandingDTO|null} $resource
 */
final class BrandingResource extends JsonResource
{
    /**
     * @param  array{platform: PlatformBrandingDTO, tenant: TenantBrandingDTO|null}  $resource
     */
    public function __construct(array $resource)
    {
        parent::__construct($resource);
    }

    /**
     * @return array{platform: array<string, mixed>, tenant: array<string, mixed>|null}
     */
    public function toArray(Request $request): array
    {
        $tenant = $this->resource['tenant'];

        return [
            'platform' => self::platformBlock($this->resource['platform']),
            'tenant' => $tenant !== null ? self::publicTenantBlock($tenant) : null,
        ];
    }

    /**
     * The public platform brand (allowlist; no legal name, no powered-by switch).
     *
     * @return array<string, mixed>
     */
    public static function platformBlock(PlatformBrandingDTO $platform): array
    {
        return [
            'name' => $platform->name,
            'short_name' => $platform->shortName,
            'subtitle' => $platform->subtitle,
            'logos' => [
                'light' => self::assetUrl($platform->logoLight),
                'dark' => self::assetUrl($platform->logoDark),
            ],
            'favicon' => self::assetUrl($platform->favicon),
            'app_icon' => self::assetUrl($platform->appIcon),
            'primary_color' => $platform->primaryColor,
            'website_url' => $platform->websiteUrl,
            'support_email' => $platform->supportEmail,
            'support_phone' => $platform->supportPhone,
        ];
    }

    /**
     * The public shop brand (allowlist: what the login screen needs).
     *
     * @return array<string, mixed>
     */
    public static function publicTenantBlock(TenantBrandingDTO $tenant): array
    {
        return [
            'name' => $tenant->name,
            'subtitle' => $tenant->subtitle,
            'logos' => [
                'light' => $tenant->logoLightUrl,
                'dark' => $tenant->logoDarkUrl,
            ],
            'theme_color' => $tenant->themeColor,
        ];
    }

    /**
     * The shop brand for authenticated screens and prints (/system/context, settings
     * uploads): public block + contact, receipt and legal info.
     *
     * @return array<string, mixed>
     */
    public static function tenantBlock(TenantBrandingDTO $tenant): array
    {
        return self::publicTenantBlock($tenant) + [
            'phone' => $tenant->phone,
            'address' => $tenant->address,
            'invoice_color' => $tenant->invoiceColor,
            'print' => [
                'show_logo' => $tenant->showLogo,
                'show_name' => $tenant->showName,
                'show_subtitle' => $tenant->showSubtitle,
            ],
            'receipt' => [
                'header_lines' => $tenant->receiptHeaderLines,
                'footer_text' => $tenant->receiptFooterText,
            ],
            'legal' => [
                'commercial_register' => $tenant->commercialRegister,
                'tax_registration_no' => $tenant->taxRegistrationNo,
            ],
        ];
    }

    /**
     * Platform asset reference → URL: absolute URLs as stored, "/path" from config (files in
     * public/, served on every host), anything else a path on the central public disk (BRND-2).
     */
    public static function assetUrl(?string $reference): ?string
    {
        $reference = $reference !== null ? trim($reference) : '';

        if ($reference === '') {
            return null;
        }

        if (str_starts_with($reference, 'https://') || str_starts_with($reference, 'http://')) {
            return $reference;
        }

        if (str_starts_with($reference, '/')) {
            return url($reference);
        }

        return Storage::disk('central_public')->url($reference);
    }
}
