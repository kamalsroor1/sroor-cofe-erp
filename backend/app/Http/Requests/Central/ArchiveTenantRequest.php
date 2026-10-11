<?php

declare(strict_types=1);

namespace App\Http\Requests\Central;

use App\Enums\CentralPermission;
use App\Enums\TenantStatus;
use App\Models\Tenant;
use App\Models\TenantLifecycleEvent;
use App\Support\PlatformSuperAdmin;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * DELETE /api/v1/super-admin/tenants/{id} (OPS-9): request the asynchronous archive.
 *
 *  - confirm_code     required, the tenant's shop code (slug) typed exactly (CTO: typed confirmation);
 *  - expected_status  required, suspended | cancelled: the status the operator saw (409 on a stale screen);
 *  - note             optional, max 500.
 *
 * An unknown tenant is left to the controller (translated 404).
 */
final class ArchiveTenantRequest extends FormRequest
{
    public function authorize(): bool
    {
        return PlatformSuperAdmin::can($this->user(), CentralPermission::TenantsManage);
    }

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'confirm_code' => ['required', 'string', 'max:100'],
            'expected_status' => ['required', 'string', Rule::in([TenantStatus::Suspended->value, TenantStatus::Cancelled->value])],
            'note' => ['nullable', 'string', 'max:'.TenantLifecycleEvent::NOTE_MAX],
        ];
    }

    /**
     * @return list<Closure(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->has('confirm_code')) {
                    return;
                }

                $tenant = Tenant::query()->find((string) $this->route('id'));
                if ($tenant instanceof Tenant && $this->input('confirm_code') !== (string) $tenant->slug) {
                    $validator->errors()->add('confirm_code', (string) __('subscription.archive_errors.confirm_code_mismatch'));
                }
            },
        ];
    }
}
