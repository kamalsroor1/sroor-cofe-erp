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
use Illuminate\Support\Str;

/**
 * Console: create a custom plan with every setting (PLAN-EDIT).
 *
 * The slug is generated from the name: ASCII slug (or "plan" when the name has no Latin
 * characters), prefixed with "custom-" when it would be a catalog slug (Plan::CATALOG_SLUGS), and
 * suffixed -2, -3 ... until unique. So the catalog seeder / data migrations never touch it.
 * sort_order defaults to after the last plan; is_public defaults to false (hidden until published). One central transaction; the plans table is locked
 * for the slug check through the last row, so two creates of the same name get distinct slugs
 * (the unique index on plans.slug is the final guard).
 */
final class CreatePlanAction
{
    public function __construct(
        private readonly PlanSettingsWriter $writer,
        private readonly CentralAuditLogger $audit,
    ) {}

    public function execute(PlanDTO $dto, ?CentralUser $operator = null): PlanWriteResult
    {
        $connection = DB::connection((string) (new Plan)->getConnectionName());

        return $connection->transaction(function () use ($dto, $operator): PlanWriteResult {
            // Serialises concurrent creates on the highest plan row (slug + sort order).
            Plan::query()->orderByDesc('id')->lockForUpdate()->first();

            $plan = new Plan;
            $plan->slug = $this->uniqueSlug((string) ($dto->attributes['name'] ?? ''));
            // A new plan stays off the public price list until the operator publishes it.
            if (! array_key_exists('is_public', $dto->attributes)) {
                $plan->is_public = false;
            }
            if (! array_key_exists('sort_order', $dto->attributes)) {
                $plan->sort_order = ((int) Plan::query()->max('sort_order')) + 1;
            }
            // PLAN-ENF: these limits are enforced now; an omitted one is unlimited, never the legacy
            // column default (100 invoices / 500 MB) that would silently cap a new plan.
            foreach (['max_invoices_per_month', 'max_storage_mb'] as $column) {
                if (! array_key_exists($column, $dto->attributes)) {
                    $plan->setAttribute($column, null);
                }
            }

            $ignored = $this->writer->write($plan, $dto);

            $this->audit->record(
                CentralAuditEvent::PlanCreated,
                ['slug' => $plan->slug, 'values' => $dto->toArray(), 'ignored_features' => $ignored],
                $operator,
                $plan,
            );

            return new PlanWriteResult($plan->load('addons'), $ignored);
        });
    }

    private function uniqueSlug(string $name): string
    {
        $base = Str::slug($name);
        if ($base === '') {
            $base = 'plan';
        }
        $base = Str::limit($base, 80, '');
        if (in_array($base, Plan::CATALOG_SLUGS, true)) {
            $base = 'custom-'.$base;
        }

        $slug = $base;
        for ($n = 2; Plan::query()->where('slug', $slug)->exists(); $n++) {
            $slug = $base.'-'.$n;
        }

        return $slug;
    }
}
