<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Http\Requests\Concerns\ValidatesUserStores;
use App\Support\PasswordPolicy;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreUserRequest extends FormRequest
{
    use ValidatesUserStores;

    /** ROLE-01: runs before authorize(); `role` becomes the stored role name. */
    protected function prepareForValidation(): void
    {
        $this->canonicalRole();
    }

    public function authorize(): bool
    {
        $user = $this->user();
        if (! $user || (! $user->hasRole('admin') && ! $user->can('roles.manage'))) {
            return false;
        }

        // SEC-1 (L12): only an admin may create another admin account (ROLE-01: on the resolved
        // role, so "Admin" on a case-insensitive collation counts as admin).
        return $user->hasRole('admin') || ! $this->resolvesToAdminRole();
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['required', 'string', 'max:20', 'unique:users,phone'],
            'email' => ['nullable', 'string', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', PasswordPolicy::rule()],
            'role' => ['required', 'string', Rule::exists('roles', 'name')->where('guard_name', 'web'), Rule::notIn(['super_admin'])],
            ...$this->storeRules(),
            'is_active' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * @return list<callable(Validator): void>
     */
    public function after(): array
    {
        return [
            fn (Validator $validator) => $this->requireStoreForNonAdmin($validator),
        ];
    }
}
