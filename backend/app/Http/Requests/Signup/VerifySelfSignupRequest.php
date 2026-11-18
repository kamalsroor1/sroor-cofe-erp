<?php

declare(strict_types=1);

namespace App\Http\Requests\Signup;

use App\Support\Signup\SignupSecrets;
use Illuminate\Foundation\Http\FormRequest;

/**
 * SIGN-11: POST /api/v1/signup/verify {token, code} (public, throttled `self-signup-verify`).
 * Arabic-Indic digits and blanks in the code are normalised; a malformed token or code is a
 * plain 422 (no lookup is made).
 */
final class VerifySelfSignupRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('code'))) {
            $code = strtr($this->input('code'), [
                '٠' => '0', '١' => '1', '٢' => '2', '٣' => '3', '٤' => '4',
                '٥' => '5', '٦' => '6', '٧' => '7', '٨' => '8', '٩' => '9',
            ]);
            $this->merge(['code' => (string) preg_replace('/\s+/u', '', $code)]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'token' => ['required', 'string', 'size:'.SignupSecrets::TOKEN_LENGTH],
            'code' => ['required', 'string', 'regex:/^[0-9]{6}$/'],
        ];
    }
}
