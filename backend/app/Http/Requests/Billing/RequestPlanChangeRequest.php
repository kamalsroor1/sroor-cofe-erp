<?php

declare(strict_types=1);

namespace App\Http\Requests\Billing;

use App\Enums\Billing\BillingCycle;
use App\Enums\Billing\PlanChangeRequestKind;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * POST /api/v1/billing/plan-change-requests, tenant permission billing.manage.
 *
 * Shape only: the plan / add-on rules (active, public, different from the current plan, offered on
 * the plan) are answered by RequestPlanChangeAction against the CENTRAL database, because a
 * `exists` rule here would run on the tenant connection.
 */
final class RequestPlanChangeRequest extends FormRequest
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
            'kind' => ['required', 'string', Rule::in(PlanChangeRequestKind::values())],
            'requested_plan_code' => ['required_if:kind,'.PlanChangeRequestKind::PlanChange->value, 'nullable', 'string', 'max:100'],
            'addon_key' => ['required_if:kind,'.PlanChangeRequestKind::Addon->value, 'nullable', 'string', 'max:100'],
            'quantity' => ['sometimes', 'nullable', 'integer', 'min:1', 'max:50'],
            'billing_cycle' => ['required', 'string', Rule::in(array_map(static fn (BillingCycle $cycle): string => $cycle->value, BillingCycle::sellable()))],
            'notes' => ['sometimes', 'nullable', 'string', 'max:500', 'not_regex:/[<>]/'],
        ];
    }
}
