<?php

declare(strict_types=1);

namespace App\Http\Requests\Central;

use App\Enums\CentralPermission;
use App\Support\PlatformSuperAdmin;
use Illuminate\Foundation\Http\FormRequest;

/**
 * POST /api/v1/super-admin/billing/payments/{payment}/verify, super_admin.billing.manage + step-up.
 *
 * #232 spec A: `transferred_at` = the ACTUAL transfer date-time (ISO 8601, an offset is honoured and
 * converted to the platform clock). Never in the future. "Required for manual methods" and "not
 * before the invoice's issue day (start of that day)" need the locked payment / invoice, so
 * VerifyManualPaymentAction enforces them (422 on the same `transferred_at` key).
 */
final class VerifyBillingPaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return PlatformSuperAdmin::can($this->user(), CentralPermission::BillingManage);
    }

    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'transferred_at' => ['sometimes', 'nullable', 'date', 'before_or_equal:now'],
        ];
    }
}
