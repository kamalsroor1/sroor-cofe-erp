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
        if (! $supplier->canBeDeleted()) {
            throw ValidationException::withMessages([
                'supplier' => [__('contacts.cannot_delete_has_balance')],
            ]);
        }

        return (bool) $supplier->delete();
    }
}
