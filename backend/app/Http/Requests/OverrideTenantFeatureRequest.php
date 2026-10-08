<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Support\PlatformSuperAdmin;
use Illuminate\Foundation\Http\FormRequest;

class OverrideTenantFeatureRequest extends FormRequest
{
    public function authorize(): bool
    {
        return PlatformSuperAdmin::check($this->user());
    }

    public function rules(): array
    {
        return [
            'feature_key' => 'required|string|max:100',
        ];
    }
}
