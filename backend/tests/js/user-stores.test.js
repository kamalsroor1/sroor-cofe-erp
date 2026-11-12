// ROLE-01: branch assignment rules of the users form. Run: node --test tests/js/user-stores.test.js
import { test } from 'node:test';
import assert from 'node:assert/strict';
import {
    isStoreRequired,
    normalizeDefaultStore,
    toggleStoreId,
    userStoreFieldErrors,
    userStoreIds,
    userStoresErrorKey,
} from '../../resources/js/helpers/userStores.js';

test('every role except admin needs a branch', () => {
    assert.equal(isStoreRequired('cashier'), true);
    assert.equal(isStoreRequired('accountant'), true);
    assert.equal(isStoreRequired('admin'), false);
});

test('a non-admin without branches gets the required error key; admin and a filled selection do not', () => {
    assert.equal(userStoresErrorKey({ role: 'cashier', store_ids: [] }), 'users.store_required');
    assert.equal(userStoresErrorKey({ role: 'cashier' }), 'users.store_required');
    assert.equal(userStoresErrorKey({ role: 'cashier', store_ids: [2] }), '');
    assert.equal(userStoresErrorKey({ role: 'admin', store_ids: [] }), '');
});

test('toggling adds a missing id and removes a present one', () => {
    assert.deepEqual(toggleStoreId([1], 2), [1, 2]);
    assert.deepEqual(toggleStoreId([1, 2], 1), [2]);
    assert.deepEqual(toggleStoreId([], 3), [3]);
});

test('the default branch stays among the selected ones', () => {
    assert.equal(normalizeDefaultStore([1, 2], 2), 2);
    assert.equal(normalizeDefaultStore([1, 2], 9), 1);
    assert.equal(normalizeDefaultStore([3], null), 3);
    assert.equal(normalizeDefaultStore([], 2), null);
});

test('stored branch ids of a user are numbers without duplicates', () => {
    assert.deepEqual(userStoreIds({ store_ids: ['1', 2, 2] }), [1, 2]);
    assert.deepEqual(userStoreIds({}), []);
    assert.deepEqual(userStoreIds(null), []);
});

test('server errors on store_ids, store_ids.* and default_store_id map to the two form fields', () => {
    const err = (errors) => ({ response: { status: 422, data: { errors } } });
    assert.deepEqual(userStoreFieldErrors(err({ store_ids: ['Pick one'] })), {
        store_ids: 'Pick one',
        default_store_id: '',
    });
    assert.deepEqual(userStoreFieldErrors(err({ 'store_ids.1': ['Bad branch'], default_store_id: ['Bad default'] })), {
        store_ids: 'Bad branch',
        default_store_id: 'Bad default',
    });
    assert.deepEqual(userStoreFieldErrors(err({ name: ['x'] })), { store_ids: '', default_store_id: '' });
    assert.deepEqual(userStoreFieldErrors(new Error('boom')), { store_ids: '', default_store_id: '' });
});
