<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool)($this->user() && ($this->user()->hasRole('admin') || $this->user()->can('users.manage') || $this->user()->can('roles.manage')));
    }

    public function rules(): array
    {
        $userId = $this->route('id') ?? $this->route('user') ?? ($this->getPathInfo() ? (int)basename($this->getPathInfo()) : null);

        return [
            'name'             => ['required', 'string', 'max:255'],
            'phone'            => ['required', 'string', 'max:20', Rule::unique('users', 'phone')->ignore($userId)],
            'email'            => ['nullable', 'string', 'email', 'max:255', Rule::unique('users', 'email')->ignore($userId)],
            'password'         => ['nullable', 'string', 'min:6'],
            'role'             => ['required', 'string', 'exists:roles,name'],
            'default_store_id' => ['nullable', 'exists:stores,id'],
            'is_active'        => ['sometimes', 'boolean'],
        ];
    }
}
