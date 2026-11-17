<?php

declare(strict_types=1);

namespace App\Support;

use App\Actions\Customers\ResolveWalkInCustomerAction;
use App\Enums\LimitResource;
use App\Models\Customer;
use App\Models\Supplier;
use InvalidArgumentException;

/**
 * PLAN-11: live usage of the `customers` / `suppliers` plan limits (tenant DB). Soft-deleted rows
 * do not count (a restore takes the slot back, RestoreTrashRecordAction); inactive ones do
 * (deactivating must not free a slot).
 *
 * The shop's walk-in (cash) customer is a system row every sale without a customer is booked on,
 * so it never takes a slot and creating it is never refused. Exactly ONE walk-in row is left out
 * of the count, never every row that looks like one: otherwise customers created with the
 * placeholder phone or a walk-in name would all be free and the limit could be bypassed.
 */
final class PartyLimitCounter
{
    public static function count(LimitResource $resource): int
    {
        return match ($resource) {
            LimitResource::Customers => self::customers(),
            LimitResource::Suppliers => self::suppliers(),
            default => throw new InvalidArgumentException("Not a party limit [{$resource->value}]."),
        };
    }

    /** Customers that take a plan slot: every live customer except one walk-in row. */
    public static function customers(): int
    {
        $total = Customer::query()->count();

        return self::hasWalkInCustomer() ? max(0, $total - 1) : $total;
    }

    public static function suppliers(): int
    {
        return Supplier::query()->count();
    }

    /** Is there a live walk-in customer row (same match as ResolveWalkInCustomerAction)? */
    public static function hasWalkInCustomer(): bool
    {
        return Customer::query()
            ->where(static function ($query): void {
                $query->whereIn('name', ResolveWalkInCustomerAction::walkInNames())
                    ->orWhere('phone', ResolveWalkInCustomerAction::PLACEHOLDER_PHONE);
            })
            ->exists();
    }
}
