import { test } from 'node:test';
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { fileURLToPath, URL } from 'node:url';
import { POS_PIN_INPUT_ATTRS, POS_PIN_LENGTH, posPinError } from '../../resources/js/helpers/posPin.js';

// SEC-21: the server accepts exactly 6 digits (UpdateUserPosPinRequest / UpdateProfilePosPinRequest).
const SERVER_RULE = /^\d{6}$/;

const read = (relative) => readFileSync(fileURLToPath(new URL(relative, import.meta.url)), 'utf8');

test('the PIN length matches the server rule', () => {
    assert.equal(POS_PIN_LENGTH, 6);
    for (const candidate of ['1234', '12345', '123456', '1234567', '12a456']) {
        assert.equal(posPinError(candidate, candidate) === null, SERVER_RULE.test(candidate), candidate);
    }
});

test('posPinError accepts a confirmed 6-digit PIN, trimming spaces', () => {
    assert.equal(posPinError('123456', '123456'), null);
    assert.equal(posPinError(' 123456 ', '123456'), null);
});

test('posPinError rejects 4 and 5 digit PINs the old form allowed', () => {
    assert.equal(posPinError('1234', '1234'), 'format');
    assert.equal(posPinError('12345', '12345'), 'format');
});

test('posPinError rejects non-digits, empty and over-long values', () => {
    for (const bad of ['', null, undefined, '12a456', '12 456', '١٢٣٤٥٦', '1234567']) {
        assert.equal(posPinError(bad, bad), 'format', String(bad));
    }
});

test('posPinError reports a mismatched confirmation after the format check', () => {
    assert.equal(posPinError('123456', '654321'), 'mismatch');
    assert.equal(posPinError('123456', ''), 'mismatch');
});

test('the input attributes constrain the browser to exactly 6 digits', () => {
    assert.equal(POS_PIN_INPUT_ATTRS.minlength, '6');
    assert.equal(POS_PIN_INPUT_ATTRS.maxlength, '6');
    assert.equal(POS_PIN_INPUT_ATTRS.inputmode, 'numeric');
    assert.equal(POS_PIN_INPUT_ATTRS.placeholder.length, 6);
    const pattern = new RegExp(`^(?:${POS_PIN_INPUT_ATTRS.pattern})$`);
    assert.ok(pattern.test('123456'));
    assert.ok(!pattern.test('1234'));
});

for (const component of ['Users/UserFormModal.vue', 'Profile/ProfilePosPinCard.vue']) {
    test(`${component} uses the shared 6-digit rule, not a local 4-digit one`, () => {
        const source = read(`../../resources/js/Components/${component}`);
        assert.match(source, /from '@\/helpers\/posPin'/);
        assert.equal((source.match(/v-bind="POS_PIN_INPUT_ATTRS"/g) || []).length, 2);
        assert.doesNotMatch(source, /minlength="4"/);
        assert.doesNotMatch(source, /\\d\{4,6\}/);
        assert.match(source, /posPinError\(pin, confirmation\)/);
    });
}
