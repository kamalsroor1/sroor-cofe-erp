<?php

declare(strict_types=1);

namespace App\Actions\Suppliers;

use App\Models\Supplier;

final class ToggleSupplierActiveAction
{
    /**
     * Toggle active state of supplier
     */
    public function execute(Supplier $supplier): Supplier
    {
        $supplier->update(['is_active' => !$supplier->is_active]);

        return $supplier->fresh();
    }
}
