<?php

declare(strict_types=1);

namespace App\Http\Requests;

final class CommitImportRequest extends ImportTemplateRequest
{
    public function rules(): array
    {
        return [
            'token' => ['required', 'string', 'max:100'],
            'skip_invalid' => ['sometimes', 'boolean'],
        ];
    }
}
