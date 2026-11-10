<?php

declare(strict_types=1);

namespace App\Actions\Reports;

use App\Models\Item;
use Illuminate\Support\Facades\DB;

/**
 * Computes items whose non-deleted branch balances (SUM(store_stocks.quantity))
 * differ from items.current_stock using bcmath at scale 3.
 */
final class GetStockDriftReportAction
{
    private const CHUNK = 500;

    /**
     * @return array{
     *     items: list<array{
     *         id: int,
     *         code: string|null,
     *         name: string,
     *         unit: string,
     *         current_stock: string,
     *         stores_stock_sum: string,
     *         drift: string,
     *         difference: string
     *     }>,
     *     meta: array{current_page: int, last_page: int, per_page: int, total: int}
     * }
     */
    public function execute(int $page = 1, int $perPage = 25, ?string $search = null): array
    {
        $driftItems = $this->collectDriftItems($search);
        $total = count($driftItems);

        if ($perPage <= 0) {
            return [
                'items' => $driftItems,
                'meta' => [
                    'current_page' => 1,
                    'last_page' => 1,
                    'per_page' => $total,
                    'total' => $total,
                ],
            ];
        }

        $safePage = max(1, $page);
        $safePerPage = max(1, min(200, $perPage));
        $lastPage = max(1, (int) ceil($total / $safePerPage));
        $offset = ($safePage - 1) * $safePerPage;
        $pagedItems = array_slice($driftItems, $offset, $safePerPage);

        return [
            'items' => $pagedItems,
            'meta' => [
                'current_page' => $safePage,
                'last_page' => $lastPage,
                'per_page' => $safePerPage,
                'total' => $total,
            ],
        ];
    }

    /**
     * @return list<array{
     *     id: int,
     *     code: string|null,
     *     name: string,
     *     unit: string,
     *     current_stock: string,
     *     stores_stock_sum: string,
     *     drift: string,
     *     difference: string
     * }>
     */
    private function collectDriftItems(?string $search = null): array
    {
        $query = Item::query()
            ->select(['id', 'code', 'name', 'unit', 'current_stock'])
            ->orderBy('id');

        $trimmed = trim((string) $search);
        if ($trimmed !== '') {
            $query->where(function ($q) use ($trimmed): void {
                $q->where('name', 'like', "%{$trimmed}%")
                    ->orWhere('code', 'like', "%{$trimmed}%");
            });
        }

        $drifted = [];

        $query->chunkById(self::CHUNK, function ($items) use (&$drifted): void {
            $itemIds = $items->pluck('id')->all();
            if ($itemIds === []) {
                return;
            }

            $stocksByItem = DB::table('store_stocks')
                ->whereIn('item_id', $itemIds)
                ->whereNull('deleted_at')
                ->get(['item_id', 'quantity'])
                ->groupBy('item_id');

            foreach ($items as $item) {
                $rows = $stocksByItem->get($item->id, collect());
                $sum = '0.000';
                foreach ($rows as $row) {
                    $sum = bcadd($sum, (string) $row->quantity, 3);
                }

                $currentStock = bcadd((string) ($item->current_stock ?? '0'), '0', 3);

                if (bccomp($sum, $currentStock, 3) !== 0) {
                    $drift = bcsub($sum, $currentStock, 3);
                    $code = (string) $item->code;
                    $drifted[] = [
                        'id' => (int) $item->id,
                        'code' => $code !== '' ? $code : null,
                        'name' => (string) $item->name,
                        'unit' => (string) ($item->unit ?: 'piece'),
                        'current_stock' => $currentStock,
                        'stores_stock_sum' => $sum,
                        'drift' => $drift,
                        'difference' => $drift,
                    ];
                }
            }
        });

        return $drifted;
    }
}
