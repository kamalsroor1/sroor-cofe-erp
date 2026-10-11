'use strict';

// Regression tests for P0-ELEC-4 (02-security.md finding 14, updater half).
// nativeUpdater used to download any renderer-supplied URL over http or https,
// follow redirects to any host, and spawn the result with /S, unverified.
//
// Contract required from src/updater/updateVerify.js (pure, must not require electron):
//   extractChecksum(manifest): string           -> lowercase 64-hex SHA-256, throws otherwise
//   verifySha256(filePath, expectedHex): boolean | Promise<boolean>
//   resolveRedirect(currentUrl, location): string | null
//       -> absolute URL when the redirect stays on an allowed https official host, else null
// Download URLs are checked with isAllowedAppUrl from src/security/urlPolicy.js.

const test = require('node:test');
const assert = require('node:assert/strict');
const crypto = require('node:crypto');
const fs = require('node:fs');
const os = require('node:os');
const path = require('node:path');

const SRC = path.join(__dirname, '..', 'src');

function verify() {
    return require(path.join(SRC, 'updater', 'updateVerify.js'));
}

function policy() {
    return require(path.join(SRC, 'security', 'urlPolicy.js'));
}

const HEX = 'AB'.repeat(32); // 64 hex chars, upper case

test('extractChecksum throws when the manifest has no checksum (current backend)', () => {
    const { extractChecksum } = verify();
    assert.throws(() => extractChecksum({}));
    assert.throws(() => extractChecksum({ download_url: 'https://2m.baraa-solutions.com/x.exe' }));
    assert.throws(() => extractChecksum(null));
    assert.throws(() => extractChecksum(undefined));
});

test('extractChecksum throws on a malformed checksum', () => {
    const { extractChecksum } = verify();
    assert.throws(() => extractChecksum({ checksum: 'zz' }));
    assert.throws(() => extractChecksum({ checksum: 'g'.repeat(64) }));
    assert.throws(() => extractChecksum({ checksum: 'a'.repeat(63) }));
    assert.throws(() => extractChecksum({ checksum: 'a'.repeat(65) }));
    assert.throws(() => extractChecksum({ checksum: 12345 }));
});

test('extractChecksum returns the lowercased 64-hex digest', () => {
    assert.equal(verify().extractChecksum({ checksum: HEX }), HEX.toLowerCase());
});

test('download URL over plain http is not allowed (finding 14 PoC)', () => {
    assert.equal(policy().isAllowedAppUrl('http://baraa-solutions.com/x.exe'), false);
    assert.equal(policy().isAllowedAppUrl('http://evil/x.exe'), false);
});

test('download URL on a foreign host is not allowed', () => {
    assert.equal(policy().isAllowedAppUrl('https://evil.com/x.exe'), false);
    assert.equal(policy().isAllowedAppUrl('https://baraa-solutions.com.evil.com/x.exe'), false);
});

test('resolveRedirect rejects a redirect to another host', () => {
    const { resolveRedirect } = verify();
    const from = 'https://2m.baraa-solutions.com/Sroor-ERP-POS-Setup.exe';
    assert.equal(resolveRedirect(from, 'https://evil.com/x.exe'), null);
    assert.equal(resolveRedirect(from, '//evil.com/x.exe'), null);
    assert.equal(resolveRedirect(from, 'https://2m.baraa-solutions.com.evil.com/x.exe'), null);
});

test('resolveRedirect rejects an https -> http downgrade', () => {
    const { resolveRedirect } = verify();
    assert.equal(resolveRedirect('https://2m.baraa-solutions.com/a.exe', 'http://2m.baraa-solutions.com/b.exe'), null);
});

test('resolveRedirect rejects non-http schemes and empty locations', () => {
    const { resolveRedirect } = verify();
    const from = 'https://2m.baraa-solutions.com/a.exe';
    assert.equal(resolveRedirect(from, 'file:///C:/x.exe'), null);
    assert.equal(resolveRedirect(from, 'javascript:alert(1)'), null);
    assert.equal(resolveRedirect(from, ''), null);
    assert.equal(resolveRedirect(from, undefined), null);
});

test('resolveRedirect keeps a same-host relative redirect', () => {
    const out = verify().resolveRedirect('https://2m.baraa-solutions.com/a.exe', '/downloads/b.exe');
    assert.equal(out, 'https://2m.baraa-solutions.com/downloads/b.exe');
});

test('verifySha256 matches only the right digest', async (t) => {
    const { verifySha256 } = verify();
    const dir = fs.mkdtempSync(path.join(os.tmpdir(), 'sroor-updater-test-'));
    t.after(() => fs.rmSync(dir, { recursive: true, force: true }));

    const file = path.join(dir, 'setup.exe');
    const content = Buffer.from('MZ fake installer payload \u0633\u0631\u0648\u0631');
    fs.writeFileSync(file, content);
    const right = crypto.createHash('sha256').update(content).digest('hex');
    const wrong = right.replace(/^./, (c) => (c === '0' ? '1' : '0'));

    assert.equal(await verifySha256(file, wrong), false);
    assert.equal(await verifySha256(file, right), true);
    assert.equal(await verifySha256(file, right.toUpperCase()), true);
    assert.equal(await verifySha256(file, ''), false);
});

