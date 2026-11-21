<?php

declare(strict_types=1);

namespace App\Actions\Tenants;

use App\Enums\CentralAuditEvent;
use App\Jobs\RunTenantMigrationsJob;
use App\Models\CentralUser;
use App\Models\Tenant;
use App\Services\CentralAuditLogger;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Runs the pending tenant migrations of ONE tenant and audits the outcome. Called from
 * RunTenantMigrationsJob (OPS-25: queued by the super-admin console, never inside the HTTP
 * request). The console output (paths, SQL, connection names) goes to the log only.
 *
 * One run per tenant at a time: a per-tenant cache lock (central store, OPS-26) refuses a second
 * run while one is still migrating, and that refusal is audited as `skipped`. A tenant that is
 * no longer `ready` when the job starts is audited as `skipped` too (provisioning owns it).
 * Every run writes exactly one `tenant_migrations_run` line carrying the run id.
 */
final class RunTenantMigrationsAction
{
    public function __construct(private readonly CentralAuditLogger $auditLogger) {}

    public function execute(Tenant $tenant, ?CentralUser $operator, string $runId): bool
    {
        $tenant = $tenant->fresh() ?? $tenant;
        if (! $tenant->isProvisioned()) {
            $this->audit($tenant, $operator, $runId, 'skipped', null, 'workspace_not_ready');

            return false;
        }

        $lock = Cache::lock(self::lockKey((string) $tenant->getKey()), RunTenantMigrationsJob::TIMEOUT_SECONDS + 60);
        if (! $lock->get()) {
            $this->audit($tenant, $operator, $runId, 'skipped', null, 'already_running');

            return false;
        }

        try {
            try {
                $exitCode = Artisan::call('tenants:migrate', ['--tenants' => [(string) $tenant->getKey()], '--force' => true]);
                Log::info('Tenant migrations run by an operator', [
                    'tenant' => $tenant->getKey(),
                    'run_id' => $runId,
                    'exit_code' => $exitCode,
                    'output' => Artisan::output(),
                ]);
            } catch (Throwable $e) {
                Log::error('Tenant migrations failed', ['tenant' => $tenant->getKey(), 'run_id' => $runId, 'exception' => $e]);
                $exitCode = null;
            }
        } finally {
            $lock->release();
        }

        $succeeded = $exitCode === 0;

        // An attempt: the row must exist whatever happened (a failed run is audited too).
        $this->audit($tenant, $operator, $runId, $succeeded ? 'succeeded' : 'failed', $exitCode);

        return $succeeded;
    }

    public static function lockKey(string $tenantId): string
    {
        return 'tenant-migrations-run:'.$tenantId;
    }

    private function audit(Tenant $tenant, ?CentralUser $operator, string $runId, string $status, ?int $exitCode, ?string $reason = null): void
    {
        $this->auditLogger->recordAttempt(
            CentralAuditEvent::TenantMigrationsRun,
            array_filter(
                ['status' => $status, 'exit_code' => $exitCode, 'run_id' => $runId, 'reason' => $reason],
                static fn (mixed $value, string $key): bool => $value !== null || $key === 'exit_code',
                ARRAY_FILTER_USE_BOTH,
            ),
            actor: $operator,
            subject: $tenant,
        );
    }
}
