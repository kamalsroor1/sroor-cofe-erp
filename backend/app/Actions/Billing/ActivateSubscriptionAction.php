<?php

declare(strict_types=1);

namespace App\Actions\Billing;

use App\Actions\Tenants\TransitionTenantStatusAction;
use App\Enums\Billing\BillingCycle;
use App\Enums\Billing\BillingInvoiceType;
use App\Enums\Billing\SubscriptionAddonStatus;
use App\Enums\Billing\SubscriptionStatus;
use App\Enums\CentralAuditEvent;
use App\Enums\TenantLifecycleActor;
use App\Enums\TenantStatus;
use App\Exceptions\Billing\BillingException;
use App\Models\Addon;
use App\Models\BillingInvoice;
use App\Models\BillingPayment;
use App\Models\CentralUser;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\SubscriptionAddon;
use App\Models\Tenant;
use App\Services\Billing\FounderPricingService;
use App\Services\CentralAuditLogger;
use App\Services\Entitlements\TenantEntitlementService;
use App\Support\Money\Decimal;
use App\Support\Tenancy\TenantStatusTransition;
use App\Support\Tenancy\TenantSuspensionReason;
use Carbon\CarbonImmutable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use LogicException;

/**
 * PRD-03 / ENTI-3.4: apply a PAID plan / renewal / upgrade invoice to the tenant's subscription.
 *
 * Called only by VerifyManualPaymentAction, inside its central transaction (it refuses to run
 * outside one). Lock order after payment and invoice: tenant row, then the subscription row.
 *
 *  - subscription: plan + cycle from the invoice, status active, price = the billed plan line,
 *    period extended by one month / one year from max(now, current paid end, invoice period
 *    start) - the last one is the trial end for the SELL-06 trial-end invoice;
 *  - tenant: plan_id and subscription_ends_at follow, and the lifecycle status moves to `active`
 *    ONLY through TransitionTenantStatusAction (actor Billing) when it is not already active.
 *    A `violation` suspension is never lifted by a payment: the period is extended and the
 *    operator decides (the audit row says so). An archived or purged tenant refuses (409);
 *  - founder price window: FounderPricingService::claim (self-checking, first payment only);
 *  - entitlement cache: bumped by the model observers and once more here after commit;
 *  - audit: SubscriptionActivated.
 *
 * Two support-driven shapes (owner decision: no proration, separate invoices):
 *  - a DIFFERENCE upgrade (type upgrade, plan line flagged `difference`): the plan switches at
 *    once and the paid term is KEPT (ends_at untouched) while it is still in the future; with no
 *    future end it behaves like a normal plan invoice (extends by the cycle);
 *  - an ADD-ON invoice (type addon): creates or tops up the `subscription_addons` line (active,
 *    aligned to the subscription end, or one cycle from now when there is none) so the add-on
 *    counts in TenantEntitlementService. The plan, the term and the tenant status are untouched.
 *
 * Idempotency lives in the caller (a payment is verified once); the invoice can only settle once.
 */
final class ActivateSubscriptionAction
{
    public function __construct(
        private readonly TransitionTenantStatusAction $transition,
        private readonly FounderPricingService $founder,
        private readonly TenantEntitlementService $entitlements,
        private readonly CentralAuditLogger $audit,
    ) {}

