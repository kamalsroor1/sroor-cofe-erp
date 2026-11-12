<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * BE-08: an admin / user manager sets (or clears with null) another user's manager approval PIN.
 */
final class UpdateUserPosPinRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user !== null && ($user->hasRole('admin') || $user->can('roles.manage'));
    }

    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            // SEC-21: a NEW PIN is exactly 6 digits. Existing shorter PINs keep verifying until changed.
            'pin' => ['present', 'nullable', 'string', 'regex:/^\d{6}$/'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return ['pin.regex' => __('users.pos_pin_format')];
    }
}
