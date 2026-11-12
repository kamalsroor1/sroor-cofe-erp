<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Actions\Perf\SeedPerfDatasetAction;
use App\DTOs\Perf\PerfSeedOptions;
use App\Models\Tenant;
use Illuminate\Console\Command;
use Throwable;

/**
 * PERF-04: `tenant:seed-perf {tenant}` — a deterministic performance dataset for ONE tenant
 * (local / testing environments only, no override). See docs/07-operations/perf-testing.md.
 */
final class SeedPerfDataCommand extends Command
{
    protected $signature = 'tenant:seed-perf
        {tenant : Tenant id or one of its domains}
        {--invoices= : Exact number of sales invoices (default 5000)}
        {--items= : Number of items (default 500)}
        {--stores= : Number of branches to spread the data over, created when missing (default 3)}
        {--days=365 : Days of history ending today}
        {--customers= : Number of account customers (default invoices/25, 20..2000)}
        {--suppliers= : Number of suppliers (default 20)}';

    public function __construct(
        private readonly SeedPerfDatasetAction $seedPerfDataset,
    ) {
        parent::__construct();

        $this->setDescription((string) __('console.perf_seed.description'));
    }

    public function handle(): int
    {
        // Before any tenant lookup: never against production / staging data, no override flag.
        if (! app()->environment(['local', 'testing'])) {
            $this->error((string) __('console.perf_seed.refused_environment', ['env' => (string) app()->environment()]));

            return self::FAILURE;
        }

        $options = PerfSeedOptions::fromArray([
            'invoices' => $this->stringOption('invoices'),
            'items' => $this->stringOption('items'),
            'stores' => $this->stringOption('stores'),
            'days' => $this->stringOption('days'),
            'customers' => $this->stringOption('customers'),
            'suppliers' => $this->stringOption('suppliers'),
        ]);
        foreach ($options->problems() as $problem) {
            $this->error((string) __($problem['key'], $problem['params']));
        }
        if ($options->problems() !== []) {
            return self::FAILURE;
        }

        $identifier = (string) $this->argument('tenant');
        $tenant = Tenant::query()->find($identifier)
            ?? Tenant::query()->whereHas('domains', fn ($q) => $q->where('domain', $identifier))->first();
        if (! $tenant instanceof Tenant) {
            $this->error((string) __('console.perf_seed.tenant_not_found', ['tenant' => $identifier]));

            return self::FAILURE;
        }

        $started = microtime(true);

        try {
            $result = $tenant->run(fn (): array => $this->seedPerfDataset->execute($options));
        } catch (Throwable $e) {
            $this->error((string) __('console.perf_seed.failed', ['tenant' => $tenant->getTenantKey(), 'error' => $e->getMessage()]));

            return self::FAILURE;
        }

        if ($result['status'] === 'conflict') {
            $this->error((string) __('console.perf_seed.conflict', ['tenant' => $tenant->getTenantKey(), 'options' => (string) json_encode($result['options'])]));

            return self::FAILURE;
        }

        if ($result['status'] === 'already_seeded') {
            $this->info((string) __('console.perf_seed.already_seeded', ['tenant' => $tenant->getTenantKey()]));

            return self::SUCCESS;
        }

        $this->table(
            [__('console.perf_seed.col_rows'), __('console.perf_seed.col_count')],
            array_map(fn (string $key, int $count): array => [$key, $count], array_keys($result['counts']), $result['counts']),
        );
        $this->info((string) __('console.perf_seed.done', [
            'tenant' => $tenant->getTenantKey(),
            'seconds' => number_format(microtime(true) - $started, 1, '.', ''),
        ]));

        return self::SUCCESS;
    }

    private function stringOption(string $name): ?string
    {
        $value = $this->option($name);

        return is_scalar($value) && ! is_bool($value) ? trim((string) $value) : null;
    }
}
