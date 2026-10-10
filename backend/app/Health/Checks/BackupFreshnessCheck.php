<?php

declare(strict_types=1);

namespace App\Health\Checks;

use App\Console\Commands\BackupTenantsCommand;
use App\Models\Tenant;
use App\Models\TenantBackup;
use Spatie\Health\Checks\Check;
use Spatie\Health\Checks\Result;

/**
 * OPS-5 / OPS-7: every database that must be backed up has a VERIFIED backup taken in the
 * last backup.tenants.max_age_hours (26 h: a daily run plus slack).
 *
 * Databases that must be backed up: the central DB (when backup.tenants.include_central)
 * and every tenant whose status is not skipped (archived) and that is older than the
 * window (a tenant created this morning has not met a nightly run yet).
 *
 * Failed in production, warning elsewhere. Not part of the deploy gate
 * (App\Providers\HealthServiceProvider::isDeployGate()).
 */
final class BackupFreshnessCheck extends Check
{
    /** Subjects listed in the message; the full list is in the result meta. */
    private const LISTED = 10;

    public function run(): Result
    {
        $hours = max(1, (int) config('backup.tenants.max_age_hours', 26));
        $result = Result::make();

        if (! BackupTenantsCommand::ledgerAvailable()) {
            return $this->problem($result, (string) __('console.health.backup_ledger_missing'));
        }

        $cutoff = now()->subHours($hours);

        /** @var array<string, true> $fresh */
        $fresh = [];
        foreach (TenantBackup::query()->verified()->where('created_at', '>=', $cutoff)->distinct()->pluck('tenant_id') as $tenantId) {
            $fresh[$tenantId === null ? TenantBackup::CENTRAL : (string) $tenantId] = true;
        }

        $required = [];
        if ((bool) config('backup.tenants.include_central', true)) {
            $required[] = TenantBackup::CENTRAL;
        }
        $skip = array_values(array_filter((array) config('backup.tenants.skip_statuses', []), 'is_string'));
        $tenantIds = Tenant::query()
            ->when($skip !== [], fn ($query) => $query->whereNotIn('status', $skip))
            ->where('created_at', '<', $cutoff)
            ->orderBy('id')
            ->pluck('id');
        foreach ($tenantIds as $id) {
            $required[] = (string) $id;
        }

        $stale = array_values(array_filter($required, static fn (string $subject): bool => ! isset($fresh[$subject])));
        $result->meta(['stale' => $stale, 'checked' => count($required), 'max_age_hours' => $hours]);

        if ($stale !== []) {
            $listed = implode(', ', array_slice($stale, 0, self::LISTED)).(count($stale) > self::LISTED ? ', ...' : '');

            return $this->problem(
                $result->shortSummary(count($stale).'/'.count($required)),
                (string) __('console.health.backup_stale', ['count' => count($stale), 'hours' => $hours, 'subjects' => $listed]),
            );
        }

        return $result
            ->shortSummary('0/'.count($required))
            ->ok((string) __('console.health.backup_fresh', ['count' => count($required), 'hours' => $hours]));
    }

    private function problem(Result $result, string $message): Result
    {
        return app()->environment('production') ? $result->failed($message) : $result->warning($message);
    }
}
