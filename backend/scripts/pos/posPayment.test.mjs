// POS payment helpers: amount input sanitising and the short-cash tender guard.
// Run: node --test scripts/pos/posPayment.test.mjs   (also picked up by `npm run test:scripts`)
import { test, describe } from 'node:test';
import assert from 'node:assert/strict';

import { sanitizeAmountInput, buildCheckoutPayment } from '../../resources/js/helpers/posCheckout.js';

describe('sanitizeAmountInput', () => {
    test('converts Arabic-Indic and extended digits to Latin', () => {
        assert.equal(sanitizeAmountInput('١٢٣'), '123');
        assert.equal(sanitizeAmountInput('۱۰۰'), '100');
    });

    test('keeps ٫ and . as the decimal separator', () => {
        assert.equal(sanitizeAmountInput('١٢٣٫٥٠'), '123.50');
        assert.equal(sanitizeAmountInput('12.5'), '12.5');
        assert.equal(sanitizeAmountInput('1.2.3'), '1.23');
    });

    test('strips thousands separators', () => {
        assert.equal(sanitizeAmountInput('1,500'), '1500');
        assert.equal(sanitizeAmountInput('١٬٥٠٠'), '1500');
        assert.equal(sanitizeAmountInput('١،٥٠٠'), '1500');
    });

    test('drops other characters and handles empty input', () => {
        assert.equal(sanitizeAmountInput('ab 5 !'), '5');
        assert.equal(sanitizeAmountInput(''), '');
        assert.equal(sanitizeAmountInput(null), '');
    });
});

describe('buildCheckoutPayment tender guard', () => {
    const base = { paymentType: 'cash', paymentMethod: 'cash', net: '100.000' };

    test('blocks a cash tender below the net', () => {
        const res = buildCheckoutPayment({ ...base, cashReceived: '50' });
        assert.equal(res.valid, false);
        assert.equal(res.reason, 'tender_short');
    });

    test('allows exact and over tender', () => {
        assert.equal(buildCheckoutPayment({ ...base, cashReceived: '100' }).valid, true);
        const over = buildCheckoutPayment({ ...base, cashReceived: '150' });
        assert.equal(over.valid, true);
        assert.deepEqual(over.payments, [{ method: 'cash', amount: '150.000' }]);
    });

    test('does not block a split that sums to the net', () => {
        const res = buildCheckoutPayment({
            ...base,
            cashReceived: '0',
            splitPayments: [
                { method: 'cash', amount: '40' },
                { method: 'instapay', amount: '60' },
            ],
        });
        assert.equal(res.valid, true);
    });

    test('does not apply the cash guard to partial or credit', () => {
        assert.equal(buildCheckoutPayment({ ...base, paymentType: 'partial', cashReceived: '30' }).valid, true);
        assert.equal(buildCheckoutPayment({ ...base, paymentType: 'credit', cashReceived: '0' }).valid, true);
    });

    test('does not block a non-cash method sent as the exact net', () => {
        const res = buildCheckoutPayment({ ...base, paymentMethod: 'instapay', cashReceived: '100' });
        assert.equal(res.valid, true);
    });

    test('does not block a zero net', () => {
        assert.equal(buildCheckoutPayment({ ...base, net: '0.000', cashReceived: '0' }).valid, true);
    });
});
