<?php

declare(strict_types=1);

namespace App\Actions\Customers;

use App\Models\Customer;
use Illuminate\Validation\ValidationException;

final class DeleteCustomerAction
{
    /**
     * Delete customer or throw if financial history/balance blocks deletion
     */
    public function execute(Customer $customer): bool
    {
        if (!$customer->canBeDeleted()) {
            $blockers = implode(', ', $customer->getDeletionBlockers());
            throw ValidationException::withMessages([
                'customer' => [__('contacts.cannot_delete_has_balance') ?: "لا يمكن حذف العميل ({$customer->name}) لوجود موانع: {$blockers}"],
            ]);
        }

        return (bool)$customer->delete();
    }
}
