<?php

declare(strict_types=1);

namespace App\Observers;

use App\Models\Expense;
use App\Models\Invoice;
use App\Models\Payment;
use App\Services\Shifts\ShiftAttribution;

/**
 * DATE-01: stamps every new payment voucher, expense and invoice with the cash shift open in its
 * store (ShiftAttribution). Registered in AppServiceProvider; `creating` only, so an update,
 * a restore or a reversal never moves an existing row to another shift (a reversal / refund
 * voucher is a NEW row and belongs to the shift open when it is written).
 */
final class CashShiftStampObserver
{
    public function __construct(
        private readonly ShiftAttribution $attribution,
    ) {}

    public function creating(Invoice|Payment|Expense $model): void
    {
        $this->attribution->stamp($model);
    }
}
