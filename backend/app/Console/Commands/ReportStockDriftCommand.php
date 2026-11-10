<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Actions\Reports\GetStockDriftReportAction;
use App\Models\Tenant;
use Illuminate\Console\Command;
use Throwable;

/**
 * Read-only audit command that reports items where SUM(store_stocks.quantity where deleted_at is null)
 * differs from items.current_stock across provisioned tenants (or a single --tenant=).
 */
final class ReportStockDriftCommand extends Command
{
    protected $signature = 'stock:report-drift
        {--tenant= : Limit to a single tenant id}';

    public function __construct(
        private readonly GetStockDriftReportAction $getStockDriftReportAction,
    ) {
        parent::__construct();

        $this->setDescription((string) __('console.stock_drift.description'));
    }

    public function handle(): int
    {
        $query = Tenant::query();
        $tenantId = $this->option('tenant');

        if ($tenantId === null || $tenantId === '') {
            $query->provisioned();
        } else {
            $query->whereKey($tenantId);

            if (! (clone $query)->exists()) {
                $this->error((string) __('console.stock_drift.tenant_not_found', ['tenant' => $tenantId]));

                return self::FAILURE;
            }
        }

        $rows = [];
        $total = 0;
        $affected = 0;

        foreach ($query->cursor() as $tenant) {
            try {
                /** @var list<array{id: int, code: string|null, name: string, unit: string, current_stock: string, stores_stock_sum: string, drift: string, difference: string}> $items */
                $items = $tenant->run(fn (): array => $this->getStockDriftReportAction->execute(1, 0)['items']);
            } catch (Throwable $e) {
                $this->warn((string) __('console.stock_drift.tenant_failed', [
                    'tenant' => $tenant->getTenantKey(),
                    'error' => $e->getMessage(),
                ]));

                continue;
            }

            $total++;
            $affected += count($items);

            foreach ($items as $item) {
                $rows[] = [
                    $tenant->getTenantKey(),
                    $item['id'],
                    $item['code'] ?? '-',
                    $item['name'],
                    $item['current_stock'],
                    $item['stores_stock_sum'],
                    $item['drift'],
                ];
            }
        }

        if ($total === 0) {
            $this->info((string) __('console.stock_drift.no_tenants'));

            return self::SUCCESS;
        }

        if ($rows !== []) {
            $this->table([
                __('console.stock_drift.col_tenant'),
                __('console.stock_drift.col_id'),
                __('console.stock_drift.col_code'),
                __('console.stock_drift.col_name'),
                __('console.stock_drift.col_current_stock'),
                __('console.stock_drift.col_stores_stock'),
                __('console.stock_drift.col_drift'),
            ], $rows);
        }

        $this->info((string) __('console.stock_drift.summary', [
            'count' => $affected,
            'total' => $total,
        ]));

        return self::SUCCESS;
    }
}
