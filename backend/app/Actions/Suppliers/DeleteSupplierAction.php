<?php

declare(strict_types=1);

namespace App\Actions\Suppliers;

use App\Models\Supplier;
use Illuminate\Validation\ValidationException;

final class DeleteSupplierAction
{
    /**
     * Delete supplier or throw if financial history/balance blocks deletion
     */
    public function execute(Supplier $supplier): bool
    {
        if (!$supplier->canBeDeleted()) {
            $blockers = implode(', ', $supplier->getDeletionBlockers());
            throw ValidationException::withMessages([
                'supplier' => [__('contacts.cannot_delete_has_balance') ?: "لا يمكن حذف المورد ({$supplier->name}) لوجود موانع: {$blockers}"],
            ]);
        }

        return (bool)$supplier->delete();
    }
}
