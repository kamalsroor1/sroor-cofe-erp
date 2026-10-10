// SETG-13 display: money is rounded half-up to the tenant currency decimals
// (system.currency_decimals) by helpers/decimal.js — no parseFloat / toLocaleString.
// Run from backend/: node --test "tests/js/*.test.js"
import { test } from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { fileURLToPath, URL } from 'node:url';
import { dRoundTo, formatDecimal } from '../../resources/js/helpers/decimal.js';
import { formatMoneyAmount, resolveCurrencyDecimals } from '../../resources/js/helpers/formatters.js';

const VECTORS = [
    { currency: 'KWD', decimals: 3, input: '1.0005', expected: '1.001' },
    { currency: 'EGP', decimals: 2, input: '2.345', expected: '2.35' },
    { currency: 'EGP', decimals: 2, input: '2.344', expected: '2.34' },
    { currency: 'EGP', decimals: 2, input: '-0.005', expected: '-0.01' },
    { currency: 'EGP', decimals: 2, input: '1234567.5', expected: '1,234,567.50' },
];

for (const { currency, decimals, input, expected } of VECTORS) {
    test(`${currency} (${decimals} decimals): ${input} -> ${expected}`, () => {
        assert.equal(formatDecimal(input, decimals), expected);
        assert.equal(formatMoneyAmount(input, { decimals }), expected);
        assert.equal(formatMoneyAmount(Number(input), { decimals }), expected, 'number input');
    });
}

test('dRoundTo rounds once from the full input (no double rounding through scale 3)', () => {
    assert.equal(dRoundTo('2.3449', 2), '2.34');
    assert.equal(dRoundTo('2.345', 2), '2.35');
    assert.equal(dRoundTo('-2.345', 2), '-2.35');
    assert.equal(dRoundTo('-0.004', 2), '0.00', 'no negative zero');
    assert.equal(dRoundTo('999.995', 2), '1000.00');
    assert.equal(dRoundTo('5', 0), '5');
    assert.equal(dRoundTo('0.5', 0), '1');
    assert.equal(dRoundTo(1235n, 2), '1.24', 'bigint input is thousandths');
    assert.equal(dRoundTo('1e3', 2), '1000.00');
    assert.equal(dRoundTo('abc', 2), '0.00');
    assert.equal(dRoundTo(null, 2), '0.00');
});

test('formatDecimal groups thousands and keeps the sign', () => {
    assert.equal(formatDecimal('-1234567.891', 3), '-1,234,567.891');
    assert.equal(formatDecimal('999', 2), '999.00');
    assert.equal(formatDecimal('1000', 0), '1,000');
});

test('whole amounts drop the fraction unless fixed', () => {
    assert.equal(formatMoneyAmount('150', { decimals: 2 }), '150');
    assert.equal(formatMoneyAmount('150.000', { decimals: 3 }), '150');
    assert.equal(formatMoneyAmount('2.999', { decimals: 2 }), '3');
    assert.equal(formatMoneyAmount('150', { decimals: 2, fixed: true }), '150.00');
    assert.equal(formatMoneyAmount('150.5', { decimals: 3 }), '150.500');
    assert.equal(formatMoneyAmount(undefined), '0');
});

test('currency decimals come from the context, defaulting to 2', () => {
    assert.equal(resolveCurrencyDecimals(3), 3);
    assert.equal(resolveCurrencyDecimals(0), 0);
    assert.equal(resolveCurrencyDecimals(undefined), 2);
    assert.equal(resolveCurrencyDecimals('x'), 2);
    assert.equal(resolveCurrencyDecimals(7), 2);
});

test('the money display path has no parseFloat / toLocaleString / toFixed', () => {
    const files = [
        '../../resources/js/helpers/formatters.js',
        '../../resources/js/Composables/useMoney.js',
        '../../resources/js/Composables/useFormatters.js',
    ];
    for (const file of files) {
        const source = readFileSync(fileURLToPath(new URL(file, import.meta.url)), 'utf8');
        assert.ok(
            !/parseFloat\s*\(|toLocaleString\s*\(|toFixed\s*\(/.test(source),
            `${file} formats money with floats`
        );
    }
});
