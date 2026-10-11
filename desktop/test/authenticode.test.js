'use strict';

// APP-7: Authenticode decision for a downloaded installer. Policy is off by default (CTO Q11:
// no certificate yet); when enforced, only a Valid signature from a listed publisher passes.

const test = require('node:test');
const assert = require('node:assert/strict');
const path = require('node:path');

const SRC = path.join(__dirname, '..', 'src');
const { evaluateSignature, parseSignatureJson, normalizeStatus, readAuthenticodeSignature, PS_SCRIPT } = require(
    path.join(SRC, 'updater', 'authenticode.js')
);
const defaultPolicy = require(path.join(SRC, 'config', 'updatePolicy.js'));

const THUMB = 'AB12CD34EF56AB12CD34EF56AB12CD34EF56AB12';
const SUBJECT = 'CN=Baraa Solutions, O=Baraa Solutions, C=EG';
const valid = { status: 'Valid', subject: SUBJECT, thumbprint: THUMB };
const enforced = (trustedPublishers) => ({ requireAuthenticode: true, trustedPublishers });

test('the shipped policy does not enforce Authenticode yet', () => {
    assert.equal(defaultPolicy.requireAuthenticode, false);
    assert.deepEqual([...defaultPolicy.trustedPublishers], []);
    assert.ok(Object.isFrozen(defaultPolicy));
});

test('policy off: unsigned or unreadable installers pass (SHA-256 is the integrity check)', () => {
    assert.equal(evaluateSignature({ status: 'NotSigned' }, defaultPolicy).ok, true);
    assert.equal(evaluateSignature(null, defaultPolicy).ok, true);
    assert.equal(evaluateSignature(valid, defaultPolicy).reason, 'not_enforced');
});

test('a broken signature (HashMismatch) is refused even with the policy off', () => {
    assert.deepEqual(evaluateSignature({ status: 'HashMismatch' }, defaultPolicy), {
        ok: false,
        reason: 'hash_mismatch',
    });
    assert.equal(evaluateSignature({ status: 3 }, defaultPolicy).ok, false);
});

test('policy on: unsigned, unreadable and untrusted signatures are refused', () => {
    const policy = enforced([THUMB]);
    assert.deepEqual(evaluateSignature({ status: 'NotSigned' }, policy), { ok: false, reason: 'not_signed' });
    assert.deepEqual(evaluateSignature(null, policy), { ok: false, reason: 'signature_unreadable' });
    assert.deepEqual(evaluateSignature({ ...valid, status: 'NotTrusted' }, policy), {
        ok: false,
        reason: 'invalid_signature',
    });
    assert.deepEqual(evaluateSignature({ ...valid, status: 'UnknownError' }, policy), {
        ok: false,
        reason: 'invalid_signature',
    });
});

test('policy on: a valid signature from another publisher is refused', () => {
    const other = { status: 'Valid', subject: 'CN=Someone Else', thumbprint: 'FF'.repeat(20) };
    assert.deepEqual(evaluateSignature(other, enforced([THUMB, SUBJECT])), {
        ok: false,
        reason: 'publisher_untrusted',
    });
});

test('policy on with no trusted publisher listed fails closed', () => {
    assert.deepEqual(evaluateSignature(valid, enforced([])), { ok: false, reason: 'no_trusted_publishers' });
});

test('policy on: trusted publisher by thumbprint (any case / spacing), subject, or both', () => {
    assert.equal(evaluateSignature(valid, enforced([THUMB.toLowerCase().match(/.{2}/g).join(' ')])).ok, true);
    assert.equal(evaluateSignature(valid, enforced([SUBJECT])).ok, true);
    assert.equal(evaluateSignature(valid, enforced([{ thumbprint: THUMB, subject: SUBJECT }])).ok, true);
    assert.equal(evaluateSignature(valid, enforced([{ thumbprint: THUMB, subject: 'CN=Other' }])).ok, false);
    assert.equal(evaluateSignature(valid, enforced([{}, ''])).ok, false);
});

test('status accepts the enum number or name', () => {
    assert.equal(normalizeStatus(0), 'Valid');
    assert.equal(normalizeStatus(2), 'NotSigned');
    assert.equal(normalizeStatus('valid'), 'Valid');
    assert.equal(normalizeStatus(99), 'UnknownError');
    assert.equal(normalizeStatus(undefined), 'UnknownError');
});

test('parseSignatureJson reads Get-AuthenticodeSignature output and rejects garbage', () => {
    assert.deepEqual(parseSignatureJson(`{"Status":"Valid","Subject":"${SUBJECT}","Thumbprint":"${THUMB}"}`), valid);
    assert.deepEqual(parseSignatureJson('{"Status":2,"Subject":null,"Thumbprint":null}'), {
        status: 'NotSigned',
        subject: null,
        thumbprint: null,
    });
    assert.equal(parseSignatureJson('not json'), null);
    assert.equal(parseSignatureJson('[1,2]'), null);
    assert.equal(parseSignatureJson(''), null);
});

test('readAuthenticodeSignature passes the file via env, never inside the command', async () => {
    const calls = [];
    const execFile = (file, args, options, cb) => {
        calls.push({ file, args, options });
        cb(null, `{"Status":"Valid","Subject":"${SUBJECT}","Thumbprint":"${THUMB}"}`);
    };
    const evil = "C:\\Temp\\x'; Remove-Item C:\\ -Recurse; '.exe";
    const result = await readAuthenticodeSignature(evil, {
        execFile,
        platform: 'win32',
        env: { SystemRoot: 'C:\\Windows' },
    });
    assert.deepEqual(result, valid);
    assert.equal(calls.length, 1);
    assert.equal(calls[0].file, 'C:\\Windows\\System32\\WindowsPowerShell\\v1.0\\powershell.exe');
    assert.ok(calls[0].args.every((arg) => !arg.includes(evil)));
    assert.ok(calls[0].args.includes(PS_SCRIPT));
    assert.equal(calls[0].options.env.SROOR_UPDATE_FILE, evil);
    assert.equal(calls[0].options.windowsHide, true);
});

test('readAuthenticodeSignature resolves null off Windows or when PowerShell fails', async () => {
    const neverCalled = () => assert.fail('must not run');
    assert.equal(await readAuthenticodeSignature('/tmp/a.exe', { execFile: neverCalled, platform: 'linux' }), null);
    const failing = (file, args, options, cb) => cb(new Error('timeout'), '');
    assert.equal(await readAuthenticodeSignature('C:\\a.exe', { execFile: failing, platform: 'win32', env: {} }), null);
});
