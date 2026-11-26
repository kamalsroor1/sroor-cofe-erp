<?php

declare(strict_types=1);

namespace App\Actions\Billing;

use App\Actions\Tenants\MoveTenantToFreePlanAction;
use App\DTOs\Billing\RequestPlanChangeDTO;
use App\Enums\Billing\AddonType;
use App\Enums\Billing\PlanChangeRequestKind;
use App\Enums\Billing\PlanChangeRequestStatus;
use App\Enums\CentralAuditEvent;
use App\Enums\TenantStatus;
use App\Exceptions\Billing\BillingException;
use App\Models\Addon;
use App\Models\Plan;
use App\Models\PlanAddon;
use App\Models\PlanChangeRequest;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Services\CentralAuditLogger;
use Illuminate\Support\Facades\DB;

/**
 * Owner decision: a plan change or an add-on goes through support. The shop (tenant context,
 * `billing.manage`) files a request; nothing is billed and nothing changes until the console
 * issues an invoice and it is paid.
 *
 * Central transaction with the tenant row locked first, so two clicks cannot create two pending
 * requests of the same kind. Rules (all BillingException 422, translated):
 *  - one PENDING request per tenant per kind;
 *  - plan_change: an active, public plan different from the tenant's current one;
 *  - addon: an active, public, recurring add-on offered on the tenant's plan, and the tenant must
 *    already hold an IN-FORCE subscription (an add-on rides on one; expired -> addon_needs_active_subscription).
 * The requesting user's name is snapshotted (the user lives in the tenant database).
 *
 * PLAN-10 review, downgrade to a permanent free plan (`plans.is_free`): it can never be invoiced,
 * so the request is stored as `scheduled` (no invoice, never reaches IssuePlanChangeInvoiceAction,
 * which only takes `pending` rows). The daily lifecycle sweep applies it at the end of the paid
 * term through MoveTenantToFreePlanAction (source `downgrade`), and the shop can cancel it until
 * then. When NO paid term is running (a trial, or a term that already ended: past_due / read_only /
 * active without a future end) it is applied at once in this transaction and returned `completed`.
 * A suspended / cancelled / archived shop is refused (billing.downgrade_unavailable).
 * "One open request per kind" counts pending AND scheduled rows.
 */
final class RequestPlanChangeAction
{
    /** Stored statuses a downgrade to a free plan may start from. */
    private const DOWNGRADABLE = [TenantStatus::Trial, TenantStatus::Active, TenantStatus::PastDue, TenantStatus::ReadOnly];

    public function __construct(
        private readonly CentralAuditLogger $audit,
        private readonly MoveTenantToFreePlanAction $moveToFreePlan,
    ) {}

