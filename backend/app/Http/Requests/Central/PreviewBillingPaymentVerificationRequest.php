<?php

declare(strict_types=1);

namespace App\Http\Requests\Central;

use App\Enums\CentralPermission;
use App\Support\PlatformSuperAdmin;
use Illuminate\Foundation\Http\FormRequest;

/**
 * GET /api/v1/super-admin/billing/payments/{payment}/verify-preview?transferred_at=ISO,
 * super_admin.billing.manage (the verify permission; read-only, so no step-up).
 *
 * Only `transferred_at` is read (same shape and rule as VerifyBillingPaymentRequest); the
 * "required for a manual payment" and "not before the invoice day" rules need the payment and the
 * invoice, so the Action answers them (422 on `transferred_at`, exactly like Verify).
 */
final class PreviewBillingPaymentVerificationRequest extends FormRequest
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
