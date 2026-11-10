<?php

declare(strict_types=1);

namespace App\Http\Requests\Central;

use App\Enums\Billing\PlanChangeRequestStatus;
use App\Enums\CentralPermission;
use App\Support\PlatformSuperAdmin;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * GET /api/v1/super-admin/billing/plan-change-requests: ?status=pending|invoiced|rejected|cancelled
 * &tenant_id=&per_page=. super_admin.billing.view.
 */
final class ListPlanChangeRequestsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return PlatformSuperAdmin::can($this->user(), CentralPermission::BillingView);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'status' => ['sometimes', 'string', Rule::in(PlanChangeRequestStatus::values())],
            'tenant_id' => ['sometimes', 'string', 'max:64'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ];
    }
}
