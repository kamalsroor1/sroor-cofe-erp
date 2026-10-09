<?php

declare(strict_types=1);

namespace App\Http\Requests\Central;

use Illuminate\Foundation\Http\FormRequest;

/**
 * POST /api/v1/super-admin/auth/login (IDEN-1.3). Public by design: the route is gated by
 * EnsureCentralContext and the `central-login` limiter (per IP, per email + IP, and a
 * per-email hourly cap that ignores the IP). Operators sign in by email only (CTO Q-B5).
 */
final class CentralLoginRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'email' => ['required', 'string', 'email', 'max:255'],
            'password' => ['required', 'string', 'max:255'],
            'device_name' => ['nullable', 'string', 'max:100'],
        ];
    }
}
