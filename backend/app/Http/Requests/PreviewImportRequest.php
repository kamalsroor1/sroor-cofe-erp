<?php

declare(strict_types=1);

namespace App\Http\Requests;

final class PreviewImportRequest extends ImportTemplateRequest
{
    public function rules(): array
    {
        return [
            // 5 MB; the extension is checked as well because csv MIME sniffing is unreliable.
            'file' => ['required', 'file', 'max:5120', 'extensions:xlsx,csv', 'mimes:xlsx,csv,txt'],
        ];
    }
}
