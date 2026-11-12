<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Tenant;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Symfony\Component\Process\Process;
use Throwable;

/**
 * OPS-25: deploy-grade tenant migration. `tenants:migrate` stops at the first failing
 * tenant and has no per-tenant time limit; one hung shop blocks every shop after it and a
 * killed session leaves half the fleet unreported.
 *
 * This command runs `tenants:migrate --tenants=<id> --force` in a CHILD PROCESS per ready
 * tenant (a hung ALTER cannot freeze the parent), with a per-tenant timeout (SIGTERM, then
 * SIGKILL after a grace period), always continues with the next tenant, writes a JSON
 * status file after every tenant (atomic rename; deploy.sh polls it) and prints a final
 * ok / failed / timeout / skipped report. Exit code 1 when any tenant failed or timed out.
 *
 * Re-running is safe: already migrated tenants have nothing pending and finish in a
 * second. Migrations must be backward compatible with the previous release
 * (docs/07-operations/deploy-runbook.md, expand/contract).
 */
final class MigrateAllTenantsCommand extends Command
{
    protected $signature = 'tenants:migrate-all
        {--tenants=* : Only these tenant ids}
        {--timeout=900 : Seconds allowed per tenant before it is stopped}
        {--grace=30 : Seconds between SIGTERM and SIGKILL on a timed-out tenant}
        {--status-file= : Absolute path of the JSON progress file}
        {--force : Required in production (passed to tenants:migrate)}';

    protected $description = 'Migrate every ready tenant one by one with a per-tenant timeout; never stops at the first failure';

    /** @var array<string, array<string, mixed>> */
    private array $results = [];

    private string $startedAt = '';

    private int $total = 0;

    public function handle(): int
    {
        $timeout = max(10, (int) $this->option('timeout'));
        $grace = max(1, (int) $this->option('grace'));
        $statusFile = (string) $this->option('status-file');
        $only = array_filter((array) $this->option('tenants'));
        $this->startedAt = gmdate('c');

        $tenants = Tenant::query()->orderBy('id')->get();
        if ($only !== []) {
            $tenants = $tenants->filter(fn (Tenant $t): bool => in_array((string) $t->getKey(), $only, true));
        }
        $this->total = $tenants->count();
        $this->writeStatus($statusFile, 'running');

        foreach ($tenants as $tenant) {
            $id = (string) $tenant->getKey();
            if (! $tenant->isProvisioned()) {
                $this->results[$id] = ['status' => 'skipped', 'reason' => 'not_ready_or_purged', 'seconds' => 0];
                $this->warn("skipped $id (workspace not ready or purged)");
                $this->writeStatus($statusFile, 'running');

                continue;
            }
            $this->results[$id] = $this->migrateOne($id, $timeout, $grace);
            $this->writeStatus($statusFile, 'running');
        }

        $this->writeStatus($statusFile, 'done');

        return $this->report();
    }

    /** @return array<string, mixed> */
    private function migrateOne(string $id, int $timeout, int $grace): array
    {
        $started = microtime(true);
        $this->line("migrating $id ...");
        $process = new Process([
            PHP_BINARY, base_path('artisan'), 'tenants:migrate',
            '--tenants='.$id, '--force', '--no-interaction',
        ], base_path());
        $process->setTimeout(null);

        try {
            $process->start();
            while ($process->isRunning()) {
                if (microtime(true) - $started > $timeout) {
                    // SIGTERM first so PHP can finish the current statement's bookkeeping.
                    $process->stop($grace, defined('SIGTERM') ? SIGTERM : null);

                    return $this->outcome('timeout', $started, $process, $id);
                }
                usleep(250_000);
            }
        } catch (Throwable $e) {
            return $this->outcome('failed', $started, $process, $id, $e::class);
        }

        return $this->outcome($process->getExitCode() === 0 ? 'ok' : 'failed', $started, $process, $id);
    }

    /** @return array<string, mixed> */
    private function outcome(string $status, float $started, Process $process, string $id, ?string $error = null): array
    {
        $seconds = (int) round(microtime(true) - $started);
        $result = ['status' => $status, 'seconds' => $seconds, 'exit_code' => $process->getExitCode()];
        if ($error !== null) {
            $result['error'] = $error;
        }
        if ($status !== 'ok') {
            $tail = array_slice(preg_split('/\R/', trim($process->getOutput()."\n".$process->getErrorOutput())) ?: [], -15);
            $this->error("$id $status after {$seconds}s");
            foreach ($tail as $line) {
                $this->line('    '.$line);
            }
        } else {
            $this->info("$id ok ({$seconds}s)");
        }

        return $result;
    }

    private function report(): int
    {
        $by = ['ok' => [], 'failed' => [], 'timeout' => [], 'skipped' => []];
        foreach ($this->results as $id => $r) {
            $by[(string) $r['status']][] = $id;
        }
        $this->newLine();
        $this->line(sprintf(
            'tenants:migrate-all report: ok=%d failed=%d timeout=%d skipped=%d (total %d)',
            count($by['ok']), count($by['failed']), count($by['timeout']), count($by['skipped']), $this->total
        ));
        foreach (['failed', 'timeout', 'skipped'] as $kind) {
            if ($by[$kind] !== []) {
                $this->line("  $kind: ".implode(', ', $by[$kind]));
            }
        }

        return ($by['failed'] === [] && $by['timeout'] === []) ? self::SUCCESS : self::FAILURE;
    }

    private function writeStatus(string $file, string $state): void
    {
        if ($file === '') {
            return;
        }
        $counts = array_count_values(array_map(fn (array $r): string => (string) $r['status'], $this->results));
        $payload = [
            'state' => $state,
            'pid' => getmypid(),
            'started_at' => $this->startedAt,
            'updated_at' => gmdate('c'),
            'total' => $this->total,
            'done' => count($this->results),
            'counts' => $counts,
            'results' => $this->results,
        ];
        $tmp = $file.'.tmp';
        File::put($tmp, (string) json_encode($payload, JSON_PRETTY_PRINT));
        rename($tmp, $file);
    }
}
