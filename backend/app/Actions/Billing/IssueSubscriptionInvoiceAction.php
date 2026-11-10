<?php

declare(strict_types=1);

namespace App\Actions\Billing;

use App\DTOs\Billing\IssueSubscriptionInvoiceDTO;
use App\Enums\Billing\BillingCycle;
use App\Enums\Billing\BillingInvoiceStatus;
use App\Enums\Billing\BillingInvoiceType;
use App\Enums\Billing\BillingPaymentStatus;
use App\Enums\CentralAuditEvent;
use App\Exceptions\Billing\BillingException;
use App\Models\BillingInvoice;
use App\Models\BillingPayment;
use App\Models\CentralUser;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Services\Billing\BillingSequenceService;
use App\Services\Billing\FounderPricingService;
use App\Services\Branding\PlatformBranding;
use App\Services\CentralAuditLogger;
use App\Support\Money\Decimal;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * PRD-03 / ENTI-3.2 + ENTI-3.10: issue a SaaS subscription invoice (central DB).
 *
 * Types: plan | renewal | upgrade (a plan line at the chosen cycle) and service (one-off lines).
 * Plan-type invoices may carry extra service lines. Everything is snapshotted into `lines`.
 *
 * Money: bcmath strings at scale 3. VAT = platform setting billing_tax_rate, computed ONCE on the
 * net (subtotal - discount) and rounded half-up with App\Support\Money\Decimal (the SETG-13 rule).
 *
 * One central transaction: the tenant row is locked first, so two issuers for the same tenant
 * are serialised and the duplicate check below cannot race. The gap-free number comes from
 * BillingSequenceService inside the same transaction (a failure leaves no number gap).
 * Idempotent per (tenant, type, period_start) while the earlier invoice is not void: BillingException 409.
 *
 * Runs from the central console and, for RequestRenewalInvoiceAction, from a tenant request
 * (issuedBy null); both use only central-pinned models, so tenancy context is irrelevant.
 */
final class IssueSubscriptionInvoiceAction
{
    public function __construct(
        private readonly BillingSequenceService $sequences,
        private readonly FounderPricingService $founder,
        private readonly PlatformBranding $platform,
        private readonly CentralAuditLogger $audit,
    ) {}

    public function execute(IssueSubscriptionInvoiceDTO $dto, ?CentralUser $actor = null): BillingInvoice
    {
        $connection = DB::connection((string) (new BillingInvoice)->getConnectionName());

        return $connection->transaction(function () use ($dto, $actor): BillingInvoice {
            $tenant = Tenant::query()->whereKey($dto->tenantId)->lockForUpdate()->firstOrFail();
            $subscription = Subscription::query()->where('tenant_id', $tenant->getKey())->orderByDesc('id')->first();

            $plan = $this->planFor($dto);
            $cycle = $plan !== null ? $dto->cycle : null;

            $periodStart = $this->periodStart($dto, $tenant, $subscription);
            $this->assertNotDuplicate($tenant, $dto->type, $periodStart, $this->futureEnd($tenant, $subscription) === null);

            $lines = [];
            if ($plan instanceof Plan && $cycle instanceof BillingCycle) {
                $lines[] = $this->planLine($dto, $plan, $cycle, $tenant, $subscription);
            }
            foreach ($dto->serviceLines as $service) {
                $lines[] = [
                    'type' => 'service',
                    'description' => $service['description'],
                    'quantity' => 1,
                    'unit_price' => $service['amount'],
                    'amount' => $service['amount'],
                ];
            }

            $subtotal = '0.000';
            foreach ($lines as $line) {
                $subtotal = bcadd($subtotal, (string) $line['amount'], 3);
            }

            // A discount can reduce the invoice but never to zero or below: such an invoice is unpayable.
            if (bccomp($dto->discount, '0', 3) < 0 || bccomp($dto->discount, $subtotal, 3) >= 0) {
                throw BillingException::discountTooHigh();
            }

            $net = bcsub($subtotal, $dto->discount, 3);
            $taxRate = $this->platform->get()->billingTaxRate;
            $tax = Decimal::percent($net, $taxRate);
            $total = bcadd($net, $tax, 3);

            $now = CarbonImmutable::now();

            $invoice = BillingInvoice::query()->create([
                'number' => $this->sequences->nextInvoiceNumber($now),
                'tenant_id' => (string) $tenant->getKey(),
                'subscription_id' => $subscription?->getKey(),
                'type' => $dto->type,
                'status' => BillingInvoiceStatus::Pending,
                'billing_cycle' => $cycle,
                'period_start' => $periodStart,
                'period_end' => $this->periodEnd($periodStart, $cycle),
                'lines' => $lines,
                'subtotal' => $subtotal,
                'discount' => $dto->discount,
                'tax_rate' => $taxRate,
                'tax' => $tax,
                'total' => $total,
                'currency' => Subscription::DEFAULT_CURRENCY,
                'issued_at' => $now,
                'due_at' => $now->addDays(max(1, (int) config('billing.invoice_due_days', 7))),
                'issued_by' => $dto->issuedBy,
                'notes' => $dto->notes,
            ]);

            $this->audit->record(
                CentralAuditEvent::BillingInvoiceIssued,
                [
                    'invoice_id' => (int) $invoice->getKey(),
                    'number' => $invoice->number,
                    'type' => $dto->type->value,
                    'billing_cycle' => $cycle?->value,
                    'total' => $total,
                    'tax_rate' => $taxRate,
                    'requested_by_tenant' => $dto->issuedBy === null,
                ],
                $actor,
                $invoice,
                (string) $tenant->getKey(),
            );

            return $invoice;
        });
    }

