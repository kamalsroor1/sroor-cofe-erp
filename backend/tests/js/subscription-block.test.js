// Code review R1: clearing the payment-recovery flag. Run from backend/: node --test tests/js/subscription-block.test.js
import { test } from 'node:test';
import assert from 'node:assert/strict';
import { paymentRequiredAfterMe } from '../../resources/js/helpers/subscriptionBlock.js';

test('a me payload with full or read-only access clears the flag', () => {
    assert.equal(paymentRequiredAfterMe({ subscription: { access: 'full' } }, true), false);
    assert.equal(paymentRequiredAfterMe({ subscription: { access: 'read_only' } }, true), false);
});

test('a still blocked shop keeps the flag as it was', () => {
    assert.equal(paymentRequiredAfterMe({ subscription: { access: 'blocked' } }, true), true);
    assert.equal(paymentRequiredAfterMe({ subscription: { access: 'blocked' } }, false), false);
});

test('an unknown shape never changes the flag', () => {
    assert.equal(paymentRequiredAfterMe({ subscription: null }, true), true);
    assert.equal(paymentRequiredAfterMe({}, true), true);
    assert.equal(paymentRequiredAfterMe(undefined, false), false);
    assert.equal(paymentRequiredAfterMe({ subscription: { access: '' } }, true), true);
});
