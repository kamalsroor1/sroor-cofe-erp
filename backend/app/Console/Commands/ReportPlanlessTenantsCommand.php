<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Plan;
use App\Models\Tenant;
use Illuminate\Console\Command;

/**
 * SEC-06: tenants with plan_id NULL are not plan-enforced (entitlements.enforce_planless).
 * Lists them (dry run by default); --assign=<plan slug> gives them that plan so
 * ENTITLEMENTS_ENFORCE_PLANLESS=true can be switched on safely. Central DB only.
 *
 *   php artisan tenants:report-planless [--assign=<plan_slug>]
 */
final class ReportPlanlessTenantsCommand extends Command
{
    protected $signature = 'tenants:report-planless {--assign= : Plan slug to assign to every plan-less tenant}';

    public function __construct()
    {
        parent::__construct();

        $this->setDescription((string) __('console.planless.description'));
    }

    public function handle(): int
    {
        $tenants = $this->planlessTenants();

        if ($tenants === []) {
            $this->info((string) __('console.planless.none'));

            return self::SUCCESS;
        }

        foreach ($tenants as $tenant) {
            $this->line((string) __('console.planless.row', [
                'id' => (string) $tenant->getKey(),
                'name' => (string) $tenant->name,
                'status' => (string) $tenant->getAttribute('status'),
            ]));
        }
        $this->line((string) __('console.planless.summary', ['count' => count($tenants)]));

        $slug = $this->option('assign');
        if (! is_string($slug) || $slug === '') {
            $this->comment((string) __('console.planless.dry_run_hint'));

            return self::SUCCESS;
        }

        $plan = Plan::query()->where('slug', $slug)->first();
        if (! $plan instanceof Plan) {
            $this->error((string) __('console.planless.unknown_plan', ['slug' => $slug]));

            return self::FAILURE;
        }

        // Per model so the Tenant observer bumps the entitlements cache.
        foreach ($tenants as $tenant) {
            $tenant->update(['plan_id' => $plan->id]);
        }

        $this->info((string) __('console.planless.assigned', ['count' => count($tenants), 'slug' => $slug]));

        return self::SUCCESS;
    }

    /**
     * @return list<Tenant>
     */
    private function planlessTenants(): array
    {
        $found = [];

        foreach (Tenant::query()->whereNull('plan_id')->orderBy('id')->get() as $tenant) {
            if ($tenant instanceof Tenant) {
                $found[] = $tenant;
            }
        }

        return $found;
    }
}
