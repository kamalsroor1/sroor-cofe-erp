// POS-09: tier pricing and cart re-pricing with ItemResource-shaped items.
// Run: node --test scripts/pos/posPricing.test.mjs
import { test } from 'node:test';
import assert from 'node:assert/strict';

import { priceFor, repriceLines, updateLinePrice } from '../../resources/js/helpers/posCartLines.js';

const item = { selling_price: 100, wholesale_price: 80, min_selling_price: 60 };

test('retail and wholesale tiers read their own price, never min_selling_price', () => {
    assert.equal(priceFor(item, 'retail'), 100);
    assert.equal(priceFor(item, 'wholesale'), 80);
    assert.equal(priceFor({ selling_price: 100, wholesale_price: null, min_selling_price: 60 }, 'wholesale'), 100);
    assert.equal(priceFor({ selling_price: 0, min_selling_price: 60 }, 'retail'), 0);
});

test('repriceLines switches tier, keeps manual edits and lines without a price', () => {
    const cart = [
        { id: 1, ...item, unit_price: 100 },
        { id: 2, ...item, unit_price: 90, price_overridden: true },
        { id: 3, selling_price: 0, unit_price: 5 },
    ];
    repriceLines(cart, 'wholesale');
    assert.deepEqual(
        cart.map((l) => l.unit_price),
        [80, 90, 5]
    );
});

test('a manual price edit marks the line as overridden', () => {
    const cart = [{ id: 1, ...item, unit_price: 100 }];
    updateLinePrice(cart, { index: 0, value: 70 });
    assert.equal(cart[0].price_overridden, true);
});
