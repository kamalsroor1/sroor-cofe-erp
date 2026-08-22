<?php

declare(strict_types=1);

namespace App\Actions\Stores;

use App\DTOs\Stores\StoreDTO;
use App\Models\Store;
use App\Models\User;
use Illuminate\Support\Facades\DB;

final class CreateStoreAction
{
    /**
     * Create a new store/branch inside a DB transaction
     */
    public function execute(StoreDTO $dto, ?User $creator = null): Store
    {
        return DB::transaction(function () use ($dto, $creator) {
            if ($dto->is_main) {
                Store::where('is_main', true)->update(['is_main' => false]);
            }

            $generatedCode = $dto->code ?? (strtoupper(substr($dto->type, 0, 3)) . '-' . rand(100, 999));

            $store = Store::create([
                'name'      => $dto->name,
                'code'      => $generatedCode,
                'type'      => $dto->type,
                'address'   => $dto->address,
                'phone'     => $dto->phone,
                'is_active' => $dto->is_active,
                'is_main'   => $dto->is_main,
            ]);

            if ($creator) {
                $store->users()->syncWithoutDetaching([$creator->id]);
            }

            return $store;
        });
    }
}
