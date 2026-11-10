<?php

declare(strict_types=1);

namespace App\Http\Requests\Central;

use App\Enums\CentralPermission;
use App\Support\PlatformSuperAdmin;
use Illuminate\Foundation\Http\FormRequest;

/**
 * POST /api/v1/super-admin/billing/payments/{payment}/reject: the reason is required (3-500 chars)
 * because the shop reads it. super_admin.billing.manage.
 */
final class RejectBillingPaymentRequest extends FormRequest
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
            'reason' => ['required', 'string', 'min:3', 'max:500', 'not_regex:/[<>]/'],
        ];
    }
}
