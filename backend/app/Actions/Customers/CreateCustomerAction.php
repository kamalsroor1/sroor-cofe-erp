<?php

declare(strict_types=1);

namespace App\Actions\Customers;

use App\DTOs\Customers\CustomerDTO;
use App\Models\Customer;
use Illuminate\Support\Facades\DB;

final class CreateCustomerAction
{
    /**
     * Create customer inside DB transaction
     */
    public function execute(CustomerDTO $dto): Customer
    {
        return DB::transaction(function () use ($dto) {
            return Customer::create([
                'name'            => $dto->name,
                'phone'           => $dto->phone,
                'address'         => $dto->address,
                'tax_number'      => $dto->tax_number,
                'current_balance' => $dto->opening_balance,
                'is_active'       => $dto->is_active,
                'notes'           => $dto->notes,
            ]);
        });
    }
}
