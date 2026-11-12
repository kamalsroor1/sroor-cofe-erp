// ROLE-01: which dashboard blocks an own-shift (cashier) payload draws. Run: node --test tests/js/dashboard-scope.test.js
import { test } from 'node:test';
import assert from 'node:assert/strict';
import { isOwnShiftScope, ownShiftKpiKeys, ownShiftSections } from '../../resources/js/helpers/dashboardScope.js';

const shiftMetrics = {
    today_sales: '120.000',
    today_invoices_count: 3,
    today_returns: '0.000',
    cash_sales: '100.000',
    credit_sales: '20.000',
    customers_debt: null,
    monthly_sales: null,
    monthly_gross_profit: null,
    today_expenses: null,
};

test('only scope own_shift selects the cashier layout', () => {
    assert.equal(isOwnShiftScope({ scope: 'own_shift' }), true);
    assert.equal(isOwnShiftScope({ scope: 'store' }), false);
    assert.equal(isOwnShiftScope({}), false);
    assert.equal(isOwnShiftScope(null), false);
});

test('KPI cards exist only for the figures the API returned', () => {
    assert.deepEqual(ownShiftKpiKeys(shiftMetrics), ['sales', 'invoices']);
    assert.deepEqual(ownShiftKpiKeys({ today_sales: '0.000', today_invoices_count: 0 }), ['sales', 'invoices']);
    assert.deepEqual(ownShiftKpiKeys({ today_sales: null, today_invoices_count: null }), []);
    assert.deepEqual(ownShiftKpiKeys({}), []);
});

test('with an open shift the cashier layout draws the shift, the KPIs and its invoices', () => {
    const sections = ownShiftSections({
        metrics: shiftMetrics,
        activeShift: { id: 1, shift_number: 'S-1' },
        recentInvoices: [{ id: 9 }],
        lowStockItems: [],
    });
    assert.equal(sections.emptyShift, false);
    assert.equal(sections.kpis, true);
    assert.equal(sections.shiftBlock, true);
    assert.equal(sections.recentInvoices, true);
    assert.equal(sections.hasInvoices, true);
    assert.equal(sections.lowStock, false);
});

test('without an open shift only the empty state is drawn', () => {
    const sections = ownShiftSections({ metrics: shiftMetrics, activeShift: null, recentInvoices: [{ id: 1 }] });
    assert.equal(sections.emptyShift, true);
    assert.equal(sections.kpis, false);
    assert.equal(sections.shiftBlock, false);
    assert.equal(sections.recentInvoices, false);
    assert.equal(sections.lowStock, false);
});

test('low stock is drawn only when there is something to list', () => {
    const open = { metrics: shiftMetrics, activeShift: { id: 1 } };
    assert.equal(ownShiftSections({ ...open, lowStockItems: [{ id: 5 }] }).lowStock, true);
    assert.equal(ownShiftSections({ ...open, lowStockItems: [] }).lowStock, false);
});
