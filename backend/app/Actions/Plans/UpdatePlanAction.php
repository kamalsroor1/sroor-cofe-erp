<?php

declare(strict_types=1);

namespace App\Actions\Plans;

use App\DTOs\Plans\PlanDTO;
use App\DTOs\Plans\PlanWriteResult;
use App\Enums\Billing\SubscriptionStatus;
use App\Enums\CentralAuditEvent;
use App\Models\CentralUser;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Services\CentralAuditLogger;
use App\Services\Plans\PlanSettingsWriter;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Console: edit every setting of a plan (PLAN-EDIT). The slug never changes. Hidden features are
 * never written (PlanSettingsWriter); the writable columns are PlanDTO::COLUMNS (every limit).
 * One central transaction with the plan row locked; entitlement and public catalog caches follow
 * through the model observers. Turning `is_free` on is refused (422) while shops on the plan still
 * hold a paid subscription (PLAN-10 review).
 */
final class UpdatePlanAction
{
    public function __construct(
        private readonly PlanSettingsWriter $writer,
        private readonly CentralAuditLogger $audit,
    ) {}

    public function execute(int $planId, PlanDTO $dto, ?CentralUser $operator = null): PlanWriteResult
    {
        $connection = DB::connection((string) (new Plan)->getConnectionName());

        return $connection->transaction(function () use ($planId, $dto, $operator): PlanWriteResult {
            /** @var Plan $plan */
            $plan = Plan::query()->whereKey($planId)->lockForUpdate()->firstOrFail();

            if (! $plan->is_free && (bool) ($dto->attributes['is_free'] ?? false)) {
                $this->assertNoPayingTenants($plan);
            }

            $ignored = $this->writer->write($plan, $dto);

            $this->audit->record(
                CentralAuditEvent::PlanUpdated,
                ['changed' => array_keys($dto->attributes), 'values' => $dto->toArray(), 'ignored_features' => $ignored],
                $operator,
                $plan,
            );

            return new PlanWriteResult($plan->load('addons'), $ignored);
        });
    }

    /**
     * PLAN-10 review: a plan cannot become a permanent free plan while shops on it still hold a
     * non-zero subscription (open: active / past_due / pending_payment / trialing). They would stop
     * being billed and their paid term would be dropped by the sweep. 422; PLAN-14 adds a guided move.
     */
    private function assertNoPayingTenants(Plan $plan): void
    {
        $paying = Subscription::query()
            ->where('plan_id', $plan->getKey())
            ->whereIn('status', [
                SubscriptionStatus::Active->value,
                SubscriptionStatus::PastDue->value,
                SubscriptionStatus::PendingPayment->value,
                SubscriptionStatus::Trialing->value,
            ])
            ->where('amount', '>', 0)
            ->whereIn('tenant_id', Tenant::query()->select('id')->where('plan_id', $plan->getKey()))
            ->distinct()
            ->count('tenant_id');

        if ($paying > 0) {
            throw ValidationException::withMessages([
                'is_free' => (string) __('billing.plan_editor.free_plan_has_paying_tenants', ['count' => $paying]),
            ]);
        }
    }
}
