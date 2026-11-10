<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * BRND-1: the whitelist of platform (central) settings stored in `platform_settings`.
 * Anything not listed here can never be written through PlatformBranding::update().
 */
enum PlatformSettingKey: string
{
    case Name = 'name';
    case ShortName = 'short_name';
    case Subtitle = 'subtitle';
    case LegalName = 'legal_name';
    case LogoLight = 'logo_light';
    case LogoDark = 'logo_dark';
    case Favicon = 'favicon';
    case AppIcon = 'app_icon';
    case PrimaryColor = 'primary_color';
    case SupportEmail = 'support_email';
    case SupportPhone = 'support_phone';
    case WebsiteUrl = 'website_url';
    case PoweredByEnabled = 'powered_by_enabled';

    // PRD-03: manual SaaS billing. Shown to tenants on their payment screen; all start empty.
    case BillingInstapayAddress = 'billing_instapay_address';
    case BillingVodafoneCashNumber = 'billing_vodafone_cash_number';
    case BillingAccountName = 'billing_account_name';
    case BillingPaymentNote = 'billing_payment_note';
    // Subscription VAT, a percentage with 3 decimals (0-100). Default 0.
    case BillingTaxRate = 'billing_tax_rate';

    /**
     * Storage type of the value: string | bool ('1'/'0') | color (#rrggbb) | asset
     * (path on the central public disk, managed by BRND-2).
     */
    public function type(): string
    {
        return match ($this) {
            self::PoweredByEnabled => 'bool',
            self::PrimaryColor => 'color',
            self::LogoLight, self::LogoDark, self::Favicon, self::AppIcon => 'asset',
            default => 'string',
        };
    }

    /** Keys that must never resolve to a blank string (they fall back to config instead). */
    public function isRequired(): bool
    {
        return $this === self::Name || $this === self::ShortName;
    }
}
