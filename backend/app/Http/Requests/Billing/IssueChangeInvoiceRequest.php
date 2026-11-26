<?php

declare(strict_types=1);

namespace App\Http\Requests\Billing;

use App\DTOs\Billing\SelfServiceChangeInvoiceDTO;
use App\Enums\Billing\BillingCycle;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * POST /api/v1/billing/change-invoice {action: upgrade|subscribe, plan_id, cycle, renew},
 * tenant permission billing.manage (#232 UI §1, "issue the invoice and pay").
 *
 * Shape only: whether the plan exists, is active/public, paid and higher than the current one is
 * answered by IssueSelfServiceChangeInvoiceAction against the CENTRAL database (an `exists` rule here
 * would run on the tenant connection), with the same codes as /billing/change-preview.
 */
final class IssueChangeInvoiceRequest extends FormRequest
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
            'action' => ['required', 'string', Rule::in(SelfServiceChangeInvoiceDTO::ACTIONS)],
            'plan_id' => ['required', 'integer', 'min:1'],
            'cycle' => ['required', 'string', Rule::in(array_map(static fn (BillingCycle $cycle): string => $cycle->value, BillingCycle::sellable()))],
            'renew' => ['sometimes', 'boolean'],
        ];
    }
}
