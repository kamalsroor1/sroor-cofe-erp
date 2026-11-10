<?php

declare(strict_types=1);

namespace App\Actions\Billing;

use App\DTOs\Billing\RequestPlanChangeDTO;
use App\Enums\Billing\AddonType;
use App\Enums\Billing\PlanChangeRequestKind;
use App\Enums\Billing\PlanChangeRequestStatus;
use App\Enums\CentralAuditEvent;
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
 */
final class RequestPlanChangeAction
{
    public function __construct(private readonly CentralAuditLogger $audit) {}

    public function execute(RequestPlanChangeDTO $dto): PlanChangeRequest
    {
        $connection = DB::connection((string) (new PlanChangeRequest)->getConnectionName());

        return $connection->transaction(function () use ($dto): PlanChangeRequest {
            $tenant = Tenant::query()->whereKey($dto->tenantId)->lockForUpdate()->firstOrFail();

            $target = $dto->kind === PlanChangeRequestKind::PlanChange
                ? ['requested_plan_id' => $this->requestedPlan($dto, $tenant)->getKey(), 'addon_id' => null]
                : ['requested_plan_id' => null, 'addon_id' => $this->requestedAddon($dto, $tenant)->getKey()];

            $pending = PlanChangeRequest::query()
                ->where('tenant_id', $tenant->getKey())
                ->where('kind', $dto->kind->value)
                ->where('status', PlanChangeRequestStatus::Pending->value)
                ->exists();

            if ($pending) {
                throw BillingException::requestAlreadyPending();
            }

            $request = PlanChangeRequest::query()->create($target + [
                'tenant_id' => (string) $tenant->getKey(),
                'kind' => $dto->kind,
                'current_plan_id' => $tenant->plan_id,
                'quantity' => $dto->quantity,
                'billing_cycle' => $dto->cycle,
                'notes' => $dto->notes,
                'status' => PlanChangeRequestStatus::Pending,
                'requested_by_name' => $dto->requestedByName,
                'requested_by_user_id' => $dto->requestedByUserId,
            ]);

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
                ],
                null,
                $request,
                (string) $tenant->getKey(),
            );

            return $request;
        });
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

        if ($tenant->plan_id !== null && (int) $tenant->plan_id === (int) $plan->getKey()) {
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
