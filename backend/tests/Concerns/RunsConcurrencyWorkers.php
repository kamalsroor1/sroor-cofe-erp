<?php

declare(strict_types=1);

namespace Tests\Concerns;

use App\Models\Tenant;
use Illuminate\Database\Connection;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * QA-1 / BUG-07: real parallel PHP processes (tests/Support/concurrency-worker.php) against a
 * harness tenant on the MySQL/MariaDB test server, released together through a READY/GO barrier.
 * For TenantTestCase subclasses in the `mysql-only` group.
 */
trait RunsConcurrencyWorkers
{
    /** Seconds a worker may take to boot Laravel and connect before the race starts. */
    protected int $workerReadyTimeout = 60;

    /** A second MySQL session on the tenant's schema (a different transaction than the test's). */
    private function tenantSideConnection(Tenant $tenant, string $name): Connection
    {
        config([
            "database.connections.{$name}" => array_merge(
                (array) config('database.connections.'.$this->centralConnectionName()),
                ['database' => $this->tenantDatabaseName($tenant)],
            ),
        ]);

        return DB::connection($name);
    }

    /**
     * Start one PHP process per payload, wait until every process has booted and
     * connected, release them at the same moment and collect their JSON results.
     *
     * @param  list<array<string, mixed>>  $payloads
     * @return list<array<string, mixed>>
     */
    private function runWorkers(string $scenario, Tenant $tenant, array $payloads): array
    {
        $jobs = [];
        foreach ($payloads as $payload) {
            // A payload may pick its own scenario (checkout and update racing together).
            $workerScenario = (string) ($payload['__scenario'] ?? $scenario);
            unset($payload['__scenario']);
            $jobs[] = ['tenant' => $tenant, 'scenario' => $workerScenario, 'payload' => $payload];
        }

        return $this->runWorkerJobs(base_path('tests/Support/concurrency-worker.php'), $jobs);
    }

    /**
     * PERF-04: one process per job, each on ITS OWN tenant, all released together. Used with
     * tests/Support/batch-worker.php to run several tenants' business days at the same time.
     *
     * @param  list<array{tenant: Tenant, scenario: string, payload: array<string, mixed>}>  $jobs
     * @return list<array<string, mixed>>
     */
    private function runWorkerJobs(string $script, array $jobs): array
    {
        $workers = [];

        try {
            foreach ($jobs as $job) {
                $stderr = (string) tempnam(sys_get_temp_dir(), 'qa-worker-');
                $pipes = [];
                $process = proc_open(
                    [PHP_BINARY, $script, $job['scenario'], $this->tenantDatabaseName($job['tenant']), base64_encode((string) json_encode($job['payload']))],
                    [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['file', $stderr, 'w']],
                    $pipes,
                    base_path(),
                );

                if (! is_resource($process)) {
                    throw new RuntimeException('Could not start a concurrency worker.');
                }

                $workers[] = ['process' => $process, 'pipes' => $pipes, 'stderr' => $stderr];
            }

            foreach ($workers as $worker) {
                stream_set_timeout($worker['pipes'][1], $this->workerReadyTimeout);
                $line = trim((string) fgets($worker['pipes'][1]));
                if ($line !== 'READY') {
                    throw new RuntimeException('Worker did not become ready: '.$line.' '.(string) file_get_contents($worker['stderr']));
                }
            }

            // Barrier released: all workers hit the database at (almost) the same moment.
            foreach ($workers as $worker) {
                fwrite($worker['pipes'][0], "GO\n");
                fflush($worker['pipes'][0]);
            }

            $results = [];
            foreach ($workers as $worker) {
                $output = trim((string) stream_get_contents($worker['pipes'][1]));
                // Not \R: without /u it also splits on byte 0x85, which occurs inside Arabic UTF-8.
                $lines = preg_split('/\r?\n/', $output) ?: [];
                $decoded = json_decode((string) end($lines), true);

                if (! is_array($decoded) || ! array_key_exists('ok', $decoded)) {
                    throw new RuntimeException('Worker returned no result: '.$output.' '.(string) file_get_contents($worker['stderr']));
                }

                $results[] = $decoded;
            }

            return $results;
        } finally {
            foreach ($workers as $worker) {
                foreach ($worker['pipes'] as $pipe) {
                    if (is_resource($pipe)) {
                        fclose($pipe);
                    }
                }
                proc_close($worker['process']);
                @unlink($worker['stderr']);
            }
        }
    }
}
