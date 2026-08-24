<?php

declare(strict_types=1);

namespace App\Http\Requests\AppVersions;

use Illuminate\Foundation\Http\FormRequest;

final class CheckUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Public OTA update check & download endpoint
    }

    public function rules(): array
    {
        return [
            'platform'        => ['sometimes', 'string', 'in:android,ios,windows'],
            'version_code'    => ['sometimes', 'integer', 'min:1'],
            'version_name'    => ['sometimes', 'string', 'max:50'],
            'current_version' => ['sometimes', 'string', 'max:50'],
        ];
    }
}
