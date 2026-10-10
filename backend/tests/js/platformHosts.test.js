// The SPA reads the platform's central / admin hosts from server-rendered meta tags
// (resources/views/app.blade.php) instead of hardcoding production domains.
// Run from backend/: node --test "tests/js/*.test.js"
import { test } from 'node:test';
import assert from 'node:assert/strict';
import {
    getAdminDomains,
    getCentralDomains,
    isCentralHost,
    isLoopbackHost,
    parseHostList,
} from '../../resources/js/helpers/platformHosts.js';

function fakeDocument(metas) {
    return {
        querySelector(selector) {
            const name = /meta\[name="([^"]+)"\]/.exec(selector)?.[1];
            if (!name || !(name in metas)) return null;

            return { getAttribute: (attribute) => (attribute === 'content' ? metas[name] : null) };
        },
    };
}

test('host lists are trimmed, lower-cased, de-duplicated and skip blanks', () => {
    assert.deepEqual(parseHostList(' Sroor.TEST , ,localhost,sroor.test'), ['sroor.test', 'localhost']);
    assert.deepEqual(parseHostList(''), []);
    assert.deepEqual(parseHostList(undefined), []);
});

test('central and admin domains come from their own meta tags', () => {
    const doc = fakeDocument({ 'central-domains': 'sroor.test,localhost', 'admin-domains': 'admin.sroor.test' });

    assert.deepEqual(getCentralDomains(doc), ['sroor.test', 'localhost']);
    assert.deepEqual(getAdminDomains(doc), ['admin.sroor.test']);
});

test('a missing meta tag or document yields no hosts', () => {
    assert.deepEqual(getCentralDomains(fakeDocument({})), []);
    assert.deepEqual(getAdminDomains(fakeDocument({ 'central-domains': 'sroor.test' })), []);
    assert.deepEqual(getCentralDomains(null), []);
});

test('isCentralHost matches the configured hosts only', () => {
    const doc = fakeDocument({ 'central-domains': 'sroor.test,localhost' });

    assert.equal(isCentralHost('sroor.test', doc), true);
    assert.equal(isCentralHost('SROOR.test', doc), true);
    assert.equal(isCentralHost('demo.sroor.test', doc), false);
    assert.equal(isCentralHost('', doc), false);
    assert.equal(isCentralHost('sroor.test', fakeDocument({})), false);
});

test('loopback hosts are recognised', () => {
    assert.equal(isLoopbackHost('localhost'), true);
    assert.equal(isLoopbackHost('127.0.0.1'), true);
    assert.equal(isLoopbackHost('sroor.test'), false);
    assert.equal(isLoopbackHost('demo.localhost'), false);
});
