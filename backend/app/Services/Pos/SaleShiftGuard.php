<?php

declare(strict_types=1);

namespace App\Services\Pos;

use App\Exceptions\BusinessRuleException;
use App\Models\Invoice;
use App\Models\Store;
use App\Models\StorePosSetting;
use App\Services\Shifts\OpenShiftResolver;
use App\Services\Shifts\ShiftAttribution;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

/**
 * POS-10: a branch that requires an open cash shift (store_pos_settings.require_shift_for_sales,
 * default ON) cannot sell without one. SHIFT-01: which shift counts follows the branch's shift
 * mode (OpenShiftResolver): in a shared branch "this store has an open shift"; in a per-cashier
 * branch (default) "the SELLER has their own open shift in this store". A shift of another store,
 * or (per-cashier) of another cashier, never counts.
 *
 * Two checks:
 *  - assertCanSell(), before the sale's transaction: no open shift -> 422 on `shift` (the POS
 *    gate's message). Cheap, and it lets an offline replay of a saved sale through.
 *  - assertShiftStillOpen(), INSIDE the sale's transaction (DEC-SHIFT, owner decision): the shift
 *    closed between the first check and the write -> 409, and the whole sale rolls back. It reads
 *    the open shift under the SAME shared lock the DATE-01 stamp takes, at the same point (right
 *    before the invoice row is inserted), so it adds no lock and no new lock-order edge: the close
 *    (ShiftService::closeShift, lockForUpdate on the shift row) either committed first, and the
 *    sale sees it closed and is refused, or waits for the sale to commit and counts it.
 */
final class SaleShiftGuard
{
    public function __construct(
        private readonly ShiftAttribution $attribution,
        private readonly OpenShiftResolver $resolver,
    ) {}

    /**
     * $sellerId: the user the invoice will be attributed to (default: the authenticated user).
     *
     * @throws ValidationException 422 on `shift` when the store requires a shift and none is open
     */
    public function assertCanSell(?int $storeId, ?string $clientUuid = null, ?int $sellerId = null): void
    {
        $storeId ??= Auth::user()?->getCurrentStore()->id ?? Store::getMainStore()?->id;
        if ($storeId === null || ! $this->isRequired((int) $storeId)) {
            return;
        }

        $authId = Auth::id();
        $sellerId ??= $authId !== null ? (int) $authId : null;
        if ($this->resolver->hasOpenFor((int) $storeId, $sellerId)) {
            return;
        }

        // An offline retry of a sale that was already saved replays it; it must not be refused
        // just because the shift was closed in between.
        if ($clientUuid !== null && $clientUuid !== '' && Invoice::query()->where('client_uuid', $clientUuid)->exists()) {
            return;
        }

        $perCashier = $this->resolver->modeFor((int) $storeId)->isPerCashier();

        throw ValidationException::withMessages(['shift' => [__($perCashier ? 'pos.shift_required_own' : 'pos.shift_required')]]);
    }

    /**
     * Call inside the sale's transaction, right before the invoice row is inserted (after the
     * idempotency replay check, so a replayed sale is never refused). $sellerId: the invoice's user.
     *
     * @throws BusinessRuleException 409 `pos.shift_closed_during_sale` (`..._own` in a per-cashier
     *                               branch) when the store requires a shift and none is open any more
     */
    public function assertShiftStillOpen(?int $storeId, ?int $sellerId = null): void
    {
        if ($storeId === null || ! $this->isRequired($storeId)) {
            return;
        }

        if ($this->attribution->openShiftIdFor($storeId, $sellerId) === null) {
            throw BusinessRuleException::conflict(
                $this->resolver->modeFor($storeId)->isPerCashier() ? 'pos.shift_closed_during_sale_own' : 'pos.shift_closed_during_sale'
            );
        }
    }

    public function isRequired(int $storeId): bool
    {
        return (bool) StorePosSetting::forStore($storeId)->require_shift_for_sales;
    }
}
