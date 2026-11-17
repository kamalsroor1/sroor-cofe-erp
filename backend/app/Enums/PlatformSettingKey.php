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

    // SELL-06: trial length (whole days, 1-90) given to an approved signup whose plan has
    // trial_days = 0. Blank = config('tenant_lifecycle.trial_days').
    case SignupTrialDays = 'signup_trial_days';

    // PLAN-10: self-signup and free plan (docs/03-architecture/free-plan-and-self-signup-2026-11-18.md 5).
    // Plan a signup without a chosen plan gets (an active plan id; blank = the catalog fallback).
    case DefaultSignupPlanId = 'default_signup_plan_id';
    // Plan a SELF-SIGNUP trial falls back to when it ends unpaid (an active is_free plan id;
    // PLAN-11: blank = the active catalog `free` plan, see App\Services\Signup\TrialFallbackPlan).
    case TrialFallbackPlanId = 'trial_fallback_plan_id';
    // Public self-signup on/off (default on, config signup.self_signup_enabled).
    case SelfSignupEnabled = 'self_signup_enabled';
    // Shops created automatically per day before new signups wait for a manual review (SIGN-12).
    case SignupDailyCap = 'signup_daily_cap';

    /**
     * Storage type of the value: string | bool ('1'/'0') | color (#rrggbb) | asset
     * (path on the central public disk, managed by BRND-2).
     */
    public function type(): string
    {
        return match ($this) {
            self::PoweredByEnabled, self::SelfSignupEnabled => 'bool',
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
