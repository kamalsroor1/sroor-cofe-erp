// APP-2: the Capacitor webDir must never ship server-side or sensitive files from public/.
// Run: node --test scripts/capacitor/
import { test } from 'node:test';
import assert from 'node:assert/strict';
import { mkdtempSync, mkdirSync, writeFileSync, readdirSync, readFileSync, rmSync, existsSync } from 'node:fs';
import { tmpdir } from 'node:os';
import path from 'node:path';
import { fileURLToPath } from 'node:url';
import { deflateRawSync } from 'node:zlib';

import {
    WEB_DIR,
    ALLOWED_FILES,
    buildCapacitorWeb,
    findForbiddenEntries,
    listZipEntries,
    listDirEntries,
} from './webAssets.mjs';

const backendDir = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '..', '..');

function makeFakePublic() {
    const root = mkdtempSync(path.join(tmpdir(), 'cap-web-'));
    const pub = path.join(root, 'public');
    mkdirSync(path.join(pub, 'build', 'assets'), { recursive: true });
    mkdirSync(path.join(pub, 'storage'), { recursive: true });
    for (const name of ALLOWED_FILES) writeFileSync(path.join(pub, name), `ok:${name}`);
    for (const name of [
        'update_webhook.php',
        'index.php',
        '.htaccess',
        'hot',
        'app.apk',
        'sroor-cofe-erp-2m.apk',
        '.env',
    ]) {
        writeFileSync(path.join(pub, name), 'SENSITIVE');
    }
    writeFileSync(path.join(pub, 'build', 'assets', 'app.js'), 'bundle');
    writeFileSync(path.join(pub, 'storage', 'logo.png'), 'tenant upload');
    return { root, pub, out: path.join(root, 'out') };
}

// Minimal ZIP writer (deflate) so the APK scanner is tested against a real archive layout.
function writeZip(file, names) {
    const locals = [];
    const centrals = [];
    let offset = 0;
    for (const name of names) {
        const nameBuf = Buffer.from(name, 'utf8');
        const data = deflateRawSync(Buffer.from('x'));
        const local = Buffer.alloc(30);
        local.writeUInt32LE(0x04034b50, 0);
        local.writeUInt16LE(20, 4);
        local.writeUInt16LE(8, 8);
        local.writeUInt32LE(data.length, 18);
        local.writeUInt32LE(1, 22);
        local.writeUInt16LE(nameBuf.length, 26);
        const central = Buffer.alloc(46);
        central.writeUInt32LE(0x02014b50, 0);
        central.writeUInt16LE(20, 4);
        central.writeUInt16LE(20, 6);
        central.writeUInt16LE(8, 10);
        central.writeUInt32LE(data.length, 20);
        central.writeUInt32LE(1, 24);
        central.writeUInt16LE(nameBuf.length, 28);
        central.writeUInt32LE(offset, 42);
        locals.push(local, nameBuf, data);
        centrals.push(central, nameBuf);
        offset += local.length + nameBuf.length + data.length;
    }
    const cd = Buffer.concat(centrals);
    const eocd = Buffer.alloc(22);
    eocd.writeUInt32LE(0x06054b50, 0);
    eocd.writeUInt16LE(names.length, 8);
    eocd.writeUInt16LE(names.length, 10);
    eocd.writeUInt32LE(cd.length, 12);
    eocd.writeUInt32LE(offset, 16);
    writeFileSync(file, Buffer.concat([...locals, cd, eocd]));
}

test('capacitor.config.json webDir is separated from public/', () => {
    const config = JSON.parse(readFileSync(path.join(backendDir, 'capacitor.config.json'), 'utf8'));
    assert.equal(config.webDir, WEB_DIR);
    assert.notEqual(path.normalize(config.webDir), 'public');
    assert.ok(!path.normalize(config.webDir).startsWith(`public${path.sep}`));
});

