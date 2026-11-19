// MOB-06: hub (central host) -> shop subdomain hand-over and the remembered last shop.
// Run from backend/: node --test "tests/js/*.test.js"
import { test } from 'node:test';
import assert from 'node:assert/strict';
import {
    LAST_SHOP_KEY,
    centralConnectUrl,
    forgetShop,
    publicCentralHost,
    readRememberedShop,
    rememberShop,
    shopLoginUrl,
} from '../../resources/js/helpers/workspaceHandoff.js';

function fakeDocument(central) {
    return {
        querySelector: (selector) => (selector.includes('central-domains') ? { getAttribute: () => central } : null),
    };
}

function memoryStorage() {
    const data = new Map();
    return {
        getItem: (k) => (data.has(k) ? data.get(k) : null),
        setItem: (k, v) => data.set(k, String(v)),
        removeItem: (k) => data.delete(k),
        data,
    };
}

test('the public hub is the first non-loopback central host', () => {
    assert.equal(publicCentralHost(fakeDocument('localhost,ibraa.app')), 'ibraa.app');
    assert.equal(publicCentralHost(fakeDocument('localhost,127.0.0.1')), null);
    assert.equal(publicCentralHost(fakeDocument('')), null);
});

test('shop login url keeps the https origin only', () => {
    assert.equal(shopLoginUrl('https://2m.ibraa.app'), 'https://2m.ibraa.app/login');
    assert.equal(shopLoginUrl('https://2m.ibraa.app/some/path?x=1'), 'https://2m.ibraa.app/login');
    assert.equal(shopLoginUrl('http://localhost:8000'), 'http://localhost:8000/login');
});

test('shop login url rejects insecure or malformed urls', () => {
    assert.equal(shopLoginUrl('http://2m.ibraa.app'), null);
    assert.equal(shopLoginUrl('javascript:alert(1)'), null);
    assert.equal(shopLoginUrl('https://user:pass@2m.ibraa.app'), null);
    assert.equal(shopLoginUrl(''), null);
    assert.equal(shopLoginUrl(undefined), null);
});

test('switching shop goes back to the hub connect screen', () => {
    assert.equal(centralConnectUrl(fakeDocument('ibraa.app')), 'https://ibraa.app/connect?switch=1');
    assert.equal(centralConnectUrl(fakeDocument('ibraa.app'), { switchShop: false }), 'https://ibraa.app/connect');
    assert.equal(centralConnectUrl(fakeDocument('localhost')), null);
});

test('the last shop is remembered, read back and forgotten', () => {
    const storage = memoryStorage();
    assert.equal(readRememberedShop(storage), null);

    rememberShop(storage, { tenant_id: 't1', slug: '2m', name: '2M', server_url: 'https://2m.ibraa.app' });
    assert.deepEqual(readRememberedShop(storage), { code: '2m', name: '2M' });
    assert.deepEqual(Object.keys(JSON.parse(storage.data.get(LAST_SHOP_KEY))), ['code', 'name']);

    forgetShop(storage);
    assert.equal(readRememberedShop(storage), null);
});

test('a corrupt or empty remembered shop reads as none', () => {
    const storage = memoryStorage();
    storage.setItem(LAST_SHOP_KEY, '{oops');
    assert.equal(readRememberedShop(storage), null);
    storage.setItem(LAST_SHOP_KEY, JSON.stringify({ code: '  ' }));
    assert.equal(readRememberedShop(storage), null);
    rememberShop(storage, {});
    assert.equal(readRememberedShop(storage), null);
    assert.equal(readRememberedShop(null), null);
});
