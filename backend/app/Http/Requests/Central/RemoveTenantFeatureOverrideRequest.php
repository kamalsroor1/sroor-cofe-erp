<?php

declare(strict_types=1);

namespace App\Http\Requests\Central;

use App\Enums\CentralPermission;
use App\Support\PlatformSuperAdmin;
use Illuminate\Foundation\Http\FormRequest;

/**
 * DELETE /api/v1/super-admin/tenants/{id}/feature-overrides/{feature} (PLAN-12).
 */
final class RemoveTenantFeatureOverrideRequest extends FormRequest
{
    public function authorize(): bool
    {
        return PlatformSuperAdmin::can($this->user(), CentralPermission::TenantsManage);
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['feature' => SetTenantFeatureOverrideRequest::canonical((string) $this->route('feature'))]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return ['feature' => ['required', 'string', 'max:100', SetTenantFeatureOverrideRequest::catalogRule()]];
    }
}
