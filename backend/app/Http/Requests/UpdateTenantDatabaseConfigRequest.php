<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateTenantDatabaseConfigRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasRole('super_admin') || $this->user()?->hasRole('admin') || $this->user()?->can('super_admin.access') ?? false;
    }

    public function rules(): array
    {
        return [
            'tenancy_db_name'     => ['nullable', 'string', 'max:100'],
            'tenancy_db_username' => ['nullable', 'string', 'max:100'],
            'tenancy_db_password' => ['nullable', 'string', 'max:255'],
        ];
    }
}
