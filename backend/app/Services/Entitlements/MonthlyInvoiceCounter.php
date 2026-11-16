<?php

declare(strict_types=1);

namespace App\Services\Entitlements;

use App\Models\Invoice;
use App\Support\TenantClock;
use Carbon\CarbonImmutable;

/**
 * PLAN-ENF: usage of the `invoices_month` plan limit (max_invoices_per_month) for the CURRENT
 * tenant (call while tenancy is initialized).
 *
 * What counts: every sales invoice (`invoices` row) CREATED in the current business month,
 * whatever its status, including cancelled and soft-deleted ones, so cancel/delete + recreate
 * cannot game the cap. Sales returns and purchases live in other tables and never count.
 *
 * The month: the calendar month of TenantClock::businessNow() (tenant timezone + business-day
 * cutoff, like every other date of the app), as a half-open [start, end) range in the storage
 * timezone, filtered on `created_at`. `created_at` is server-stamped, so a back-dated
 * `invoice_date` cannot move an invoice out of the month either.
 */
final class MonthlyInvoiceCounter
{
    public function __construct(private readonly TenantClock $clock) {}

    public function count(): int
    {
        [$start, $end] = $this->period();

        return Invoice::withTrashed()
            ->where('created_at', '>=', $start)
            ->where('created_at', '<', $end)
            ->count();
    }

    /**
     * [start, end) of the current business month, in the storage timezone.
     *
     * @return array{0: CarbonImmutable, 1: CarbonImmutable}
     */
    public function period(): array
    {
        $first = $this->clock->businessNow()->startOfMonth();

        return [
            $this->clock->startOfDay($first->toDateString()),
            $this->clock->startOfDay($first->addMonthNoOverflow()->toDateString()),
        ];
    }

    /** When the counter starts again (start of the next business month), storage timezone. */
    public function resetsAt(): CarbonImmutable
    {
        return $this->period()[1];
    }
}
