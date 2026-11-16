<?php

declare(strict_types=1);

namespace App\DTOs\Plans;

use App\Models\Plan;

/**
 * Output of CreatePlanAction / UpdatePlanAction: the saved plan (add-ons loaded) and the hidden
 * feature keys the request sent but that were not written (hidden features are not sold).
 */
final readonly class PlanWriteResult
{
    /**
     * @param  list<string>  $ignoredFeatures
     */
    public function __construct(
        public Plan $plan,
        public array $ignoredFeatures,
    ) {}
}
