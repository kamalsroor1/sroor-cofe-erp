// SETG-6: dates and numbers are formatted only by resources/js/helpers/formatters.js
// (Western digits, tenant time zone). No raw `toLocale*String('ar…')` or
// `new Intl.*Format('ar…')` anywhere else. ESLint enforces the same rule
// (no-restricted-syntax); this scan also covers files ESLint may be told to skip.
// Run from backend/: node --test "tests/js/*.test.js"
import { test } from 'node:test';
import assert from 'node:assert/strict';
import { readdirSync, readFileSync } from 'node:fs';
import { join, relative, sep } from 'node:path';
import { fileURLToPath, URL } from 'node:url';

const JS_ROOT = fileURLToPath(new URL('../../resources/js/', import.meta.url));

const HELPER = 'helpers/formatters.js';

// TEMPORARY allowlist — Antigravity-owned settings files this lane may not edit.
const ALLOWLIST = new Set(['Composables/useSettings.js']);

const RAW_AR_LOCALE = [
    /\.toLocale(?:Date|Time)?String\s*\(\s*(['"`])ar\b/,
    /new\s+Intl\s*\.\s*(?:DateTimeFormat|NumberFormat)\s*\(\s*(['"`])ar\b/,
];

function sourceFiles(dir) {
    return readdirSync(dir, { withFileTypes: true }).flatMap((entry) => {
        const full = join(dir, entry.name);
        if (entry.isDirectory()) return sourceFiles(full);
        if (!/\.(js|vue)$/.test(entry.name) || entry.name.startsWith('defaultTranslations.')) return [];
        return [full];
    });
}

test('the pattern catches the known raw forms', () => {
    const hits = [
        "d.toLocaleTimeString('ar-EG', { hour: '2-digit' })",
        'new Date().toLocaleString("ar-EG")',
        'd.toLocaleDateString(`ar`)',
        "new Intl.DateTimeFormat('ar-SA')",
    ];
    for (const sample of hits) {
        assert.ok(
            RAW_AR_LOCALE.some((pattern) => pattern.test(sample)),
            sample
        );
    }
    assert.ok(!RAW_AR_LOCALE.some((pattern) => pattern.test("n.toLocaleString('en-US')")));
});

test('no raw Arabic locale formatting outside helpers/formatters.js', () => {
    const offenders = [];
    for (const file of sourceFiles(JS_ROOT)) {
        const rel = relative(JS_ROOT, file).split(sep).join('/');
        if (rel === HELPER || ALLOWLIST.has(rel)) continue;
        readFileSync(file, 'utf8')
            .split('\n')
            .forEach((line, index) => {
                if (RAW_AR_LOCALE.some((pattern) => pattern.test(line))) {
                    offenders.push(`${rel}:${index + 1}: ${line.trim()}`);
                }
            });
    }
    assert.deepEqual(offenders, [], `use useFormatters() instead:\n${offenders.join('\n')}`);
});
