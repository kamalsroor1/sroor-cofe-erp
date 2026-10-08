'use strict';

// Regression tests for P0-ELEC-3. `config:save-settings` used to merge any
// renderer-supplied object straight into the config file and loadURL the new
// serverUrl, so a hostile page could persist itself (or flip kioskMode).
//
// Contract required (pure, must not require electron), exported from
// src/security/settingsSanitizer.js (or, alternatively, src/security/urlPolicy.js):
//   sanitizeSettings(input: unknown):
//       { ok: true,  settings: object }   -> only whitelisted keys, validated values
//     | { ok: false, error?: string }     -> reject the whole save

const test = require('node:test');
const assert = require('node:assert/strict');
const path = require('node:path');

function sanitizer() {
    const dir = path.join(__dirname, '..', 'src', 'security');
    let firstError;
    for (const file of ['settingsSanitizer.js', 'urlPolicy.js']) {
        try {
            const mod = require(path.join(dir, file));
            if (typeof mod.sanitizeSettings === 'function') return mod.sanitizeSettings;
        } catch (e) {
            firstError = firstError || e;
        }
    }
    throw firstError || new Error('sanitizeSettings is not exported from src/security/');
}

test('rejects a foreign serverUrl', () => {
    const res = sanitizer()({ serverUrl: 'https://evil.com' });
    assert.equal(res.ok, false);
});

test('rejects the finding 14 serverUrl shape', () => {
    assert.equal(sanitizer()({ serverUrl: 'https://evil.example/.baraa-solutions.com' }).ok, false);
});

test('rejects an http (downgraded) tenant serverUrl', () => {
    assert.equal(sanitizer()({ serverUrl: 'http://2m.baraa-solutions.com' }).ok, false);
});

test('rejects a hostile tenantId', () => {
    for (const tenantId of ['evil.example/', 'a.b', '-x', 'user@evil.com', 'a'.repeat(64)]) {
        assert.equal(sanitizer()({ tenantId }).ok, false, `tenantId ${tenantId} must be rejected`);
    }
});

test('rejects non-object input', () => {
    const sanitize = sanitizer();
    for (const input of [null, undefined, 'https://evil.com', 42, ['serverUrl']]) {
        assert.equal(sanitize(input).ok, false, `input ${JSON.stringify(input)} must be rejected`);
    }
});

test('drops unknown and privileged keys (kioskMode, windowBounds, centralUrl, foo)', () => {
    const res = sanitizer()({
        kioskMode: true,
        foo: 1,
        windowBounds: { width: 1, height: 1 },
        centralUrl: 'https://evil.com',
        __proto__: { polluted: true },
        thermalPrinterName: 'XP-80',
    });
    assert.equal(res.ok, true);
    assert.equal(Object.hasOwn(res.settings, 'kioskMode'), false);
    assert.equal(Object.hasOwn(res.settings, 'foo'), false);
    assert.equal(Object.hasOwn(res.settings, 'windowBounds'), false);
    assert.equal(Object.hasOwn(res.settings, 'centralUrl'), false);
    assert.equal(res.settings.polluted, undefined);
    assert.equal(res.settings.thermalPrinterName, 'XP-80');
});

// Legitimate payloads the SPA sends today must keep working.
test('accepts WorkspaceConnectView payload (tenant origin + tenant id)', () => {
    const res = sanitizer()({ serverUrl: 'https://2m.baraa-solutions.com', tenantId: '2m' });
    assert.equal(res.ok, true);
    assert.equal(new URL(res.settings.serverUrl).origin, 'https://2m.baraa-solutions.com');
    assert.equal(res.settings.tenantId, '2m');
});

test('accepts LoginView "switch workspace" payload (central connect page + empty tenant)', () => {
    const res = sanitizer()({ tenantId: '', serverUrl: 'https://baraa-solutions.com/connect' });
    assert.equal(res.ok, true);
    assert.equal(res.settings.tenantId, '');
    assert.equal(res.settings.serverUrl, 'https://baraa-solutions.com/connect');
});
