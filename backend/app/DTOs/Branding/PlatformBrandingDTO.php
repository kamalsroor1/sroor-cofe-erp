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
        public readonly int $signupTrialDays = 14,
        public readonly ?int $defaultSignupPlanId = null,
        public readonly ?int $trialFallbackPlanId = null,
        public readonly bool $selfSignupEnabled = true,
        public readonly int $signupDailyCap = 100,
        public readonly string $billingVodafoneCashNote = '',
        public readonly string $billingInstapayNote = '',
        public readonly string $billingBankName = '',
        public readonly string $billingBankAccountHolder = '',
        public readonly string $billingBankAccountNumber = '',
        public readonly string $billingBankIban = '',
        public readonly string $billingBankBranch = '',
        public readonly string $billingBankNote = '',
    ) {}

    /**
     * @param  array{
     *     name: string, short_name: string, subtitle: string, legal_name: string,
     *     logo_light: ?string, logo_dark: ?string, favicon: ?string, app_icon: ?string,
     *     primary_color: string, support_email: string, support_phone: string,
     *     website_url: string, powered_by_enabled: bool,
     *     billing_instapay_address?: string, billing_vodafone_cash_number?: string,
     *     billing_account_name?: string, billing_payment_note?: string, billing_tax_rate?: string,
     *     signup_trial_days?: int, default_signup_plan_id?: int|null, trial_fallback_plan_id?: int|null,
     *     self_signup_enabled?: bool, signup_daily_cap?: int,
     *     billing_vodafone_cash_note?: string, billing_instapay_note?: string, billing_bank_name?: string, billing_bank_account_holder?: string, billing_bank_account_number?: string, billing_bank_iban?: string, billing_bank_branch?: string, billing_bank_note?: string
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
            signupTrialDays: $data['signup_trial_days'] ?? 14,
            defaultSignupPlanId: $data['default_signup_plan_id'] ?? null,
            trialFallbackPlanId: $data['trial_fallback_plan_id'] ?? null,
            selfSignupEnabled: $data['self_signup_enabled'] ?? true,
            signupDailyCap: $data['signup_daily_cap'] ?? 100,
            billingVodafoneCashNote: $data['billing_vodafone_cash_note'] ?? '',
            billingInstapayNote: $data['billing_instapay_note'] ?? '',
            billingBankName: $data['billing_bank_name'] ?? '',
            billingBankAccountHolder: $data['billing_bank_account_holder'] ?? '',
            billingBankAccountNumber: $data['billing_bank_account_number'] ?? '',
            billingBankIban: $data['billing_bank_iban'] ?? '',
            billingBankBranch: $data['billing_bank_branch'] ?? '',
            billingBankNote: $data['billing_bank_note'] ?? '',
        );
    }

    /**
     * @return array{
     *     name: string, short_name: string, subtitle: string, legal_name: string,
     *     logo_light: ?string, logo_dark: ?string, favicon: ?string, app_icon: ?string,
     *     primary_color: string, support_email: string, support_phone: string,
     *     website_url: string, powered_by_enabled: bool,
     *     billing_instapay_address?: string, billing_vodafone_cash_number?: string,
     *     billing_account_name?: string, billing_payment_note?: string, billing_tax_rate?: string,
     *     signup_trial_days?: int, default_signup_plan_id?: int|null, trial_fallback_plan_id?: int|null,
     *     self_signup_enabled?: bool, signup_daily_cap?: int,
     *     billing_vodafone_cash_note?: string, billing_instapay_note?: string, billing_bank_name?: string, billing_bank_account_holder?: string, billing_bank_account_number?: string, billing_bank_iban?: string, billing_bank_branch?: string, billing_bank_note?: string
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
            'signup_trial_days' => $this->signupTrialDays,
            'default_signup_plan_id' => $this->defaultSignupPlanId,
            'trial_fallback_plan_id' => $this->trialFallbackPlanId,
            'self_signup_enabled' => $this->selfSignupEnabled,
            'signup_daily_cap' => $this->signupDailyCap,
            'billing_vodafone_cash_note' => $this->billingVodafoneCashNote,
            'billing_instapay_note' => $this->billingInstapayNote,
            'billing_bank_name' => $this->billingBankName,
            'billing_bank_account_holder' => $this->billingBankAccountHolder,
            'billing_bank_account_number' => $this->billingBankAccountNumber,
            'billing_bank_iban' => $this->billingBankIban,
            'billing_bank_branch' => $this->billingBankBranch,
            'billing_bank_note' => $this->billingBankNote,
        ];
    }
}
