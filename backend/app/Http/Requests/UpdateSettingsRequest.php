<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Http\Requests\Settings\SendTestTelegramRequest;
use App\Services\Settings\TenantSettings;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) ($this->user() && (
            $this->user()->hasRole('admin')
            || $this->user()->can('roles.manage')
            || $this->user()->can('settings.manage')
        ));
    }

    public function rules(): array
    {
        return [
            'company_name' => ['required', 'string', 'max:255'],
            'company_subtitle' => ['nullable', 'string', 'max:255'],
            'company_phone' => ['nullable', 'string', 'max:50'],
            'company_address' => ['nullable', 'string', 'max:255'],
            'invoice_footer_note' => ['nullable', 'string', 'max:500'],
            'show_print_company_name' => ['sometimes', 'boolean'],
            'show_print_subtitle' => ['sometimes', 'boolean'],
            'show_print_logo' => ['sometimes', 'boolean'],
            'thermal_show_customer_balance' => ['sometimes', 'boolean'],
            'print_show_qr' => ['sometimes', 'boolean'],
            'invoice_primary_color' => ['nullable', 'string', 'in:amber,emerald,blue,slate'],
            'system_theme_color' => ['nullable', 'string', 'max:50'],
            // SETG-10: the tenant unit list (comma-separated); item create/update validate against it.
            TenantSettings::KEY_INVENTORY_UNITS => ['nullable', 'string', 'max:1000'],
            // SETG-7: write-only secret — blank keeps the stored token, clear_telegram_bot_token removes it.
            'telegram_bot_token' => ['nullable', 'string', 'max:255', SendTestTelegramRequest::BOT_TOKEN_RULE],
            'clear_telegram_bot_token' => ['sometimes', 'boolean'],
            'telegram_chat_id' => ['nullable', 'string', 'max:255', SendTestTelegramRequest::CHAT_ID_RULE],
            // SETG-7 / SETG-1 ext (CTO W1 Q5): legal info printed on the A4 invoice (empty = line hidden).
            TenantSettings::KEY_COMMERCIAL_REGISTER => ['nullable', 'string', 'max:50'],
            TenantSettings::KEY_TAX_REGISTRATION_NO => ['nullable', 'string', 'max:50'],
            'telegram_notifications_enabled' => ['sometimes', 'boolean'],
            'logo_file' => ['nullable', 'image', 'max:4096'],
            'logo_light_file' => ['nullable', 'image', 'max:4096'],
            'logo_dark_file' => ['nullable', 'image', 'max:4096'],

            // SETG-1 tenant localization. Optional so older clients that omit them keep the stored values.
            // SETG-1 ext (CTO W1 Q5): only the 6 Phase-1 currencies can be newly saved; a legacy
            // value already stored may be re-sent unchanged so older tenants can still save the form.
            TenantSettings::KEY_CURRENCY => ['sometimes', 'required', 'string', Rule::in($this->savableCurrencies())],
            // SETG-2 ext: any IANA zone — write-side business dates now come from TenantClock.
            TenantSettings::KEY_TIMEZONE => ['sometimes', 'required', 'string', Rule::in(TenantSettings::selectableTimezones())],
            // SETG-2 ext: business day = [D + cutoff, D+1 + cutoff) on the tenant clock.
            TenantSettings::KEY_BUSINESS_DAY_CUTOFF => ['sometimes', 'required', 'string', 'regex:'.TenantSettings::CUTOFF_PATTERN],
            // SETG-10: low-stock threshold for items without their own minimum.
            TenantSettings::KEY_LOW_STOCK_DEFAULT_THRESHOLD => ['sometimes', 'required', 'numeric', 'min:0', 'max:999999999.999', 'decimal:0,3'],
            TenantSettings::KEY_DEFAULT_LOCALE => ['sometimes', 'required', 'string', Rule::in(TenantSettings::SUPPORTED_LOCALES)],
            TenantSettings::KEY_NUMBER_DIGITS => ['sometimes', 'required', 'string', Rule::in(TenantSettings::SUPPORTED_NUMBER_DIGITS)],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            TenantSettings::KEY_CURRENCY => __('settings.currency'),
            TenantSettings::KEY_TIMEZONE => __('settings.timezone'),
            TenantSettings::KEY_DEFAULT_LOCALE => __('settings.default_locale'),
            TenantSettings::KEY_NUMBER_DIGITS => __('settings.number_digits'),
            TenantSettings::KEY_BUSINESS_DAY_CUTOFF => __('settings.business_day_cutoff'),
            TenantSettings::KEY_LOW_STOCK_DEFAULT_THRESHOLD => __('settings.low_stock_default_threshold'),
            TenantSettings::KEY_INVENTORY_UNITS => __('settings.inventory_units'),
            'telegram_bot_token' => __('settings.attr_telegram_bot_token'),
            'telegram_chat_id' => __('settings.attr_telegram_chat_id'),
            TenantSettings::KEY_COMMERCIAL_REGISTER => __('settings.commercial_register'),
            TenantSettings::KEY_TAX_REGISTRATION_NO => __('settings.tax_registration_no'),
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            TenantSettings::KEY_BUSINESS_DAY_CUTOFF.'.regex' => __('settings.business_day_cutoff_invalid'),
        ];
    }

    /**
     * The 6 selectable currencies, plus the tenant's currently stored legacy currency (if
     * any) so re-saving an unchanged form never fails.
     *
     * @return list<string>
     */
    private function savableCurrencies(): array
    {
        $allowed = TenantSettings::selectableCurrencies();
        $stored = app(TenantSettings::class)->currency();

        if (! in_array($stored, $allowed, true)) {
            $allowed[] = $stored;
        }

        return $allowed;
    }
}
