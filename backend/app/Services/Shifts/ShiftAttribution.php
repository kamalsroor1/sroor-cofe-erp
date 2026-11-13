<?php

declare(strict_types=1);

namespace App\Services\Shifts;

use App\Models\CashShift;
use App\Models\Expense;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Purchase;
use App\Models\Store;
use App\Models\User;
use Carbon\CarbonInterface;
use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * DATE-01: which cash shift a payment voucher, an expense or an invoice belongs to.
 *
 * Timestamps are stored on the tenant's wall clock (Africa/Cairo), so the old rule, "a row
 * belongs to the shift when created_at lies in [opened_at, closed_at ?? now]", broke whenever the
 * clock jumped: on the night the clock goes back an hour the sales of the repeated hour fell
 * outside the window (expected cash 100 instead of 6,373), and two consecutive shifts closed and
 * opened in the same second both counted a voucher of that second.
 *
 * The rule now:
 *  - a row is stamped, ONCE, when it is created (`creating` hook, CashShiftStampObserver), with
 *    the shift OPEN in its store at that moment (one open shift per store, ShiftService). A row
 *    without a resolvable store, or created while its store has no open shift, stays NULL. The
 *    value is never taken from the client: it is not mass assignable and is always overwritten.
 *  - a row belongs to shift S when `cash_shift_id = S.id`, OR (history, rows written before the
 *    column existed / without an open shift) `cash_shift_id IS NULL` and its created_at lies in
 *    S's legacy window, exactly as before. A stamped row never matches a second shift.
 *
 * Concurrency (the close must see every row stamped with the shift): the open shift is read with
 * a SHARED row lock (sharedLock(), FOR SHARE) by primary key. Stampers never block each other;
 * ShiftService::closeShift() takes lockForUpdate() on the same row, so it waits for every
 * transaction that stamped a row with the shift to commit, and a stamper that comes after the
 * close sees it closed. See openShiftIdFor().
 */
final class ShiftAttribution
{
    public const COLUMN = 'cash_shift_id';

    /**
     * Sets cash_shift_id on a row about to be inserted (overwrites any value already set).
     */
    public function stamp(Invoice|Payment|Expense $row): void
    {
        $storeId = $this->storeIdOf($row);

        $row->setAttribute(self::COLUMN, $storeId === null ? null : $this->openShiftIdFor($storeId));
    }

    /**
     * The store whose drawer the row belongs to. Invoices and expenses carry it; a payment
     * voucher without payments.store_id maps exactly like Payment::forStore() (legacy half):
     * the store of its invoice, else of its purchase; an unlinked voucher to its user's default
     * store, and to the main store when the user has none (or there is no user).
     */
    public function storeIdOf(Invoice|Payment|Expense $row): ?int
    {
        if ($row->store_id !== null) {
            return (int) $row->store_id;
        }

        if (! $row instanceof Payment) {
            return null;
        }

        if ($row->invoice_id !== null || $row->purchase_id !== null) {
            $invoiceStore = $row->invoice_id !== null
                ? Invoice::withTrashed()->whereKey($row->invoice_id)->value('store_id')
                : null;
            if ($invoiceStore !== null) {
                return (int) $invoiceStore;
            }

            $purchaseStore = $row->purchase_id !== null
                ? Purchase::withTrashed()->whereKey($row->purchase_id)->value('store_id')
                : null;

            return $purchaseStore !== null ? (int) $purchaseStore : null;
        }

        if ($row->user_id !== null) {
            $user = User::withTrashed()->whereKey($row->user_id)->first(['id', 'default_store_id']);
            if (! $user instanceof User) {
                return null; // forStore() maps a voucher of an unknown user to no store
            }
            if ($user->default_store_id !== null) {
                return (int) $user->default_store_id;
            }
        }

        $mainStoreId = Store::getMainStore()?->id;

        return $mainStoreId !== null ? (int) $mainStoreId : null;
    }

