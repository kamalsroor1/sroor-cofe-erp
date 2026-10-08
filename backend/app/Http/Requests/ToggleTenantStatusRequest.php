<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Support\PlatformSuperAdmin;
use Illuminate\Foundation\Http\FormRequest;

class ToggleTenantStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return PlatformSuperAdmin::check($this->user());
    }

    public function rules(): array
    {
        return [
            'status' => 'required|string|in:active,trial,suspended,expired',
            'extend_days' => 'nullable|integer|min:0|max:3650',
        ];
    }
}
