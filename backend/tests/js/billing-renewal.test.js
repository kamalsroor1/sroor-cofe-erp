// #230: the shop billing page trusts the server flag for the renewal button.
import { test } from 'node:test';
import assert from 'node:assert/strict';
import { canRequestRenewal } from '../../resources/js/helpers/billingRenewal.js';

test('a trial or free-plan payload (flag false) cannot request a renewal', () => {
    assert.equal(canRequestRenewal({ subscription: { can_request_renewal: false } }), false);
});

test('a paying payload (flag true) can request a renewal', () => {
    assert.equal(canRequestRenewal({ subscription: { can_request_renewal: true } }), true);
});

test('a payload without the flag keeps the button', () => {
    assert.equal(canRequestRenewal({ subscription: {} }), true);
    assert.equal(canRequestRenewal(null), true);
});
