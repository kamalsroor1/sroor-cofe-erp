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
            'inventory_units' => ['nullable', 'string', 'max:1000'],
            // SETG-7: write-only secret — blank keeps the stored token, clear_telegram_bot_token removes it.
            'telegram_bot_token' => ['nullable', 'string', 'max:255', SendTestTelegramRequest::BOT_TOKEN_RULE],
            'clear_telegram_bot_token' => ['sometimes', 'boolean'],
            'telegram_chat_id' => ['nullable', 'string', 'max:255', SendTestTelegramRequest::CHAT_ID_RULE],
            // SETG-7: legal info printed on the A4 invoice (empty = line hidden).
            // TODO(CTO): final registry keys come with SETG-1 registry / SETG-4 (`company.commercial_register`, `tax.registration_no`).
            'commercial_register' => ['nullable', 'string', 'max:50'],
            'tax_registration_no' => ['nullable', 'string', 'max:50'],
            'telegram_notifications_enabled' => ['sometimes', 'boolean'],
            'logo_file' => ['nullable', 'image', 'max:4096'],
            'logo_light_file' => ['nullable', 'image', 'max:4096'],
            'logo_dark_file' => ['nullable', 'image', 'max:4096'],

            // SETG-1 tenant localization. Optional so older clients that omit them keep the stored values.
            TenantSettings::KEY_CURRENCY => ['sometimes', 'required', 'string', Rule::in(TenantSettings::SUPPORTED_CURRENCIES)],
            // Pinned to the application timezone until write-side business dates are tenant-local (see TenantSettings::selectableTimezones()).
            TenantSettings::KEY_TIMEZONE => ['sometimes', 'required', 'string', Rule::in(TenantSettings::selectableTimezones())],
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
            'telegram_bot_token' => __('settings.attr_telegram_bot_token'),
            'telegram_chat_id' => __('settings.attr_telegram_chat_id'),
            'commercial_register' => __('settings.commercial_register'),
            'tax_registration_no' => __('settings.tax_registration_no'),
        ];
    }
}
