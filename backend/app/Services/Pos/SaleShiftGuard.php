<?php

declare(strict_types=1);

namespace App\Services\Pos;

use App\Models\CashShift;
use App\Models\Invoice;
use App\Models\Store;
use App\Models\StorePosSetting;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

/**
 * POS-10: a branch that requires an open cash shift (store_pos_settings.require_shift_for_sales,
 * default ON) cannot sell without one. Shifts are per store (ShiftService: one open shift per
 * store, shared by its cashiers), so the check is "this store has an open shift" - a shift of
 * another store never counts.
 *
 * Known race: the check runs before the invoice transaction and does not lock the shift row, so a
 * shift closed in the exact moment between the check and the insert can still let that one sale
 * through. Accepted: the window is milliseconds and an exclusive shift lock on every sale would
 * serialize the POS. DATE-01: such a sale finds no open shift when it is stamped
 * (ShiftAttribution), stays unstamped and is outside both shifts' windows; a sale stamped BEFORE
 * the close is always in the closed shift's stored totals (the close waits for its shared lock).
 */
final class SaleShiftGuard
{
    /**
     * @throws ValidationException 422 on `shift` when the store requires a shift and none is open
     */
    public function assertCanSell(?int $storeId, ?string $clientUuid = null): void
    {
        $storeId ??= Auth::user()?->getCurrentStore()->id ?? Store::getMainStore()?->id;
        if ($storeId === null || ! $this->isRequired((int) $storeId)) {
            return;
        }

        if (CashShift::query()->where('status', 'open')->where('store_id', $storeId)->exists()) {
            return;
        }

        // An offline retry of a sale that was already saved replays it; it must not be refused
        // just because the shift was closed in between.
        if ($clientUuid !== null && $clientUuid !== '' && Invoice::query()->where('client_uuid', $clientUuid)->exists()) {
            return;
        }

        throw ValidationException::withMessages(['shift' => [__('pos.shift_required')]]);
    }

    public function isRequired(int $storeId): bool
    {
        return (bool) StorePosSetting::forStore($storeId)->require_shift_for_sales;
    }
}