    public function execute(BillingInvoice $invoice, BillingPayment $payment, ?CentralUser $operator = null): Subscription
    {
        $connection = DB::connection((string) (new Subscription)->getConnectionName());

        if ($connection->transactionLevel() < 1) {
            throw new LogicException('ActivateSubscriptionAction must run inside a central transaction.');
        }

        $tenant = Tenant::query()->whereKey($invoice->tenant_id)->lockForUpdate()->firstOrFail();

        if ($tenant->isPurged() || $tenant->status === TenantStatus::Archived->value) {
            throw BillingException::tenantNotActivatable();
        }

        if ($invoice->type === BillingInvoiceType::Addon) {
            return $this->activateAddons($invoice, $payment, $tenant, $operator);
        }

        $plan = $this->planOf($invoice);
        $cycle = $invoice->billing_cycle;

        if (! $plan instanceof Plan || ! $cycle instanceof BillingCycle || ! $cycle->isSellable()) {
            throw BillingException::noPlan();
        }

        $now = CarbonImmutable::now();
        $currentEnd = $tenant->subscription_ends_at;
        $hasFutureEnd = $currentEnd !== null && $currentEnd->isAfter($now);
        $base = $hasFutureEnd ? CarbonImmutable::instance($currentEnd) : $now;
        // SELL-06: an invoice billed for a period that has not started yet (the trial-end invoice,
        // period_start = trial end) starts the paid term at that period, so paying early never
        // eats the rest of the trial; the shop is `active` (full access) in between.
        $periodStart = $invoice->period_start;
        if ($periodStart !== null && $periodStart->isAfter($base)) {
            $base = CarbonImmutable::instance($periodStart);
        }
        $difference = $this->isDifference($invoice);
        $keepsTerm = $difference && $hasFutureEnd;
        $newEnd = $keepsTerm
            ? $base
            : ($cycle === BillingCycle::Yearly ? $base->addYearNoOverflow() : $base->addMonthNoOverflow());

        $subscription = Subscription::query()
            ->where('tenant_id', $tenant->getKey())
            ->orderByDesc('id')
            ->lockForUpdate()
            ->first();

        $wasPaying = $subscription instanceof Subscription && $subscription->status === SubscriptionStatus::Active;
        $subscription ??= new Subscription(['tenant_id' => (string) $tenant->getKey()]);

        $subscription->fill([
            'plan_id' => $plan->getKey(),
            'billing_cycle' => $cycle,
            'status' => SubscriptionStatus::Active,
            // A difference invoice bills only the gap: the subscription price is the plan's catalog price.
            'amount' => $difference ? Decimal::normalize($cycle === BillingCycle::Yearly ? $plan->price_yearly : $plan->price_monthly) : $this->planAmount($invoice),
            'currency' => $invoice->currency,
            'starts_at' => $wasPaying ? $subscription->starts_at : $now,
            'ends_at' => $newEnd,
            'cancelled_at' => null,
            'payment_method' => 'manual',
        ]);
        $subscription->save();

        // Keeps the invoice pointing at the subscription it activated (older rows may have none).
        if ($invoice->subscription_id === null) {
            $invoice->subscription_id = $subscription->getKey();
            $invoice->save();
        }

        $tenant->plan_id = (int) $plan->getKey();
        $tenant->subscription_ends_at = Carbon::instance($newEnd);
        $tenant->save();

        if (! $keepsTerm) {
            $this->carryAddonsForward($subscription, $currentEnd, $now, $newEnd);
        }

        $statusKept = $this->keepsStatus($tenant);

        if (! $statusKept && $tenant->status !== TenantStatus::Active->value) {
            $this->transition->execute(TenantStatusTransition::to(
                (string) $tenant->getKey(),
                TenantStatus::Active,
                TenantLifecycleActor::Billing,
                context: [
                    'billing_invoice_id' => (int) $invoice->getKey(),
                    'billing_payment_id' => (int) $payment->getKey(),
                ],
                causer: $operator,
            ));
        }

        if (! $difference) {
            $this->founder->claim($subscription, $payment);
        }

        $tenantId = (string) $tenant->getKey();
        $connection->afterCommit(fn () => $this->entitlements->forget($tenantId));

        $this->audit->record(
            CentralAuditEvent::SubscriptionActivated,
            [
                'invoice_id' => (int) $invoice->getKey(),
                'invoice_number' => $invoice->number,
                'payment_id' => (int) $payment->getKey(),
                'plan_id' => (int) $plan->getKey(),
                'billing_cycle' => $cycle->value,
                'period_end' => $newEnd->toIso8601String(),
                'kind' => $difference ? 'upgrade_difference' : 'plan',
                'term_kept' => $keepsTerm,
                'status_kept_for_violation' => $statusKept,
            ],
            $operator,
            $subscription,
            $tenantId,
        );

        return $subscription;
    }

