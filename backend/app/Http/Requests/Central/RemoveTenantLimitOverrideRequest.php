<?php

declare(strict_types=1);

namespace App\Http\Requests\Central;

use App\Enums\CentralPermission;
use App\Enums\LimitResource;
use App\Support\PlatformSuperAdmin;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * DELETE /api/v1/super-admin/tenants/{id}/limit-overrides/{resource} (PLAN-12).
 */
final class RemoveTenantLimitOverrideRequest extends FormRequest
{
    public function authorize(): bool
    {
        return PlatformSuperAdmin::can($this->user(), CentralPermission::TenantsManage);
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['resource' => (string) $this->route('resource')]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return ['resource' => ['required', 'string', Rule::in(LimitResource::values())]];
    }
}
