<?php

declare(strict_types=1);

namespace App\Actions\Plans;

use App\Enums\CentralAuditEvent;
use App\Models\CentralAuditLog;
use App\Models\Plan;
use Illuminate\Support\Collection;

/**
 * PLAN-14: the most recent console changes of one plan, read from the central audit log
 * (App\Services\CentralAuditLogger writes PlanCreated / PlanUpdated with the plan as subject).
 * Newest first; the operator is eager loaded, only the changed field names are exposed.
 */
final class ListPlanChangesAction
{
    public const LIMIT = 10;

    /**
     * @return Collection<int, CentralAuditLog>
     */
    public function execute(Plan $plan): Collection
    {
        return CentralAuditLog::query()
            ->with('causer')
            ->where('subject_type', $plan->getMorphClass())
            ->where('subject_id', (string) $plan->getKey())
            ->whereIn('event', [CentralAuditEvent::PlanCreated->value, CentralAuditEvent::PlanUpdated->value])
            ->orderByDesc('id')
            ->limit(self::LIMIT)
            ->get();
    }
}
