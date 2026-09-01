<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateTenantUnitsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasRole('super_admin') || $this->user()?->hasRole('admin') || $this->user()?->can('super_admin.access') ?? false;
    }

    public function rules(): array
    {
        return [
            'units'   => ['required', 'array', 'min:1'],
            'units.*' => ['required', 'string', 'max:50'],
        ];
    }
}
