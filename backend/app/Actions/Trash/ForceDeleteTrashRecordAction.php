<?php

declare(strict_types=1);

namespace App\Actions\Trash;

use App\Models\Customer;
use App\Models\Expense;
use App\Models\Item;
use App\Models\ReturnDocument;
use App\Models\Store;
use App\Models\Supplier;
use App\Models\User;
use App\Support\Trash\TrashScope;
use Illuminate\Validation\ValidationException;

final class ForceDeleteTrashRecordAction
{
    /**
     * Permanently delete soft-deleted record by type and id.
     *
     * SEC-21: looked up through TrashScope::findForWrite, so a record of a branch the actor cannot
     * access (read-only stores.view_all is not enough), or a deleted branch without stores.manage,
     * is "not found" (404), never purged.
     */
    public function execute(string $type, int $id, User $actor): bool
    {
        $model = match ($type) {
            'items' => TrashScope::findForWrite(Item::onlyTrashed(), $actor, $id),
            'customers' => TrashScope::findForWrite(Customer::onlyTrashed(), $actor, $id),
            'suppliers' => TrashScope::findForWrite(Supplier::onlyTrashed(), $actor, $id),
            'stores' => TrashScope::findForWrite(Store::onlyTrashed(), $actor, $id),
            'expenses' => TrashScope::findForWrite(Expense::onlyTrashed(), $actor, $id),
            'returns' => TrashScope::findForWrite(ReturnDocument::onlyTrashed(), $actor, $id),
            default => throw ValidationException::withMessages(['type' => __('common.trash_invalid_type')]),
        };

        return (bool) $model->forceDelete();
    }
}