    public function execute(RequestPlanChangeDTO $dto): PlanChangeRequest
    {
        $connection = DB::connection((string) (new PlanChangeRequest)->getConnectionName());

        return $connection->transaction(function () use ($dto): PlanChangeRequest {
            $tenant = Tenant::query()->whereKey($dto->tenantId)->lockForUpdate()->firstOrFail();

            $plan = $dto->kind === PlanChangeRequestKind::PlanChange ? $this->requestedPlan($dto, $tenant) : null;
            $target = $plan instanceof Plan
                ? ['requested_plan_id' => $plan->getKey(), 'addon_id' => null]
                : ['requested_plan_id' => null, 'addon_id' => $this->requestedAddon($dto, $tenant)->getKey()];
            $toFreePlan = $plan instanceof Plan && $plan->is_free;
            $stored = $tenant->lifecycleStatus();

            if ($toFreePlan && ! in_array($stored, self::DOWNGRADABLE, true)) {
                throw BillingException::downgradeUnavailable();
            }

            $open = PlanChangeRequest::query()
                ->where('tenant_id', $tenant->getKey())
                ->where('kind', $dto->kind->value)
                ->open()
                ->exists();

            if ($open) {
                throw BillingException::requestAlreadyPending();
            }

            $request = PlanChangeRequest::query()->create($target + [
                'tenant_id' => (string) $tenant->getKey(),
                'kind' => $dto->kind,
                'current_plan_id' => $tenant->plan_id,
                'quantity' => $dto->quantity,
                'billing_cycle' => $dto->cycle,
                // A free-plan downgrade is never invoiced, so `renew` means nothing there.
                'renew' => $dto->renew && ! $toFreePlan,
                'notes' => $dto->notes,
                'status' => $toFreePlan ? PlanChangeRequestStatus::Scheduled : PlanChangeRequestStatus::Pending,
                'requested_by_name' => $dto->requestedByName,
                'requested_by_user_id' => $dto->requestedByUserId,
            ]);
            $applyNow = $toFreePlan && ! $this->paidTermRunning($tenant, $stored);

            $this->audit->record(
                CentralAuditEvent::PlanChangeRequested,
                [
                    'request_id' => (int) $request->getKey(),
                    'kind' => $dto->kind->value,
                    'current_plan_id' => $tenant->plan_id,
                    'requested_plan_id' => $target['requested_plan_id'],
                    'addon_id' => $target['addon_id'],
                    'quantity' => $dto->quantity,
                    'billing_cycle' => $dto->cycle->value,
                    'renew' => $dto->renew && ! $toFreePlan,
                    'downgrade_to_free' => $toFreePlan,
                    'applies_at' => ! $toFreePlan ? null : ($applyNow ? 'now' : $tenant->subscription_ends_at?->toIso8601String()),
                ],
                null,
                $request,
                (string) $tenant->getKey(),
            );

            if ($applyNow) {
                $this->moveToFreePlan->execute((string) $tenant->getKey(), $plan, $stored, MoveTenantToFreePlanAction::SOURCE_DOWNGRADE, (int) $request->getKey());
                $request->refresh();
            }

            return $request;
        });
    }

    /** A trial has no paid term; otherwise the term runs while subscription_ends_at is in the future. */
    private function paidTermRunning(Tenant $tenant, ?TenantStatus $stored): bool
    {
        return $stored !== TenantStatus::Trial
            && $tenant->subscription_ends_at !== null
            && $tenant->subscription_ends_at->isFuture();
    }

    private function requestedPlan(RequestPlanChangeDTO $dto, Tenant $tenant): Plan
    {
        $plan = Plan::query()
            ->where('slug', (string) $dto->requestedPlanCode)
            ->where('is_active', true)
            ->where('is_public', true)
            ->first();

        if (! $plan instanceof Plan) {
            throw BillingException::requestPlanUnavailable();
        }

        // #232 item 8: a shop with no paid term running (a trial on a paid plan, or an expired term)
        // may SUBSCRIBE to the plan it is on; a free plan, or a running paid term, stays refused.
        if ($tenant->plan_id !== null && (int) $tenant->plan_id === (int) $plan->getKey() && ($plan->is_free || $tenant->hasPaidTermRunning())) {
            throw BillingException::requestSamePlan();
        }

        return $plan;
    }

    private function requestedAddon(RequestPlanChangeDTO $dto, Tenant $tenant): Addon
    {
        $addon = Addon::query()
            ->where('key', (string) $dto->addonKey)
            ->where('is_active', true)
            ->where('is_public', true)
            ->where('type', AddonType::Recurring->value)
            ->first();

        $offered = $addon instanceof Addon && $tenant->plan_id !== null && PlanAddon::query()
            ->where('plan_id', $tenant->plan_id)
            ->where('addon_id', $addon->getKey())
            ->where('is_available', true)
            ->exists();

        if (! $addon instanceof Addon || ! $offered) {
            throw BillingException::requestAddonUnavailable();
        }

        $subscription = Subscription::query()->where('tenant_id', $tenant->getKey())->orderByDesc('id')->first();

        if (! $subscription instanceof Subscription) {
            throw BillingException::addonNeedsSubscription();
        }

        if (! $subscription->isInForce()) {
            throw BillingException::addonNeedsActiveSubscription();
        }

        return $addon;
    }
}
