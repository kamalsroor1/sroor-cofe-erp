// resources/js/helpers/posCartLines.js with the API's decimal-string money fields ('62.500', '0.000').
// Run from backend/: node --test "tests/js/*.test.js"
import { test } from 'node:test';
import assert from 'node:assert/strict';
import { priceFor, repriceLines, increaseLineQty, decreaseLineQty } from '../../resources/js/helpers/posCartLines.js';

test('tier prices are read from decimal strings', () => {
    const item = { selling_price: '62.500', price_retail: '62.500', price_wholesale: '55.250' };
    assert.equal(priceFor(item, 'retail'), 62.5);
    assert.equal(priceFor(item, 'wholesale'), 55.25);
});

test("a '0.000' price is unset, not a zero price (the string is truthy)", () => {
    // /items: no wholesale price set -> wholesale customers pay retail.
    assert.equal(priceFor({ selling_price: '40.000', wholesale_price: '0.000' }, 'wholesale'), 40);
    assert.equal(priceFor({ selling_price: '40.000', wholesale_price: null }, 'wholesale'), 40);
    // POS payloads: an empty selling price falls through to price_retail.
    assert.equal(priceFor({ selling_price: '0.000', price_retail: '12.000' }, 'retail'), 12);
    assert.equal(priceFor({ price_retail: '0.000', price_wholesale: '0.000' }, 'wholesale'), 0);
});

test('min_selling_price is a floor, never used as a price', () => {
    const item = { price_retail: '30.000', price_wholesale: '0.000', min_selling_price: '25.000' };
    assert.equal(priceFor(item, 'wholesale'), 30);
});

test('repricing keeps hand-priced lines and lines without a tier price', () => {
    const cart = [
        { price_retail: '30.000', price_wholesale: '27.000', unit_price: 30 },
        { price_retail: '30.000', price_wholesale: '27.000', unit_price: 31, price_overridden: true },
        { price_retail: '0.000', price_wholesale: '0.000', unit_price: 5 },
    ];
    repriceLines(cart, 'wholesale');
    assert.deepEqual(
        cart.map((l) => l.unit_price),
        [27, 31, 5]
    );
});

test('quantity steppers never concatenate string quantities', () => {
    const cart = [
        { is_weighted: true, quantity: '1.500' },
        { is_weighted: false, quantity: '2' },
    ];
    increaseLineQty(cart, 0);
    increaseLineQty(cart, 1);
    assert.equal(cart[0].quantity, '1.750');
    assert.equal(cart[1].quantity, 3);
    decreaseLineQty(cart, 0);
    decreaseLineQty(cart, 1);
    assert.equal(cart[0].quantity, '1.500');
    assert.equal(cart[1].quantity, 2);
});