    private function planFor(IssueSubscriptionInvoiceDTO $dto): ?Plan
    {
        if ($dto->type === BillingInvoiceType::Service) {
            return null;
        }

        if ($dto->planId === null || ! $dto->cycle instanceof BillingCycle || ! $dto->cycle->isSellable()) {
            throw BillingException::noPlan();
        }

        $plan = Plan::query()->find($dto->planId);

        if (! $plan instanceof Plan) {
            throw BillingException::noPlan();
        }

        return $plan;
    }

    /**
     * @return array<string, mixed>
     */
    private function planLine(IssueSubscriptionInvoiceDTO $dto, Plan $plan, BillingCycle $cycle, Tenant $tenant, ?Subscription $subscription): array
    {
        $price = $cycle === BillingCycle::Yearly ? $plan->price_yearly : $plan->price_monthly;
        $founder = false;

        if ($dto->useFounderPrice) {
            $founderPrice = $this->founderPrice($plan, $cycle, $tenant, $subscription);
            if ($founderPrice === null) {
                throw BillingException::founderPriceUnavailable();
            }
            $price = $founderPrice;
            $founder = true;
        }

        $price = Decimal::normalize($price);

        return [
            'type' => 'plan',
            'plan_id' => (int) $plan->getKey(),
            'plan_name' => $plan->name,
            'billing_cycle' => $cycle->value,
            'quantity' => 1,
            'unit_price' => $price,
            'amount' => $price,
            'founder' => $founder,
        ];
    }

    /** The founder price when FounderPricingService allows it for this tenant, else null. */
    private function founderPrice(Plan $plan, BillingCycle $cycle, Tenant $tenant, ?Subscription $subscription): ?string
    {
        $price = $cycle === BillingCycle::Yearly ? $plan->founder_price_yearly : $plan->founder_price_monthly;

        if ($price === null || ! $this->founder->isEligiblePlan($plan, $cycle)) {
            return null;
        }

        // A founder who still holds the window keeps the price on renewals.
        if ($subscription instanceof Subscription
            && $subscription->is_founder
            && $subscription->founder_price_until !== null
            && $subscription->founder_price_until->isFuture()) {
            return $price;
        }

        // Otherwise only a first-ever payment can claim a free slot.
        $paidBefore = BillingPayment::query()
            ->where('tenant_id', $tenant->getKey())
            ->whereIn('status', [BillingPaymentStatus::Verified->value, BillingPaymentStatus::Refunded->value])
            ->exists();

        return ! $paidBefore && $this->founder->hasAvailableSlot() ? $price : null;
    }

    private function periodStart(IssueSubscriptionInvoiceDTO $dto, Tenant $tenant, ?Subscription $subscription): CarbonImmutable
    {
        if ($dto->periodStart !== null) {
            return $dto->periodStart->startOfSecond();
        }

        $now = CarbonImmutable::now();

        if ($dto->type === BillingInvoiceType::Service) {
            return $now->startOfSecond();
        }

        if ($dto->type === BillingInvoiceType::Renewal) {
            $end = $this->futureEnd($tenant, $subscription);

            if ($end !== null) {
                return $end->startOfSecond();
            }
        }

        // One plan/upgrade/expired-renewal invoice per day: a double click is a 409, not a second invoice.
        return $now->startOfDay();
    }

    /** The subscription end when it is still in the future, else null (expired / never paid). */
    private function futureEnd(Tenant $tenant, ?Subscription $subscription): ?CarbonImmutable
    {
        $end = $tenant->subscription_ends_at ?? $subscription?->ends_at;

        return $end !== null && $end->isFuture() ? CarbonImmutable::instance($end) : null;
    }

    private function periodEnd(CarbonImmutable $start, ?BillingCycle $cycle): ?CarbonImmutable
    {
        return match ($cycle) {
            BillingCycle::Monthly => $start->addMonthNoOverflow(),
            BillingCycle::Yearly => $start->addYearNoOverflow(),
            default => null,
        };
    }

    private function assertNotDuplicate(Tenant $tenant, BillingInvoiceType $type, CarbonImmutable $periodStart, bool $noFutureEnd): void
    {
        $query = BillingInvoice::query()
            ->where('tenant_id', $tenant->getKey())
            ->where('type', $type->value)
            ->where('status', '!=', BillingInvoiceStatus::Void->value);

        if ($type === BillingInvoiceType::Renewal && $noFutureEnd) {
            // Expired / never-paid shop: the period start is "today", which would let a new invoice
            // through every day. ANY unpaid renewal invoice counts, whatever its day.
            $query->where(fn ($q) => $q
                ->where('status', BillingInvoiceStatus::Pending->value)
                ->orWhere('period_start', $periodStart));
        } else {
            $query->where('period_start', $periodStart);
        }

        $existing = $query->orderBy('id')->first();

        if ($existing instanceof BillingInvoice) {
            throw BillingException::duplicateInvoice($existing);
        }
    }
}
