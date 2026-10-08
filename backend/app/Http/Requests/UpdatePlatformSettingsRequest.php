<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Support\PlatformSuperAdmin;
use Illuminate\Foundation\Http\FormRequest;

class UpdatePlatformSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return PlatformSuperAdmin::check($this->user());
    }

    public function rules(): array
    {
        return [
            'platform_name' => ['required', 'string', 'max:100'],
            'platform_subtitle' => ['nullable', 'string', 'max:255'],
            'support_email' => ['nullable', 'email', 'max:100'],
            'support_phone' => ['nullable', 'string', 'max:50'],
        ];
    }
}
