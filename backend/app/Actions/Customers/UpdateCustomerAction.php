<?php

declare(strict_types=1);

namespace App\Actions\Customers;

use App\DTOs\Customers\CustomerDTO;
use App\Models\Customer;
use Illuminate\Support\Facades\DB;

final class UpdateCustomerAction
{
    /**
     * Update customer inside DB transaction
     */
    public function execute(Customer $customer, CustomerDTO $dto): Customer
    {
        return DB::transaction(function () use ($customer, $dto) {
            $customer->update([
                'name'       => $dto->name,
                'phone'      => $dto->phone,
                'address'    => $dto->address,
                'tax_number' => $dto->tax_number,
                'notes'      => $dto->notes,
                'is_active'  => $dto->is_active,
            ]);

            return $customer->fresh();
        });
    }
}
