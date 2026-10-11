<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();
        if (! $user || (! $user->hasRole('admin') && ! $user->can('roles.manage'))) {
            return false;
        }

        // SEC-1 (L12): only an admin may create another admin account.
        return $user->hasRole('admin') || $this->input('role') !== 'admin';
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['required', 'string', 'max:20', 'unique:users,phone'],
            'email' => ['nullable', 'string', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:6'],
            'role' => ['required', 'string', 'exists:roles,name', Rule::notIn(['super_admin'])],
            'default_store_id' => ['nullable', 'exists:stores,id'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
