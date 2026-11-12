<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Actions\Perf\SnapshotTenantStockAction;
use App\Actions\Perf\VerifyTenantConsistencyAction;
use App\Models\Tenant;
use Illuminate\Console\Command;
use Throwable;

/**
 * PERF-04: `tenant:perf-verify {tenant}` — read-only consistency check of one tenant after a
 * load test (duplicate numbers, stock drift / negative stock, one voucher per paid invoice,
 * party balances, and with --baseline: stock == start - sold). Exit code 1 on any problem.
 *
 * --snapshot=FILE writes the starting state for a later --baseline=FILE run and checks nothing.
 */
final class VerifyPerfConsistencyCommand extends Command
{
    protected $signature = 'tenant:perf-verify
        {tenant : Tenant id or one of its domains}
        {--snapshot= : Write the current branch balances to this JSON file and exit}
        {--baseline= : Also compare branch balances with a snapshot file (start - sold + returned + other movements)}';

    public function __construct(
        private readonly VerifyTenantConsistencyAction $verify,
        private readonly SnapshotTenantStockAction $snapshot,
    ) {
        parent::__construct();

        $this->setDescription((string) __('console.perf_verify.description'));
    }

    public function handle(): int
    {
        $identifier = (string) $this->argument('tenant');
        $tenant = Tenant::query()->find($identifier)
            ?? Tenant::query()->whereHas('domains', fn ($q) => $q->where('domain', $identifier))->first();
        if (! $tenant instanceof Tenant) {
            $this->error((string) __('console.perf_verify.tenant_not_found', ['tenant' => $identifier]));

            return self::FAILURE;
        }

        $snapshotFile = $this->stringOption('snapshot');
        if ($snapshotFile !== null) {
            $state = $tenant->run(fn (): array => $this->snapshot->execute());
            if (@file_put_contents($snapshotFile, (string) json_encode($state)) === false) {
                $this->error((string) __('console.perf_verify.file_unwritable', ['file' => $snapshotFile]));

                return self::FAILURE;
            }
            $this->info((string) __('console.perf_verify.snapshot_written', ['file' => $snapshotFile, 'rows' => count($state['stocks'])]));

            return self::SUCCESS;
        }

        $baseline = null;
        $baselineFile = $this->stringOption('baseline');
        if ($baselineFile !== null) {
            $baseline = $this->readBaseline($baselineFile);
            if ($baseline === null) {
                $this->error((string) __('console.perf_verify.baseline_invalid', ['file' => $baselineFile]));

                return self::FAILURE;
            }
        }

        try {
            $result = $tenant->run(fn (): array => $this->verify->execute($baseline));
        } catch (Throwable $e) {
            $this->error((string) __('console.perf_verify.failed', ['tenant' => $tenant->getTenantKey(), 'error' => $e->getMessage()]));

            return self::FAILURE;
        }

        foreach ($result['problems'] as $problem) {
            $this->error((string) __('console.perf_verify.problems.'.$problem['check'], ['count' => $problem['count']]));
            foreach ($problem['sample'] as $row) {
                $this->line('  - '.$row);
            }
        }

        if ($result['problems'] !== []) {
            $this->error((string) __('console.perf_verify.failed_summary', ['tenant' => $tenant->getTenantKey(), 'count' => count($result['problems'])]));

            return self::FAILURE;
        }

        $this->info((string) __('console.perf_verify.passed', [
            'tenant' => $tenant->getTenantKey(),
            'invoices' => $result['checked']['invoices'] ?? 0,
            'baseline' => $baseline !== null ? __('console.perf_verify.with_baseline') : __('console.perf_verify.without_baseline'),
        ]));

        return self::SUCCESS;
    }

    /**
     * @return array{max_ids: array{invoices: int, returns: int, stock_movements: int}, stocks: array<string, string>}|null
     */
    private function readBaseline(string $file): ?array
    {
        $raw = is_file($file) ? file_get_contents($file) : false;
        $data = is_string($raw) ? json_decode($raw, true) : null;
        if (! is_array($data) || ! is_array($data['max_ids'] ?? null) || ! is_array($data['stocks'] ?? null)) {
            return null;
        }

        $stocks = [];
        foreach ($data['stocks'] as $key => $quantity) {
            if (! is_string($key) || ! is_string($quantity) || preg_match('/^\d+:\d+$/', $key) !== 1 || ! is_numeric($quantity)) {
                return null;
            }
            $stocks[$key] = $quantity;
        }

        return [
            'max_ids' => [
                'invoices' => (int) ($data['max_ids']['invoices'] ?? 0),
                'returns' => (int) ($data['max_ids']['returns'] ?? 0),
                'stock_movements' => (int) ($data['max_ids']['stock_movements'] ?? 0),
            ],
            'stocks' => $stocks,
        ];
    }

    private function stringOption(string $name): ?string
    {
        $value = $this->option($name);

        return is_string($value) && trim($value) !== '' ? trim($value) : null;
    }
}
