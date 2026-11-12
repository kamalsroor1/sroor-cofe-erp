<?php

declare(strict_types=1);

namespace App\Actions\Perf;

use App\Support\Money\Decimal;
use Illuminate\Support\Facades\DB;

/**
 * PERF-04: the state VerifyTenantConsistencyAction::execute($baseline) compares against after a
 * load test: every branch balance of the CURRENT tenant and the highest document / movement ids
 * (everything above them happened during the test). Read-only.
 */
final class SnapshotTenantStockAction
{
    /**
     * @return array{max_ids: array{invoices: int, returns: int, stock_movements: int}, stocks: array<string, string>}
     */
    public function execute(): array
    {
        $stocks = [];
        foreach (DB::table('store_stocks')->whereNull('deleted_at')->orderBy('id')->select(['id', 'store_id', 'item_id', 'quantity'])->lazy(2_000) as $row) {
            $stocks[$row->store_id.':'.$row->item_id] = Decimal::normalize((string) $row->quantity);
        }

        return [
            'max_ids' => [
                'invoices' => (int) DB::table('invoices')->max('id'),
                'returns' => (int) DB::table('returns')->max('id'),
                'stock_movements' => (int) DB::table('stock_movements')->max('id'),
            ],
            'stocks' => $stocks,
        ];
    }
}
