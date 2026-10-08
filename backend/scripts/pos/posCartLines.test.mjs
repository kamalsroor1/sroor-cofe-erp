// POS cart line edits: the view applies the { index, value } events emitted by the cart
// components (no prop mutation), and the checkout net follows quantity / price / discount edits.
// Run: node --test scripts/pos/posCartLines.test.mjs   (also picked up by `npm run test:scripts`)
import { test, describe } from 'node:test';
import assert from 'node:assert/strict';

import {
    updateLineQty,
    updateLinePrice,
    increaseLineQty,
    decreaseLineQty,
    removeLine,
    stepQuantity,
} from '../../resources/js/helpers/posCartLines.js';
import { computeCheckoutNet } from '../../resources/js/helpers/posCheckout.js';

const makeCart = () => [
    { id: 1, name: 'Espresso', unit: 'piece', quantity: 2, unit_price: 50 },
    { id: 2, name: 'Coffee beans', unit: 'كجم', quantity: 0.5, unit_price: 400 },
];

describe('quantity edits', () => {
    test('typed quantity updates the line', () => {
        const cart = makeCart();
        assert.equal(updateLineQty(cart, { index: 0, value: '3' }), true);
        assert.equal(cart[0].quantity, 3);
    });

    test('typed quantity ignores empty, non-numeric and non-positive input', () => {
        const cart = makeCart();
        for (const value of ['', 'abc', '0', '-1']) {
            assert.equal(updateLineQty(cart, { index: 0, value }), false);
        }
        assert.equal(cart[0].quantity, 2);
    });

    test('out-of-range index is a no-op', () => {
        const cart = makeCart();
        assert.equal(updateLineQty(cart, { index: 5, value: '3' }), false);
        assert.equal(increaseLineQty(cart, -1), false);
        assert.equal(cart.length, 2);
    });

    test('increase and decrease step by one; decrease at 1 removes the line', () => {
        const cart = makeCart();
        increaseLineQty(cart, 0);
        assert.equal(cart[0].quantity, 3);
        decreaseLineQty(cart, 0);
        decreaseLineQty(cart, 0);
        assert.equal(cart[0].quantity, 1);
        decreaseLineQty(cart, 0);
        assert.equal(cart.length, 1);
        assert.equal(cart[0].id, 2);
    });

    test('removeLine drops the line', () => {
        const cart = makeCart();
        removeLine(cart, 1);
        assert.deepEqual(
            cart.map((l) => l.id),
            [1]
        );
    });
});

describe('touch stepper (POSCartItem)', () => {
    test('unit lines step by 1 and ask for removal at 1', () => {
        assert.equal(stepQuantity(2, { direction: 1 }), 3);
        assert.equal(stepQuantity(2, { direction: -1 }), 1);
        assert.equal(stepQuantity(1, { direction: -1 }), null);
    });

    test('weight lines step by 0.25 and ask for removal at 0.125', () => {
        assert.equal(stepQuantity(0.5, { weightBased: true, direction: 1 }), 0.75);
        assert.equal(stepQuantity(0.5, { weightBased: true, direction: -1 }), 0.25);
        assert.equal(stepQuantity(0.125, { weightBased: true, direction: -1 }), null);
    });

    test('stepper result applied through the same update path', () => {
        const cart = makeCart();
        const next = stepQuantity(cart[1].quantity, { weightBased: true, direction: 1 });
        updateLineQty(cart, { index: 1, value: next });
        assert.equal(cart[1].quantity, 0.75);
    });
});

describe('price edits', () => {
    test('typed price updates the line, zero allowed', () => {
        const cart = makeCart();
        assert.equal(updateLinePrice(cart, { index: 0, value: '45.5' }), true);
        assert.equal(cart[0].unit_price, 45.5);
        assert.equal(updateLinePrice(cart, { index: 0, value: '0' }), true);
        assert.equal(cart[0].unit_price, 0);
    });

    test('negative or non-numeric price is ignored', () => {
        const cart = makeCart();
        assert.equal(updateLinePrice(cart, { index: 0, value: '-5' }), false);
        assert.equal(updateLinePrice(cart, { index: 0, value: '' }), false);
        assert.equal(cart[0].unit_price, 50);
    });
});

describe('checkout net follows line and discount edits', () => {
    test('net reflects quantity, price and discount changes', () => {
        const cart = makeCart();
        // 2 x 50 + 0.5 x 400 = 300
        assert.equal(computeCheckoutNet({ cart, discountType: 'fixed', discountValue: '0' }), '300.000');

        updateLineQty(cart, { index: 0, value: '4' }); // 200 + 200
        assert.equal(computeCheckoutNet({ cart, discountType: 'fixed', discountValue: '0' }), '400.000');

        updateLinePrice(cart, { index: 1, value: '380' }); // 200 + 190
        assert.equal(computeCheckoutNet({ cart, discountType: 'fixed', discountValue: '0' }), '390.000');

        assert.equal(computeCheckoutNet({ cart, discountType: 'fixed', discountValue: '40' }), '350.000');
        assert.equal(computeCheckoutNet({ cart, discountType: 'percentage', discountValue: '10' }), '351.000');

        decreaseLineQty(cart, 0); // 150 + 190
        assert.equal(computeCheckoutNet({ cart, discountType: 'percentage', discountValue: '10' }), '306.000');
    });

    test('fixed discount never exceeds the subtotal', () => {
        const cart = makeCart();
        assert.equal(computeCheckoutNet({ cart, discountType: 'fixed', discountValue: '1000' }), '0.000');
    });
});
