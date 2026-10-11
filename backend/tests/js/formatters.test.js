// SETG-6: resources/js/helpers/formatters.js is the single date/number formatter —
// Western digits in ar and en, the tenant time zone, '' for bad input.
// Run from backend/: node --test "tests/js/*.test.js"
import { test } from 'node:test';
import assert from 'node:assert/strict';
import {
    formatDate,
    formatDateTime,
    formatNumber,
    formatTime,
    resolveLocaleTag,
    resolveTimeZone,
} from '../../resources/js/helpers/formatters.js';

const EASTERN_ARABIC_DIGITS = /[٠-٩۰-۹]/;
const INSTANT = new Date('2026-10-10T12:05:09Z');

test('locale tags: ar uses Latin digits, en is en-GB, unknown falls back to ar', () => {
    assert.equal(resolveLocaleTag('ar'), 'ar-EG-u-nu-latn');
    assert.equal(resolveLocaleTag('ar-EG'), 'ar-EG-u-nu-latn');
    assert.equal(resolveLocaleTag('en'), 'en-GB');
    assert.equal(resolveLocaleTag('en_US'), 'en-GB');
    assert.equal(resolveLocaleTag(undefined), 'ar-EG-u-nu-latn');
    assert.equal(resolveLocaleTag('fr'), 'ar-EG-u-nu-latn');
});

test('Arabic output never contains Eastern Arabic digits', () => {
    const outputs = [
        formatDate(INSTANT, { locale: 'ar', timeZone: 'Africa/Cairo' }),
        formatTime(INSTANT, { locale: 'ar', timeZone: 'Africa/Cairo', second: '2-digit' }),
        formatDateTime(INSTANT, { locale: 'ar', timeZone: 'Africa/Cairo' }),
        formatDate(INSTANT, {
            locale: 'ar',
            timeZone: 'Africa/Cairo',
            weekday: 'long',
            day: 'numeric',
            month: 'long',
            year: 'numeric',
        }),
        formatNumber(1234567.5, { locale: 'ar' }),
    ];
    for (const text of outputs) {
        assert.notEqual(text, '');
        assert.ok(!EASTERN_ARABIC_DIGITS.test(text), `Eastern Arabic digits in "${text}"`);
    }
    assert.match(outputs[0], /10.*10.*2026/);
    assert.match(outputs[1], /03:05:09/);
});

test('the tenant time zone is applied to instants', () => {
    assert.equal(formatTime(INSTANT, { locale: 'en', timeZone: 'Africa/Cairo' }), '15:05');
    assert.equal(formatTime(INSTANT, { locale: 'en', timeZone: 'Asia/Kuwait' }), '15:05');
    assert.equal(formatTime(INSTANT, { locale: 'en', timeZone: 'UTC' }), '12:05');
    assert.equal(
        formatDate('2026-10-10T22:30:00Z', { locale: 'en', timeZone: 'Asia/Riyadh' }),
        '11/10/2026',
        'the calendar day follows the tenant zone'
    );
});

test('an unknown or empty time zone falls back to the browser zone', () => {
    assert.equal(resolveTimeZone('Mars/Olympus'), undefined);
    assert.equal(resolveTimeZone(''), undefined);
    assert.equal(resolveTimeZone(null), undefined);
    assert.equal(resolveTimeZone('Africa/Cairo'), 'Africa/Cairo');

    const browser = new Intl.DateTimeFormat('en-GB', { hour: '2-digit', minute: '2-digit' }).format(INSTANT);
    assert.equal(formatTime(INSTANT, { locale: 'en', timeZone: 'Mars/Olympus' }), browser);
    assert.equal(formatTime(INSTANT, { locale: 'en' }), browser);
});

test('naive server wall-clock strings are shown as written, whatever the zone', () => {
    for (const timeZone of ['Africa/Cairo', 'Asia/Kuwait', 'America/New_York', undefined]) {
        assert.equal(formatTime('2026-10-10 14:30', { locale: 'en', timeZone }), '14:30');
        assert.equal(formatDate('2026-10-10', { locale: 'en', timeZone }), '10/10/2026');
        assert.equal(formatDateTime('2026-10-10 14:30:00', { locale: 'en', timeZone }), '10/10/2026, 14:30');
    }
});

test('invalid input returns an empty string', () => {
    const bad = [null, undefined, '', '   ', 'not a date', '2026-13-45', '14:30', NaN, Infinity, {}, new Date('x')];
    for (const value of bad) {
        assert.equal(formatDate(value), '', `formatDate(${String(value)})`);
        assert.equal(formatTime(value), '', `formatTime(${String(value)})`);
        assert.equal(formatDateTime(value), '', `formatDateTime(${String(value)})`);
    }
    assert.equal(formatNumber('abc'), '');
    assert.equal(formatNumber(null), '');
    assert.equal(formatNumber(NaN), '');
});

test('formatNumber groups with Western digits in both languages and keeps string precision', () => {
    assert.equal(formatNumber(1234567.5, { locale: 'ar' }), '1,234,567.5');
    assert.equal(formatNumber(1234567.5, { locale: 'en' }), '1,234,567.5');
    assert.equal(
        formatNumber('12345678901234567.25', { locale: 'en', minimumFractionDigits: 2 }),
        '12,345,678,901,234,567.25'
    );
});

test('dateStyle / timeStyle options replace the default fields', () => {
    assert.equal(formatDate(INSTANT, { locale: 'en', timeZone: 'UTC', dateStyle: 'medium' }), '10 Oct 2026');
});
