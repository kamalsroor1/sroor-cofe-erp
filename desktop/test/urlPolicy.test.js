'use strict';

// Regression tests for P0-ELEC-1 (02-security.md finding 14).
// The deep link sroor://connect?tenant=<x> used to be turned into
// `https://${x}.baraa-solutions.com` with no validation, so a crafted tenant
// value could move the main window (with the preload bridge) to any origin.
//
// Contract required from src/security/urlPolicy.js (pure, must not require electron):
//   parseDeepLink(url: string): string | null        -> normalised tenant code or null
//   buildTenantOrigin(code: string): string           -> 'https://<code>.baraa-solutions.com'
//   isAllowedAppUrl(url: string, tenant?: string): boolean
//   sanitizeServerUrl(url: string): string | null

const test = require('node:test');
const assert = require('node:assert/strict');
const path = require('node:path');

const MODULE_PATH = path.join(__dirname, '..', 'src', 'security', 'urlPolicy.js');

// Loaded lazily inside each test so a missing module fails every case
// individually instead of crashing the whole file.
function policy() {
    return require(MODULE_PATH);
}

test('urlPolicy module does not depend on electron', () => {
    const mod = policy();
    for (const fn of ['parseDeepLink', 'buildTenantOrigin', 'isAllowedAppUrl', 'sanitizeServerUrl']) {
        assert.equal(typeof mod[fn], 'function', `${fn} must be exported`);
    }
});

test('parseDeepLink rejects tenant values that change the host or are not a single DNS label', async (t) => {
    const hostile = [
        'sroor://connect?tenant=evil.example%2F', // finding 14 PoC: host becomes evil.example
        'sroor://connect?tenant=evil.com%23', // fragment breaks out of the host
        'sroor://connect?tenant=evil.com%3F', // query breaks out of the host
        'sroor://connect?tenant=a.b', // nested subdomain
        'sroor://connect?tenant=-x', // leading hyphen is not a valid label
        'sroor://connect?tenant=x-', // trailing hyphen is not a valid label
        'sroor://connect?tenant=' + 'a'.repeat(64), // label longer than 63 chars
        'sroor://connect?tenant=user%40evil.com', // userinfo injection
        'sroor://connect?tenant=evil.com%3A443', // port injection
        'sroor://connect?tenant=%E2%80%8B2m', // zero-width space
        'sroor://connect?tenant=', // empty
        'sroor://connect?code=evil.example%2F', // legacy `code` alias must be validated too
    ];
    for (const link of hostile) {
        await t.test(link, () => {
            assert.equal(policy().parseDeepLink(link), null);
        });
    }
});

test('parseDeepLink accepts a valid tenant and lowercases it', () => {
    assert.equal(policy().parseDeepLink('sroor://connect?tenant=2M'), '2m');
    assert.equal(policy().parseDeepLink('sroor://connect?tenant=my-shop'), 'my-shop');
});

test('parseDeepLink only accepts the connect action of the sroor scheme', () => {
    const { parseDeepLink } = policy();
    assert.equal(parseDeepLink('sroor://other?tenant=2m'), null);
    assert.equal(parseDeepLink('https://connect?tenant=2m'), null);
    assert.equal(parseDeepLink('javascript://connect?tenant=2m'), null);
    assert.equal(parseDeepLink(''), null);
    assert.equal(parseDeepLink(null), null);
    assert.equal(parseDeepLink(undefined), null);
    assert.equal(parseDeepLink(42), null);
});

test('buildTenantOrigin builds the official tenant origin', () => {
    assert.equal(policy().buildTenantOrigin('2m'), 'https://2m.baraa-solutions.com');
});

test('buildTenantOrigin refuses an invalid tenant code instead of building a foreign origin', () => {
    const { buildTenantOrigin } = policy();
    for (const bad of ['evil.example/', 'evil.com#', 'a.b', '-x', 'user@evil.com', '', 'a'.repeat(64)]) {
        let out;
        try {
            out = buildTenantOrigin(bad);
        } catch {
            continue; // throwing is an acceptable refusal
        }
        assert.equal(out, null, `buildTenantOrigin(${JSON.stringify(bad)}) must refuse, got ${out}`);
    }
});

test('isAllowedAppUrl accepts the tenant origin and the central connect page', () => {
    const { isAllowedAppUrl } = policy();
    assert.equal(isAllowedAppUrl('https://2m.baraa-solutions.com', '2m'), true);
    assert.equal(isAllowedAppUrl('https://2m.baraa-solutions.com/login', '2m'), true);
    assert.equal(isAllowedAppUrl('https://2m.baraa-solutions.com/pos?tab=1#x', '2m'), true);
    assert.equal(isAllowedAppUrl('https://baraa-solutions.com/connect', '2m'), true);
});

test('isAllowedAppUrl rejects foreign, downgraded and malformed URLs', async (t) => {
    const hostile = [
        'http://2m.baraa-solutions.com', // https downgrade
        'http://2m.baraa-solutions.com/login',
        'https://evil.com/.baraa-solutions.com', // suffix in the path
        'https://evil.example/.baraa-solutions.com/login', // finding 14 resulting URL
        'https://baraa-solutions.com.evil.com', // suffix spoof
        'https://2m.baraa-solutions.com.evil.com/login',
        'https://x@evil.com', // userinfo
        'https://2m.baraa-solutions.com@evil.com/login',
        'https://2m.baraa-solutions.com:8443', // non-default port
        'https://a.b.baraa-solutions.com', // nested subdomain
        'https://evilbaraa-solutions.com', // no dot boundary
        'data:text/html,<script>alert(1)</script>',
        'file:///C:/Windows/System32/calc.exe',
        'javascript:alert(1)',
        'ftp://2m.baraa-solutions.com',
        'not a url',
        '',
    ];
    for (const url of hostile) {
        await t.test(url || '(empty)', () => {
            assert.equal(policy().isAllowedAppUrl(url, '2m'), false);
        });
    }
});

test('isAllowedAppUrl rejects non-string input', () => {
    const { isAllowedAppUrl } = policy();
    assert.equal(isAllowedAppUrl(null, '2m'), false);
    assert.equal(isAllowedAppUrl(undefined, '2m'), false);
    assert.equal(isAllowedAppUrl({ href: 'https://2m.baraa-solutions.com' }, '2m'), false);
});

test('sanitizeServerUrl rejects the finding 14 serverUrl and other foreign origins', () => {
    const { sanitizeServerUrl } = policy();
    assert.equal(sanitizeServerUrl('https://evil.example/.baraa-solutions.com'), null);
    assert.equal(sanitizeServerUrl('https://evil.com'), null);
    assert.equal(sanitizeServerUrl('http://2m.baraa-solutions.com'), null);
    assert.equal(sanitizeServerUrl('https://baraa-solutions.com.evil.com'), null);
    assert.equal(sanitizeServerUrl('https://x@evil.com'), null);
    assert.equal(sanitizeServerUrl('javascript:alert(1)'), null);
    assert.equal(sanitizeServerUrl(''), null);
    assert.equal(sanitizeServerUrl(null), null);
});

test('sanitizeServerUrl keeps a valid tenant origin', () => {
    const out = policy().sanitizeServerUrl('https://2m.baraa-solutions.com');
    assert.equal(typeof out, 'string');
    assert.equal(new URL(out).origin, 'https://2m.baraa-solutions.com');
});
