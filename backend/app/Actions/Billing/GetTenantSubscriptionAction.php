<?php

declare(strict_types=1);

namespace App\Actions\Billing;

use App\Contracts\TenantFeatureManagerInterface;
use App\Enums\Billing\AddonType;
use App\Enums\Billing\BillingCycle;
use App\Enums\Billing\BillingInvoiceStatus;
use App\Enums\Billing\BillingPaymentStatus;
use App\Enums\Billing\PlanChangeRequestKind;
use App\Enums\LimitResource;
use App\Exceptions\Billing\AddonPricingException;
use App\Http\Resources\Billing\PlanChangeRequestResource;
use App\Models\BillingInvoice;
use App\Models\Invoice;
use App\Models\Item;
use App\Models\Plan;
use App\Models\PlanAddon;
use App\Models\PlanChangeRequest;
use App\Models\Subscription;
use App\Models\SubscriptionAddon;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Branding\PlatformBranding;
use App\Support\StoreLimitBucket;
use App\Support\Tenancy\TenantLifecyclePolicy;
use App\Support\TenantClock;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * PRD-04: the "subscription & usage" payload of the shop's billing screen (tenant context).
 *
 * Plan and subscription come from the central DB (by the resolved tenant id, never the request);
 * usage is counted in the tenant DB. Limits come from TenantFeatureManagerInterface (plan + active
 * add-ons); `null` is unlimited, and a legacy tenant with no plan is unlimited too (it is not
 * enforced either, PlanEnforcer). `percent` is a whole number and can pass 100 for a tenant that
 * was downgraded below what it already holds.
 */
