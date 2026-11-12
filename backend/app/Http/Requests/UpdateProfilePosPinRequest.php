<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * BE-08: the signed-in user sets (or clears with null) their own manager approval PIN. The account
 * password is required, so a left-open session cannot mint an approval PIN; it is checked (with the
 * shared lockout) by SetOwnPosPinAction.
 */
final class UpdateProfilePosPinRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            // SEC-21: a NEW PIN is exactly 6 digits. Existing shorter PINs keep verifying until changed.
            'pin' => ['present', 'nullable', 'string', 'regex:/^\d{6}$/'],
            // Verified (with the account lockout) by SetOwnPosPinAction, never here.
            'current_password' => ['required', 'string', 'max:255'],
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
