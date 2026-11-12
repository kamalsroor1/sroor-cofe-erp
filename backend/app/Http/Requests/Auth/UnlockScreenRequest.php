<?php

declare(strict_types=1);

namespace App\Http\Requests\Auth;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;

/**
 * SET-02: the signed-in user re-enters THEIR OWN password or POS PIN to dismiss the idle lock
 * screen. Self-service (no permission): it only proves the person at the device is the token's
 * user. Exactly one of the two fields.
 */
class UnlockScreenRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() instanceof User;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'password' => ['required_without:pin', 'prohibits:pin', 'nullable', 'string', 'max:255'],
            // A string: a JSON number would lose a leading zero.
            'pin' => ['required_without:password', 'nullable', 'string', 'regex:/^\d{4,6}$/'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'password' => __('screen_lock.password'),
            'pin' => __('screen_lock.pin'),
        ];
    }
}
