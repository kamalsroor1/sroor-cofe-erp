<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Hash;

/**
 * BE-08: the signed-in user sets (or clears with null) their own manager approval PIN. The account
 * password is required, so a left-open session cannot mint an approval PIN.
 */
final class UpdateProfilePosPinRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            // SEC-21: a NEW PIN is exactly 6 digits. Existing shorter PINs keep verifying until changed.
            'pin' => ['present', 'nullable', 'string', 'regex:/^\d{6}$/'],
            'current_password' => ['required', 'string', function (string $attribute, mixed $value, Closure $fail): void {
                $hash = $this->user()?->password;
                if (! is_string($value) || ! is_string($hash) || ! Hash::check($value, $hash)) {
                    $fail(__('auth.current_password_incorrect'));
                }
            }],
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
