// Landing pricing page: yearly saving, founder price, merged comparison features and the mobile
// comparison pair are derived only from GET /public/plans data.
// Run from backend/: node --test "tests/js/*.test.js"
import { test } from 'node:test';
import assert from 'node:assert/strict';
import {
    catalogYearlySaving,
    defaultComparePair,
    distinctFirstFeatures,
    groupCatalogFeatures,
    mergeCatalogFeatures,
    planDisplayPrice,
    yearlySavingPercent,
} from '../../resources/js/helpers/landingCatalog.js';

const f = (key, module = 'sales') => ({ key, name: key, description: null, module });

test('yearly saving is a whole percent and 0 without a real discount', () => {
    assert.equal(yearlySavingPercent('449.000', '4490.000'), 16);
    assert.equal(yearlySavingPercent('0.000', '0.000'), 0);
    assert.equal(yearlySavingPercent('100.000', '1200.000'), 0);
    assert.equal(yearlySavingPercent(null, '10.000'), 0);
});

test('catalog saving is the best percent, "up to" only when plans differ', () => {
    const same = [
        { price_monthly: '0.000', price_yearly: '0.000' },
        { price_monthly: '100.000', price_yearly: '1000.000' },
        { price_monthly: '200.000', price_yearly: '2000.000' },
    ];
    assert.deepEqual(catalogYearlySaving(same), { percent: 16, upTo: false });
    const mixed = [...same, { price_monthly: '100.000', price_yearly: '900.000' }];
    assert.deepEqual(catalogYearlySaving(mixed), { percent: 25, upTo: true });
    assert.deepEqual(catalogYearlySaving([]), { percent: 0, upTo: false });
});

test('the founder price is shown only while the offer is available', () => {
    const plan = {
        price_monthly: '449.000',
        price_yearly: '4490.000',
        founder_price_monthly: '299.000',
        founder_price_yearly: null,
    };
    assert.equal(planDisplayPrice(plan, 'monthly', { available: true }).shown, '299.000');
    assert.equal(planDisplayPrice(plan, 'monthly', { available: false }).shown, '449.000');
    const yearly = planDisplayPrice(plan, 'yearly', { available: true });
    assert.equal(yearly.shown, '4490.000');
    assert.equal(yearly.hasFounder, false);
});

test('merged features keep catalog order and group by module', () => {
    const plans = [
        { code: 'a', features: [f('pos'), f('items', 'inventory')] },
        { code: 'b', features: [f('pos'), f('quotes'), f('items', 'inventory'), f('mixes', 'inventory')] },
    ];
    const merged = mergeCatalogFeatures(plans);
    assert.deepEqual(
        merged.map((x) => x.key),
        ['pos', 'quotes', 'items', 'mixes']
    );
    assert.deepEqual(
        groupCatalogFeatures(merged).map((g) => [g.module, g.features.length]),
        [
            ['sales', 2],
            ['inventory', 2],
        ]
    );
    assert.deepEqual(groupCatalogFeatures([{ key: 'x', name: 'x' }])[0].module, 'other');
});

test('distinctive features come first on a card', () => {
    const plans = [
        { code: 'a', features: [f('pos'), f('items')] },
        { code: 'b', features: [f('pos'), f('items'), f('audit')] },
    ];
    assert.deepEqual(
        distinctFirstFeatures(plans[1], plans).map((x) => x.key),
        ['audit', 'pos', 'items']
    );
    assert.deepEqual(
        distinctFirstFeatures(plans[0], [plans[0]]).map((x) => x.key),
        ['pos', 'items']
    );
});

test('the mobile comparison starts with the popular plan and its cheaper neighbour', () => {
    const plans = ['free', 'basic', 'pro', 'enterprise'].map((code) => ({ code, is_popular: code === 'pro' }));
    assert.deepEqual(defaultComparePair(plans), ['basic', 'pro']);
    plans[2].is_popular = false;
    assert.deepEqual(defaultComparePair(plans), ['free', 'basic']);
    plans[0].is_popular = true;
    assert.deepEqual(defaultComparePair(plans), ['free', 'basic']);
    assert.deepEqual(defaultComparePair(plans.slice(0, 1)), ['free']);
});
