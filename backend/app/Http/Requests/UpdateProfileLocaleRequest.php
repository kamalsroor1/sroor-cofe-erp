<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Services\Settings\TenantSettings;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateProfileLocaleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'locale' => ['required', 'string', Rule::in(TenantSettings::SUPPORTED_LOCALES)],
        ];
    }
}
