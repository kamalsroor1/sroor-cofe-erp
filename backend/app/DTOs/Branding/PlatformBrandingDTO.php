<?php

declare(strict_types=1);

namespace App\DTOs\Branding;

/**
 * BRND-1: the effective platform branding (stored override, else config fallback).
 *
 * Asset props hold a reference, not necessarily a full URL: either a public URL path
 * from config/branding.php (e.g. '/logo-light.png') or a path on the central public
 * disk written by BRND-2. Turning them into absolute URLs is the presenter's job (BRND-3).
 */
final class PlatformBrandingDTO
{
    public function __construct(
        public readonly string $name,
        public readonly string $shortName,
        public readonly string $subtitle,
        public readonly string $legalName,
        public readonly ?string $logoLight,
        public readonly ?string $logoDark,
        public readonly ?string $favicon,
        public readonly ?string $appIcon,
        public readonly string $primaryColor,
        public readonly string $supportEmail,
        public readonly string $supportPhone,
        public readonly string $websiteUrl,
        public readonly bool $poweredByEnabled,
        public readonly string $billingInstapayAddress = '',
        public readonly string $billingVodafoneCashNumber = '',
        public readonly string $billingAccountName = '',
        public readonly string $billingPaymentNote = '',
        public readonly string $billingTaxRate = '0.000',
    ) {}

    /**
     * @param  array{
     *     name: string, short_name: string, subtitle: string, legal_name: string,
     *     logo_light: ?string, logo_dark: ?string, favicon: ?string, app_icon: ?string,
     *     primary_color: string, support_email: string, support_phone: string,
     *     website_url: string, powered_by_enabled: bool,
     *     billing_instapay_address?: string, billing_vodafone_cash_number?: string,
     *     billing_account_name?: string, billing_payment_note?: string, billing_tax_rate?: string
     * }  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            name: $data['name'],
            shortName: $data['short_name'],
            subtitle: $data['subtitle'],
            legalName: $data['legal_name'],
            logoLight: $data['logo_light'],
            logoDark: $data['logo_dark'],
            favicon: $data['favicon'],
            appIcon: $data['app_icon'],
            primaryColor: $data['primary_color'],
            supportEmail: $data['support_email'],
            supportPhone: $data['support_phone'],
            websiteUrl: $data['website_url'],
            poweredByEnabled: $data['powered_by_enabled'],
            billingInstapayAddress: $data['billing_instapay_address'] ?? '',
            billingVodafoneCashNumber: $data['billing_vodafone_cash_number'] ?? '',
            billingAccountName: $data['billing_account_name'] ?? '',
            billingPaymentNote: $data['billing_payment_note'] ?? '',
            billingTaxRate: $data['billing_tax_rate'] ?? '0.000',
        );
    }

    /**
     * @return array{
     *     name: string, short_name: string, subtitle: string, legal_name: string,
     *     logo_light: ?string, logo_dark: ?string, favicon: ?string, app_icon: ?string,
     *     primary_color: string, support_email: string, support_phone: string,
     *     website_url: string, powered_by_enabled: bool,
     *     billing_instapay_address?: string, billing_vodafone_cash_number?: string,
     *     billing_account_name?: string, billing_payment_note?: string, billing_tax_rate?: string
     * }
     */
    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'short_name' => $this->shortName,
            'subtitle' => $this->subtitle,
            'legal_name' => $this->legalName,
            'logo_light' => $this->logoLight,
            'logo_dark' => $this->logoDark,
            'favicon' => $this->favicon,
            'app_icon' => $this->appIcon,
            'primary_color' => $this->primaryColor,
            'support_email' => $this->supportEmail,
            'support_phone' => $this->supportPhone,
            'website_url' => $this->websiteUrl,
            'powered_by_enabled' => $this->poweredByEnabled,
            'billing_instapay_address' => $this->billingInstapayAddress,
            'billing_vodafone_cash_number' => $this->billingVodafoneCashNumber,
            'billing_account_name' => $this->billingAccountName,
            'billing_payment_note' => $this->billingPaymentNote,
            'billing_tax_rate' => $this->billingTaxRate,
        ];
    }
}
