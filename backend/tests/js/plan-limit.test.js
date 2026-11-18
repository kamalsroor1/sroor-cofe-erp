// PLAN-13: plan-limit helpers (403 detection, usage levels).
// Run from backend/: node --test tests/js/plan-limit.test.js
import { test } from 'node:test';
import assert from 'node:assert/strict';
import {
    isLimitReachedError,
    limitResourceOf,
    limitedRows,
    usageLevel,
    worstUsageLevel,
} from '../../resources/js/helpers/planLimit.js';

const err = (status, data) => ({ response: { status, data } });

test('only the 403 limit_reached answer is a limit error', () => {
    const limit = err(403, { error_code: 'subscription.limit_reached', details: { limit: 'items', max: 100 } });
    assert.equal(isLimitReachedError(limit), true);
    assert.equal(limitResourceOf(limit), 'items');
    // Other 403s are not hijacked.
    assert.equal(isLimitReachedError(err(403, { error_code: 'subscription.feature_unavailable' })), false);
    assert.equal(isLimitReachedError(err(403, { message: 'Forbidden' })), false);
    assert.equal(isLimitReachedError(err(422, { error_code: 'subscription.limit_reached' })), false);
    assert.equal(isLimitReachedError(new Error('network')), false);
    assert.equal(isLimitReachedError(null), false);
    assert.equal(limitResourceOf(err(403, {})), null);
});

test('usage turns yellow at 80% and red at 100%', () => {
    assert.equal(usageLevel(null), 'none');
    assert.equal(usageLevel(undefined), 'none');
    assert.equal(usageLevel(0), 'ok');
    assert.equal(usageLevel(79), 'ok');
    assert.equal(usageLevel(80), 'warn');
    assert.equal(usageLevel(99), 'warn');
    assert.equal(usageLevel(100), 'full');
    assert.equal(usageLevel(140), 'full');
});

test('unlimited resources are dropped and the worst level wins', () => {
    const rows = limitedRows([
        { resource: 'users', limit: 2, percent: 50 },
        { resource: 'items', limit: null, percent: null },
        { resource: 'invoices_month', limit: 150, percent: 85 },
    ]);
    assert.deepEqual(
        rows.map((r) => r.resource),
        ['users', 'invoices_month']
    );
    assert.equal(worstUsageLevel(rows), 'warn');
    assert.equal(worstUsageLevel([]), 'none');
    assert.deepEqual(limitedRows(undefined), []);
});
