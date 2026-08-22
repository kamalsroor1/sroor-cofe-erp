<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool)($this->user() && ($this->user()->hasRole('admin') || $this->user()->can('roles.manage') || $this->user()->can('users.manage')));
    }

    public function rules(): array
    {
        return [
            'company_name'                   => ['required', 'string', 'max:255'],
            'company_subtitle'               => ['nullable', 'string', 'max:255'],
            'company_phone'                  => ['nullable', 'string', 'max:50'],
            'company_address'                => ['nullable', 'string', 'max:255'],
            'invoice_footer_note'            => ['nullable', 'string', 'max:500'],
            'show_print_company_name'        => ['sometimes', 'boolean'],
            'show_print_subtitle'            => ['sometimes', 'boolean'],
            'show_print_logo'                => ['sometimes', 'boolean'],
            'thermal_show_customer_balance'  => ['sometimes', 'boolean'],
            'print_show_qr'                  => ['sometimes', 'boolean'],
            'invoice_primary_color'          => ['nullable', 'string', 'in:amber,emerald,blue,slate'],
            'system_theme_color'             => ['nullable', 'string', 'max:50'],
            'telegram_bot_token'             => ['nullable', 'string', 'max:255'],
            'telegram_chat_id'               => ['nullable', 'string', 'max:255'],
            'telegram_notifications_enabled' => ['sometimes', 'boolean'],
            'logo_file'                      => ['nullable', 'image', 'max:4096'],
            'logo_light_file'                => ['nullable', 'image', 'max:4096'],
            'logo_dark_file'                 => ['nullable', 'image', 'max:4096'],
        ];
    }
}
