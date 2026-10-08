<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Support\PlatformSuperAdmin;
use Illuminate\Foundation\Http\FormRequest;

class UpdateTenantDatabaseConfigRequest extends FormRequest
{
    public function authorize(): bool
    {
        return PlatformSuperAdmin::check($this->user());
    }

    public function rules(): array
    {
        return [
            'tenancy_db_name' => ['nullable', 'string', 'max:100'],
            'tenancy_db_username' => ['nullable', 'string', 'max:100'],
            'tenancy_db_password' => ['nullable', 'string', 'max:255'],
        ];
    }
}
