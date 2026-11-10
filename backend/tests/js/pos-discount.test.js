// POS-07: resources/js/helpers/posDiscount.js and the discount-aware checkout net.
// Run from backend/: node --test "tests/js/*.test.js"
import { test } from 'node:test';
import assert from 'node:assert/strict';
import { lineDiscountAmount, lineNet, summarizeDiscounts } from '../../resources/js/helpers/posDiscount.js';
import { computeCheckoutNet } from '../../resources/js/helpers/posCheckout.js';

const line = (extra = {}) => ({ id: 1, quantity: 2, unit_price: 50, ...extra });

test('a line discount is a percent of the line total or a fixed amount, never above the total', () => {
    assert.equal(lineDiscountAmount(line({ discount_type: 'percentage', discount_value: '5' })), '5.000');
    assert.equal(lineDiscountAmount(line({ discount_type: 'fixed', discount_value: '7.5' })), '7.500');
    assert.equal(lineDiscountAmount(line({ discount_type: 'fixed', discount_value: '500' })), '100.000');
    assert.equal(lineDiscountAmount(line()), '0.000');
    assert.equal(lineNet(line({ discount_type: 'percentage', discount_value: '10' })), '90.000');
});

test('percent and amount give the same net when they are the same money', () => {
    const byPercent = computeCheckoutNet({ cart: [line()], discountType: 'percentage', discountValue: '7.5' });
    const byAmount = computeCheckoutNet({ cart: [line()], discountType: 'fixed', discountValue: '7.5' });
    assert.equal(byPercent, '92.500');
    assert.equal(byAmount, byPercent);
});

test('the invoice discount applies after the line discounts', () => {
    // 100 gross - 5 line = 95; 5 % of 95 = 4.75; net 90.25 (identical to the server).
    const cart = [line({ discount_type: 'fixed', discount_value: '5' })];
    assert.equal(computeCheckoutNet({ cart, discountType: 'percentage', discountValue: '5' }), '90.250');
});

test('the cap compares the total discount with a percent of the gross', () => {
    const cart = [line({ discount_type: 'fixed', discount_value: '5' })];
    const within = summarizeDiscounts({ cart, discountType: 'percentage', discountValue: '5', maxPercent: '10' });
    assert.equal(within.totalDiscount, '9.750');
    assert.equal(within.exceedsCap, false);

    const over = summarizeDiscounts({
        cart: [line({ discount_type: 'fixed', discount_value: '6' })],
        discountType: 'percentage',
        discountValue: '5',
        maxPercent: '10',
    });
    assert.equal(over.totalDiscount, '10.700');
    assert.equal(over.exceedsCap, true);
    assert.equal(over.percentOfGross, '10.700');
});

test('exactly the cap is allowed and no cap never warns', () => {
    const exact = summarizeDiscounts({
        cart: [line()],
        discountType: 'fixed',
        discountValue: '10',
        maxPercent: '10.000',
    });
    assert.equal(exact.exceedsCap, false);
    const noCap = summarizeDiscounts({ cart: [line()], discountType: 'fixed', discountValue: '90', maxPercent: null });
    assert.equal(noCap.exceedsCap, false);
});