test('verifySha256 returns false for a missing file instead of passing', async () => {
    const { verifySha256 } = verify();
    const missing = path.join(os.tmpdir(), `sroor-missing-${process.pid}-${Date.now()}.exe`);
    let result;
    try {
        result = await verifySha256(missing, 'a'.repeat(64));
    } catch {
        result = false; // throwing is also an acceptable refusal
    }
    assert.equal(result, false);
});

// APP-7: manifest -> release to install. Fails closed and never trusts URLs outside the allow-list.

const SHA = 'c'.repeat(64);
const manifest = (overrides = {}) => ({
    success: true,
    has_update: true,
    force_update: false,
    latest_version: '1.2.0',
    latest_version_code: 1002000,
    download_url: 'https://baraa-solutions.com/api/v1/app/download-apk?platform=windows&version_code=1002000',
    manual_download_url: 'https://baraa-solutions.com/download',
    file_size_bytes: 73400320,
    checksum: SHA,
    checksum_algorithm: 'sha256',
    rollback: null,
    ...overrides,
});

test('releaseFromManifest returns the verified target of a newer release', () => {
    const res = verify().releaseFromManifest(manifest(), 1001000);
    assert.equal(res.ok, true);
    assert.deepEqual(res.release, {
        version: '1.2.0',
        versionCode: 1002000,
        downloadUrl: manifest().download_url,
        sha256: SHA,
        sizeBytes: 73400320,
    });
});

test('releaseFromManifest reports already_latest instead of reinstalling the same build', () => {
    const { releaseFromManifest } = verify();
    assert.deepEqual(releaseFromManifest(manifest(), 1002000), { ok: false, code: 'app_update.already_latest' });
    assert.deepEqual(releaseFromManifest(manifest({ has_update: false }), 1), {
        ok: false,
        code: 'app_update.already_latest',
    });
});

test('releaseFromManifest refuses a release without checksum, size or trusted URL', () => {
    const { releaseFromManifest } = verify();
    assert.equal(releaseFromManifest(manifest({ checksum: null }), 1).code, 'app_update.error_missing_checksum');
    assert.equal(releaseFromManifest(manifest({ file_size_bytes: 0 }), 1).code, 'app_update.error_manifest_invalid');
    assert.equal(releaseFromManifest(manifest({ file_size_bytes: null }), 1).code, 'app_update.error_manifest_invalid');
    assert.equal(
        releaseFromManifest(manifest({ download_url: 'https://evil.com/x.exe' }), 1).code,
        'app_update.error_missing_url'
    );
    assert.equal(releaseFromManifest(null, 1).code, 'app_update.error_manifest_invalid');
});

test('rollbackFromManifest only accepts an older, fully described, allow-listed release', () => {
    const { rollbackFromManifest } = verify();
    const rollback = {
        version: '1.1.0',
        version_code: 1001000,
        download_url: 'https://baraa-solutions.com/api/v1/app/download-apk?platform=windows&version_code=1001000',
        checksum: SHA,
        file_size_bytes: 1000,
    };
    assert.deepEqual(rollbackFromManifest(manifest({ rollback }), 1002000), {
        version: '1.1.0',
        versionCode: 1001000,
        downloadUrl: rollback.download_url,
        sha256: SHA,
        sizeBytes: 1000,
    });
    assert.equal(rollbackFromManifest(manifest({ rollback }), 1001000), null, 'not older than the running build');
    assert.equal(rollbackFromManifest(manifest({ rollback: { ...rollback, checksum: null } }), 1002000), null);
    assert.equal(
        rollbackFromManifest(
            manifest({ rollback: { ...rollback, download_url: 'http://baraa-solutions.com/a.exe' } }),
            1002000
        ),
        null
    );
    assert.equal(rollbackFromManifest(manifest(), 1002000), null);
});

test('manualDownloadUrl keeps an allow-listed page and falls back to the official one', () => {
    const { manualDownloadUrl } = verify();
    assert.equal(manualDownloadUrl(manifest()), 'https://baraa-solutions.com/download');
    assert.equal(
        manualDownloadUrl(manifest({ manual_download_url: 'https://evil.com/download' })),
        'https://baraa-solutions.com/download'
    );
    assert.equal(manualDownloadUrl(null), 'https://baraa-solutions.com/download');
});

test('the renderer only ever receives app_update.* codes', () => {
    const { toRendererCode, updaterError } = verify();
    assert.equal(
        toRendererCode(updaterError('app_update.error_checksum_mismatch')),
        'app_update.error_checksum_mismatch'
    );
    assert.equal(toRendererCode(new Error('ENOENT C:/Users/x/setup.exe')), 'app_update.error_download_failed');
    assert.equal(
        toRendererCode(Object.assign(new Error('x'), { code: 'ECONNRESET' })),
        'app_update.error_download_failed'
    );
    assert.equal(toRendererCode(null), 'app_update.error_download_failed');
});
