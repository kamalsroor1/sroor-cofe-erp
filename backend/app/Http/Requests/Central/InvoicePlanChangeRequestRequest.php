<?php

declare(strict_types=1);

namespace App\Http\Requests\Central;

use App\Enums\CentralPermission;
use App\Support\PlatformSuperAdmin;
use Illuminate\Foundation\Http\FormRequest;

/**
 * POST /api/v1/super-admin/billing/plan-change-requests/{changeRequest}/invoice,
 * super_admin.billing.manage + step-up.
 *
 * plan_change: `amount` (the upgrade DIFFERENCE, DECIMAL string > 0) is optional: omitted = the pro-rata suggestion; `description`
 * is optional (default: translated "difference of upgrade from X to Y").
 * addon: `quantity` (default: the request's) and `unit_price` (default: the plan's catalog price)
 * are optional. `notes` are internal operator notes.
 */
final class InvoicePlanChangeRequestRequest extends FormRequest
{
    private const MONEY = 'regex:/^\d{1,9}(\.\d{1,3})?$/';

    public function authorize(): bool
    {
        return PlatformSuperAdmin::can($this->user(), CentralPermission::BillingManage);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'amount' => [
                'sometimes', 'nullable', 'numeric', 'gt:0', self::MONEY,
            ],
            'description' => ['sometimes', 'nullable', 'string', 'min:2', 'max:255', 'not_regex:/[<>]/'],
            'quantity' => ['sometimes', 'nullable', 'integer', 'min:1', 'max:50'],
            'unit_price' => ['sometimes', 'nullable', 'numeric', 'gt:0', self::MONEY],
            'notes' => ['sometimes', 'nullable', 'string', 'max:1000', 'not_regex:/[<>]/'],
        ];
    }
}
