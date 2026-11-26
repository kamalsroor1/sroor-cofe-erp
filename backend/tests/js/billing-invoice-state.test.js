// #232 UI §2: the invoice screen's status card follows the server review_state.
import { test } from 'node:test';
import assert from 'node:assert/strict';
import {
    invoiceStatusCard,
    isPartlyPaid,
    paidSummary,
    pendingPaymentOf,
    rejectionReasonOf,
    showsPaySteps,
} from '../../resources/js/helpers/billingInvoiceState.js';
import { nowLocalInput, paidAtPayload } from '../../resources/js/helpers/billing.js';

test('awaiting payment has no status card (the steps are shown)', () => {
    assert.equal(invoiceStatusCard('awaiting_payment'), null);
});

test('review states are amber, manual review is neutral, rejection is rose, paid is emerald', () => {
    assert.equal(invoiceStatusCard('under_review_temporary').tone, 'warning');
    assert.equal(invoiceStatusCard('under_review_grace_used').tone, 'warning');
    assert.equal(invoiceStatusCard('under_review_manual').tone, 'neutral');
    assert.equal(invoiceStatusCard('rejected').tone, 'danger');
    assert.equal(invoiceStatusCard('paid').tone, 'success');
});

test('an under-review receipt can be withdrawn, a rejected one is re-uploaded, paid opens the invoice', () => {
    assert.deepEqual(invoiceStatusCard('under_review_temporary').actions, ['withdraw']);
    assert.deepEqual(invoiceStatusCard('rejected').actions, ['upload_again']);
    assert.deepEqual(invoiceStatusCard('paid').actions, ['view_invoice']);
});

test('the pay steps show for awaiting and rejected pending invoices only', () => {
    const pending = (review_state) => ({ status: { value: 'pending' }, review_state });
    assert.equal(showsPaySteps(pending('awaiting_payment')), true);
    assert.equal(showsPaySteps(pending('rejected')), true);
    assert.equal(showsPaySteps(pending('under_review_temporary')), false);
    assert.equal(showsPaySteps({ status: { value: 'paid' }, review_state: 'paid' }), false);
    assert.equal(showsPaySteps(pending(undefined)), true);
});

test('the pending payment and the last rejection reason come from the payments list', () => {
    const invoice = {
        payments: [
            { id: 1, status: { value: 'rejected' }, rejection_reason: 'blurry' },
            { id: 2, status: { value: 'pending' } },
        ],
    };
    assert.equal(pendingPaymentOf(invoice).id, 2);
    assert.equal(rejectionReasonOf(invoice), 'blurry');
    assert.equal(pendingPaymentOf({ payments: [] }), null);
});

test('paid summary starts at the verified transfer time and ends at the period end', () => {
    const invoice = {
        period_start: '2026-11-26T00:00:00Z',
        period_end: '2026-12-26T00:00:00Z',
        payments: [{ status: { value: 'verified' }, transferred_at: '2026-11-25T08:00:00Z' }],
    };
    assert.deepEqual(paidSummary(invoice), { start: '2026-11-25T08:00:00Z', end: '2026-12-26T00:00:00Z' });
});

test('a partly paid pending invoice is detected from the paid and remaining amounts', () => {
    assert.equal(isPartlyPaid({ status: { value: 'pending' }, paid_amount: '100.000', remaining: '50.000' }), true);
    assert.equal(isPartlyPaid({ status: { value: 'pending' }, paid_amount: '0.000', remaining: '150.000' }), false);
});

test('datetime-local value is built from local time parts', () => {
    assert.equal(nowLocalInput(new Date(2026, 10, 5, 9, 7)), '2026-11-05T09:07');
});

test('paid_at: an untouched default is "now" a minute back, a picked time is an absolute instant', () => {
    const now = new Date('2026-11-26T10:00:00Z');
    assert.equal(paidAtPayload('', now), '2026-11-26T09:59:00.000Z');
    const local = '2026-11-25T14:30';
    assert.equal(paidAtPayload(local, now), new Date(local).toISOString());
    assert.equal(paidAtPayload('not a date', now), '2026-11-26T09:59:00.000Z');
});
