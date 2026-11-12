<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Http\Requests\Concerns\ValidatesUserStores;
use App\Models\User;
use App\Support\PasswordPolicy;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateUserRequest extends FormRequest
{
    use ValidatesUserStores;

    /** ROLE-01: runs before authorize(); `role` becomes the stored role name. */
    protected function prepareForValidation(): void
    {
        $this->canonicalRole();
    }

    public function authorize(): bool
    {
        return (bool) ($this->user() && ($this->user()->hasRole('admin') || $this->user()->can('roles.manage')));
    }

    public function rules(): array
    {
        $userId = $this->targetUserId();

        return [
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['required', 'string', 'max:20', Rule::unique('users', 'phone')->ignore($userId)],
            'email' => ['nullable', 'string', 'email', 'max:255', Rule::unique('users', 'email')->ignore($userId)],
            'password' => ['nullable', 'string', PasswordPolicy::rule()],
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
            // An unknown user id is left to the action (404), never answered with a 422 first.
            function (Validator $validator): void {
                $target = $this->targetUser();
                if ($target instanceof User) {
                    $this->requireStoreForNonAdmin($validator, $target);
                }
            },
        ];
    }

    private function targetUserId(): mixed
    {
        return $this->route('id') ?? $this->route('user') ?? ($this->getPathInfo() ? (int) basename($this->getPathInfo()) : null);
    }

    private function targetUser(): ?User
    {
        $id = $this->targetUserId();

        return is_numeric($id) ? User::query()->find((int) $id) : null;
    }
}
