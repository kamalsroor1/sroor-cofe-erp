// W1: a NULL plan limit means UNLIMITED. The super-admin plans UI must never turn it into 0.
// Run from backend/: node --test "tests/js/*.test.js"
import { test } from 'node:test';
import assert from 'node:assert/strict';
import {
    isUnlimited,
    normalizeLimit,
    validateLimit,
    pickPlanLimits,
    presentLimitFields,
    preparePlanLimits,
    PLAN_LIMIT_MAX,
} from '../../resources/js/helpers/planLimits.js';

test('null, undefined and empty strings are unlimited; 0 is a real limit', () => {
    assert.equal(isUnlimited(null), true);
    assert.equal(isUnlimited(undefined), true);
    assert.equal(isUnlimited(''), true);
    assert.equal(isUnlimited('  '), true);
    assert.equal(isUnlimited(0), false);
    assert.equal(isUnlimited('0'), false);
});

test('normalizeLimit keeps null and never coerces it to 0', () => {
    assert.equal(normalizeLimit(null), null);
    assert.equal(normalizeLimit(''), null);
    assert.equal(normalizeLimit('25'), 25);
    assert.equal(normalizeLimit(0), 0);
});

test('validateLimit accepts empty as unlimited and enforces integer range', () => {
    assert.equal(validateLimit(null, 1), null);
    assert.equal(validateLimit('', 1), null);
    assert.equal(validateLimit(5, 1), null);
    assert.equal(validateLimit(0, 0), null);
    assert.deepEqual(validateLimit(0, 1), { min: 1, max: PLAN_LIMIT_MAX });
    assert.deepEqual(validateLimit(-1, 0), { min: 0, max: PLAN_LIMIT_MAX });
    assert.deepEqual(validateLimit(2.5, 1), { min: 1, max: PLAN_LIMIT_MAX });
    assert.deepEqual(validateLimit('abc', 1), { min: 1, max: PLAN_LIMIT_MAX });
    assert.deepEqual(validateLimit(PLAN_LIMIT_MAX + 1, 1), { min: 1, max: PLAN_LIMIT_MAX });
});

test('pickPlanLimits copies only returned fields and preserves null', () => {
    const limits = pickPlanLimits({ id: 1, max_users: null, max_stores: 3, max_vans: 0 });

    assert.deepEqual(limits, { max_users: null, max_stores: 3, max_vans: 0 });
    assert.equal('max_items' in limits, false);
});

test('presentLimitFields follows the payload keys', () => {
    const keys = presentLimitFields({ max_users: null, max_items: 10, name: 'x' }).map((f) => f.key);

    assert.deepEqual(keys, ['max_users', 'max_items']);
});

test('preparePlanLimits sends null for unlimited and reports invalid fields', () => {
    const ok = preparePlanLimits({ max_users: null, max_stores: '', max_items: '100', max_warehouses: 0 });
    assert.deepEqual(ok.errors, {});
    assert.deepEqual(ok.values, { max_users: null, max_stores: null, max_items: 100, max_warehouses: 0 });

    const bad = preparePlanLimits({ max_users: 0, max_vans: -2 });
    assert.deepEqual(Object.keys(bad.errors).sort(), ['max_users', 'max_vans']);
});
