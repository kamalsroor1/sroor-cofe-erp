<?php

namespace App\Actions\Plans;

use App\Models\Plan;
use App\Models\PlanFeature;

class UpdatePlanAction
{
    public function execute(Plan $plan, array $data): Plan
    {
        if (isset($data['features']) && is_array($data['features'])) {
            // Hidden features (PRD-10) cannot be sold or edited from the console: keep
            // whatever the plan already stores for them.
            $stored = is_array($plan->features) ? $plan->features : [];
            foreach (PlanFeature::hiddenKeys() as $key) {
                unset($data['features'][$key]);
                if (array_key_exists($key, $stored)) {
                    $data['features'][$key] = $stored[$key];
                }
            }
        }

        // Not-yet-enforced limits cannot be sold or edited from the console: keep what is stored.
        $data = array_diff_key($data, array_flip(Plan::HIDDEN_LIMIT_COLUMNS));

        $plan->update($data);

        return $plan;
    }
}
