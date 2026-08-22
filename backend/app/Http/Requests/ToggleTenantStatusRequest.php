<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ToggleTenantStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasRole('super_admin') || $this->user()?->can('super_admin.access') || $this->user()?->hasRole('admin') || in_array($this->user()?->phone, ['01012316954', '01558088841']);
    }

    public function rules(): array
    {
        return [
            'status' => 'required|string|in:active,trial,suspended,expired',
            'extend_days' => 'nullable|integer|min:0|max:3650',
        ];
    }
}
