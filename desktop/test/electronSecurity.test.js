'use strict';

// APP-5: Electron must stay on a supported major with isolation + sandbox on every window.

const test = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');

const ROOT = path.join(__dirname, '..');
// Electron supports the latest three stable majors; 44 is current (2026-10), so 42 is the floor.
const MIN_SUPPORTED_ELECTRON_MAJOR = 42;

function read(rel) {
    return fs.readFileSync(path.join(ROOT, rel), 'utf8');
}

test('package.json pins a supported Electron major', () => {
    const pkg = JSON.parse(read('package.json'));
    const range = pkg.devDependencies.electron;
    const major = Number(
        String(range)
            .replace(/^[^\d]*/, '')
            .split('.')[0]
    );
    assert.ok(major >= MIN_SUPPORTED_ELECTRON_MAJOR, `electron ${range} is below ${MIN_SUPPORTED_ELECTRON_MAJOR}`);
});

test('no unused runtime dependencies ship inside the app', () => {
    const pkg = JSON.parse(read('package.json'));
    assert.deepEqual(Object.keys(pkg.dependencies || {}), []);
});

for (const file of ['main.js', 'src/hardware/printerManager.js']) {
    test(`${file}: every BrowserWindow keeps contextIsolation + sandbox, no nodeIntegration`, () => {
        const src = read(file);
        const blocks = src.split('webPreferences:').slice(1);
        assert.ok(blocks.length > 0, 'expected webPreferences blocks');
        for (const block of blocks) {
            const body = block.slice(0, block.indexOf('}'));
            assert.match(body, /contextIsolation:\s*true/);
            assert.match(body, /sandbox:\s*true/);
            assert.match(body, /nodeIntegration:\s*false/);
        }
        assert.doesNotMatch(src, /contextIsolation:\s*false/);
        assert.doesNotMatch(src, /sandbox:\s*false/);
        assert.doesNotMatch(src, /nodeIntegration:\s*true/);
        assert.doesNotMatch(src, /webSecurity:\s*false/);
    });
}

test('the receipt worker window runs without JavaScript', () => {
    assert.match(read('src/hardware/printerManager.js'), /javascript:\s*false/);
});

test('printer list no longer depends on the removed PrinterInfo.isDefault/status fields', () => {
    assert.doesNotMatch(read('src/hardware/printerManager.js'), /p\.isDefault|p\.status/);
});
