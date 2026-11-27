<?php

declare(strict_types=1);

namespace App\Http\Resources\Billing;

use App\Models\Plan;
use App\Models\PlanChangeRequest;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A plan-change / add-on request, for the shop (own requests) and the console queue.
 *
 * Plan, add-on and invoice summaries appear when the relation is loaded; `tenant`,
 * `requested_by_name` context and `reviewed_by` appear only in the console (relation loaded).
 *
 * @mixin PlanChangeRequest
 */
final class PlanChangeRequestResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => (int) $this->id,
            'kind' => ['value' => $this->kind->value, 'label' => $this->kind->label()],
            'status' => ['value' => $this->status->value, 'label' => $this->status->label()],
            'billing_cycle' => ['value' => $this->billing_cycle->value, 'label' => $this->billing_cycle->label()],
            'tenant' => $this->whenLoaded('tenant', fn () => $this->tenant === null ? null : [
                'id' => (string) $this->tenant->getKey(),
                'name' => $this->tenant->name,
                // #232: false (trial / expired) -> the console issues a full plan invoice, not a difference.
                'has_paid_term' => $this->tenant->hasPaidTermRunning(),
            ]),
            'current_plan' => $this->whenLoaded('currentPlan', fn () => $this->planSummary($this->currentPlan)),
            'requested_plan' => $this->whenLoaded('requestedPlan', fn () => $this->planSummary($this->requestedPlan)),
            // PLAN-10 review: a downgrade to a permanent free plan (status `scheduled`, never invoiced).
            'downgrade_to_free' => $this->whenLoaded('requestedPlan', fn (): bool => $this->requestedPlan !== null && $this->requestedPlan->is_free),
            'addon' => $this->whenLoaded('addon', fn () => $this->addon === null ? null : [
                'key' => $this->addon->key,
                'name' => (string) __($this->addon->name_key),
            ]),
            'quantity' => (int) $this->quantity,
            // #232: the shop asked for upgrade + renewal (the console's default for `with_renewal`).
            'renew' => (bool) $this->renew,
            'notes' => $this->notes,
            'requested_by_name' => $this->requested_by_name,
            'invoice' => $this->whenLoaded('invoice', fn () => $this->invoice === null ? null : [
                'id' => (int) $this->invoice->getKey(),
                'number' => $this->invoice->number,
                'total' => $this->invoice->total,
                'status' => ['value' => $this->invoice->status->value, 'label' => $this->invoice->status->label()],
            ]),
            'rejection_reason' => $this->rejection_reason,
            'reviewed_by' => $this->whenLoaded('reviewer', fn () => $this->reviewer === null ? null : [
                'id' => (int) $this->reviewer->getKey(),
                'name' => $this->reviewer->name,
            ]),
            'reviewed_at' => $this->reviewed_at?->toIso8601String(),
            // #232 spec C: held for the owner's founder-price review (issue again with release_founder_hold).
            'founder_review_held_at' => $this->founder_review_held_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }

    /**
     * @return array{id: int, name: string, code: string}|null
     */
    private function planSummary(?Plan $plan): ?array
    {
        return $plan === null ? null : ['id' => (int) $plan->getKey(), 'name' => $plan->localizedName(), 'code' => $plan->slug];
    }
}
