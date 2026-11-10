<?php

declare(strict_types=1);

namespace App\Actions\Billing;

use App\Contracts\TenantFeatureManagerInterface;
use App\Enums\Billing\BillingInvoiceStatus;
use App\Enums\LimitResource;
use App\Models\BillingInvoice;
use App\Models\Invoice;
use App\Models\Item;
use App\Models\Plan;
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

        return [
            'plan' => $plan === null ? null : ['id' => (int) $plan->getKey(), 'name' => $plan->name, 'code' => $plan->slug],
            'subscription' => [
                'status' => $subscription === null ? null : ['value' => $subscription->status->value, 'label' => $subscription->status->label()],
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
            'open_invoices_count' => BillingInvoice::query()
                ->where('tenant_id', $tenant->getKey())
                ->where('status', BillingInvoiceStatus::Pending->value)
                ->count(),
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
}
