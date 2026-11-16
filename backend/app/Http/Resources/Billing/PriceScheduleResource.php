<?php

declare(strict_types=1);

namespace App\Http\Resources\Billing;

use App\Enums\Billing\FounderPriceStep;
use App\Models\Plan;
use App\Models\Subscription;
use App\Services\Billing\FounderPricingService;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * PRICE-2026-11: where a subscription stands on the founder price schedule (shop billing
 * screen and the console tenant summary). Every value comes from FounderPricingService.
 *
 * `current_price` / `next_price` are the plan-line price of ONE cycle of the subscription's own
 * plan and cycle, excluding VAT and add-ons. `notice_due` is true once the next step is at most
 * billing.founder.notice_days (30) days away, so the screen can warn about the price change.
 * Resolves to null for a subscription without a plan or on an unsold cycle (biennial).
 *
 * @property Subscription $resource
 */
final class PriceScheduleResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $subscription = $this->resource;
        $plan = $subscription->plan;

        if (! $plan instanceof Plan || ! $subscription->billing_cycle->isSellable()) {
            return [];
        }

        $founder = app(FounderPricingService::class);
        $now = CarbonImmutable::now();
        $cycle = $subscription->billing_cycle;
        // The step of the cycle running now; next_step_at is the start of the first future cycle
        // billed at another price (FounderPricingService::nextStepChange, same term chain).
        $step = $founder->currentStep($subscription, $now);
        $next = $founder->nextStepChange($subscription, $now);
        $nextAt = $next === null ? null : CarbonImmutable::instance($next['at']);

        return [
            'step' => ['value' => $step->value, 'label' => $step->label()],
            'billing_cycle' => $cycle->value,
            'current_price' => $founder->stepPrice($plan, $cycle, $step),
            'list_price' => $founder->stepPrice($plan, $cycle, FounderPriceStep::List),
            'founder_price_until' => $subscription->is_founder ? $subscription->founder_price_until?->toIso8601String() : null,
            'transition_until' => $founder->transitionUntil($subscription)?->toIso8601String(),
            'next_step' => $next === null ? null : ['value' => $next['step']->value, 'label' => $next['step']->label()],
            'next_step_at' => $nextAt?->toIso8601String(),
            'next_price' => $next === null ? null : $founder->stepPrice($plan, $cycle, $next['step']),
            'notice_due' => $nextAt !== null && $nextAt->lessThanOrEqualTo($now->addDays($founder->noticeDays())),
        ];
    }

    /**
     * The schedule of $subscription, or null when it has none to show.
     *
     * @return array<string, mixed>|null
     */
    public static function for(?Subscription $subscription): ?array
    {
        if (! $subscription instanceof Subscription) {
            return null;
        }

        $data = (new self($subscription))->resolve(new Request);

        return $data === [] ? null : $data;
    }
}
