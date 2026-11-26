<?php

declare(strict_types=1);

namespace App\Http\Resources\Central;

use App\DTOs\Branding\PlatformBrandingDTO;
use App\Enums\PlatformAssetSlot;
use App\Services\Billing\PaymentMethodDetails;
use App\Services\Signup\TrialFallbackPlan;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * BRND-2: the effective platform settings for the super-admin console
 * (stored value, else the config/branding.php default).
 *
 * Assets are exposed as public URLs plus `custom` (true = an uploaded file on the central
 * public disk, false = the config default or nothing). Disk paths are never returned.
 *
 * @property PlatformBrandingDTO $resource
 */
final class PlatformSettingsResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $branding = $this->resource;

        return [
            'name' => $branding->name,
            'short_name' => $branding->shortName,
            'subtitle' => $branding->subtitle,
            'legal_name' => $branding->legalName,
            'primary_color' => $branding->primaryColor,
            'support_email' => $branding->supportEmail,
            'support_phone' => $branding->supportPhone,
            'website_url' => $branding->websiteUrl,
            'powered_by_enabled' => $branding->poweredByEnabled,
            'billing_instapay_address' => $branding->billingInstapayAddress,
            'billing_vodafone_cash_number' => $branding->billingVodafoneCashNumber,
            'billing_account_name' => $branding->billingAccountName,
            'billing_payment_note' => $branding->billingPaymentNote,
            'billing_tax_rate' => $branding->billingTaxRate,
            // #232 spec F: per-method payment details and their computed status (unfilled = hidden).
            'billing_vodafone_cash_note' => $branding->billingVodafoneCashNote,
            'billing_instapay_note' => $branding->billingInstapayNote,
            'billing_bank_name' => $branding->billingBankName,
            'billing_bank_account_holder' => $branding->billingBankAccountHolder,
            'billing_bank_account_number' => $branding->billingBankAccountNumber,
            'billing_bank_iban' => $branding->billingBankIban,
            'billing_bank_branch' => $branding->billingBankBranch,
            'billing_bank_note' => $branding->billingBankNote,
            'payment_methods_status' => app(PaymentMethodDetails::class)->statuses($branding),
            'signup_trial_days' => $branding->signupTrialDays,
            // PLAN-10: signup / free plan settings (null plan id = not set).
            'default_signup_plan_id' => $branding->defaultSignupPlanId,
            'trial_fallback_plan_id' => $branding->trialFallbackPlanId,
            // PLAN-11: the plan that actually applies (the setting, else the catalog free plan; null = none).
            'effective_trial_fallback_plan_id' => app(TrialFallbackPlan::class)->planId(),
            'self_signup_enabled' => $branding->selfSignupEnabled,
            'signup_daily_cap' => $branding->signupDailyCap,
            'assets' => [
                PlatformAssetSlot::LogoLight->value => $this->asset($branding->logoLight),
                PlatformAssetSlot::LogoDark->value => $this->asset($branding->logoDark),
                PlatformAssetSlot::Favicon->value => $this->asset($branding->favicon),
                PlatformAssetSlot::AppIcon->value => $this->asset($branding->appIcon),
            ],
        ];
    }

    /**
     * @return array{url: string|null, custom: bool}
     */
    private function asset(?string $reference): array
    {
        return [
            'url' => PlatformAssetSlot::publicUrl($reference),
            'custom' => PlatformAssetSlot::isManagedPath($reference),
        ];
    }
}
