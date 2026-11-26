<?php

declare(strict_types=1);

namespace App\Actions\Billing;

use App\DTOs\Billing\BillingChangePreview;
use App\DTOs\Billing\BillingChangePreviewDTO;
use App\Enums\Billing\BillingCycle;
use App\Enums\Billing\BillingInvoiceStatus;
use App\Enums\Billing\BillingInvoiceType;
use App\Enums\TenantStatus;
use App\Exceptions\Billing\BillingException;
use App\Models\BillingInvoice;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Services\Billing\FounderPricingService;
use App\Services\Billing\SubscriptionInvoicePricer;
use App\Support\Money\Decimal;
use App\Support\Tenancy\TenantLifecyclePolicy;
use Carbon\CarbonImmutable;

/**
 * #232 UI spec 1.3: the price of a plan change / renewal BEFORE the shop creates anything (tenant
 * context, read-only: no row lock, no write, so a GET never blocks a real issue).
 *
 * Every amount comes from SubscriptionInvoicePricer, the code the invoice is issued with, along the
 * same branch the issuing Action takes, so the preview equals the invoice that would be issued at
 * the same moment:
 *  - renew   (RequestRenewalInvoiceAction): the current plan at `cycle`, founder step at the period
 *    start like any renewal, plus the add-ons the renewal charges for; appended after a running paid
 *    end, else starting at the payment;
 *  - upgrade with a running paid term (IssuePlanChangeInvoiceAction): the pro-rata difference
 *    (ProrationCalculator) for the rest of the term, end kept; with `renew`, plus one full cycle of the
 *    new plan at the LIST price appended after the current end;
 *  - upgrade / subscribe with no paid term (trial / expired): one full cycle of the plan at the LIST
 *    price, the term starting at the verified payment (spec A). The tenant's OWN paid plan is
 *    accepted here (a trial subscribing to the plan it tries); with a running term it is 422
 *    request_same_plan (use renew). Remaining trial days are NOT added: open
 *    owner decision (أ), reported as `trial_days_policy = pending` during a trial.
 * A founder with an agreed price (FounderPricingService::hasAgreedFounderPrice) is held for a manual
 * price review on the upgrade path (spec C): `held_for_price_review` true, no lines, no total.
 *
 * The upgrade difference shown is an ESTIMATE as of now: it is final at Verify, re-priced from the
 * verified transfer moment (owner decision 2026-11-26, UpgradeDifferenceRepricer) - `estimate` true.
 * Errors (422, same codes as the issuing flows): request_plan_unavailable, free_plan_not_billable,
 * request_same_plan, upgrade_not_higher, no_plan, renewal_not_for_trial.
 */
final class PreviewBillingChangeAction
{
    public function __construct(
        private readonly SubscriptionInvoicePricer $pricer,
        private readonly FounderPricingService $founder,
        private readonly TenantLifecyclePolicy $policy,
    ) {}

    public function execute(Tenant $tenant, BillingChangePreviewDTO $dto): BillingChangePreview
    {
        $now = CarbonImmutable::now();
        $subscription = Subscription::query()->where('tenant_id', $tenant->getKey())->orderByDesc('id')->first();
        $current = $tenant->plan_id === null ? null : Plan::query()->find($tenant->plan_id);
        $trial = $this->policy->evaluate($tenant->lifecycleSnapshot(), $now)->effectiveStatus === TenantStatus::Trial;
        $termEnd = $tenant->hasPaidTermRunning() && $tenant->subscription_ends_at !== null
            ? CarbonImmutable::instance($tenant->subscription_ends_at)
            : null;

        $quote = $dto->isUpgrade()
            ? $this->upgrade($tenant, $dto, $subscription, $current, $termEnd, $now)
            : $this->renewal($tenant, $dto, $subscription, $current, $termEnd, $now);

        return new BillingChangePreview(
            action: $dto->action,
            kind: $quote['kind'],
            plan: $quote['plan'],
            currentPlan: $current,
            cycle: $dto->cycle,
            lines: $quote['lines'],
            taxRate: $quote['tax_rate'],
            subtotal: $quote['subtotal'],
            tax: $quote['tax'],
            total: $quote['total'],
            periodEnd: $termEnd,
            newPeriodEnd: $quote['new_period_end'],
            startsAtPayment: $termEnd === null,
            estimate: $quote['estimate'],
            heldForPriceReview: $quote['held'],
            trialDaysPolicy: $trial ? BillingChangePreview::TRIAL_DAYS_PENDING : BillingChangePreview::TRIAL_DAYS_NOT_APPLICABLE,
            openInvoice: $this->openInvoice($tenant),
        );
    }

