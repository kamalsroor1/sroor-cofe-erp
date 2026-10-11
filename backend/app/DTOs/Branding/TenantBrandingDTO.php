<?php

declare(strict_types=1);

namespace App\DTOs\Branding;

/**
 * BRND-5: the effective shop (tenant) branding, as resolved by TenantBranding.
 *
 * Text values are plain text: every consumer (API, Blade, SPA) must output them escaped.
 * Logo URLs are absolute URLs of the host-bound public route
 * (GET /api/v1/branding/logo/{variant}?v=<sha256 prefix>) on the tenant's own host, or
 * null when the shop has no logo of that variant (never the shared public/logo*.png).
 */
final class TenantBrandingDTO
{
    /**
     * @param  list<string>  $receiptHeaderLines
     */
    public function __construct(
        public readonly string $name,
        public readonly string $subtitle,
        public readonly string $phone,
        public readonly string $address,
        public readonly string $themeColor,
        public readonly string $invoiceColor,
        public readonly array $receiptHeaderLines,
        public readonly string $receiptFooterText,
        public readonly string $commercialRegister,
        public readonly string $taxRegistrationNo,
        public readonly bool $showLogo,
        public readonly bool $showName,
        public readonly bool $showSubtitle,
        public readonly ?string $logoLightUrl,
        public readonly ?string $logoDarkUrl,
    ) {}

    /**
     * @param  array{
     *     name: string, subtitle: string, phone: string, address: string,
     *     theme_color: string, invoice_color: string, receipt_header_lines: list<string>,
     *     receipt_footer_text: string, commercial_register: string, tax_registration_no: string,
     *     show_logo: bool, show_name: bool, show_subtitle: bool,
     *     logo_light_url: ?string, logo_dark_url: ?string
     * }  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            name: $data['name'],
            subtitle: $data['subtitle'],
            phone: $data['phone'],
            address: $data['address'],
            themeColor: $data['theme_color'],
            invoiceColor: $data['invoice_color'],
            receiptHeaderLines: $data['receipt_header_lines'],
            receiptFooterText: $data['receipt_footer_text'],
            commercialRegister: $data['commercial_register'],
            taxRegistrationNo: $data['tax_registration_no'],
            showLogo: $data['show_logo'],
            showName: $data['show_name'],
            showSubtitle: $data['show_subtitle'],
            logoLightUrl: $data['logo_light_url'] !== '' ? $data['logo_light_url'] : null,
            logoDarkUrl: $data['logo_dark_url'] !== '' ? $data['logo_dark_url'] : null,
        );
    }

    /**
     * @return array{
     *     name: string, subtitle: string, phone: string, address: string,
     *     theme_color: string, invoice_color: string, receipt_header_lines: list<string>,
     *     receipt_footer_text: string, commercial_register: string, tax_registration_no: string,
     *     show_logo: bool, show_name: bool, show_subtitle: bool,
     *     logo_light_url: ?string, logo_dark_url: ?string
     * }
     */
    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'subtitle' => $this->subtitle,
            'phone' => $this->phone,
            'address' => $this->address,
            'theme_color' => $this->themeColor,
            'invoice_color' => $this->invoiceColor,
            'receipt_header_lines' => $this->receiptHeaderLines,
            'receipt_footer_text' => $this->receiptFooterText,
            'commercial_register' => $this->commercialRegister,
            'tax_registration_no' => $this->taxRegistrationNo,
            'show_logo' => $this->showLogo,
            'show_name' => $this->showName,
            'show_subtitle' => $this->showSubtitle,
            'logo_light_url' => $this->logoLightUrl,
            'logo_dark_url' => $this->logoDarkUrl,
        ];
    }
}
