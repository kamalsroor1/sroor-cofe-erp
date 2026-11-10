// POS-11: helpers/refundMethod.js (default by walk-in flag, cash-cap 422 detection).
import { test } from 'node:test';
import assert from 'node:assert/strict';
import {
    defaultRefundMethod,
    exceedsCashRefundable,
    isWalkInCustomer,
    isCashRefundCapError,
    refundErrorMessage,
} from '../../resources/js/helpers/refundMethod.js';

test('walk-in defaults to cash whatever the cap', () => {
    assert.equal(defaultRefundMethod({ walkIn: true }), 'cash');
    assert.equal(defaultRefundMethod({ walkIn: true, refundable: '0.000', total: '50.000' }), 'cash');
});

test('a named customer gets cash only when the refundable amount covers the return total', () => {
    assert.equal(defaultRefundMethod({ refundable: '100.000', total: '100.000' }), 'cash');
    assert.equal(defaultRefundMethod({ refundable: '100.000', total: '40.500' }), 'cash');
    assert.equal(defaultRefundMethod({ refundable: '30.000', total: '40.000' }), 'balance');
    assert.equal(defaultRefundMethod({ refundable: '100.000', total: '0.000' }), 'balance');
    assert.equal(defaultRefundMethod({ refundable: null, total: '40.000' }), 'balance');
    assert.equal(defaultRefundMethod(), 'balance');
});

test('the cash hint shows only when a known cap is below the total', () => {
    assert.equal(exceedsCashRefundable({ refundable: '30.000', total: '40.000' }), true);
    assert.equal(exceedsCashRefundable({ refundable: '40.000', total: '40.000' }), false);
    assert.equal(exceedsCashRefundable({ refundable: null, total: '40.000' }), false);
});

test('walk-in is read from the flag, falling back to the placeholder phone', () => {
    assert.equal(isWalkInCustomer({ is_walk_in: true }), true);
    assert.equal(isWalkInCustomer({ is_walk_in: false, phone: '0000000000' }), false);
    assert.equal(isWalkInCustomer({ phone: '0000000000' }), true);
    assert.equal(isWalkInCustomer(null), false);
});

test('only the 422 cash-cap error code is treated as the inline cap error', () => {
    const cap = { response: { status: 422, data: { error_code: 'returns.cash_refund_exceeds_paid', message: 'cap' } } };
    const other = { response: { status: 422, data: { error_code: 'x' } } };
    assert.equal(isCashRefundCapError(cap), true);
    assert.equal(isCashRefundCapError(other), false);
    assert.equal(isCashRefundCapError({}), false);
    assert.equal(refundErrorMessage(cap, 'fb'), 'cap');
    assert.equal(refundErrorMessage({}, 'fb'), 'fb');
});
