<?php

declare(strict_types=1);

namespace App\Actions\Billing;

use App\DTOs\Billing\IssueSubscriptionInvoiceDTO;
use App\DTOs\Billing\TrialInvoiceRunResult;
use App\Enums\Billing\BillingCycle;
use App\Enums\Billing\BillingInvoiceStatus;
use App\Enums\Billing\BillingInvoiceType;
use App\Enums\TenantStatus;
use App\Exceptions\Billing\BillingException;
use App\Models\BillingInvoice;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Support\Database\TransactionRetry;
use App\Support\Money\Decimal;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * SELL-06: issue the first subscription invoice of every trial that ends within
 * config('billing.trial_invoice_lead_days') days (3), so the shop has something to pay before
 * the trial (and its grace days) run out. Run daily by `billing:issue-trial-invoices`.
 *
 * Candidates: stored status `trial` (so suspended / cancelled / archived tenants are never
 * billed), a trial end at most lead days away, not purged. Skipped: no plan, a plan priced 0
 * (monthly price at the subscription's cycle), an invoice already issued for the trial-end
 * period (ANY status: an operator who voided it decided), or another unpaid plan / renewal /
 * upgrade invoice (an operator already billed the shop by hand).
 *
 * Idempotency: the invoice is a `plan` invoice whose period starts at the trial end, so
 * IssueSubscriptionInvoiceAction's own (tenant, type, period_start) check under the tenant row
 * lock refuses a second one even if two runs overlap, and `refuseWhenOpenPlanInvoice` re-checks
 * the open-invoice rule under the same lock (a shop renewal request racing the run); both
 * refusals count as skips here. The checks before the transaction only label the skip early.
 * Each invoice is ONE central transaction through TransactionRetry::run() (billing_sequences
 * meets MariaDB 1020 / 1213 under concurrency). A failure for one tenant is logged and the run
 * goes on with the next.
 */
final class IssueTrialEndInvoicesAction
{
    private const CHUNK = 100;

    public function __construct(private readonly IssueSubscriptionInvoiceAction $issue) {}

    public function execute(bool $dryRun, ?CarbonImmutable $now = null): TrialInvoiceRunResult
    {
        $now ??= CarbonImmutable::now();
        $leadDays = max(0, (int) config('billing.trial_invoice_lead_days', 3));

        $checked = 0;
        $issued = [];
        $skipped = [];
        $failed = [];

        $tenants = Tenant::query()
            ->where('status', TenantStatus::Trial->value)
            ->whereNotNull('trial_ends_at')
            ->where('trial_ends_at', '<=', $now->addDays($leadDays))
            ->whereNull('purge_started_at')
            ->whereNull('purged_at')
            ->lazyById(self::CHUNK);

        foreach ($tenants as $tenant) {
            $checked++;
            $tenantId = (string) $tenant->getKey();
            $trialEndsAt = CarbonImmutable::instance($tenant->trial_ends_at)->startOfSecond();

            $plan = $tenant->plan_id === null ? null : Plan::query()->find($tenant->plan_id);
            if (! $plan instanceof Plan) {
                $skipped[] = ['tenant_id' => $tenantId, 'reason' => 'no_plan'];

                continue;
            }

            $cycle = $this->cycleOf($tenantId);
            $reason = $this->skipReason($tenantId, $plan, $cycle, $trialEndsAt);
            if ($reason !== null) {
                $skipped[] = ['tenant_id' => $tenantId, 'reason' => $reason];

                continue;
            }

            if ($dryRun) {
                $issued[] = ['tenant_id' => $tenantId, 'trial_ends_at' => $trialEndsAt->toIso8601String(), 'number' => null, 'total' => null];

                continue;
            }

            $dto = new IssueSubscriptionInvoiceDTO(
                tenantId: $tenantId,
                type: BillingInvoiceType::Plan,
                planId: (int) $plan->getKey(),
                cycle: $cycle,
                useFounderPrice: false,
                serviceLines: [],
                discount: '0.000',
                notes: null,
                periodStart: $trialEndsAt,
                issuedBy: null,
                refuseWhenOpenPlanInvoice: true,
            );

            try {
                $invoice = TransactionRetry::run(fn (): BillingInvoice => $this->issue->execute($dto));
                $issued[] = ['tenant_id' => $tenantId, 'trial_ends_at' => $trialEndsAt->toIso8601String(), 'number' => $invoice->number, 'total' => $invoice->total];
            } catch (BillingException $e) {
                if ($e->errorCode() === 'billing.invoice_duplicate') {
                    $open = ($e->details()['open_invoice'] ?? false) === true;
                    $skipped[] = ['tenant_id' => $tenantId, 'reason' => $open ? 'open_invoice' : 'already_issued'];

                    continue;
                }

                $failed[] = ['tenant_id' => $tenantId, 'error' => $e->getMessage()];
            } catch (Throwable $e) {
                Log::error('Trial-end subscription invoice failed', ['tenant_id' => $tenantId, 'exception' => $e]);
                $failed[] = ['tenant_id' => $tenantId, 'error' => $e->getMessage()];
            }
        }

        return new TrialInvoiceRunResult($dryRun, $checked, $issued, $skipped, $failed);
    }

    /** The cycle of the tenant's latest subscription when sellable, else monthly. */
    private function cycleOf(string $tenantId): BillingCycle
    {
        $subscription = Subscription::query()->where('tenant_id', $tenantId)->orderByDesc('id')->first();
        $cycle = $subscription?->billing_cycle;

        return $cycle instanceof BillingCycle && $cycle->isSellable() ? $cycle : BillingCycle::Monthly;
    }

    /** A console.trial_invoices.reasons.* key, or null when the invoice should be issued. */
    private function skipReason(string $tenantId, Plan $plan, BillingCycle $cycle, CarbonImmutable $periodStart): ?string
    {
        $price = Decimal::normalize($cycle === BillingCycle::Yearly ? $plan->price_yearly : $plan->price_monthly);
        if (bccomp($price, '0', 3) <= 0) {
            return 'free_plan';
        }

        $alreadyIssued = BillingInvoice::query()
            ->where('tenant_id', $tenantId)
            ->where('type', BillingInvoiceType::Plan->value)
            ->where('period_start', $periodStart)
            ->exists();
        if ($alreadyIssued) {
            return 'already_issued';
        }

        $openInvoice = BillingInvoice::query()
            ->where('tenant_id', $tenantId)
            ->whereIn('type', [BillingInvoiceType::Plan->value, BillingInvoiceType::Renewal->value, BillingInvoiceType::Upgrade->value])
            ->whereIn('status', [BillingInvoiceStatus::Draft->value, BillingInvoiceStatus::Pending->value])
            ->exists();

        return $openInvoice ? 'open_invoice' : null;
    }
}
