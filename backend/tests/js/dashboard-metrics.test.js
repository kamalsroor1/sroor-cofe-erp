// UI-05: dashboard cards hide when the API leaves a value out, and a never-sold shop is detected from the payload.
import { test } from 'node:test';
import assert from 'node:assert/strict';
import { hasMetric, isEmptyShop, isNonZeroAmount } from '../../resources/js/helpers/dashboardMetrics.js';

test('hasMetric treats null, undefined and empty string as absent but keeps zero', () => {
    assert.equal(hasMetric(null), false);
    assert.equal(hasMetric(undefined), false);
    assert.equal(hasMetric(''), false);
    assert.equal(hasMetric('0.000'), true);
    assert.equal(hasMetric(0), true);
});

test('isNonZeroAmount reads decimal strings without float math', () => {
    assert.equal(isNonZeroAmount('0.000'), false);
    assert.equal(isNonZeroAmount('0'), false);
    assert.equal(isNonZeroAmount(null), false);
    assert.equal(isNonZeroAmount('12.500'), true);
    assert.equal(isNonZeroAmount('-0.001'), true);
    assert.equal(isNonZeroAmount('900719925474099312.345'), true);
});

test('isEmptyShop is true only when nothing was ever sold', () => {
    const zeros = { today_sales: '0.000', monthly_sales: '0.000', today_invoices_count: 0 };
    assert.equal(isEmptyShop({ metrics: zeros, recentInvoices: [], period: { sales: '0.000' } }), true);
    assert.equal(isEmptyShop({ metrics: zeros, recentInvoices: [{ id: 1 }], period: {} }), false);
    assert.equal(
        isEmptyShop({ metrics: { ...zeros, monthly_sales: '10.000' }, recentInvoices: [], period: {} }),
        false
    );
    assert.equal(
        isEmptyShop({ metrics: { ...zeros, today_invoices_count: 2 }, recentInvoices: [], period: {} }),
        false
    );
    assert.equal(isEmptyShop(), true);
});
