<?php

declare(strict_types=1);

namespace App\Http\Requests\System;

use App\Actions\System\GetTranslationsAction;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class GetTranslationsRequest extends FormRequest
{
    /**
     * The translations endpoint is deliberately public (needed before login).
     */
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
            'locale' => ['nullable', 'string', Rule::in(GetTranslationsAction::SUPPORTED_LOCALES)],
        ];
    }
}
