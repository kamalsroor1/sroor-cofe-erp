// FE-05: the currency symbol comes from the tenant (system.currency), never a hardcoded 'ج.م'.
// Run from backend/: node --test "tests/js/*.test.js"
import { test } from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync, readdirSync, statSync } from 'node:fs';
import { join, relative } from 'node:path';
import { fileURLToPath, URL } from 'node:url';
import {
    formatCurrencyAmount,
    resolveCurrencyCode,
    resolveCurrencyLabel,
} from '../../resources/js/helpers/formatters.js';

const dictionaries = {
    ar: JSON.parse(readFileSync(new URL('../../resources/js/helpers/defaultTranslations.ar.json', import.meta.url))),
    en: JSON.parse(readFileSync(new URL('../../resources/js/helpers/defaultTranslations.en.json', import.meta.url))),
};
const translator = (locale) => (key) => {
    let value = dictionaries[locale];
    for (const part of key.split('.')) value = value?.[part];
    return typeof value === 'string' ? value : key;
};

const SETTINGS_CURRENCIES = ['EGP', 'SAR', 'AED', 'KWD', 'QAR', 'USD'];

test('every currency the settings UI offers has a symbol in ar and en', () => {
    for (const code of SETTINGS_CURRENCIES) {
        for (const locale of ['ar', 'en']) {
            const label = resolveCurrencyLabel(code, translator(locale));
            assert.ok(dictionaries[locale].common.currencies[code], `${code} (${locale}) is translated`);
            assert.equal(label, dictionaries[locale].common.currencies[code]);
        }
    }
    assert.equal(resolveCurrencyLabel('SAR', translator('ar')), 'ر.س');
    assert.equal(resolveCurrencyLabel('SAR', translator('en')), 'SAR');
});

test('an unknown or missing code falls back safely', () => {
    assert.equal(resolveCurrencyLabel('XYZ', translator('ar')), 'XYZ');
    assert.equal(resolveCurrencyLabel(undefined, translator('ar')), 'ج.م');
    assert.equal(resolveCurrencyLabel('', translator('en')), 'EGP');
    assert.equal(resolveCurrencyCode('sar'), 'SAR');
    assert.equal(resolveCurrencyCode('<script>'), 'EGP');
});

test('formatCurrencyAmount puts the symbol after in Arabic and before in English', () => {
    assert.equal(formatCurrencyAmount('1234.5', { decimals: 2, label: 'ج.م', locale: 'ar' }), '1,234.50 ج.م');
    assert.equal(formatCurrencyAmount('150', { decimals: 2, label: 'EGP', locale: 'en' }), 'EGP 150');
    assert.equal(formatCurrencyAmount('1.0005', { decimals: 3, label: 'KWD', locale: 'en-GB' }), 'KWD 1.001');
});

const JS_ROOT = fileURLToPath(new URL('../../resources/js', import.meta.url));
const POS_PATH = /(^|\/)(views\/POS|Components\/POS|Composables\/POS)\/|usePos/;

// Only mention the symbol in doc comments.
const ALLOWED = new Set(['Composables/useFormatters.js', 'helpers/formatters.js']);

function* sourceFiles(dir) {
    for (const name of readdirSync(dir)) {
        const full = join(dir, name);
        if (statSync(full).isDirectory()) yield* sourceFiles(full);
        else if (/\.(vue|js)$/.test(name) && !name.startsWith('defaultTranslations')) yield full;
    }
}

test("no new 'common.currency' usage or hardcoded 'ج.م' outside POS", () => {
    const offenders = [];
    for (const file of sourceFiles(JS_ROOT)) {
        const rel = relative(JS_ROOT, file);
        if (POS_PATH.test(rel) || ALLOWED.has(rel)) continue;
        const text = readFileSync(file, 'utf8');
        if (/common\.currency(?!_|ies)/.test(text) || /ج\.م/.test(text)) offenders.push(rel);
    }
    assert.deepEqual(offenders, [], 'use useFormatters().currencyLabel / formatCurrency instead');
});