    /**
     * The id of the store's open shift, read under a shared lock (inside the caller's
     * transaction; outside one the lock ends with the statement, as any autocommit read).
     *
     * The lookup is a plain read; the lock is then taken on the primary key only (a record lock,
     * no gap/next-key lock on the store/status index that would block opening a shift) and the
     * status is checked again under it (a locking read always sees the latest committed row).
     * When a close committed in between, the lookup is redone once. Whether that re-read can find
     * a shift opened meanwhile depends on the caller's isolation level: under READ COMMITTED (the
     * TransactionRetry default) each plain read sees the latest commits, so a new open shift is
     * found and stamped; inside a plain DB::transaction() at REPEATABLE READ the re-read sees the
     * transaction's old snapshot (the shift just closed, no new one), returns the closed id again,
     * fails the status check, and the row stays unstamped (NULL). It is then attributed by the
     * legacy window rule like history: its created_at is after the old shift's closed_at, so it
     * counts in the new shift when one was opened before it (inside that shift's window, barring
     * a clock change) and in no shift otherwise; written in the very second of the close it also
     * matches the old shift's inclusive window (the legacy rule's known double count).
     *
     * Lock order: this is a leaf lock. The only writers that take the shift row exclusively
     * (closeShift, RecalculateClosedShiftTotalsAction) take it as their FIRST lock and wait for
     * nothing else while holding it (their sums are plain reads), so a stamper waiting for them
     * can never be part of a wait cycle through the shift row alone. The one remaining cycle
     * (stamper A holds the shared lock and waits for a row held by stamper B, B queues for the
     * shared lock behind the close's pending exclusive request) is detected by InnoDB as 1213,
     * whose victim is the close (it has written nothing yet), and TransactionRetry restarts it.
     */
    public function openShiftIdFor(int $storeId): ?int
    {
        for ($attempt = 0; $attempt < 2; $attempt++) {
            $candidate = CashShift::query()
                ->where('store_id', $storeId)
                ->where('status', 'open')
                ->latest('id')
                ->value('id');

            if ($candidate === null) {
                return null;
            }

            $locked = CashShift::query()->whereKey($candidate)->sharedLock()->first(['id', 'status']);

            if ($locked instanceof CashShift && $locked->status === 'open') {
                return (int) $locked->id;
            }
        }

        return null;
    }

    /**
     * Restricts $query (payments, expenses or invoices) to the rows of $shift: stamped with it,
     * or unstamped history inside its legacy window [opened_at, $until] (no upper bound when
     * $until is null). $legacyStore filters the unstamped half by store (a stamped row already
     * belongs to the shift's store). One condition on the (cash_shift_id, created_at) index: both
     * halves are ranges of it (cash_shift_id = S, and cash_shift_id IS NULL with created_at in
     * the window), so the OR does not cost the index.
     *
     * @template TModel of Model
     *
     * @param  Builder<TModel>  $query
     * @param  (Closure(Builder<TModel>): mixed)|null  $legacyStore
     * @return Builder<TModel>
     */
    public static function scopeToShift(Builder $query, CashShift $shift, ?CarbonInterface $until, ?Closure $legacyStore = null): Builder
    {
        $table = $query->getModel()->getTable();
        $openedAt = $shift->opened_at;

        return $query->where(function (Builder $q) use ($table, $shift, $openedAt, $until, $legacyStore): void {
            $q->where($table.'.'.self::COLUMN, (int) $shift->id)
                ->orWhere(function (Builder $legacy) use ($table, $openedAt, $until, $legacyStore): void {
                    $legacy->whereNull($table.'.'.self::COLUMN)
                        ->where($table.'.created_at', '>=', $openedAt);

                    if ($until !== null) {
                        $legacy->where($table.'.created_at', '<=', $until);
                    }

                    if ($legacyStore !== null) {
                        $legacyStore($legacy);
                    }
                });
        });
    }
}
