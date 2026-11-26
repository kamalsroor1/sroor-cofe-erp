<?php

declare(strict_types=1);

namespace App\DTOs\Billing;

use App\Enums\Billing\BillingCycle;
use App\Models\Plan;
use App\Models\Subscription;
use Carbon\CarbonImmutable;

/**
 * Result of PreviewBillingChangeAction (#232 UI spec 1.3). Money: DECIMAL(12,3) strings; null
 * totals only when the request is held for a founder price review. Dates are calendar dates on the
 * platform clock (Y-m-d).
 */
final readonly class BillingChangePreview
{
    public const KIND_UPGRADE = 'upgrade';

    public const KIND_UPGRADE_RENEWAL = 'upgrade_renewal';

    public const KIND_RENEWAL = 'renewal';

    public const KIND_SUBSCRIBE = 'subscribe';

    /** Open owner decision (أ): what happens to remaining trial days is not decided yet. */
    public const TRIAL_DAYS_PENDING = 'pending';

    public const TRIAL_DAYS_NOT_APPLICABLE = 'n/a';

    /**
     * @param  list<array{kind: string, label_key: string, label: string, params: array<string, int|string|null>, amount: string}>  $lines
     * @param  array{id: int, number: string, total: string}|null  $openInvoice
     */
    public function __construct(
        public string $action,
        public string $kind,
        public Plan $plan,
        public ?Plan $currentPlan,
        public BillingCycle $cycle,
        public array $lines,
        public ?string $taxRate,
        public ?string $subtotal,
        public ?string $tax,
        public ?string $total,
        public ?CarbonImmutable $periodEnd,
        public ?CarbonImmutable $newPeriodEnd,
        public bool $startsAtPayment,
        public bool $estimate,
        public bool $heldForPriceReview,
        public string $trialDaysPolicy,
        public ?array $openInvoice,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $timezone = (string) config('app.timezone');

        return [
            'action' => $this->action,
            'kind' => $this->kind,
            'plan' => ['id' => (int) $this->plan->getKey(), 'code' => $this->plan->slug, 'name' => $this->plan->name],
            'current_plan' => $this->currentPlan === null ? null : ['id' => (int) $this->currentPlan->getKey(), 'code' => $this->currentPlan->slug, 'name' => $this->currentPlan->name],
            'cycle' => ['value' => $this->cycle->value, 'label' => $this->cycle->label()],
            'lines' => $this->lines,
            'subtotal' => $this->subtotal,
            'tax_rate' => $this->taxRate,
            'tax' => $this->tax,
            'total' => $this->total,
            'currency' => Subscription::DEFAULT_CURRENCY,
            'period_end' => $this->periodEnd?->setTimezone($timezone)->toDateString(),
            'new_period_end' => $this->newPeriodEnd?->setTimezone($timezone)->toDateString(),
            'starts_at_payment' => $this->startsAtPayment,
            'estimate' => $this->estimate,
            'held_for_price_review' => $this->heldForPriceReview,
            'trial_days_policy' => $this->trialDaysPolicy,
            'open_invoice' => $this->openInvoice,
        ];
    }
}
