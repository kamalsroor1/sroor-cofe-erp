<?php

declare(strict_types=1);

namespace App\Http\Requests\Central;

use App\Enums\CentralPermission;
use App\Support\PlatformSuperAdmin;
use Illuminate\Foundation\Http\FormRequest;

/**
 * SELL-01: POST /api/v1/super-admin/tenants/{id}/reset-owner-password. No body: the temporary
 * password is generated on the server. super_admin.tenants.manage (the read-only `support` role
 * does not hold it); the route also demands a recent second factor (RequireRecentTwoFactor).
 */
final class ResetTenantOwnerPasswordRequest extends FormRequest
{
    public function authorize(): bool
    {
        return PlatformSuperAdmin::can($this->user(), CentralPermission::TenantsManage);
    }

    /**
     * @return array<string, never>
     */
    public function rules(): array
    {
        return [];
    }
}
