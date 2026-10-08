'use strict';

const test = require('node:test');
const assert = require('node:assert/strict');
const { normalizeWhatsappNumber, getSupportWhatsappUrl } = require('../src/config/supportContact');

test('accepts 10-15 digit numbers', () => {
    assert.equal(normalizeWhatsappNumber('0123456789'), '0123456789');
    assert.equal(normalizeWhatsappNumber(' 123456789012345 '), '123456789012345');
});

test('rejects anything that is not plain digits of valid length', () => {
    for (const raw of [
        '',
        '123456789',
        '1234567890123456',
        '+201000000000',
        '20100 000000',
        'https://evil.test',
        '2010000000/x',
        null,
        undefined,
        201000000000,
    ]) {
        assert.equal(normalizeWhatsappNumber(raw), null);
    }
});

test('builds the wa.me URL, env value first', () => {
    assert.equal(getSupportWhatsappUrl('1111111111', '2222222222'), 'https://wa.me/1111111111');
    assert.equal(getSupportWhatsappUrl('', '2222222222'), 'https://wa.me/2222222222');
    assert.equal(getSupportWhatsappUrl('bad', 'https://evil.test'), null);
    assert.equal(getSupportWhatsappUrl(undefined, ''), null);
});
