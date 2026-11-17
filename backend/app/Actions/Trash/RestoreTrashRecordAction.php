<?php

declare(strict_types=1);

namespace App\Actions\Trash;

use App\Actions\Customers\ResolveWalkInCustomerAction;
use App\Enums\LimitResource;
use App\Models\Customer;
use App\Models\Expense;
use App\Models\Item;
use App\Models\Store;
use App\Models\Supplier;
use App\Models\User;
use App\Services\Entitlements\PlanEnforcer;
use App\Support\PartyLimitCounter;
use App\Support\StoreLimitBucket;
use App\Support\Trash\TrashScope;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class RestoreTrashRecordAction
{
    public function __construct(
        private readonly PlanEnforcer $planEnforcer,
        private readonly ResolveWalkInCustomerAction $walkInCustomer,
    ) {}

    /**
     * Restore soft-deleted record by type and id.
     *
     * SEC-21: looked up through TrashScope::findForWrite, so a record of a branch the actor cannot
     * access (read-only stores.view_all is not enough), or a deleted branch without stores.manage,
     * is "not found" (404), never restored.
     */
    public function execute(string $type, int $id, User $actor): bool
    {
        $model = match ($type) {
            'items' => TrashScope::findForWrite(Item::onlyTrashed(), $actor, $id),
            'customers' => TrashScope::findForWrite(Customer::onlyTrashed(), $actor, $id),
            'suppliers' => TrashScope::findForWrite(Supplier::onlyTrashed(), $actor, $id),
            'stores' => TrashScope::findForWrite(Store::onlyTrashed(), $actor, $id),
            'expenses' => TrashScope::findForWrite(Expense::onlyTrashed(), $actor, $id),
            // BE-02: deleting a return reverses its stock and balance; restoring would have to
            // re-apply them against stock that has since moved, so it is not offered.
            'returns' => throw ValidationException::withMessages(['type' => __('returns.restore_not_allowed')]),
            default => throw ValidationException::withMessages(['type' => __('common.trash_invalid_type')]),
        };

        // SEC-06 / BE-12: a restored item/store takes a plan slot again (delete + create + restore must not bypass the limit).
        // PLAN-11: same for customers and suppliers.
        return DB::transaction(function () use ($model): bool {
            if ($model instanceof Item) {
                $this->planEnforcer->assertWithinLimit(LimitResource::Items, fn (): int => Item::query()->count());
            } elseif ($model instanceof Store) {
                $resource = StoreLimitBucket::resourceFor((string) $model->type);
                $this->planEnforcer->assertWithinLimit($resource, fn (): int => StoreLimitBucket::count($resource));
            } elseif ($model instanceof Customer && ! $this->restoresTheOnlyWalkIn($model)) {
                $this->planEnforcer->assertWithinLimit(LimitResource::Customers, fn (): int => PartyLimitCounter::customers());
            } elseif ($model instanceof Supplier) {
                $this->planEnforcer->assertWithinLimit(LimitResource::Suppliers, fn (): int => PartyLimitCounter::suppliers());
            }

            return (bool) $model->restore();
        });
    }

    /**
     * PLAN-11: restoring the walk-in customer while the shop has no live one takes no plan slot
     * (PartyLimitCounter leaves one walk-in row out of the count), so it is never refused.
     */
    private function restoresTheOnlyWalkIn(Customer $customer): bool
    {
        return $this->walkInCustomer->isWalkIn($customer) && ! PartyLimitCounter::hasWalkInCustomer();
    }
}
