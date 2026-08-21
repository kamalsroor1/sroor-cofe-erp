<?php

declare(strict_types=1);

namespace App\Actions\Customers;

use App\Models\Customer;

final class ToggleCustomerActiveAction
{
    /**
     * Toggle active state of customer
     */
    public function execute(Customer $customer): Customer
    {
        $customer->update(['is_active' => !$customer->is_active]);

        return $customer->fresh();
    }
}
