<?php

declare(strict_types=1);

namespace App\Services\Shifts;

use App\Enums\ShiftMode;
use App\Models\CashShift;
use App\Models\Expense;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Purchase;
use App\Models\ReturnDocument;
use App\Models\Store;
use App\Models\User;
use Carbon\CarbonInterface;
use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * DATE-01: which cash shift a payment voucher, an expense or an invoice (DEC-SHIFT: or a sales
 * return) belongs to.
 *
 * Timestamps are stored on the tenant's wall clock (Africa/Cairo), so the old rule, "a row
 * belongs to the shift when created_at lies in [opened_at, closed_at ?? now]", broke whenever the
 * clock jumped: on the night the clock goes back an hour the sales of the repeated hour fell
 * outside the window (expected cash 100 instead of 6,373), and two consecutive shifts closed and
 * opened in the same second both counted a voucher of that second.
 *
 * The rule now:
 *  - a row is stamped, ONCE, when it is created (`creating` hook, CashShiftStampObserver), with
 *    the shift OPEN in its store at that moment (one open shift per store, ShiftService; SHIFT-01:
 *    in a per-cashier branch, the open shift of the row's user in its store; SHIFT-02: a reversal /
 *    refund first goes to the still-open shift of the document it reverses). A row without a
 *    resolvable store, or created while its store has no open shift (per-cashier: its writer has
 *    none), stays NULL = paid from the main treasury, outside every drawer. The value is never
 *    taken from the client: it is not mass assignable and is always overwritten.
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
     *
     * DEC-SHIFT: a sales return is stamped like an invoice (its store's open shift); a purchase
     * return is not a drawer document and stays NULL.
     */
    public function stamp(Invoice|Payment|Expense|ReturnDocument $row): void
    {
        if ($row instanceof ReturnDocument && $row->return_type !== 'sales_return') {
            $row->setAttribute(self::COLUMN, null);

            return;
        }

        $storeId = $this->storeIdOf($row);
        if ($storeId === null) {
            $row->setAttribute(self::COLUMN, null);

            return;
        }

        // SHIFT-02 (owner decision 2026-11-21): a reversal / refund of a document goes back to the
        // shift holding the original document's money while that shift is still open - an admin
        // without a drawer cancelling a cashier's cash sale must not leave the refund on no shift
        // (the cashier's Z would show a false surplus). Closed (or none): the normal rule below.
        $originalShiftId = $this->originalShiftIdOf($row);
        if ($originalShiftId !== null) {
            $open = $this->lockIfOpen($originalShiftId, $storeId);
            if ($open !== null) {
                $row->setAttribute(self::COLUMN, $open);

                return;
            }
        }

        // SHIFT-01: in a per-cashier branch the row belongs to the open shift of the user who wrote
        // it (the seller of an invoice, the writer of a voucher / expense / return). SHIFT-02: a
        // writer without an open shift there (an admin paying an expense) leaves the row unstamped:
        // it was paid from the main treasury, not from any cashier's drawer.
        $userId = $row->user_id !== null ? (int) $row->user_id : null;

        $row->setAttribute(self::COLUMN, $this->openShiftIdFor($storeId, $userId));
    }

    /**
     * SHIFT-02: the shift that holds the money of the document $row reverses or refunds, or null
     * when $row is not a reversal / refund (or the original was never stamped):
     *  - an invoice-cancel reversal voucher (reverses_payment_id): the reversed voucher's shift;
     *  - a sales-return refund voucher (return_id): its return's shift (stamped just before it,
     *    by this same rule, in the same transaction);
     *  - a sales return against an invoice: the shift of the invoice's own sale voucher, else of
     *    the invoice (a credit sale has no voucher).
     */
    private function originalShiftIdOf(Invoice|Payment|Expense|ReturnDocument $row): ?int
    {
        $shiftId = null;

        if ($row instanceof Payment && $row->reverses_payment_id !== null) {
            $shiftId = Payment::withTrashed()->whereKey($row->reverses_payment_id)->value(self::COLUMN);
        } elseif ($row instanceof Payment && $row->return_id !== null) {
            $shiftId = ReturnDocument::withTrashed()->whereKey($row->return_id)->value(self::COLUMN);
        } elseif ($row instanceof ReturnDocument && $row->invoice_id !== null) {
            $shiftId = Payment::withTrashed()
                ->where('payments.invoice_id', $row->invoice_id)
                ->where('payments.amount', '>', 0)
                ->excludingRefunds()
                ->whereNotNull('payments.'.self::COLUMN)
                ->orderBy('payments.id')
                ->value('payments.'.self::COLUMN)
                ?? Invoice::withTrashed()->whereKey($row->invoice_id)->value(self::COLUMN);
        }

        return $shiftId !== null ? (int) $shiftId : null;
    }

    /**
     * SHIFT-02: $shiftId when it is an OPEN shift of $storeId, read under the same shared lock as
     * openShiftIdFor() (a close waits for this transaction; a leaf lock by primary key), else null.
     */
    private function lockIfOpen(int $shiftId, int $storeId): ?int
    {
        $locked = CashShift::query()->whereKey($shiftId)->sharedLock()->first(['id', 'store_id', 'status']);

        return $locked instanceof CashShift && $locked->status === 'open' && (int) $locked->store_id === $storeId
            ? (int) $locked->id
            : null;
    }

    /**
     * The store whose drawer the row belongs to. Invoices and expenses carry it; a payment
     * voucher without payments.store_id maps exactly like Payment::forStore() (legacy half):
     * the store of its invoice, else of its purchase; an unlinked voucher to its user's default
     * store, and to the main store when the user has none (or there is no user).
     */
    public function storeIdOf(Invoice|Payment|Expense|ReturnDocument $row): ?int
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
    public function openShiftIdFor(int $storeId, ?int $userId): ?int
    {
        // SHIFT-01: shared branch -> its open shift; per-cashier branch -> $userId's open shift there.
        // SHIFT-02: the mode is the candidate shift's own (cash_shifts.shift_mode, fixed when it
        // opened), never a separate unlocked read of the branch setting. A branch's open shifts all
        // share one mode (the setting cannot change while any of them is open).
        for ($attempt = 0; $attempt < 2; $attempt++) {
            $candidate = CashShift::query()
                ->where('cash_shifts.store_id', $storeId)
                ->where('cash_shifts.status', 'open')
                ->where(function (Builder $q) use ($userId): void {
                    $q->where('cash_shifts.shift_mode', '!=', ShiftMode::PerCashier->value)
                        ->orWhere(function (Builder $own) use ($userId): void {
                            $own->where('cash_shifts.shift_mode', ShiftMode::PerCashier->value);
                            $userId === null
                                ? $own->whereNull('cash_shifts.user_id')
                                : $own->where('cash_shifts.user_id', $userId);
                        });
                })
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
     * Restricts $query (payments, expenses, invoices or returns) to the rows of $shift: stamped with it,
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
                ->orWhere(function (Builder $legacy) use ($table, $shift, $openedAt, $until, $legacyStore): void {
                    $legacy->whereNull($table.'.'.self::COLUMN)
                        ->where($table.'.created_at', '>=', $openedAt);

                    if ($until !== null) {
                        $legacy->where($table.'.created_at', '<=', $until);
                    }

                    if ($legacyStore !== null) {
                        $legacyStore($legacy);
                    }

                    // SHIFT-01: per-cashier shifts of one branch overlap in time, so an unstamped
                    // row inside the window belongs to its opener's shift only (never to every
                    // cashier's shift at once). Shared shifts keep the store-wide window.
                    if ($shift->isPerCashier()) {
                        $shift->user_id !== null
                            ? $legacy->where($table.'.user_id', (int) $shift->user_id)
                            : $legacy->whereNull($table.'.user_id');
                    }
                });
        });
    }
}
