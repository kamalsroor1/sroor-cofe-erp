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

    public function rules(): array
    {
        return [
            'units' => ['required', 'array', 'min:1'],
            'units.*' => ['required', 'string', 'max:50'],
        ];
    }
}