    /**
     * @return array{kind: string, plan: Plan, lines: list<array<string, mixed>>, tax_rate: string|null, subtotal: string|null, tax: string|null, total: string|null, new_period_end: CarbonImmutable|null, estimate: bool, held: bool}
     */
    private function upgrade(Tenant $tenant, BillingChangePreviewDTO $dto, ?Subscription $subscription, ?Plan $current, ?CarbonImmutable $termEnd, CarbonImmutable $now): array
    {
        $target = Plan::query()->whereKey((int) $dto->planId)->where('is_active', true)->where('is_public', true)->first();

        if (! $target instanceof Plan) {
            throw BillingException::requestPlanUnavailable();
        }
        if ($target->is_free) {
            throw BillingException::freePlanNotBillable();
        }
        // #232 item 8: with no paid term running the same (paid) plan is a subscribe, not a no-op.
        if ($current instanceof Plan && (int) $current->getKey() === (int) $target->getKey() && $termEnd !== null) {
            throw BillingException::requestSamePlan();
        }

        if ($this->founder->hasAgreedFounderPrice((string) $tenant->getKey())) {
            return ['kind' => $termEnd === null ? BillingChangePreview::KIND_SUBSCRIBE : ($dto->renew ? BillingChangePreview::KIND_UPGRADE_RENEWAL : BillingChangePreview::KIND_UPGRADE),
                'plan' => $target, 'lines' => [], 'tax_rate' => null, 'subtotal' => null, 'tax' => null, 'total' => null,
                'new_period_end' => null, 'estimate' => false, 'held' => true];
        }

        // No paid term: IssuePlanChangeInvoiceAction answers with a full-cycle plan invoice (list price).
        if ($termEnd === null) {
            $line = $this->pricer->planLine($target, $dto->cycle, $tenant, $subscription, $now->startOfDay(), listPriceOnly: true);

            return $this->priced(BillingChangePreview::KIND_SUBSCRIBE, $target, [
                $this->previewLine('plan', 'subscription.billing.change.line_plan', ['plan' => $target->name, 'cycle' => $dto->cycle->label()], $line),
            ], $this->pricer->periodEnd($now, $dto->cycle), false);
        }

        $difference = $this->pricer->upgradeDifference($tenant, $current, $target);

        if (bccomp($difference['amount'], '0', 3) <= 0) {
            throw BillingException::upgradeNotHigher();
        }

        $lines = [$this->previewLine('proration', 'subscription.billing.change.line_proration', [
            'days' => $difference['remaining_days'],
            'period_days' => $difference['period_days'],
            'new' => $difference['new_price'],
            'old' => $difference['old_price'],
            'plan' => $target->name,
            'from_plan' => $current?->name,
        ], ['amount' => $difference['amount']])];

        if (! $dto->renew) {
            return $this->priced(BillingChangePreview::KIND_UPGRADE, $target, $lines, null, true);
        }

        $renewal = $this->pricer->planLine($target, $dto->cycle, $tenant, $subscription, $termEnd->startOfSecond(), listPriceOnly: true);
        $lines[] = $this->previewLine('renewal', $this->renewalKey($dto->cycle), ['plan' => $target->name], $renewal);

        return $this->priced(BillingChangePreview::KIND_UPGRADE_RENEWAL, $target, $lines, $this->pricer->periodEnd($termEnd->startOfSecond(), $dto->cycle), true);
    }

