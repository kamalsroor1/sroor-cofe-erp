import { test } from 'node:test';
import assert from 'node:assert/strict';
import { Arabic } from 'flatpickr/dist/l10n/ar.js';
import { datePickerLocale } from '../../resources/js/helpers/datePickerLocale.js';

test('the date pickers use the Arabic locale in RTL', () => {
    assert.equal(datePickerLocale(true), Arabic);
});

test("the date pickers use flatpickr's 'default' locale in LTR", () => {
    assert.equal(datePickerLocale(false), 'default');
});

test('switching the direction switches the locale back and forth', () => {
    assert.equal(datePickerLocale(false), 'default');
    assert.equal(datePickerLocale(true), Arabic);
    assert.equal(datePickerLocale(false), 'default');
});
