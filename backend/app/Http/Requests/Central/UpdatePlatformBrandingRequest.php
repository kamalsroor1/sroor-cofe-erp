<?php

declare(strict_types=1);

namespace App\Http\Requests\Central;

use App\DTOs\Central\PlatformSettingsDTO;
use App\Enums\CentralPermission;
use App\Models\Plan;
use App\Services\Branding\PlatformBranding;
use App\Support\PlatformSuperAdmin;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

/**
 * PUT /api/v1/super-admin/platform-settings (BRND-2), super_admin.settings.manage + step-up.
 *
 * Strict partial update: only PlatformSettingsDTO::EDITABLE keys, each optional; any other
 * key (including the asset slots, which have their own endpoints) is a 422, and so is an
 * empty body. A nullable field sent as null/'' resets it to the config default; name and
 * short_name can be changed but never blanked. No markup in text values.
 */
final class UpdatePlatformBrandingRequest extends FormRequest
{
    private const NO_MARKUP = 'not_regex:/[<>]/';

    public function authorize(): bool
    {
        return PlatformSuperAdmin::can($this->user(), CentralPermission::SettingsManage);
    }

    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'required', 'string', 'max:100', self::NO_MARKUP],
            'short_name' => ['sometimes', 'required', 'string', 'max:30', self::NO_MARKUP],
            'subtitle' => ['sometimes', 'nullable', 'string', 'max:255', self::NO_MARKUP],
            'legal_name' => ['sometimes', 'nullable', 'string', 'max:150', self::NO_MARKUP],
            'primary_color' => ['sometimes', 'nullable', 'string', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'support_email' => ['sometimes', 'nullable', 'string', 'email:rfc', 'max:100'],
            'support_phone' => ['sometimes', 'nullable', 'string', 'max:30', 'regex:/^\+?[0-9][0-9 ()\-]{2,29}$/'],
            'website_url' => ['sometimes', 'nullable', 'string', 'max:255', 'url:https,http'],
            'powered_by_enabled' => ['sometimes', 'required', 'boolean'],
            // PRD-03 manual billing details shown to tenants (blank/null = not set).
            // #232 UI spec 6b: `name@instapay` or an Egyptian mobile number.
            'billing_instapay_address' => ['sometimes', 'nullable', 'string', 'max:100', 'regex:/^([A-Za-z0-9._-]{1,64}@instapay|01\d{9})$/i'],
            // #232 UI spec 6b: an Egyptian mobile (11 digits starting 01; spaces / dashes are stripped first).
            'billing_vodafone_cash_number' => ['sometimes', 'nullable', 'string', 'regex:/^01\d{9}$/'],
            'billing_account_name' => ['sometimes', 'nullable', 'string', 'max:100', self::NO_MARKUP],
            'billing_payment_note' => ['sometimes', 'nullable', 'string', 'max:500', self::NO_MARKUP],
            // #232 spec F / UI spec 6b: per-method details (null / '' = not set; a method missing a required
            // detail is hidden from shops). IBAN / account number are stored without spaces.
            'billing_vodafone_cash_note' => ['sometimes', 'nullable', 'string', 'max:300', self::NO_MARKUP],
            'billing_instapay_note' => ['sometimes', 'nullable', 'string', 'max:300', self::NO_MARKUP],
            'billing_bank_name' => ['sometimes', 'nullable', 'string', 'max:120', self::NO_MARKUP],
            'billing_bank_account_holder' => ['sometimes', 'nullable', 'string', 'max:120', self::NO_MARKUP],
            'billing_bank_account_number' => ['sometimes', 'nullable', 'string', 'regex:/^\d{1,34}$/'],
            'billing_bank_iban' => ['sometimes', 'nullable', 'string', 'regex:/^EG\d{27}$/'],
            'billing_bank_branch' => ['sometimes', 'nullable', 'string', 'max:120', self::NO_MARKUP],
            'billing_bank_note' => ['sometimes', 'nullable', 'string', 'max:300', self::NO_MARKUP],
            'billing_tax_rate' => ['sometimes', 'nullable', 'numeric', 'min:0', 'max:100', 'regex:/^\d{1,3}(\.\d{1,3})?$/'],
            // SELL-06: trial days of an approved signup whose plan has none (null = config default).
            'signup_trial_days' => ['sometimes', 'nullable', 'integer', 'min:1', 'max:'.PlatformBranding::MAX_SIGNUP_TRIAL_DAYS],
            // PLAN-10: signup / free plan settings. Plan ids are checked against the central
            // `plans` table in after() (active; the fallback must also be a permanent free plan).
            'default_signup_plan_id' => ['sometimes', 'nullable', 'integer', 'min:1'],
            'trial_fallback_plan_id' => ['sometimes', 'nullable', 'integer', 'min:1'],
            'self_signup_enabled' => ['sometimes', 'required', 'boolean'],
            'signup_daily_cap' => ['sometimes', 'nullable', 'integer', 'min:1', 'max:'.PlatformBranding::MAX_SIGNUP_DAILY_CAP],
        ];
    }

    /**
     * #232 UI spec 6b: numbers may be typed with spaces (and dashes for the mobile number); they are
     * validated and stored without them, the IBAN upper-cased. An InstaPay phone number likewise.
     */
    protected function prepareForValidation(): void
    {
        $clean = [];

        foreach (['billing_vodafone_cash_number' => '/[\s\-]+/u', 'billing_bank_account_number' => '/\s+/u', 'billing_bank_iban' => '/\s+/u'] as $field => $pattern) {
            $value = $this->input($field);
            if (is_string($value)) {
                $clean[$field] = (string) preg_replace($pattern, '', $value);
            }
        }

        if (isset($clean['billing_bank_iban'])) {
            $clean['billing_bank_iban'] = strtoupper($clean['billing_bank_iban']);
        }

        $instapay = $this->input('billing_instapay_address');
        if (is_string($instapay) && ! str_contains($instapay, '@')) {
            $clean['billing_instapay_address'] = (string) preg_replace('/[\s\-]+/u', '', $instapay);
        }

        if ($clean !== []) {
            $this->merge($clean);
        }
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'billing_vodafone_cash_number.regex' => (string) __('billing.console.settings.vodafone_number_invalid'),
            'billing_instapay_address.regex' => (string) __('billing.console.settings.instapay_address_invalid'),
            'billing_bank_account_number.regex' => (string) __('billing.console.settings.bank_account_number_invalid'),
            'billing_bank_iban.regex' => (string) __('billing.console.settings.iban_invalid'),
        ];
    }

    /**
     * @return list<callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $sent = array_map('strval', array_keys($this->all()));

                foreach (array_diff($sent, PlatformSettingsDTO::EDITABLE) as $unknown) {
                    $validator->errors()->add($unknown, __('super.platform_branding.unknown_field'));
                }

                if (array_intersect($sent, PlatformSettingsDTO::EDITABLE) === []) {
                    $validator->errors()->add('settings', __('super.platform_branding.nothing_to_update'));
                }
            },
            fn (Validator $validator) => $this->validateSignupPlans($validator),
        ];
    }

    /**
     * PLAN-10: the default signup plan must be an active plan; the trial fallback plan an active
     * permanent free plan (plans.is_free). Plan is pinned to the central connection.
     */
    private function validateSignupPlans(Validator $validator): void
    {
        $checks = [
            'default_signup_plan_id' => ['default_plan_invalid', false],
            'trial_fallback_plan_id' => ['fallback_plan_invalid', true],
        ];

        foreach ($checks as $field => [$message, $mustBeFree]) {
            if ($validator->errors()->has($field)) {
                continue;
            }

            $value = $this->input($field);
            if ($value === null || $value === '') {
                continue;
            }

            $valid = Plan::query()
                ->whereKey((int) $value)
                ->where('is_active', true)
                ->when($mustBeFree, fn ($query) => $query->where('is_free', true))
                ->exists();

            if (! $valid) {
                $validator->errors()->add($field, (string) __('billing.signup_settings.'.$message));
            }
        }
    }
}
