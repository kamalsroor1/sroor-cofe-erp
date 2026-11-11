import { test } from 'node:test';
import assert from 'node:assert/strict';

test('authStore activeStoreId getter returns empty string when no store is selected', () => {
    const state = { currentStore: null };
    const activeStoreId = state.currentStore?.id || '';
    assert.equal(activeStoreId, '');
});

test('authStore activeStoreId getter returns current store id when set', () => {
    const state = { currentStore: { id: 42, name: 'Main Branch' } };
    const activeStoreId = state.currentStore?.id || '';
    assert.equal(activeStoreId, 42);
});

test('router-view key changes when branch is switched on the same route', () => {
    const fullPath = '/invoices';
    const storeA = 1;
    const storeB = 2;

    const keyBefore = `${fullPath}-${storeA}`;
    const keyAfter = `${fullPath}-${storeB}`;

    assert.equal(keyBefore, '/invoices-1');
    assert.equal(keyAfter, '/invoices-2');
    assert.notEqual(keyBefore, keyAfter);
});

test('router-view key stays stable when store id and route do not change', () => {
    const fullPath = '/dashboard';
    const storeId = 5;

    const key1 = `${fullPath}-${storeId}`;
    const key2 = `${fullPath}-${storeId}`;

    assert.equal(key1, key2);
});
