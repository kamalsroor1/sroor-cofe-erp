<?php

declare(strict_types=1);

namespace App\Http\Requests\Billing;

use App\DTOs\Billing\BillingChangePreviewDTO;
use App\Enums\Billing\BillingCycle;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * GET /api/v1/billing/change-preview?action=upgrade|subscribe|renew&plan_id=&cycle=monthly|yearly&renew=0|1,
 * tenant permission billing.manage. Only these four query keys are read (anything else is ignored).
 *
 * Shape only: whether the plan exists, is active/public, paid and higher than the current one is
 * answered by PreviewBillingChangeAction against the CENTRAL database (an `exists` rule here would
 * run on the tenant connection) with the same 422 codes as the plan change request.
 */
final class BillingChangePreviewRequest extends FormRequest
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
            'action' => ['required', 'string', Rule::in(BillingChangePreviewDTO::ACTIONS)],
            'plan_id' => ['exclude_if:action,'.BillingChangePreviewDTO::ACTION_RENEW, 'required', 'integer', 'min:1'],
            'cycle' => ['required', 'string', Rule::in(array_map(static fn (BillingCycle $cycle): string => $cycle->value, BillingCycle::sellable()))],
            'renew' => ['exclude_if:action,'.BillingChangePreviewDTO::ACTION_RENEW, 'sometimes', 'boolean'],
        ];
    }
}