final class GetTenantSubscriptionAction
{
    public function __construct(
        private readonly TenantFeatureManagerInterface $features,
        private readonly PlatformBranding $platform,
        private readonly TenantClock $clock,
        private readonly TenantLifecyclePolicy $policy,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function execute(Tenant $tenant): array
    {
        $now = CarbonImmutable::now();
        $decision = $this->policy->evaluate($tenant->lifecycleSnapshot(), $now);
        $plan = $tenant->plan_id === null ? null : Plan::query()->find($tenant->plan_id);
        $subscription = Subscription::query()->where('tenant_id', $tenant->getKey())->orderByDesc('id')->first();
        $branding = $this->platform->get();

        $end = $decision->effectiveStatus->value === 'trial' ? $tenant->trial_ends_at : $tenant->subscription_ends_at;

        // SELL-07: one status for the screen. The subscription row says "trialing" while the shop
        // is read-only; show the shop's derived status instead of contradicting it.
        $subscriptionStatus = $subscription === null ? null : ['value' => $subscription->status->value, 'label' => $subscription->status->label()];
        if ($subscriptionStatus !== null && $decision->effectiveStatus->value !== 'trial' && $subscription?->status->value === 'trialing') {
            $subscriptionStatus = ['value' => $decision->effectiveStatus->value, 'label' => $decision->effectiveStatus->label()];
        }

        return [
            'plan' => $plan === null ? null : ['id' => (int) $plan->getKey(), 'name' => $plan->name, 'code' => $plan->slug],
            'subscription' => [
                'status' => $subscriptionStatus,
                'billing_cycle' => $subscription === null ? null : ['value' => $subscription->billing_cycle->value, 'label' => $subscription->billing_cycle->label()],
                'starts_at' => $subscription?->starts_at?->toIso8601String(),
                'ends_at' => $end?->toIso8601String(),
                'trial_ends_at' => $tenant->trial_ends_at?->toIso8601String(),
                'days_left' => $this->daysLeft($end, $now),
                'is_founder' => $subscription !== null && $subscription->is_founder,
            ],
            'tenant_status' => [
                'value' => $decision->effectiveStatus->value,
                'label' => $decision->effectiveStatus->label(),
                'access' => $decision->access()->value,
            ],
            'usage' => $this->usage($tenant),
            'addons' => $this->addons($tenant),
            'pending_plan_change' => $this->pendingRequest($tenant, PlanChangeRequestKind::PlanChange),
            'pending_addon_request' => $this->pendingRequest($tenant, PlanChangeRequestKind::Addon),
            'available_plans' => $this->availablePlans($tenant),
            'available_addons' => $this->availableAddons($plan),
            'open_invoices_count' => BillingInvoice::query()
                ->where('tenant_id', $tenant->getKey())
                ->where('status', BillingInvoiceStatus::Pending->value)
                ->count(),
            'partial_payment' => $this->partialPayment($tenant),
            'payment_instructions' => [
                'instapay_address' => $branding->billingInstapayAddress,
                'vodafone_cash_number' => $branding->billingVodafoneCashNumber,
                'account_name' => $branding->billingAccountName,
                'payment_note' => $branding->billingPaymentNote,
                'tax_rate' => $branding->billingTaxRate,
            ],
            'support' => [
                'phone' => $branding->supportPhone,
                'email' => $branding->supportEmail,
            ],
        ];
    }

    /**
     * SELL-07: an open invoice that already has verified payments but is not fully paid, so the
     * screen can say "X recorded, Y left to activate the account".
     *
     * @return array{invoice_id: int, invoice_number: string, paid: string, remaining: string}|null
     */
    private function partialPayment(Tenant $tenant): ?array
    {
        $invoices = BillingInvoice::query()
            ->where('tenant_id', $tenant->getKey())
            ->where('status', BillingInvoiceStatus::Pending->value)
            ->with(['payments' => fn ($q) => $q->where('status', BillingPaymentStatus::Verified->value)])
            ->orderBy('id')
            ->get();

        foreach ($invoices as $invoice) {
            $paid = '0.000';
            foreach ($invoice->payments as $payment) {
                $paid = bcadd($paid, (string) $payment->amount, 3);
            }

            if (bccomp($paid, '0', 3) > 0) {
                return [
                    'invoice_id' => (int) $invoice->getKey(),
                    'invoice_number' => (string) $invoice->number,
                    'paid' => $paid,
                    'remaining' => bcsub((string) $invoice->total, $paid, 3),
                ];
            }
        }

        return null;
    }

    private function daysLeft(?CarbonInterface $end, CarbonImmutable $now): ?int
    {
        if ($end === null) {
            return null;
        }

        if ($end->lessThanOrEqualTo($now)) {
            return 0;
        }

        return (int) ceil($now->diffInSeconds($end, true) / 86400);
    }

    /**
     * @return list<array{resource: string, label: string, used: int, limit: int|null, percent: int|null}>
     */
    private function usage(Tenant $tenant): array
    {
        $enforced = $this->features->entitlements($tenant)->planId !== null || (bool) config('entitlements.enforce_planless', false);
        $rows = [];

        foreach (LimitResource::cases() as $resource) {
            $used = $this->used($resource);
            $limit = $enforced ? $this->features->limit($tenant, $resource) : null;

            $rows[] = [
                'resource' => $resource->value,
                'label' => (string) __('billing.limit_resources.'.$resource->value),
                'used' => $used,
                'limit' => $limit,
                'percent' => $limit === null ? null : ($limit === 0 ? ($used > 0 ? 100 : 0) : intdiv($used * 100, $limit)),
            ];
        }

        return $rows;
    }

    private function used(LimitResource $resource): int
    {
        return match ($resource) {
            LimitResource::Users => User::query()->count(),
            LimitResource::Items => Item::query()->count(),
            LimitResource::Stores, LimitResource::Warehouses, LimitResource::Vans => StoreLimitBucket::count($resource),
            LimitResource::InvoicesMonth => Invoice::query()->where('created_at', '>=', $this->clock->now()->startOfMonth())->count(),
            LimitResource::StorageMb => $this->storageMb(),
        };
    }

    /** Whole megabytes (rounded up) held by the shop's media library; 0 when the table is absent. */
    private function storageMb(): int
    {
        if (! Schema::hasTable('media')) {
            return 0;
        }

        $bytes = (int) DB::table('media')->sum('size');

        return intdiv($bytes + 1048575, 1048576);
    }

    /**
     * @return list<array{key: string, name: string, quantity: int, ends_at: string|null}>
     */
    private function addons(Tenant $tenant): array
    {
        $rows = [];

        $lines = SubscriptionAddon::query()
            ->activeFor($tenant)
            ->with('addon')
            ->orderBy('id')
            ->get();

        foreach ($lines as $line) {
            $rows[] = [
                'key' => $line->addon->key,
                'name' => (string) __($line->addon->name_key),
                'quantity' => (int) $line->quantity,
                'ends_at' => $line->ends_at?->toIso8601String(),
            ];
        }

        return $rows;
    }

    /**
     * The shop's pending request of one kind (at most one exists), shaped like the list resource.
     *
     * @return array<string, mixed>|null
     */
    private function pendingRequest(Tenant $tenant, PlanChangeRequestKind $kind): ?array
    {
        $request = PlanChangeRequest::query()
            ->with(['currentPlan', 'requestedPlan', 'addon', 'invoice'])
            ->where('tenant_id', $tenant->getKey())
            ->where('kind', $kind->value)
            ->pending()
            ->orderByDesc('id')
            ->first();

        return $request === null ? null : (new PlanChangeRequestResource($request))->resolve(request());
    }

    /**
     * Public, active plans the shop may ask to move to (price list for the request form).
     *
     * @return list<array{code: string, name: string, price_monthly: string, price_yearly: string, is_current: bool}>
     */
    private function availablePlans(Tenant $tenant): array
    {
        $rows = [];

        foreach (Plan::query()->active()->where('is_public', true)->get() as $plan) {
            $rows[] = [
                'code' => $plan->slug,
                'name' => $plan->name,
                'price_monthly' => $plan->price_monthly,
                'price_yearly' => $plan->price_yearly,
                'is_current' => $tenant->plan_id !== null && (int) $tenant->plan_id === (int) $plan->getKey(),
            ];
        }

        return $rows;
    }

    /**
     * Recurring add-ons offered on the shop's plan, priced for one unit (plan override, else the
     * catalog base price). `yearly_price` is null when the add-on is not sold yearly.
     *
     * @return list<array{key: string, name: string, unit_price: string, yearly_price: string|null}>
     */
    private function availableAddons(?Plan $plan): array
    {
        if (! $plan instanceof Plan) {
            return [];
        }

        $rows = [];

        $offers = PlanAddon::query()
            ->with('addon')
            ->where('plan_id', $plan->getKey())
            ->where('is_available', true)
            ->whereHas('addon', fn ($addon) => $addon->where('is_active', true)->where('is_public', true)->where('type', AddonType::Recurring->value))
            ->orderBy('id')
            ->get();

        foreach ($offers as $offer) {
            try {
                $yearly = $offer->unitPriceFor(BillingCycle::Yearly);
            } catch (AddonPricingException) {
                $yearly = null;
            }

            $rows[] = [
                'key' => $offer->addon->key,
                'name' => (string) __($offer->addon->name_key),
                'unit_price' => $offer->unitPriceFor(BillingCycle::Monthly),
                'yearly_price' => $yearly,
            ];
        }

        return $rows;
    }
}
