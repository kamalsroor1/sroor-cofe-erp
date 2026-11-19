<?php

declare(strict_types=1);

namespace App\Actions\Treasury;

use App\DTOs\Treasury\TreasuryTransferFilterDTO;
use App\Filters\Treasury\TransferDateRangeFilter;
use App\Filters\Treasury\TransferMethodFilter;
use App\Filters\Treasury\TransferStoreFilter;
use App\Models\TreasuryTransfer;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Pipeline;

/**
 * SHOP-06: the treasury transfers history, newest first.
 */
final class ListTreasuryTransfersAction
{
    /**
     * @return LengthAwarePaginator<int, TreasuryTransfer>
     */
    public function execute(TreasuryTransferFilterDTO $filters): LengthAwarePaginator
    {
        /** @var Builder<TreasuryTransfer> $query */
        $query = Pipeline::send(TreasuryTransfer::query()->with(['creator', 'store']))
            ->through([
                new TransferStoreFilter($filters),
                new TransferDateRangeFilter($filters),
                new TransferMethodFilter($filters),
            ])
            ->thenReturn();

        return $query->latest('transfer_date')->latest('id')->paginate($filters->perPage);
    }
}
