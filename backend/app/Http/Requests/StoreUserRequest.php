<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('users.manage') ?? false;
    }

    public function rules(): array
    {
        return [
            'name'             => ['required', 'string', 'max:255'],
            'phone'            => ['required', 'string', 'max:20', 'unique:users,phone'],
            'email'            => ['nullable', 'string', 'email', 'max:255', 'unique:users,email'],
            'password'         => ['required', 'string', 'min:6'],
            'role'             => ['required', 'string', 'exists:roles,name'],
            'default_store_id' => ['nullable', 'exists:stores,id'],
            'is_active'        => ['sometimes', 'boolean'],
        ];
    }
}
