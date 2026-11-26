// #219: invoices are labelled by their channel (payment_method), not their settlement type.
import { test } from 'node:test';
import assert from 'node:assert/strict';
import {
    invoicePaymentKeys,
    invoicePaymentLabels,
    normalizePaymentMethod,
    paymentMethodKey,
} from '../../resources/js/helpers/paymentMethod.js';

test('an InstaPay sale paid in full is labelled InstaPay, never cash', () => {
    assert.deepEqual(invoicePaymentKeys({ payment_type: 'cash', payment_method: 'instapay' }), {
        methodKey: 'invoices.payment_instapay',
        typeKey: null,
    });
});

test('every checkout channel maps to its own label', () => {
    assert.equal(paymentMethodKey('cash'), 'invoices.payment_cash');
    assert.equal(paymentMethodKey('instapay'), 'invoices.payment_instapay');
    assert.equal(paymentMethodKey('e_wallet'), 'invoices.payment_wallet');
    assert.equal(paymentMethodKey('visa'), 'invoices.payment_card');
    assert.equal(paymentMethodKey('bank_transfer'), 'invoices.payment_bank');
    assert.equal(paymentMethodKey('check'), 'treasury.payment_methods_short.check');
    assert.equal(paymentMethodKey('other'), 'treasury.payment_methods_short.other');
});

test('legacy aliases and missing values are normalised', () => {
    assert.equal(normalizePaymentMethod('wallet'), 'e_wallet');
    assert.equal(normalizePaymentMethod('card'), 'visa');
    assert.equal(normalizePaymentMethod('bank'), 'bank_transfer');
    assert.equal(normalizePaymentMethod(null), 'cash');
    assert.equal(normalizePaymentMethod(''), 'cash');
    assert.equal(normalizePaymentMethod('something_new'), 'other');
});

test('a partial invoice shows its channel and the partial badge', () => {
    assert.deepEqual(invoicePaymentKeys({ payment_type: 'partial', payment_method: 'e_wallet' }), {
        methodKey: 'invoices.payment_wallet',
        typeKey: 'invoices.partial',
    });
});

test('a credit invoice collected nothing now, so it shows only credit', () => {
    assert.deepEqual(invoicePaymentKeys({ payment_type: 'credit', payment_method: 'cash' }), {
        methodKey: null,
        typeKey: 'invoices.credit',
    });
});

test('labels are translated in order, channel first', () => {
    const translate = (key) => `<${key}>`;
    assert.deepEqual(invoicePaymentLabels({ payment_type: 'partial', payment_method: 'visa' }, translate), [
        '<invoices.payment_card>',
        '<invoices.partial>',
    ]);
    assert.deepEqual(invoicePaymentLabels(null, translate), ['<invoices.payment_cash>']);
});
