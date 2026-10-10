<?php

declare(strict_types=1);

namespace App\Console\Tenancy;

use App\Models\Tenant;

/**
 * OPS-2: stancl's tenants:* commands run for EVERY tenant row when --tenants is omitted,
 * including pending / running / failed ones that have no database yet (a deploy's
 * `tenants:migrate --force` would then stop the release). This narrows the default to
 * provisioned (`ready`) tenants. An explicit --tenants list is kept as given: that is the
 * provisioning job itself, or an operator who named the tenant on purpose.
 *
 * Used by the command subclasses swapped in by AppServiceProvider (container extenders),
 * so deploy.sh, setup-local.ps1 and manual runs all get the same behaviour.
 */
trait DefaultsToProvisionedTenants
{
    /**
     * Fills --tenants with the ready tenant ids when none were requested.
     *
     * @return bool false when there is nothing to run for (the caller must stop: an empty
     *              --tenants list makes stancl fall back to ALL tenants)
     */
    protected function restrictToProvisionedTenants(): bool
    {
        $requested = array_filter(
            (array) $this->option('tenants'),
            static fn (mixed $id): bool => is_scalar($id) && (string) $id !== '',
        );

        if ($requested !== []) {
            return true;
        }

        $ready = Tenant::query()
            ->provisioned()
            ->orderBy('id')
            ->pluck('id')
            ->map(static fn (mixed $id): string => (string) $id)
            ->all();

        $skipped = Tenant::query()->count() - count($ready);
        if ($skipped > 0) {
            $this->warn((string) __('console.tenants.skipped_not_provisioned', ['count' => $skipped]));
        }

        if ($ready === []) {
            $this->info((string) __('console.tenants.none_provisioned'));

            return false;
        }

        $this->input->setOption('tenants', $ready);

        return true;
    }
}
