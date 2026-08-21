<?php

declare(strict_types=1);

namespace App\Actions\Suppliers;

use App\DTOs\Suppliers\SupplierDTO;
use App\Models\Supplier;
use Illuminate\Support\Facades\DB;

final class UpdateSupplierAction
{
    /**
     * Update supplier inside DB transaction
     */
    public function execute(Supplier $supplier, SupplierDTO $dto): Supplier
    {
        return DB::transaction(function () use ($supplier, $dto) {
            $supplier->update([
                'name'         => $dto->name,
                'company_name' => $dto->company_name,
                'phone'        => $dto->phone,
                'address'      => $dto->address,
                'notes'        => $dto->notes,
                'is_active'    => $dto->is_active,
            ]);

            return $supplier->fresh();
        });
    }
}
