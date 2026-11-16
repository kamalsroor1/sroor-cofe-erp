<?php

declare(strict_types=1);

namespace App\Actions\Plans;

use App\DTOs\Plans\PlanDTO;
use App\DTOs\Plans\PlanWriteResult;
use App\Enums\CentralAuditEvent;
use App\Models\CentralUser;
use App\Models\Plan;
use App\Services\CentralAuditLogger;
use App\Services\Plans\PlanSettingsWriter;
use Illuminate\Support\Facades\DB;

/**
 * Console: edit every setting of a plan (PLAN-EDIT). The slug never changes. Hidden features are
 * never written (PlanSettingsWriter); the writable columns are PlanDTO::COLUMNS (every limit).
 * One central transaction with the plan row locked; entitlement and public catalog caches follow
 * through the model observers.
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
}
