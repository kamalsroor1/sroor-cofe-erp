import { test } from 'node:test';
import assert from 'node:assert/strict';
import { collectAllPages } from '../../resources/js/helpers/customerStatement.js';
import {
    REPORT_PRINT_MAX_ROWS,
    REPORT_PRINT_PAGE_SIZE,
    reportPrintColumns,
    reportPrintSummary,
    reportPrintValue,
} from '../../resources/js/helpers/reportPrint.js';

const t = (key) => key;

const pagedSource = (total) => {
    const calls = [];
    const fetchPage = async (page, perPage) => {
        calls.push([page, perPage]);
        const start = (page - 1) * perPage;
        const count = Math.max(0, Math.min(perPage, total - start));
        return {
            rows: Array.from({ length: count }, (_, i) => ({ id: start + i + 1 })),
            totals: { profit: '9.000' },
            extra: null,
            meta: { current_page: page, per_page: perPage, total, last_page: Math.max(1, Math.ceil(total / perPage)) },
        };
    };
    return { calls, fetchPage };
};

test('the print page size is the server cap of per_page', () => {
    assert.equal(REPORT_PRINT_PAGE_SIZE, 200);
});

test('collectAllPages gathers every report row with rowsOf', async () => {
    const { calls, fetchPage } = pagedSource(450);
    const result = await collectAllPages({
        fetchPage,
        perPage: REPORT_PRINT_PAGE_SIZE,
        maxRows: REPORT_PRINT_MAX_ROWS,
        rowsOf: (page) => page.rows,
    });
    assert.equal(result.status, 'ok');
    assert.equal(result.rows.length, 450);
    assert.deepEqual(result.first.totals, { profit: '9.000' });
    assert.deepEqual(
        calls.map(([page]) => page),
        [1, 2, 3]
    );
});

test('collectAllPages refuses a report above the ceiling after one request', async () => {
    const { calls, fetchPage } = pagedSource(REPORT_PRINT_MAX_ROWS + 1);
    const result = await collectAllPages({ fetchPage, perPage: 200, rowsOf: (page) => page.rows });
    assert.equal(result.status, 'too_large');
    assert.equal(calls.length, 1);
});

test('items print columns match the screen table', () => {
    const keys = reportPrintColumns('items', t).map((c) => c.key);
    assert.deepEqual(keys, [
        'name',
        'code',
        'category',
        'total_qty',
        'total_revenue',
        'total_cogs',
        'profit',
        'margin',
    ]);
});

test('inventory cost columns follow key presence', () => {
    const withCost = reportPrintColumns('inventory', t, [{ cost_val: '1.000', profit: '1.000' }]).map((c) => c.key);
    assert.ok(withCost.includes('cost_val') && withCost.includes('profit'));
    const withoutCost = reportPrintColumns('inventory', t, [{ sell_val: '1.000' }]).map((c) => c.key);
    assert.ok(!withoutCost.includes('cost_val') && !withoutCost.includes('profit'));
});

test('inventory average cost falls back to cost_price like the screen', () => {
    const col = reportPrintColumns('inventory', t, []).find((c) => c.key === 'average_cost');
    assert.equal(reportPrintValue(col, { average_cost: '0.000', cost_price: '4.500' }), '4.500');
});

test('treasury date joins date and time', () => {
    const col = reportPrintColumns('treasury', t).find((c) => c.key === 'date');
    assert.equal(reportPrintValue(col, { date: '2026-11-01', time: '10:00' }), '2026-11-01 10:00');
});

test('summary lines come from the tab extra data', () => {
    assert.deepEqual(
        reportPrintSummary('treasury', t, {
            total_inflows: '5.000',
            total_outflows: '1.000',
            total_net_change: '4.000',
        }).map((l) => l.value),
        ['5.000', '1.000', '4.000']
    );
    assert.equal(reportPrintSummary('inventory', t, { stock_selling_valuation: '2.000' }).length, 1);
    assert.deepEqual(reportPrintSummary('sales_by_cashier', t, {}), []);
    assert.deepEqual(reportPrintColumns('sales_by_cashier', t), []);
});
