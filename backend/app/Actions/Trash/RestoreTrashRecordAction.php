<?php

declare(strict_types=1);

namespace App\Actions\Trash;

use App\Models\Customer;
use App\Models\Expense;
use App\Models\Item;
use App\Models\ReturnDocument;
use App\Models\Store;
use App\Models\Supplier;
use Exception;

final class RestoreTrashRecordAction
{
    /**
     * Restore soft-deleted record by type and id
     */
    public function execute(string $type, int $id): bool
    {
        $model = match ($type) {
            'items'     => Item::onlyTrashed()->findOrFail($id),
            'customers' => Customer::onlyTrashed()->findOrFail($id),
            'suppliers' => Supplier::onlyTrashed()->findOrFail($id),
            'stores'    => Store::onlyTrashed()->findOrFail($id),
            'expenses'  => Expense::onlyTrashed()->findOrFail($id),
            'returns'   => ReturnDocument::onlyTrashed()->findOrFail($id),
            default     => throw new Exception('نوع السجل غير صالح للاسترجاع'),
        };

        return (bool)$model->restore();
    }
}
