<?php

declare(strict_types=1);

namespace App\Http\Requests\Auth;

use App\Support\QuickLoginGate;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Testing-only passwordless login. Lookup is by user id only (never phone/email),
 * so it cannot be used to enumerate contact details.
 */
final class QuickLoginRequest extends FormRequest
{
    public function authorize(): bool
    {
        return QuickLoginGate::allowed();
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'user_id' => ['required', 'integer', 'min:1'],
            'device_name' => ['nullable', 'string', 'max:100'],
        ];
    }
}
