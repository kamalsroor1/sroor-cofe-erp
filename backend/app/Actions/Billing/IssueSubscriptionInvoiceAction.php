<?php

declare(strict_types=1);

namespace App\Actions\Billing;

use App\DTOs\Billing\IssueSubscriptionInvoiceDTO;
use App\Enums\Billing\BillingCycle;
use App\Enums\Billing\BillingInvoiceStatus;
use App\Enums\Billing\BillingInvoiceType;
use App\Enums\Billing\BillingPaymentStatus;
use App\Enums\Billing\SubscriptionAddonStatus;
use App\Enums\CentralAuditEvent;
use App\Exceptions\Billing\BillingException;
use App\Models\Addon;
use App\Models\BillingInvoice;
use App\Models\BillingPayment;
use App\Models\CentralUser;
use App\Models\Plan;
use App\Models\PlanAddon;
use App\Models\Subscription;
use App\Models\SubscriptionAddon;
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
 * Types: plan | renewal | upgrade (a plan line at the chosen cycle), service (one-off lines) and
 * addon (add-on lines priced from the tenant's plan catalog, only from a support request).
 * An upgrade can instead carry ONE "difference" line with the amount the admin decided (no
 * proration). Plan-type invoices may carry extra service lines. Everything is snapshotted into `lines`.
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
            $cycle = $plan !== null || $dto->type === BillingInvoiceType::Addon ? $dto->cycle : null;

            if ($dto->refuseWhenOpenPlanInvoice) {
                $this->assertNoOpenPlanInvoice($tenant);
            }

            // BILL-02: a difference keeps the paid term, so it stays on the subscription's own cycle
            // (monthly -> yearly would otherwise leave a yearly cycle on a monthly end date).
            if ($dto->differenceLine !== null && $subscription instanceof Subscription && $subscription->billing_cycle->isSellable()) {
                $cycle = $subscription->billing_cycle;
            }

            $termEnd = $this->futureEnd($tenant, $subscription);
            $periodStart = $dto->differenceLine !== null
                ? CarbonImmutable::now()->startOfSecond()
                : $this->periodStart($dto, $tenant, $subscription);
            if (! $dto->fromRequest && $dto->type !== BillingInvoiceType::Addon) {
                $this->assertNotDuplicate($tenant, $dto->type, $periodStart, $this->futureEnd($tenant, $subscription) === null);
            }

            // A difference bills only the gap on a paid term, so it needs a term still running.
            if ($dto->differenceLine !== null && $termEnd === null) {
                throw BillingException::differenceNeedsActiveTerm();
            }

            $lines = [];
            if ($plan instanceof Plan && $cycle instanceof BillingCycle) {
                $lines[] = $dto->differenceLine !== null
                    ? $this->differenceLine($dto->differenceLine, $plan, $cycle)
                    : $this->planLine($dto, $plan, $cycle, $tenant, $subscription);
            }
            if ($dto->type === BillingInvoiceType::Renewal && $cycle instanceof BillingCycle && $subscription instanceof Subscription) {
                foreach ($this->renewalAddonLines($subscription, $tenant, $periodStart, $this->periodEnd($periodStart, $cycle)) as $renewalLine) {
                    $lines[] = $renewalLine;
                }
            }
            if ($dto->addonLines !== [] && $cycle instanceof BillingCycle) {
                if (! $subscription instanceof Subscription) {
                    throw BillingException::addonNeedsSubscription();
                }
                if (! $subscription->isInForce()) {
                    throw BillingException::addonNeedsActiveSubscription();
                }
                foreach ($dto->addonLines as $addonLine) {
                    $lines[] = $this->addonLine($addonLine, $cycle, $tenant);
                }
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
                'period_end' => $dto->differenceLine !== null ? $termEnd : $this->periodEnd($periodStart, $cycle),
                'lines' => $lines,
                'subtotal' => $subtotal,
                'discount' => $dto->discount,
                'tax_rate' => $taxRate,
                'tax' => $tax,
                'total' => $total,
                'currency' => Subscription::DEFAULT_CURRENCY,
                'issued_at' => $now,
                'due_at' => $this->dueAt($now, $dto->differenceLine !== null ? $termEnd : null),
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

    /** Normal due date, but a difference invoice must be payable only while the term it prices is still running. */
    private function dueAt(CarbonImmutable $now, ?CarbonImmutable $termEnd): CarbonImmutable
    {
        $due = $now->addDays(max(1, (int) config('billing.invoice_due_days', 7)));

        return $termEnd !== null && $termEnd->lessThan($due) ? $termEnd : $due;
    }

    /**
     * BILL-02: add-ons are billed on renewal. An ACTIVE add-on line is charged when its own paid
     * end falls inside the period being renewed (a yearly add-on on a monthly subscription is only
     * due once a year), at its own cycle, priced from the tenant's plan catalog and falling back to
     * the stored unit price. ActivateSubscriptionAction extends exactly the lines found here.
     *
     * @return list<array<string, mixed>>
     */
    private function renewalAddonLines(Subscription $subscription, Tenant $tenant, CarbonImmutable $periodStart, ?CarbonImmutable $periodEnd): array
    {
        if ($periodEnd === null) {
            return [];
        }

        $rows = SubscriptionAddon::query()
            ->where('subscription_id', $subscription->getKey())
            ->where('status', SubscriptionAddonStatus::Active->value)
            ->where(function ($q) use ($periodEnd): void {
                $q->whereNull('ends_at')->orWhere('ends_at', '<=', $periodEnd);
            })
            ->orderBy('id')
            ->get();

        $lines = [];

        foreach ($rows as $row) {
            $addon = Addon::query()->find($row->addon_id);
            if (! $addon instanceof Addon || ! $addon->is_active || $row->ends_at === null) {
                continue;
            }

            $addonCycle = $row->billing_cycle->isSellable() ? $row->billing_cycle : BillingCycle::Monthly;
            $quantity = max(1, (int) $row->quantity);
            $planAddon = $tenant->plan_id === null ? null : PlanAddon::query()
                ->where('plan_id', $tenant->plan_id)
                ->where('addon_id', $addon->getKey())
                ->where('is_available', true)
                ->first();

            $unit = $planAddon instanceof PlanAddon
                ? Decimal::normalize($planAddon->unitPriceFor($addonCycle, $quantity))
                : Decimal::normalize((string) $row->unit_price);

            $lines[] = [
                'type' => 'addon',
                'renewal' => true,
                'addon_id' => (int) $addon->getKey(),
                'addon_key' => $addon->key,
                'description' => (string) __('billing.invoice.addon_line', ['addon' => (string) __($addon->name_key), 'cycle' => $addonCycle->label()]),
                'billing_cycle' => $addonCycle->value,
                'quantity' => $quantity,
                'unit_price' => $unit,
                'amount' => bcmul($unit, (string) $quantity, 3),
            ];
        }

        return $lines;
    }

    private function planFor(IssueSubscriptionInvoiceDTO $dto): ?Plan
    {
        if ($dto->type === BillingInvoiceType::Service) {
            return null;
        }

        if ($dto->type === BillingInvoiceType::Addon) {
            if ($dto->addonLines === [] || ! $dto->cycle instanceof BillingCycle || ! $dto->cycle->isSellable()) {
                throw BillingException::noPlan();
            }

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

    /**
     * The single line of a support-decided upgrade: the admin's difference, not a catalog price.
     * `difference` tells ActivateSubscriptionAction to switch the plan without extending the term.
     *
     * @param  array{description: string, amount: string}  $difference
     * @return array<string, mixed>
     */
    private function differenceLine(array $difference, Plan $plan, BillingCycle $cycle): array
    {
        $amount = Decimal::normalize($difference['amount']);

        return [
            'type' => 'plan',
            'difference' => true,
            'plan_id' => (int) $plan->getKey(),
            'plan_name' => $plan->name,
            'billing_cycle' => $cycle->value,
            'description' => $difference['description'],
            'quantity' => 1,
            'unit_price' => $amount,
            'amount' => $amount,
            'founder' => false,
        ];
    }

    /**
     * One add-on line, priced from the tenant's plan (plan override, then catalog tier) unless the
     * admin overrides the unit price. The add-on must be active and offered on the tenant's plan.
     *
     * @param  array{addon_id: int, quantity: int, unit_price: string|null}  $line
     * @return array<string, mixed>
     */
    private function addonLine(array $line, BillingCycle $cycle, Tenant $tenant): array
    {
        $addon = Addon::query()->find($line['addon_id']);
        $planAddon = $addon === null || $tenant->plan_id === null ? null : PlanAddon::query()
            ->where('plan_id', $tenant->plan_id)
            ->where('addon_id', $addon->getKey())
            ->where('is_available', true)
            ->first();

        if (! $addon instanceof Addon || ! $addon->is_active || ! $planAddon instanceof PlanAddon) {
            throw BillingException::requestAddonUnavailable();
        }

        $quantity = $line['quantity'];
        $unit = $line['unit_price'] !== null
            ? Decimal::normalize($line['unit_price'])
            : Decimal::normalize($planAddon->unitPriceFor($cycle, $quantity));
        $amount = bcmul($unit, (string) $quantity, 3);

        return [
            'type' => 'addon',
            'addon_id' => (int) $addon->getKey(),
            'addon_key' => $addon->key,
            'description' => (string) __('billing.invoice.addon_line', ['addon' => (string) __($addon->name_key), 'cycle' => $cycle->label()]),
            'billing_cycle' => $cycle->value,
            'quantity' => $quantity,
            'unit_price' => $unit,
            'amount' => $amount,
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

        if ($dto->type === BillingInvoiceType::Service || $dto->type === BillingInvoiceType::Addon) {
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

    /**
     * The PAID term end when it is still in the future, else null (expired / never paid). Only the
     * tenant's subscription_ends_at counts, the same source ActivateSubscriptionAction extends from:
     * a provisioned-but-unpaid subscription row can carry a future ends_at that was never paid for.
     */
    private function futureEnd(Tenant $tenant, ?Subscription $subscription): ?CarbonImmutable
    {
        $end = $tenant->subscription_ends_at;

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

    /**
     * SELL-06: an unpaid plan / renewal / upgrade invoice already exists (checked after the tenant
     * row lock, so two issuers cannot both pass). Paying two of them would extend the term twice.
     */
    private function assertNoOpenPlanInvoice(Tenant $tenant): void
    {
        $existing = BillingInvoice::query()
            ->where('tenant_id', $tenant->getKey())
            ->whereIn('type', [BillingInvoiceType::Plan->value, BillingInvoiceType::Renewal->value, BillingInvoiceType::Upgrade->value])
            ->whereIn('status', [BillingInvoiceStatus::Draft->value, BillingInvoiceStatus::Pending->value])
            ->orderBy('id')
            ->first();

        if ($existing instanceof BillingInvoice) {
            throw BillingException::openInvoiceExists($existing);
        }
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
