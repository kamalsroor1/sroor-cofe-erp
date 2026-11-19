<?php

declare(strict_types=1);

namespace App\Actions\Customers;

use App\DTOs\Customers\CustomerDTO;
use App\Models\Customer;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class UpdateCustomerAction
{
    public function __construct(private readonly ResolveWalkInCustomerAction $walkIn) {}

    /**
     * Update customer inside DB transaction
     */
    public function execute(Customer $customer, CustomerDTO $dto): Customer
    {
        // PLAN-AUDIT: the walk-in customer keeps its name and phone. Renamed, it would count as a
        // normal customer and the next sale without a customer would create a new walk-in outside
        // the plan's customers limit (repeatable: unlimited customers).
        if ($this->walkIn->isWalkIn($customer)
            && ((string) $dto->name !== (string) $customer->name || (string) $dto->phone !== (string) $customer->phone)) {
            throw ValidationException::withMessages(['name' => __('customers.walk_in_identity_locked')]);
        }

        return DB::transaction(function () use ($customer, $dto) {
            $attributes = [
                'name' => $dto->name,
                'phone' => $dto->phone,
                'address' => $dto->address,
                'tax_number' => $dto->tax_number,
                'notes' => $dto->notes,
                'is_active' => $dto->is_active,
            ];
            // SHOP-03: an omitted credit_limit keeps the stored limit (older clients); null = no limit.
            if ($dto->credit_limit_provided) {
                $attributes['credit_limit'] = $dto->credit_limit;
            }

            $customer->update($attributes);

            return $customer->fresh();
        });
    }
}
