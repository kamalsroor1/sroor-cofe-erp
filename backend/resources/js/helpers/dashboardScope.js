// ROLE-01: the dashboard API answers `scope: 'store'` (reports.view) or `scope: 'own_shift'` (a cashier:
// only the caller's own open shift). Pure helpers, no arithmetic (tests/js/dashboard-scope.test.js).
import { hasMetric } from './dashboardMetrics.js';

export const OWN_SHIFT_SCOPE = 'own_shift';

export const isOwnShiftScope = (data) => data?.scope === OWN_SHIFT_SCOPE;

/** KPI cards drawn for an own-shift payload: only the figures the API returned (null means hidden, never zero). */
export function ownShiftKpiKeys(metrics = {}) {
    const keys = [];
    if (hasMetric(metrics.today_sales)) keys.push('sales');
    if (hasMetric(metrics.today_invoices_count)) keys.push('invoices');
    return keys;
}

/** Which blocks the cashier layout draws. No open shift means an empty state and nothing else. */
export function ownShiftSections({ metrics = {}, activeShift = null, recentInvoices = [], lowStockItems = [] } = {}) {
    const hasShift = Boolean(activeShift);
    return {
        emptyShift: !hasShift,
        kpis: hasShift && ownShiftKpiKeys(metrics).length > 0,
        shiftBlock: hasShift,
        recentInvoices: hasShift,
        hasInvoices: hasShift && recentInvoices.length > 0,
        lowStock: hasShift && lowStockItems.length > 0,
    };
}
