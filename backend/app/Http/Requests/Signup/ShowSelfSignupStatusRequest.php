<?php

declare(strict_types=1);

namespace App\Http\Requests\Signup;

use App\Support\Signup\SignupSecrets;
use Illuminate\Foundation\Http\FormRequest;

/**
 * SIGN-12: GET /api/v1/signup/status (public, throttled `self-signup-status`). The token comes
 * from the `X-Signup-Token` header (preferred: kept out of access logs) or the `token` query.
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
            'token' => is_string($header) && $header !== '' ? $header : $this->query('token'),
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