test('buildCapacitorWeb copies only the allow-listed shell files', () => {
    const { root, pub, out } = makeFakePublic();
    try {
        const copied = buildCapacitorWeb({ sourceDir: pub, outDir: out });
        assert.deepEqual([...copied].sort(), [...ALLOWED_FILES].sort());
        assert.deepEqual(readdirSync(out).sort(), [...ALLOWED_FILES].sort());
        for (const leaked of [
            'update_webhook.php',
            'index.php',
            '.htaccess',
            'hot',
            'app.apk',
            '.env',
            'build',
            'storage',
        ]) {
            assert.equal(existsSync(path.join(out, leaked)), false, `${leaked} must not be in webDir`);
        }
    } finally {
        rmSync(root, { recursive: true, force: true });
    }
});

test('buildCapacitorWeb wipes stale files from a previous run', () => {
    const { root, pub, out } = makeFakePublic();
    try {
        mkdirSync(out, { recursive: true });
        writeFileSync(path.join(out, 'update_webhook.php'), 'stale');
        buildCapacitorWeb({ sourceDir: pub, outDir: out });
        assert.equal(existsSync(path.join(out, 'update_webhook.php')), false);
    } finally {
        rmSync(root, { recursive: true, force: true });
    }
});

test('buildCapacitorWeb fails loudly when the shell entry point is missing', () => {
    const { root, pub, out } = makeFakePublic();
    try {
        rmSync(path.join(pub, 'index.html'));
        assert.throws(() => buildCapacitorWeb({ sourceDir: pub, outDir: out }), /index\.html/);
    } finally {
        rmSync(root, { recursive: true, force: true });
    }
});

test('findForbiddenEntries flags php, htaccess, env, nested apks and vite hot file', () => {
    const entries = [
        'AndroidManifest.xml',
        'classes.dex',
        'assets/public/index.html',
        'assets/public/logo.png',
        'assets/public/update_webhook.php',
        'assets/public/index.php',
        'assets/public/.htaccess',
        'assets/public/.env',
        'assets/public/hot',
        'assets/public/app.apk',
        'assets/public/build/assets/app.js',
    ];
    assert.deepEqual(findForbiddenEntries(entries).sort(), [
        'assets/public/.env',
        'assets/public/.htaccess',
        'assets/public/app.apk',
        'assets/public/build/assets/app.js',
        'assets/public/hot',
        'assets/public/index.php',
        'assets/public/update_webhook.php',
    ]);
    assert.deepEqual(findForbiddenEntries(['assets/public/index.html', 'res/drawable/splash.png']), []);
});

test('listZipEntries reads APK (zip) entry names and the scan detects a leaked webhook', () => {
    const root = mkdtempSync(path.join(tmpdir(), 'cap-apk-'));
    try {
        const bad = path.join(root, 'bad.apk');
        writeZip(bad, ['AndroidManifest.xml', 'assets/public/index.html', 'assets/public/update_webhook.php']);
        assert.deepEqual(listZipEntries(bad), [
            'AndroidManifest.xml',
            'assets/public/index.html',
            'assets/public/update_webhook.php',
        ]);
        assert.deepEqual(findForbiddenEntries(listZipEntries(bad)), ['assets/public/update_webhook.php']);

        const good = path.join(root, 'good.apk');
        writeZip(good, ['AndroidManifest.xml', 'assets/public/index.html', 'assets/public/logo.png']);
        assert.deepEqual(findForbiddenEntries(listZipEntries(good)), []);
    } finally {
        rmSync(root, { recursive: true, force: true });
    }
});

test('listDirEntries maps an android assets dir to APK-style paths', () => {
    const { root, pub } = makeFakePublic();
    try {
        const entries = listDirEntries(pub, 'assets/public');
        assert.ok(entries.includes('assets/public/update_webhook.php'));
        assert.ok(entries.includes('assets/public/build/assets/app.js'));
    } finally {
        rmSync(root, { recursive: true, force: true });
    }
});
