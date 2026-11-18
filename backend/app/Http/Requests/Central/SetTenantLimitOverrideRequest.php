<?php

declare(strict_types=1);

namespace App\Http\Requests\Central;

use App\Enums\CentralPermission;
use App\Enums\LimitResource;
use App\Models\TenantLimitOverride;
use App\Support\PlatformSuperAdmin;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * PUT /api/v1/super-admin/tenants/{id}/limit-overrides/{resource} (PLAN-12).
 * `value` must be present: an explicit null means unlimited for this shop.
 */
final class SetTenantLimitOverrideRequest extends FormRequest
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
        return [
            'resource' => ['required', 'string', Rule::in(LimitResource::values())],
            'value' => ['present', 'nullable', 'integer', 'min:0', 'max:'.TenantLimitOverride::VALUE_MAX],
            'reason' => ['required', 'string', 'min:3', 'max:'.TenantLimitOverride::REASON_MAX],
            'expires_at' => ['nullable', 'date', 'after:now', 'before:'.now()->addYears(10)->toDateTimeString()],
        ];
    }
}
