<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * SELL-01: POST /api/v1/users/{id}/reset-password. Same gate as the rest of user management
 * (admin role or roles.manage; there is no separate users.manage permission). The takeover and
 * admin-account rules are enforced on the locked row by ResetUserPasswordAction.
 */
final class ResetUserPasswordRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user !== null && ($user->hasRole('admin') || $user->can('roles.manage'));
    }

    /**
     * @return array<string, never>
     */
    public function rules(): array
    {
        return [];
    }
}
