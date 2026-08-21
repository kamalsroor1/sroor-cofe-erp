<?php

declare(strict_types=1);

namespace App\Actions\Suppliers;

use App\DTOs\Suppliers\SupplierDTO;
use App\Models\Supplier;
use Illuminate\Support\Facades\DB;

final class CreateSupplierAction
{
    /**
     * Create supplier inside DB transaction
     */
    public function execute(SupplierDTO $dto): Supplier
    {
        return DB::transaction(function () use ($dto) {
            return Supplier::create([
                'name'            => $dto->name,
                'company_name'    => $dto->company_name,
                'phone'           => $dto->phone,
                'address'         => $dto->address,
                'current_balance' => $dto->opening_balance,
                'is_active'       => $dto->is_active,
                'notes'           => $dto->notes,
            ]);
        });
    }
}
