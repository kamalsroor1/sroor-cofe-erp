<?php

declare(strict_types=1);

namespace App\Http\Requests\Signup;

use App\Support\Signup\SignupSecrets;
use Illuminate\Foundation\Http\FormRequest;

/**
 * SIGN-12: GET /api/v1/signup/status (public, throttled `self-signup-status`). Security review:
 * the token comes ONLY from the `X-Signup-Token` header (never the query string, which ends up
 * in access logs, browser history and Referer headers; Telescope hides the header).
 */
final class ShowSelfSignupStatusRequest extends FormRequest
{
    public const HEADER = 'X-Signup-Token';

    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $header = $this->header(self::HEADER);

        $this->merge([
            'token' => is_string($header) && $header !== '' ? $header : null,
        ]);
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