    /**
     * @return array{kind: string, plan: Plan, lines: list<array<string, mixed>>, tax_rate: string|null, subtotal: string|null, tax: string|null, total: string|null, new_period_end: CarbonImmutable|null, estimate: bool, held: bool}
     */
    private function renewal(Tenant $tenant, BillingChangePreviewDTO $dto, ?Subscription $subscription, ?Plan $current, ?CarbonImmutable $termEnd, CarbonImmutable $now): array
    {
        if (! $current instanceof Plan) {
            throw BillingException::noPlan();
        }

        match ($tenant->renewalRefusal()) {
            'trial' => throw BillingException::renewalNotForTrial(),
            'free_plan' => throw BillingException::freePlanNotBillable(),
            'no_plan' => throw BillingException::noPlan(),
            default => null,
        };

        // Same period start as IssueSubscriptionInvoiceAction::periodStart() for a renewal.
        $periodStart = $termEnd?->startOfSecond() ?? $now->startOfDay();
        $line = $this->pricer->planLine($current, $dto->cycle, $tenant, $subscription, $periodStart);
        $lines = [$this->previewLine('renewal', $this->renewalKey($dto->cycle), ['plan' => $current->name], $line)];

        if ($subscription instanceof Subscription) {
            foreach ($this->pricer->renewalAddonLines($subscription, $tenant, $periodStart, $this->pricer->periodEnd($periodStart, $dto->cycle), $dto->cycle) as $addon) {
                $lines[] = $this->previewLine('addon', 'subscription.billing.change.line_addon', [
                    'addon' => (string) $addon['description'],
                    'months' => (int) $addon['months'],
                    'quantity' => (int) $addon['quantity'],
                ], $addon);
            }
        }

        // A running term is extended from its end; otherwise the new term starts at the payment.
        return $this->priced(BillingChangePreview::KIND_RENEWAL, $current, $lines, $this->pricer->periodEnd($termEnd ?? $now, $dto->cycle), false);
    }

    /**
     * Subtotal and VAT exactly as IssueSubscriptionInvoiceAction (no discount on these paths); a
     * `tax` line is appended only when the VAT is above zero.
     *
     * @param  list<array<string, mixed>>  $lines
     * @return array{kind: string, plan: Plan, lines: list<array<string, mixed>>, tax_rate: string, subtotal: string, tax: string, total: string, new_period_end: CarbonImmutable|null, estimate: bool, held: bool}
     */
    private function priced(string $kind, Plan $plan, array $lines, ?CarbonImmutable $newPeriodEnd, bool $estimate): array
    {
        $subtotal = $this->pricer->subtotal($lines);
        ['rate' => $rate, 'tax' => $tax, 'total' => $total] = $this->pricer->tax($subtotal);

        if (bccomp($tax, '0', 3) > 0) {
            $lines[] = $this->previewLine('tax', 'subscription.billing.change.line_tax', ['rate' => $rate], ['amount' => $tax]);
        }

        return ['kind' => $kind, 'plan' => $plan, 'lines' => $lines, 'tax_rate' => $rate, 'subtotal' => $subtotal, 'tax' => $tax, 'total' => $total,
            'new_period_end' => $newPeriodEnd, 'estimate' => $estimate, 'held' => false];
    }

    /**
     * @param  array<string, int|string|null>  $params
     * @param  array<string, mixed>  $priced  an invoice line (or ['amount' => ...])
     * @return array{kind: string, label_key: string, label: string, params: array<string, int|string|null>, amount: string}
     */
    private function previewLine(string $kind, string $labelKey, array $params, array $priced): array
    {
        return [
            'kind' => $kind,
            'label_key' => $labelKey,
            'label' => (string) __($labelKey, array_map(static fn (int|string|null $value): string => (string) $value, $params)),
            'params' => $params,
            'amount' => Decimal::normalize((string) $priced['amount']),
        ];
    }

    private function renewalKey(BillingCycle $cycle): string
    {
        return $cycle === BillingCycle::Yearly
            ? 'subscription.billing.change.line_renewal_year'
            : 'subscription.billing.change.line_renewal_month';
    }

    /**
     * The unpaid plan / renewal / upgrade invoice that would make issuing a new one a 409 (SELL-06).
     *
     * @return array{id: int, number: string, total: string}|null
     */
    private function openInvoice(Tenant $tenant): ?array
    {
        $open = BillingInvoice::query()
            ->where('tenant_id', $tenant->getKey())
            ->whereIn('type', [BillingInvoiceType::Plan->value, BillingInvoiceType::Renewal->value, BillingInvoiceType::Upgrade->value])
            ->whereIn('status', [BillingInvoiceStatus::Draft->value, BillingInvoiceStatus::Pending->value])
            ->orderBy('id')
            ->first(['id', 'number', 'total']);

        return $open === null ? null : ['id' => (int) $open->getKey(), 'number' => (string) $open->number, 'total' => (string) $open->total];
    }
}
