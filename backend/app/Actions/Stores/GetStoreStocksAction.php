<?php

declare(strict_types=1);

namespace App\Actions\Stores;

use App\Models\Store;
use App\Models\StoreStock;
use App\Services\Settings\TenantSettings;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

final class GetStoreStocksAction
{
    public function __construct(
        private readonly TenantSettings $tenantSettings,
    ) {}

    /**
     * Fetch filtered store stocks with valuations
     */
    public function execute(
        int $storeId,
        string $search = '',
        string $stockStatus = 'all',
        int $perPage = 20
    ): LengthAwarePaginator {
        $query = StoreStock::with('item')
            ->where('store_id', $storeId);

        if ($search !== '') {
            $query->whereHas('item', function ($iq) use ($search) {
                $iq->where('name', 'like', "%{$search}%")
                    ->orWhere('code', 'like', "%{$search}%");
            });
        }

        if ($stockStatus === 'low') {
            // SETG-10: the item's own minimum, else the tenant default threshold.
            $query->whereHas('item', function ($iq) {
                $this->tenantSettings->whereLowStock($iq, 'store_stocks.quantity', 'items.min_stock_level');
            });
        } elseif ($stockStatus === 'out') {
            $query->where('quantity', '<=', 0);
        }

        return $query->orderBy('id', 'desc')->paginate($perPage);
    }
}
