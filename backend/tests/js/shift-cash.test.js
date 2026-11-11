// helpers/shiftCash.js: live drawer difference in the close-shift modal (API sends '0.000' strings).
import { test } from 'node:test';
import assert from 'node:assert/strict';
import { drawerDifference } from '../../resources/js/helpers/shiftCash.js';

test('nothing counted yet gives no difference', () => {
    assert.equal(drawerDifference('', '100.000'), null);
    assert.equal(drawerDifference('   ', '100.000'), null);
    assert.equal(drawerDifference(null, '100.000'), null);
    assert.equal(drawerDifference(undefined, '100.000'), null);
});

test('counted equal to the expected string is balanced', () => {
    assert.deepEqual(drawerDifference('62.500', '62.500'), { amount: '0.000', sign: 0 });
    assert.deepEqual(drawerDifference('62.5', '62.500'), { amount: '0.000', sign: 0 });
    assert.deepEqual(drawerDifference('0', '0.000'), { amount: '0.000', sign: 0 });
});

test('surplus and shortage are exact, never string concatenation or float noise', () => {
    assert.deepEqual(drawerDifference('100.300', '100.100'), { amount: '0.200', sign: 1 });
    assert.deepEqual(drawerDifference('90', '100.250'), { amount: '-10.250', sign: -1 });
    assert.deepEqual(drawerDifference('0.3', '0.1'), { amount: '0.200', sign: 1 });
});

test('a missing expected value counts as zero', () => {
    assert.deepEqual(drawerDifference('15.000', undefined), { amount: '15.000', sign: 1 });
    assert.deepEqual(drawerDifference('15.000', null), { amount: '15.000', sign: 1 });
});
