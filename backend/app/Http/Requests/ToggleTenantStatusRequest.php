<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Enums\CentralPermission;
use App\Enums\TenantStatus;
use App\Support\PlatformSuperAdmin;
use App\Support\Tenancy\TenantSuspensionReason;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * POST /api/v1/super-admin/tenants/{id}/toggle-status (legacy SPA endpoint, routed through
 * the IDEN-3.3 state machine by ToggleTenantStatusAction).
 *
 *  - status: trial | suspended | read_only | cancelled, or `active` (accepted here so the
 *    action can answer the proper 409: only a verified payment activates a tenant);
 *  - extend_days: only with `trial` (extends the trial); must be 0/absent otherwise;
 *  - reason: required for `suspended` (TenantSuspensionReason), optional for `cancelled`;
 *  - note: optional, stored on the lifecycle event and in the audit.
 */
class ToggleTenantStatusRequest extends FormRequest
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
        $isTrial = $this->input('status') === TenantStatus::Trial->value;

        return [
            'status' => ['required', 'string', Rule::in([
                TenantStatus::Active->value,
                TenantStatus::Trial->value,
                TenantStatus::Suspended->value,
                TenantStatus::ReadOnly->value,
                TenantStatus::Cancelled->value,
            ])],
            'extend_days' => ['nullable', 'integer', 'min:0', $isTrial ? 'max:3650' : 'max:0'],
            'reason' => ['nullable', 'required_if:status,'.TenantStatus::Suspended->value, Rule::enum(TenantSuspensionReason::class)],
            'note' => ['nullable', 'string', 'max:500'],
        ];
    }
}
