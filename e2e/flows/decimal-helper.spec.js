// SP-4 / SETG-13: the POS exact-decimal helper must reproduce the server's
// rounding (app/Support/Money/Decimal.php: scale 3, half-up, a tie rounds away
// from zero) so the client never sends or shows a total the server would
// compute differently. The full shared vectors run in
// backend/tests/js/decimal-rounding.test.js and RoundingParityTest.php.
//
// Node-only: imports the helper directly, no page and no server data.
import { test, expect } from '@playwright/test';
import {
    toMilli,
    fromMilli,
    normalize,
    dAdd,
    dSub,
    dMul,
    dPercent,
    dCmp,
    dMin,
    dMax0,
    dSum,
    isPositive,
    isZero,
} from '../../backend/resources/js/helpers/decimal.js';

test.describe('decimal helper (bcmath parity)', () => {
    test('multiplies a fractional kilo line exactly', () => {
        expect(dMul('0.250', '550.500')).toBe('137.625');
        expect(dMul(0.25, 550.5)).toBe('137.625');
    });

    test('rounds multiplication half-up like Decimal::mul()', () => {
        // 1.375 x 3.333 = 4.582875 -> 4.583
        expect(dMul('1.375', '3.333')).toBe('4.583');
        // 0.333 x 0.333 = 0.110889 -> 0.111
        expect(dMul('0.333', '0.333')).toBe('0.111');
        // negative ties round away from zero: -4.582875 -> -4.583
        expect(dMul('-1.375', '3.333')).toBe('-4.583');
        // exact tie at the 4th decimal: 0.5 x 0.001 = 0.0005 -> 0.001
        expect(dMul('0.5', '0.001')).toBe('0.001');
    });

    test('adds and subtracts without float drift', () => {
        expect(dAdd('0.1', '0.2')).toBe('0.300');
        expect(dAdd(0.1, 0.2)).toBe('0.300');
        expect(dSub('1200', '1100.000')).toBe('100.000');
        expect(dSub('0.3', '0.1')).toBe('0.200');
        expect(dSub('1', '1.5')).toBe('-0.500');
    });

    test('mirrors the server percentage discount formula', () => {
        // Decimal::percent: 137.625 x 12.5 / 100 = 17.203125 -> 17.203
        expect(dPercent('137.625', '12.5')).toBe('17.203');
        expect(dPercent('1000', '10')).toBe('100.000');
        // 33.33266667 -> 33.333 (half-up, was 33.332 under truncation)
        expect(dPercent('99.999', '33.333')).toBe('33.333');
    });

    test('compares, clamps and sums', () => {
        expect(dCmp('1.000', '1')).toBe(0);
        expect(dCmp('0.999', '1')).toBe(-1);
        expect(dCmp('10', '9.999')).toBe(1);
        expect(dCmp(-1, 0)).toBe(-1);
        expect(dMin('5', '4.5')).toBe('4.500');
        expect(dMax0('-3.2')).toBe('0.000');
        expect(dMax0('3.2')).toBe('3.200');
        expect(dSum(['0.1', '0.2', 0.3, '137.625'])).toBe('138.225');
        expect(dSum([])).toBe('0.000');
        expect(isPositive('0.001')).toBe(true);
        expect(isPositive('0')).toBe(false);
        expect(isPositive('-1')).toBe(false);
        expect(isZero('0.000')).toBe(true);
    });

    test('parses and normalizes edge inputs', () => {
        expect(normalize('12')).toBe('12.000');
        expect(normalize('.5')).toBe('0.500');
        expect(normalize('7.')).toBe('7.000');
        expect(normalize('1.23456')).toBe('1.235');
        expect(normalize('-1.23456')).toBe('-1.235');
        expect(normalize('0.0005')).toBe('0.001');
        expect(normalize('-0.0005')).toBe('-0.001');
        expect(normalize('0.0004')).toBe('0.000');
        expect(normalize(1e-7)).toBe('0.000');
        expect(normalize(1e21)).toBe('1000000000000000000000.000');
        expect(normalize('')).toBe('0.000');
        expect(normalize(null)).toBe('0.000');
        expect(normalize(undefined)).toBe('0.000');
        expect(normalize('abc')).toBe('0.000');
        expect(normalize(Number.NaN)).toBe('0.000');
        expect(toMilli('123.45')).toBe(123450n);
        expect(fromMilli(123450n)).toBe('123.450');
        expect(fromMilli(-5n)).toBe('-0.005');
    });
});
