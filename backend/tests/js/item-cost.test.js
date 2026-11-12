// BUG-02: the cost shown/used client-side is the weighted average, with a safe fallback.
import { test } from 'node:test';
import assert from 'node:assert/strict';
import { effectiveAverageCost, lastPurchasePrice, hasCostValue } from '../../resources/js/helpers/itemCost.js';

test('average_cost wins over cost_price', () => {
    assert.equal(effectiveAverageCost({ average_cost: '181.205', cost_price: '200.000' }), '181.205');
});

test('falls back to cost_price when average_cost is null, empty or zero', () => {
    assert.equal(effectiveAverageCost({ average_cost: null, cost_price: '200.000' }), '200.000');
    assert.equal(effectiveAverageCost({ average_cost: '0.000', cost_price: '200.000' }), '200.000');
    assert.equal(effectiveAverageCost({ cost_price: '12' }), '12');
});

test('hidden costs stay null instead of becoming 0', () => {
    assert.equal(effectiveAverageCost({ average_cost: null, cost_price: null }), null);
    assert.equal(effectiveAverageCost(null), null);
    assert.equal(lastPurchasePrice({ last_purchase_price: null, cost_price: null }), null);
    assert.equal(hasCostValue(null), false);
    assert.equal(hasCostValue('0.000'), true);
});

test('last purchase price prefers the explicit key', () => {
    assert.equal(lastPurchasePrice({ last_purchase_price: '200', cost_price: '150' }), '200');
    assert.equal(lastPurchasePrice({ cost_price: '150' }), '150');
});
