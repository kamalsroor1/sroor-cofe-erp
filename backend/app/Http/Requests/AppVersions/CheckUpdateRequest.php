<?php

namespace App\Http\Requests\AppVersions;

use Illuminate\Foundation\Http\FormRequest;

class CheckUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Public endpoint
    }

    public function rules(): array
    {
        return [
            'platform' => ['sometimes', 'string', 'in:android,ios,windows'],
            'version_code' => ['required', 'integer', 'min:1'],
            'version_name' => ['sometimes', 'string', 'max:50'],
        ];
    }
}
