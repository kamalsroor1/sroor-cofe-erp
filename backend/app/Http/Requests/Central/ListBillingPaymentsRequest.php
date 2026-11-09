<?php

declare(strict_types=1);

namespace App\Http\Requests\Central;

use App\Enums\Billing\BillingPaymentStatus;
use App\Enums\CentralPermission;
use App\Support\PlatformSuperAdmin;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * GET /api/v1/super-admin/billing/payments: ?status=pending|verified|rejected&tenant_id=&per_page=.
 * super_admin.billing.view.
 */
final class ListBillingPaymentsRequest extends FormRequest
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
            'status' => ['sometimes', 'string', Rule::in([
                BillingPaymentStatus::Pending->value,
                BillingPaymentStatus::Verified->value,
                BillingPaymentStatus::Rejected->value,
            ])],
            'tenant_id' => ['sometimes', 'string', 'max:64'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ];
    }
}
