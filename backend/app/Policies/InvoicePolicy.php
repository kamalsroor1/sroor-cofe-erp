<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Invoice;
use App\Models\User;
use App\Support\ActiveStore;

/**
 * STOR-2: every per-invoice ability also checks the invoice's store (branch). Reading needs
 * ActiveStore::canRead (store access or `stores.view_all`); changing needs ActiveStore::canAccess.
 * Controllers answer a store denial as 404 (CTO Q13), so a branch cannot probe other branches' ids.
 */
final class InvoicePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasRole('admin')
            || $user->can('invoices.view')
            || $user->can('pos.access');
    }

    public function view(User $user, Invoice $invoice): bool
    {
        return ($user->hasRole('admin')
            || $user->can('invoices.view')
            || $user->can('pos.access'))
            && ActiveStore::canRead($user, self::storeOf($invoice));
    }

    public function create(User $user): bool
    {
        return $user->hasRole('admin')
            || $user->can('invoices.create')
            || $user->can('pos.access');
    }

    public function update(User $user, Invoice $invoice): bool
    {
        return ($user->hasRole('admin') || $user->can('invoices.edit'))
            && self::canChangeStoreOf($user, $invoice);
    }

    public function delete(User $user, Invoice $invoice): bool
    {
        return ($user->hasRole('admin') || $user->can('invoices.delete'))
            && self::canChangeStoreOf($user, $invoice);
    }

    public function cancel(User $user, Invoice $invoice): bool
    {
        return ($user->hasRole('admin') || $user->can('invoices.cancel'))
            && self::canChangeStoreOf($user, $invoice);
    }

    /** Store access alone (no ability): lets controllers tell a store denial (404) from a missing permission (403). */
    public static function storeVisibleTo(User $user, Invoice $invoice): bool
    {
        return ActiveStore::canRead($user, self::storeOf($invoice));
    }

    private static function canChangeStoreOf(User $user, Invoice $invoice): bool
    {
        $storeId = self::storeOf($invoice);

        return $storeId !== null && ActiveStore::canAccess($user, $storeId);
    }

    private static function storeOf(Invoice $invoice): ?int
    {
        return $invoice->store_id !== null ? (int) $invoice->store_id : null;
    }
}
