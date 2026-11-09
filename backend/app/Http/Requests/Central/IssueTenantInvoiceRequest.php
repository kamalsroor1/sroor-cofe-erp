<?php

declare(strict_types=1);

namespace App\Http\Requests\Central;

use App\Enums\Billing\BillingCycle;
use App\Enums\Billing\BillingInvoiceType;
use App\Enums\CentralPermission;
use App\Models\Plan;
use App\Support\PlatformSuperAdmin;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * POST /api/v1/super-admin/tenants/{id}/billing-invoices (PRD-03 / ENTI-3.10),
 * super_admin.billing.manage + step-up.
 *
 * type: plan | renewal | upgrade (plan_id + billing_cycle required) or service (service_lines required).
 * Amounts are DECIMAL(12,3) strings; the add-on type is not issued from here.
 */
final class IssueTenantInvoiceRequest extends FormRequest
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
        $planTypes = [BillingInvoiceType::Plan->value, BillingInvoiceType::Renewal->value, BillingInvoiceType::Upgrade->value];

        return [
            'type' => ['required', 'string', Rule::in([...$planTypes, BillingInvoiceType::Service->value])],
            'plan_id' => ['required_if:type,'.implode(',', $planTypes), 'nullable', 'integer', Rule::exists(Plan::class, 'id')],
            'billing_cycle' => [
                'required_if:type,'.implode(',', $planTypes),
                'nullable',
                'string',
                Rule::in(array_map(static fn (BillingCycle $cycle): string => $cycle->value, BillingCycle::sellable())),
            ],
            'use_founder_price' => ['sometimes', 'boolean'],
            'service_lines' => ['required_if:type,'.BillingInvoiceType::Service->value, 'nullable', 'array', 'max:10'],
            'service_lines.*.description' => ['required', 'string', 'min:2', 'max:255', 'not_regex:/[<>]/'],
            'service_lines.*.amount' => ['required', 'numeric', 'gt:0', self::MONEY],
            'discount' => ['sometimes', 'nullable', 'numeric', 'min:0', self::MONEY],
            'notes' => ['sometimes', 'nullable', 'string', 'max:1000', 'not_regex:/[<>]/'],
            'period_start' => ['sometimes', 'nullable', 'date'],
        ];
    }
}
