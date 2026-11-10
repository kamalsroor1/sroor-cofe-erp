<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Enums\ImportType;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;

/**
 * PRD-06: template download. Also the base of the preview/commit requests: the `{type}` route
 * segment decides which permission is required (the same one creating those records needs).
 */
class ImportTemplateRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user instanceof User && $this->importType()->authorizes($user);
    }

    public function rules(): array
    {
        return [];
    }

    public function importType(): ImportType
    {
        return ImportType::from((string) $this->route('type'));
    }
}
