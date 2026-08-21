<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateRolePermissionsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool)($this->user() && ($this->user()->hasRole('admin') || $this->user()->can('roles.manage')));
    }

    public function rules(): array
    {
        return [
            'permissions'   => ['nullable', 'array'],
            'permissions.*' => ['string', 'exists:permissions,name'],
        ];
    }
}
