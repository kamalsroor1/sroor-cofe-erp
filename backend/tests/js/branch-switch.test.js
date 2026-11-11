import { test } from 'node:test';
import assert from 'node:assert/strict';
import { routeViewKey } from '../../resources/js/helpers/routeViewKey.js';

const page = (fullPath, name = 'other') => ({ fullPath, name });

test('router-view key changes when the branch is switched on the same route', () => {
    assert.equal(routeViewKey(page('/invoices'), 1), '/invoices-1');
    assert.notEqual(routeViewKey(page('/invoices'), 1), routeViewKey(page('/invoices'), 2));
});

test('router-view key stays stable when the store id and route do not change', () => {
    assert.equal(routeViewKey(page('/dashboard'), 5), routeViewKey(page('/dashboard'), 5));
});

test('the POS keeps its key (and its open cart) when the branch is switched', () => {
    const pos = page('/pos', 'pos.index');
    assert.equal(routeViewKey(pos, 1), '/pos');
    assert.equal(routeViewKey(pos, 1), routeViewKey(pos, 2));
});

test('an empty store id still yields a stable key', () => {
    assert.equal(routeViewKey(page('/invoices'), ''), '/invoices-');
});
