<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Enums\CentralPermission;
use App\Support\PlatformSuperAdmin;
use Illuminate\Foundation\Http\FormRequest;

class UpdateTenantUnitsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return PlatformSuperAdmin::can($this->user(), CentralPermission::TenantsManage);
    }

    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'units' => ['required', 'array', 'min:1', 'max:100'],
            // Stored as CSV: a comma inside a unit would split it (BRND-2); no markup either.
            'units.*' => ['required', 'string', 'max:50', 'not_regex:/[,<>]/'],
        ];
    }
}
