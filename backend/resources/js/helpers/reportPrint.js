// PERF-01: "print A4" on a paged report tab prints every row of the selected filters, not the 25
// rows on screen. Pure helpers (no Vue, no HTTP): which tabs are paged, the print columns of the
// bespoke tabs and the summary lines above the table. The breakdown tabs bring their own columns.
import { effectiveAverageCost } from './itemCost.js';

// Server cap of `per_page` on /reports/* (FilterReportRequest).
export const REPORT_PRINT_PAGE_SIZE = 200;
// Same ceiling as the customer statement print: past it the user is asked for a shorter period.
export const REPORT_PRINT_MAX_ROWS = 5000;

const hasKey = (row, key) => row && Object.prototype.hasOwnProperty.call(row, key);

/** Print columns of the items / inventory / treasury tabs, from the same labels as the screen tables. */
export const reportPrintColumns = (tab, t, rows = []) => {
    if (tab === 'items') {
        return [
            { key: 'name', label: t('inventory.item_name'), type: 'text' },
            { key: 'code', label: t('inventory.code'), type: 'mono' },
            { key: 'category', label: t('inventory.category'), type: 'text' },
            { key: 'total_qty', label: t('reports.sold_quantity'), type: 'qty', unitKey: 'unit' },
            { key: 'total_revenue', label: t('reports.net_sales_label'), type: 'money' },
            { key: 'total_cogs', label: t('reports.total_cogs_label'), type: 'money' },
            { key: 'profit', label: t('reports.gross_profit_label'), type: 'money', strong: true },
            { key: 'margin', label: t('dashboard.profit_margin_pct'), type: 'percent' },
        ];
    }
    if (tab === 'inventory') {
        // Cost columns follow key presence, like the screen (absent without items.view_cost).
        const showCost = rows.some((row) => hasKey(row, 'cost_val'));
        const showProfit = rows.some((row) => hasKey(row, 'profit'));
        return [
            { key: 'name', label: t('inventory.item_name'), type: 'text' },
            { key: 'code', label: t('inventory.code'), type: 'mono' },
            { key: 'current_stock', label: t('inventory.current_stock'), type: 'qty', unitKey: 'unit' },
            { key: 'average_cost', label: t('inventory.average_cost'), type: 'money', value: effectiveAverageCost },
            { key: 'selling_price', label: t('inventory.selling_price'), type: 'money' },
            showCost && { key: 'cost_val', label: t('reports.cost_valuation'), type: 'money' },
            { key: 'sell_val', label: t('reports.selling_valuation'), type: 'money', strong: true },
            showProfit && { key: 'profit', label: t('reports.expected_profit'), type: 'money' },
        ].filter(Boolean);
    }
    if (tab === 'treasury') {
        return [
            {
                key: 'date',
                label: t('common.date'),
                type: 'mono',
                value: (row) => [row.date, row.time].filter(Boolean).join(' '),
            },
            { key: 'doc_number', label: t('reports.treasury_col_doc'), type: 'mono' },
            { key: 'type_label', label: t('reports.treasury_col_type'), type: 'text' },
            { key: 'method_label', label: t('reports.treasury_col_method'), type: 'text' },
            { key: 'party', label: t('reports.treasury_col_party'), type: 'text' },
            { key: 'debit', label: t('reports.treasury_col_in'), type: 'money', blankZero: true },
            { key: 'credit', label: t('reports.treasury_col_out'), type: 'money', blankZero: true },
            { key: 'running_balance', label: t('reports.treasury_col_balance'), type: 'money', strong: true },
        ];
    }
    return [];
};

/** Key/value lines printed above the table (the screen's KPI cards), money values unformatted. */
export const reportPrintSummary = (tab, t, extra) => {
    if (!extra) return [];
    if (tab === 'inventory') {
        return [
            hasKey(extra, 'stock_cost_valuation') && {
                label: t('reports.stock_cost_val_label'),
                value: extra.stock_cost_valuation,
            },
            { label: t('reports.stock_sell_val_label'), value: extra.stock_selling_valuation ?? 0 },
            hasKey(extra, 'expected_stock_profit') && {
                label: t('reports.expected_profit_val'),
                value: extra.expected_stock_profit,
            },
        ].filter(Boolean);
    }
    if (tab === 'treasury') {
        return [
            { label: t('reports.total_inflow_label'), value: extra.total_inflows ?? 0 },
            { label: t('reports.total_outflow_label'), value: extra.total_outflows ?? 0 },
            { label: t('reports.net_cash_flow_label'), value: extra.total_net_change ?? 0 },
        ];
    }
    return [];
};

/** The cell value of a print column: its `value(row)` when given, else `row[key]`. */
export const reportPrintValue = (col, row) => (typeof col.value === 'function' ? col.value(row) : row?.[col.key]);
