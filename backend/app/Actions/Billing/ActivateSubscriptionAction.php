<?php

declare(strict_types=1);

namespace App\Actions\Billing;

use App\Actions\Tenants\TransitionTenantStatusAction;
use App\Enums\Billing\BillingCycle;
use App\Enums\Billing\SubscriptionStatus;
use App\Enums\CentralAuditEvent;
use App\Enums\TenantLifecycleActor;
use App\Enums\TenantStatus;
use App\Exceptions\Billing\BillingException;
use App\Models\BillingInvoice;
use App\Models\BillingPayment;
use App\Models\CentralUser;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Services\Billing\FounderPricingService;
use App\Services\CentralAuditLogger;
use App\Services\Entitlements\TenantEntitlementService;
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
 *    period extended by one month / one year from max(now, current paid end);
 *  - tenant: plan_id and subscription_ends_at follow, and the lifecycle status moves to `active`
 *    ONLY through TransitionTenantStatusAction (actor Billing) when it is not already active.
 *    A `violation` suspension is never lifted by a payment: the period is extended and the
 *    operator decides (the audit row says so). An archived or purged tenant refuses (409);
 *  - founder price window: FounderPricingService::claim (self-checking, first payment only);
 *  - entitlement cache: bumped by the model observers and once more here after commit;
 *  - audit: SubscriptionActivated.
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

        $plan = $this->planOf($invoice);
        $cycle = $invoice->billing_cycle;

        if (! $plan instanceof Plan || ! $cycle instanceof BillingCycle || ! $cycle->isSellable()) {
            throw BillingException::noPlan();
        }

        $now = CarbonImmutable::now();
        $currentEnd = $tenant->subscription_ends_at;
        $base = $currentEnd !== null && $currentEnd->isAfter($now) ? CarbonImmutable::instance($currentEnd) : $now;
        $newEnd = $cycle === BillingCycle::Yearly ? $base->addYearNoOverflow() : $base->addMonthNoOverflow();

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
            'amount' => $this->planAmount($invoice),
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

        $this->founder->claim($subscription, $payment);

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
                'status_kept_for_violation' => $statusKept,
            ],
            $operator,
            $subscription,
            $tenantId,
        );

        return $subscription;
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
