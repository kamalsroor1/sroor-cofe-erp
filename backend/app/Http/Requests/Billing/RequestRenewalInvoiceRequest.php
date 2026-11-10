<?php

declare(strict_types=1);

namespace App\Http\Requests\Billing;

use App\Enums\Billing\BillingCycle;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * POST /api/v1/billing/renewal-invoice, tenant permission billing.manage. Only the cycle is chosen:
 * the plan is always the shop's current one.
 */
final class RequestRenewalInvoiceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->can('billing.manage');
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'cycle' => ['required', 'string', Rule::in([BillingCycle::Monthly->value, BillingCycle::Yearly->value])],
        ];
    }
}
