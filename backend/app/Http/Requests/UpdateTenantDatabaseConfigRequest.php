<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Enums\CentralPermission;
use App\Models\Tenant;
use App\Rules\TenantDatabaseName;
use App\Support\PlatformSuperAdmin;
use Illuminate\Foundation\Http\FormRequest;

class UpdateTenantDatabaseConfigRequest extends FormRequest
{
    public function authorize(): bool
    {
        return PlatformSuperAdmin::can($this->user(), CentralPermission::TenantsManage);
    }

    /** An unknown tenant stays a translated 404 (checked before the name rules run). */
    protected function prepareForValidation(): void
    {
        $tenantId = $this->route('id');

        abort_unless(is_string($tenantId) && Tenant::query()->whereKey($tenantId)->exists(), 404, __('super.tenant_not_found'));
    }

    public function rules(): array
    {
        $tenantId = $this->route('id');

        return [
            // Tenant prefix, never the central DB, unique across tenants (TenantDatabaseName).
            'tenancy_db_name' => ['nullable', 'string', 'max:64', new TenantDatabaseName(is_string($tenantId) ? $tenantId : null)],
            'tenancy_db_username' => ['nullable', 'string', 'max:100'],
            'tenancy_db_password' => ['nullable', 'string', 'max:255'],
        ];
    }
}
