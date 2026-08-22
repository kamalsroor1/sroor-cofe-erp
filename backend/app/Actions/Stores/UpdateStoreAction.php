<?php

declare(strict_types=1);

namespace App\Actions\Stores;

use App\DTOs\Stores\StoreDTO;
use App\Models\Store;
use Illuminate\Support\Facades\DB;

final class UpdateStoreAction
{
    /**
     * Update existing store/branch inside a DB transaction
     */
    public function execute(Store $store, StoreDTO $dto): Store
    {
        return DB::transaction(function () use ($store, $dto) {
            if ($dto->is_main && !$store->is_main) {
                Store::where('id', '!=', $store->id)->update(['is_main' => false]);
            }

            $store->update([
                'name'      => $dto->name,
                'code'      => $dto->code ?? $store->code,
                'type'      => $dto->type,
                'address'   => $dto->address,
                'phone'     => $dto->phone,
                'is_active' => $dto->is_active,
                'is_main'   => $dto->is_main,
            ]);

            return $store->fresh();
        });
    }
}
