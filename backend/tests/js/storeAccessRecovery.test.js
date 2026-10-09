// STOR-1 recovery: a stale X-Store-Id (branch the user lost access to) must not lock the SPA.
// Run from backend/: node --test tests/js/storeAccessRecovery.test.js
import { test } from 'node:test';
import assert from 'node:assert/strict';
import {
    STORE_ACCESS_DENIED,
    isStoreAccessDenied,
    storeAccessRetryConfig,
} from '../../resources/js/helpers/storeAccessRecovery.js';

const memoryStorage = (initial = {}) => {
    const map = new Map(Object.entries(initial));
    return {
        getItem: (k) => (map.has(k) ? map.get(k) : null),
        removeItem: (k) => map.delete(k),
        dump: () => Object.fromEntries(map),
    };
};

const deniedError = (
    config = { url: '/items', method: 'get', headers: { 'X-Store-Id': '7', Accept: 'application/json' } }
) => ({
    config,
    response: { status: 403, data: { success: false, error_code: STORE_ACCESS_DENIED } },
});

test('a store_access_denied 403 forgets the stored branch and retries once without X-Store-Id', () => {
    const storage = memoryStorage({ current_store_id: '7', auth_store: '{"id":7}', auth_token: 'tok' });

    const retry = storeAccessRetryConfig(deniedError(), storage);

    assert.ok(retry);
    assert.equal(retry.url, '/items');
    assert.equal(retry.headers['X-Store-Id'], undefined);
    assert.equal(retry.headers.Accept, 'application/json');
    assert.deepEqual(storage.dump(), { auth_token: 'tok' });
});

test('the retried request is never retried again (no loop)', () => {
    const storage = memoryStorage();
    const first = storeAccessRetryConfig(deniedError(), storage);

    assert.equal(storeAccessRetryConfig(deniedError(first), storage), null);
});

test('header names are matched case-insensitively and AxiosHeaders-like objects are supported', () => {
    const headers = { toJSON: () => ({ 'x-store-id': '7', Authorization: 'Bearer t' }) };
    const retry = storeAccessRetryConfig(deniedError({ url: '/me', headers }), memoryStorage());

    assert.deepEqual(retry.headers, { Authorization: 'Bearer t' });
});

test('other 403s and other errors are left alone', () => {
    const storage = memoryStorage({ current_store_id: '7' });
    const permission403 = { config: { url: '/x' }, response: { status: 403, data: { message: 'no' } } };
    const validation = { config: { url: '/x' }, response: { status: 422, data: { error_code: STORE_ACCESS_DENIED } } };

    assert.equal(isStoreAccessDenied(permission403), false);
    assert.equal(storeAccessRetryConfig(permission403, storage), null);
    assert.equal(storeAccessRetryConfig(validation, storage), null);
    assert.equal(storeAccessRetryConfig({ message: 'Network Error' }, storage), null);
    assert.equal(storage.getItem('current_store_id'), '7');
});
