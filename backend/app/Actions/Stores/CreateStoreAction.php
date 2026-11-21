<?php

declare(strict_types=1);

namespace App\Actions\Stores;

use App\DTOs\Stores\StoreDTO;
use App\Models\Store;
use App\Models\User;
use App\Services\Entitlements\PlanEnforcer;
use App\Services\StoreStockInitializer;
use App\Support\StoreLimitBucket;
use Illuminate\Support\Facades\DB;

final class CreateStoreAction
{
    public function __construct(
        private readonly StoreStockInitializer $stockInitializer,
        private readonly PlanEnforcer $planEnforcer,
    ) {}

    /**
     * Create a new store/branch inside a DB transaction
     */
    public function execute(StoreDTO $dto, ?User $creator = null): Store
    {
        return DB::transaction(function () use ($dto, $creator) {
            // SEC-06 / BE-12: plan limit (stores / warehouses / vans), counted under the tenant lock.
            $resource = StoreLimitBucket::resourceFor($dto->type);
            $this->planEnforcer->assertWithinLimit($resource, fn (): int => StoreLimitBucket::count($resource));

            if ($dto->is_main) {
                Store::where('is_main', true)->update(['is_main' => false]);
            }

            $generatedCode = $dto->code ?? $this->nextFreeCode($dto->type);

            $store = Store::create([
                'name' => $dto->name,
                'code' => $generatedCode,
                'type' => $dto->type,
                'address' => $dto->address,
                'phone' => $dto->phone,
                'is_active' => $dto->is_active,
                'is_main' => $dto->is_main,
            ]);

            // BE-04: every item gets a zero store_stocks row in the new store.
            $this->stockInitializer->seedStore((int) $store->id);

            if ($creator) {
                $store->users()->syncWithoutDetaching([$creator->id]);
            }

            return $store;
        });
    }

    /**
     * `RET-004`-style code that no store (trashed ones included: the column is unique) uses yet.
     * It used to be a random 100-999 suffix, which collided on the unique index now and then
     * and turned an ordinary "add branch" into a 500.
     */
    private function nextFreeCode(string $type): string
    {
        $prefix = strtoupper(substr($type, 0, 3)).'-';
        $next = (int) Store::withTrashed()->max('id') + 1;

        do {
            $code = $prefix.str_pad((string) $next, 3, '0', STR_PAD_LEFT);
            $next++;
        } while (Store::withTrashed()->where('code', $code)->exists());

        return $code;
    }
}
