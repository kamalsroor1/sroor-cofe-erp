<?php

declare(strict_types=1);

namespace App\Http\Requests\Signup;

use App\Support\Signup\SignupSecrets;
use Illuminate\Foundation\Http\FormRequest;

/**
 * SIGN-11: POST /api/v1/signup/resend {token} (public, throttled `self-signup-resend`).
 */
final class ResendSelfSignupCodeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'token' => ['required', 'string', 'size:'.SignupSecrets::TOKEN_LENGTH],
        ];
    }
}
