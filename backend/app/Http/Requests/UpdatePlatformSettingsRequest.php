<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdatePlatformSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasRole('super_admin') || $this->user()?->hasRole('admin') || $this->user()?->can('super_admin.access') ?? false;
    }

    public function rules(): array
    {
        return [
            'platform_name'     => ['required', 'string', 'max:100'],
            'platform_subtitle' => ['nullable', 'string', 'max:255'],
            'support_email'     => ['nullable', 'email', 'max:100'],
            'support_phone'     => ['nullable', 'string', 'max:50'],
        ];
    }
}
