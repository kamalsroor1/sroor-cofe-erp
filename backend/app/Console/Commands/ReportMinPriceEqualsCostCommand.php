<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Item;
use App\Models\Tenant;
use Illuminate\Console\Command;
use Throwable;

/**
 * R3 data correction. Before the ItemDTO fix, items created without `min_selling_price`
 * got `min_selling_price = cost_price`, which the POS shows as the "wholesale / minimum
 * price" (a cost leak). This lists, per tenant, items where both are equal (and > 0).
 *
 * DRY-RUN by default. `--fix` resets the matches to '0.000' ("no price floor"). It is a
 * reviewed, manual operation: nothing in a migration touches data. Items whose floor was
 * deliberately set equal to cost are indistinguishable, so review the list first.
 */
final class ReportMinPriceEqualsCostCommand extends Command
{
    protected $signature = 'items:report-min-price-equals-cost
        {--tenant= : Limit to a single tenant id}
        {--fix : Set min_selling_price to 0.000 (no floor) on the matched items}';

    public function __construct()
    {
        parent::__construct();

        $this->setDescription((string) __('console.min_price_equals_cost.description'));
    }

    public function handle(): int
    {
        $fix = (bool) $this->option('fix');
        $query = Tenant::query();
        $tenantId = $this->option('tenant');

        if ($tenantId === null || $tenantId === '') {
            $query->provisioned();
        } else {
            $query->whereKey($tenantId);

            if (! (clone $query)->exists()) {
                $this->error((string) __('console.min_price_equals_cost.tenant_not_found', ['tenant' => $tenantId]));

                return self::FAILURE;
            }
        }

        $rows = [];
        $total = 0;
        $affected = 0;

        foreach ($query->cursor() as $tenant) {
            try {
                /** @var list<array{id: int, code: string|null, name: string, cost_price: string}> $items */
                $items = $tenant->run(fn (): array => $this->process($fix));
            } catch (Throwable $e) {
                $this->warn((string) __('console.min_price_equals_cost.tenant_failed', [
                    'tenant' => $tenant->getTenantKey(),
                    'error' => $e->getMessage(),
                ]));

                continue;
            }

            $total++;
            $affected += count($items);

            foreach ($items as $item) {
                $rows[] = [$tenant->getTenantKey(), $item['id'], $item['code'] ?? '-', $item['name'], $item['cost_price']];
            }
        }

        if ($total === 0) {
            $this->info((string) __('console.min_price_equals_cost.no_tenants'));

            return self::SUCCESS;
        }

        if ($rows !== []) {
            $this->table([
                __('console.min_price_equals_cost.col_tenant'),
                __('console.min_price_equals_cost.col_id'),
                __('console.min_price_equals_cost.col_code'),
                __('console.min_price_equals_cost.col_name'),
                __('console.min_price_equals_cost.col_cost'),
            ], $rows);
        }

        $this->info((string) __($fix ? 'console.min_price_equals_cost.fixed' : 'console.min_price_equals_cost.dry_run', [
            'count' => $affected,
            'total' => $total,
        ]));

        return self::SUCCESS;
    }

    /**
     * @return list<array{id: int, code: string|null, name: string, cost_price: string}>
     */
    private function process(bool $fix): array
    {
        $query = Item::query()
            ->whereColumn('min_selling_price', 'cost_price')
            ->where('min_selling_price', '>', 0);

        $items = (clone $query)
            ->orderBy('id')
            ->get(['id', 'code', 'name', 'cost_price'])
            ->map(fn (Item $item): array => [
                'id' => (int) $item->id,
                'code' => $item->code,
                'name' => (string) $item->name,
                'cost_price' => (string) $item->cost_price,
            ])
            ->all();

        if ($fix && $items !== []) {
            $query->update(['min_selling_price' => '0.000']);
        }

        return $items;
    }
}
