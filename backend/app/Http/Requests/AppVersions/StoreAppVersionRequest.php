<?php

namespace App\Http\Requests\AppVersions;

use Illuminate\Foundation\Http\FormRequest;

class StoreAppVersionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() && $this->user()->can('system.manage');
    }

    public function rules(): array
    {
        return [
            'platform' => ['required', 'string', 'in:android,ios,windows'],
            'version_name' => ['required', 'string', 'max:50'],
            'version_code' => ['required', 'integer', 'min:1'],
            'min_version_code' => ['sometimes', 'integer', 'min:1'],
            'is_force_update' => ['sometimes', 'boolean'],
            'release_notes_ar' => ['required', 'string'],
            'release_notes_en' => ['nullable', 'string'],
            'apk_file' => ['nullable', 'file', 'max:153600'], // max 150MB
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
