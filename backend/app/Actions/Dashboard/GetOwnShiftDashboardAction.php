<?php

declare(strict_types=1);

namespace App\Actions\Dashboard;

use App\Models\CashShift;
use App\Models\Store;
use App\Models\User;
use App\Services\Dashboard\LowStockRadar;
use App\Services\Shifts\OwnSalesSummary;

/**
 * ROLE-01 (owner decision 2026-11-12): the dashboard of a user WITHOUT `reports.view` (a
 * cashier). It holds only the sales the caller booked inside the open shift of the active store
 * (their own open shift there, else the store's shared-drawer shift; see
 * OwnSalesSummary::windowShiftFor): invoice count, net sales, cash / credit split and returns, the
 * shift block WITHOUT any drawer / expected-cash figure (and the opener's name only when it is
 * the caller), and the caller's latest invoices of that shift. Every store-wide figure (monthly
 * sales, analytics, cash collected, expenses, supplier payments, net cash, debts, customer /
 * supplier counts, top sellers, profit, margin) is null or empty. No open shift: zeros and empty
 * lists. Low stock only with `items.view`.
 *
 * The payload is per user and shift and is NEVER cached: it can neither be served from nor
 * written into the store-wide DashboardCache entries. `X-Store-Id: all` ($storeId null) never
 * widens it: only the caller's own open shift is looked up (in any store), the figures stay theirs.
 *
 * The metric keys are the store dashboard's, so the SPA reads one shape; `scope` tells it which
 * layout to draw (in `own_shift` the today_* figures are the shift's).
 */
final class GetOwnShiftDashboardAction
{
    public const SCOPE_STORE = 'store';

    public const SCOPE_OWN_SHIFT = 'own_shift';

    public function __construct(
        private readonly OwnSalesSummary $ownSales,
        private readonly LowStockRadar $lowStockRadar,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function execute(User $user, ?int $storeId): array
    {
        $shift = $this->ownSales->windowShiftFor($user, $storeId);
        $shiftStoreId = $shift instanceof CashShift && $shift->store_id !== null ? (int) $shift->store_id : null;
        $activeStoreId = $storeId ?? $shiftStoreId;
        $activeStore = $activeStoreId !== null ? Store::query()->whereKey($activeStoreId)->first() : null;

        $totals = $shiftStoreId !== null
            ? $this->ownSales->totals($user, $shiftStoreId, $shift)
            : $this->ownSales->zeros();

        $canSeeStock = $user->can('items.view');

        return [
            'scope' => self::SCOPE_OWN_SHIFT,
            'active_store' => $activeStore instanceof Store ? [
                'id' => $activeStore->id,
                'name' => $activeStore->name,
                'code' => $activeStore->code,
                'type' => $activeStore->type,
            ] : null,
            'metrics' => [
                'today_sales' => $totals['net_sales'],
                'today_gross_sales' => $totals['gross_sales'],
                'today_returns' => $totals['returns_value'],
                'today_returns_count' => $totals['returns_count'],
                'today_invoices_count' => $totals['invoices_count'],
                'cash_sales' => $totals['cash_sales'],
                'credit_sales' => $totals['credit_sales'],
                'total_cash_collected' => null,
                'today_expenses' => null,
                'expenses_count' => null,
                'supplier_payments' => null,
                'net_cash_today' => null,
                'customers_debt' => null,
                'suppliers_debt' => null,
                'monthly_sales' => null,
                'monthly_gross_sales' => null,
                'monthly_returns' => null,
                'monthly_gross_profit' => null,
                'monthly_margin' => null,
                'customers_count' => null,
                'suppliers_count' => null,
                'low_stock_count' => $canSeeStock ? $this->lowStockRadar->count() : null,
            ],
            'can_view_financials' => false,
            'analytics' => null,
            'low_stock_items' => $canSeeStock ? $this->lowStockRadar->items() : [],
            'top_selling_items' => [],
            'active_shift' => $shift instanceof CashShift ? $this->ownSales->shiftBlock($user, $shift) : null,
            'recent_invoices' => $shiftStoreId !== null
                ? $this->ownSales->recentInvoices($user, $shiftStoreId, $shift)
                : [],
        ];
    }
}
