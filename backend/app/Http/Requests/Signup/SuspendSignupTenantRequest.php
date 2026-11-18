<?php

declare(strict_types=1);

namespace App\Http\Requests\Signup;

use App\Enums\CentralPermission;
use App\Support\PlatformSuperAdmin;
use App\Support\Tenancy\TenantSuspensionReason;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * SIGN-12: POST /api/v1/super-admin/signup-requests/{id}/suspend-tenant {reason, note?}. Same
 * rules as a suspension through POST /tenants/{id}/toggle-status.
 */
final class SuspendSignupTenantRequest extends FormRequest
{
    public function authorize(): bool
    {
        return PlatformSuperAdmin::can($this->user(), CentralPermission::TenantsManage);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'reason' => ['required', 'string', Rule::enum(TenantSuspensionReason::class)],
            'note' => ['nullable', 'string', 'max:500'],
        ];
    }
}
