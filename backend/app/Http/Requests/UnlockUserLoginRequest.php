<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * SEC-05: an admin / user manager lifts another user's sign-in lockout.
 */
final class UnlockUserLoginRequest extends FormRequest
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
