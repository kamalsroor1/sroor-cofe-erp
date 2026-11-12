<?php

declare(strict_types=1);

namespace App\Actions\Treasury;

use App\Actions\Dashboard\GetOwnShiftDashboardAction;
use App\Models\CashShift;
use App\Models\User;
use App\Services\Shifts\OwnSalesSummary;
use App\Support\TenantClock;

/**
 * ROLE-01: GET /api/v1/treasury/summary for a user WITHOUT `reports.view` (a cashier with
 * `daily_journal.view`). Only the movement the caller booked: inside the open shift of this
 * branch (their own, else the store's shared-drawer shift; OwnSalesSummary::windowShiftFor),
 * else on the tenant's business day. No drawer balance, no payment-account balances,
 * no receivables / payables (`balances` is null), and the shift block carries no cash figure.
 * Full figures stay on GetTreasurySummaryAction (reports.view).
 */
final class GetOwnShiftTreasurySummaryAction
{
    public const WINDOW_SHIFT = 'shift';

    public const WINDOW_DAY = 'day';

    public function __construct(
        private readonly OwnSalesSummary $ownSales,
        private readonly TenantClock $tenantClock,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function execute(User $user, int $storeId): array
    {
        $today = $this->tenantClock->today();
        $shift = $this->ownSales->windowShiftFor($user, $storeId);
        $totals = $this->ownSales->totals($user, $storeId, $shift, $today);

        return [
            'scope' => GetOwnShiftDashboardAction::SCOPE_OWN_SHIFT,
            'store_id' => $storeId,
            'today' => [
                'date' => $today,
                'window' => $shift instanceof CashShift ? self::WINDOW_SHIFT : self::WINDOW_DAY,
                'invoices_count' => $totals['invoices_count'],
                'gross_sales' => $totals['gross_sales'],
                'sales_total' => $totals['net_sales'],
                'cash_sales' => $totals['cash_sales'],
                'credit_sales' => $totals['credit_sales'],
                'partial_sales' => $totals['partial_sales'],
                'paid_total' => $totals['paid_total'],
                'returns_total' => $totals['returns_value'],
                'returns_count' => $totals['returns_count'],
                'expenses_total' => $totals['expenses_total'],
                'expenses_count' => $totals['expenses_count'],
            ],
            'balances' => null,
            'active_shift' => $shift instanceof CashShift ? $this->ownSales->shiftBlock($user, $shift) : null,
        ];
    }
}
