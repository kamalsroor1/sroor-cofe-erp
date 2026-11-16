<?php

declare(strict_types=1);

namespace App\Services\Plans;

use App\DTOs\Plans\PlanDTO;
use App\Models\Plan;
use App\Models\PlanAddon;
use App\Models\PlanFeature;

/**
 * Writes a PlanDTO onto a plan (shared by CreatePlanAction and UpdatePlanAction, which own the
 * transaction). Eloquent only, so EntitlementsCacheObserver (tenants on the plan) and
 * PublicCatalogObserver (public pricing cache) fire for the plan and every plan_addon row.
 *
 * Features: the submitted map over the registry; a hidden key (PRD-10) is never written: the
 * plan keeps its stored value (false on a new plan) and the key is returned as ignored. A
 * registry key the request omits is off on a new plan and keeps its value on an existing one.
 */
final class PlanSettingsWriter
{
    /**
     * @return list<string> hidden feature keys the request sent and that were not written
     */
    public function write(Plan $plan, PlanDTO $dto): array
    {
        $stored = is_array($plan->features) ? $plan->features : [];
        $hidden = PlanFeature::hiddenKeys();
        $features = $plan->exists ? $stored : array_fill_keys(PlanFeature::query()->pluck('key')->all(), false);
        $ignored = [];

        foreach ($dto->features as $key => $on) {
            if (in_array($key, $hidden, true)) {
                $ignored[] = $key;

                continue;
            }
            $features[$key] = $on;
        }

        foreach ($hidden as $key) {
            $features[$key] = (bool) ($stored[$key] ?? false);
        }

        $plan->fill($dto->attributes);
        $plan->features = $features;

        if (! $plan->exists) {
            $plan->save();
        }

        if ($dto->addons !== null) {
            $this->writeAddons($plan, $dto->addons);
        }

        // Always saved (even when clean) so the `saved` observers run after the add-on rows changed.
        $plan->save();

        return $ignored;
    }

    /**
     * @param  list<array{addon_id: int, is_available: bool, included_quantity: int, included_billing_cycle: string|null}>  $rows
     */
    private function writeAddons(Plan $plan, array $rows): void
    {
        foreach ($rows as $row) {
            $line = PlanAddon::query()
                ->where('plan_id', $plan->getKey())
                ->where('addon_id', $row['addon_id'])
                ->lockForUpdate()
                ->first() ?? new PlanAddon(['plan_id' => (int) $plan->getKey(), 'addon_id' => $row['addon_id']]);

            $line->fill([
                'is_available' => $row['is_available'],
                'included_quantity' => $row['included_quantity'],
                'included_billing_cycle' => $row['included_billing_cycle'],
            ]);
            $line->save();
        }
    }
}
