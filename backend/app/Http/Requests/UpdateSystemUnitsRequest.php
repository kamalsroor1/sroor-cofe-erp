<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Enums\CentralPermission;
use App\Support\PlatformSuperAdmin;
use Illuminate\Foundation\Http\FormRequest;

class UpdateSystemUnitsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return PlatformSuperAdmin::can($this->user(), CentralPermission::SettingsManage);
    }

    public function rules(): array
    {
        return [
            'units' => ['required', 'array', 'min:1'],
            'units.*' => ['required', 'string', 'max:50'],
        ];
    }
}
