<?php

declare(strict_types=1);

namespace App\Http\Requests;

/**
 * IMP-01: the error report of a previewed (not yet committed) file. Same permission as the import.
 */
final class ImportErrorsRequest extends ImportTemplateRequest
{
    public function rules(): array
    {
        return [
            'token' => ['required', 'string', 'max:100'],
        ];
    }
}