    /**
     * @param  Tenant  $tenant  the tenant row, already locked by execute()
     */
    private function activateAddons(BillingInvoice $invoice, BillingPayment $payment, Tenant $tenant, ?CentralUser $operator): Subscription
    {
        $cycle = $invoice->billing_cycle;

        if (! $cycle instanceof BillingCycle || ! $cycle->isSellable()) {
            throw BillingException::noPlan();
        }

        $subscription = Subscription::query()
            ->where('tenant_id', $tenant->getKey())
            ->orderByDesc('id')
            ->lockForUpdate()
            ->first();

        if (! $subscription instanceof Subscription) {
            throw BillingException::addonNeedsSubscription();
        }

        $now = CarbonImmutable::now();
        $end = $tenant->subscription_ends_at;
        $endsAt = $end !== null && $end->isAfter($now)
            ? CarbonImmutable::instance($end)
            : ($cycle === BillingCycle::Yearly ? $now->addYearNoOverflow() : $now->addMonthNoOverflow());

        $applied = [];

        foreach ($invoice->lines as $row) {
            if (($row['type'] ?? null) !== 'addon') {
                continue;
            }

            $addon = Addon::query()->find((int) ($row['addon_id'] ?? 0));
            if (! $addon instanceof Addon) {
                throw BillingException::requestAddonUnavailable();
            }

            $quantity = max(1, (int) ($row['quantity'] ?? 1));

            $line = SubscriptionAddon::query()
                ->where('subscription_id', $subscription->getKey())
                ->where('addon_id', $addon->getKey())
                ->where('status', SubscriptionAddonStatus::Active->value)
                ->lockForUpdate()
                ->first();

            // Top-up keeps the line's existing unit_price (earlier units are not re-priced); the price
            // of THIS purchase is on its invoice line. Only a new line takes the invoice unit_price.
            if ($line instanceof SubscriptionAddon) {
                $line->quantity += $quantity;
            } else {
                $line = new SubscriptionAddon([
                    'subscription_id' => $subscription->getKey(),
                    'addon_id' => $addon->getKey(),
                    'quantity' => $quantity,
                    'unit_price' => Decimal::normalize((string) ($row['unit_price'] ?? '0')),
                    'billing_cycle' => $cycle,
                ]);
            }

            $line->fill([
                'status' => SubscriptionAddonStatus::Active,
                'starts_at' => $line->starts_at ?? $now,
                'ends_at' => $endsAt,
            ]);
            $line->save();

            $applied[] = ['addon_id' => (int) $addon->getKey(), 'key' => $addon->key, 'quantity' => (int) $line->quantity];
        }

        if ($applied === []) {
            throw BillingException::noPlan();
        }

        if ($invoice->subscription_id === null) {
            $invoice->subscription_id = $subscription->getKey();
            $invoice->save();
        }

        $tenantId = (string) $tenant->getKey();
        DB::connection((string) (new Subscription)->getConnectionName())->afterCommit(fn () => $this->entitlements->forget($tenantId));

        $this->audit->record(
            CentralAuditEvent::SubscriptionActivated,
            [
                'invoice_id' => (int) $invoice->getKey(),
                'invoice_number' => $invoice->number,
                'payment_id' => (int) $payment->getKey(),
                'kind' => 'addon',
                'billing_cycle' => $cycle->value,
                'addons' => $applied,
                'ends_at' => $endsAt->toIso8601String(),
            ],
            $operator,
            $subscription,
            $tenantId,
        );

        return $subscription;
    }

    /**
     * A paid add-on must not silently expire on renewal day: every ACTIVE line still running, or
     * ending exactly at the old subscription end, moves to the new end.
     * NOTE (pending owner decision): renewal invoices do not charge for add-ons yet; add-ons are
     * billed as separate invoices, so this only keeps what was already paid for alive.
     */
    private function carryAddonsForward(Subscription $subscription, ?\DateTimeInterface $oldEnd, CarbonImmutable $now, CarbonImmutable $newEnd): void
    {
        $lines = SubscriptionAddon::query()
            ->where('subscription_id', $subscription->getKey())
            ->where('status', SubscriptionAddonStatus::Active->value)
            ->where(function ($q) use ($oldEnd, $now): void {
                $q->where('ends_at', '>=', $now);
                if ($oldEnd !== null) {
                    $q->orWhere('ends_at', $oldEnd);
                }
            })
            ->lockForUpdate()
            ->get();

        foreach ($lines as $line) {
            if ($line->ends_at === null || $line->ends_at->lt($newEnd)) {
                $line->ends_at = Carbon::instance($newEnd);
                $line->save();
            }
        }
    }

    /** True for the single "difference" plan line a support-decided upgrade carries. */
    private function isDifference(BillingInvoice $invoice): bool
    {
        if ($invoice->type !== BillingInvoiceType::Upgrade) {
            return false;
        }

        foreach ($invoice->lines as $line) {
            if (($line['type'] ?? null) === 'plan' && ($line['difference'] ?? false) === true) {
                return true;
            }
        }

        return false;
    }

    /** A payment does not lift a suspension imposed for a violation: the operator decides. */
    private function keepsStatus(Tenant $tenant): bool
    {
        return $tenant->status === TenantStatus::Suspended->value
            && $tenant->suspension_reason === TenantSuspensionReason::Violation;
    }

    private function planOf(BillingInvoice $invoice): ?Plan
    {
        foreach ($invoice->lines as $line) {
            if (($line['type'] ?? null) === 'plan' && isset($line['plan_id'])) {
                return Plan::query()->find((int) $line['plan_id']);
            }
        }

        return null;
    }

    private function planAmount(BillingInvoice $invoice): string
    {
        foreach ($invoice->lines as $line) {
            if (($line['type'] ?? null) === 'plan') {
                return bcadd((string) ($line['amount'] ?? '0'), '0', 3);
            }
        }

        return '0.000';
    }
}
