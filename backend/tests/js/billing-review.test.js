import test from 'node:test';
import assert from 'node:assert/strict';
import {
    amountsDiffer,
    canApprove,
    compareMoney,
    emptyChecks,
    invoiceTypeLabel,
    isFutureLocal,
    methodStatus,
    nextSelection,
    notificationLocation,
    paymentIdFromQuery,
    reviewChip,
} from '../../resources/js/helpers/billingReview.js';

const NOW = Date.parse('2026-11-26T12:00:00Z');
const pending = (window, classification = 'transfer_receipt_image') => ({
    status: { value: 'pending' },
    receipt_classification: { value: classification },
    invoice_temporary_access: window,
});

test('money comparison is decimal-exact', () => {
    assert.equal(compareMoney('10.000', '10'), 0);
    assert.equal(compareMoney('0.1', '0.10'), 0);
    assert.equal(compareMoney('285.000', '284.999'), 1);
    assert.equal(compareMoney('abc', '1'), null);
    assert.equal(amountsDiffer('250.000', '285.000'), true);
    assert.equal(amountsDiffer('285', '285.000'), false);
    assert.equal(amountsDiffer(null, '285.000'), false);
});

test('review chip: running window, urgent under 6 h, ended, manual, none', () => {
    const at = (hours) => new Date(NOW + hours * 3600_000).toISOString();
    assert.deepEqual(reviewChip(pending({ state: 'active', ends_at: at(30) }), NOW), { kind: 'grace', hours: 30 });
    assert.equal(reviewChip(pending({ state: 'active', ends_at: at(5.5) }), NOW).kind, 'urgent');
    assert.equal(reviewChip(pending({ state: 'active', ends_at: at(6) }), NOW).kind, 'urgent');
    assert.equal(reviewChip(pending({ state: 'expired' }), NOW).kind, 'ended');
    assert.equal(reviewChip(pending({ state: 'none' }, 'not_receipt'), NOW).kind, 'manual');
    assert.equal(reviewChip(pending({ state: 'none' }), NOW).kind, 'none');
    assert.equal(
        reviewChip({ ...pending({ state: 'active', ends_at: at(3) }), status: { value: 'verified' } }, NOW).kind,
        'none'
    );
});

test('approve needs the time, a past time and all three checks', () => {
    const all = { amount: true, recipient: true, statement: true };
    assert.equal(canApprove({ transferredAt: '2026-11-26T10:00', checks: all }), true);
    assert.equal(canApprove({ transferredAt: '', checks: all }), false);
    assert.equal(canApprove({ transferredAt: '2026-11-26T10:00', checks: { ...all, amount: false } }), false);
    assert.equal(canApprove({ transferredAt: '2026-11-26T10:00', checks: all, future: true }), false);
    assert.equal(canApprove({ transferredAt: 'x', checks: emptyChecks() }), false);
});

test('future detection uses the local datetime', () => {
    assert.equal(isFutureLocal('2999-01-01T00:00', NOW), true);
    assert.equal(isFutureLocal('2000-01-01T00:00', NOW), false);
    assert.equal(isFutureLocal('', NOW), false);
});

test('next selection after a decision', () => {
    const list = [{ id: 1 }, { id: 2 }, { id: 3 }];
    assert.equal(nextSelection(list, 2).id, 3);
    assert.equal(nextSelection(list, 3).id, 2);
    assert.equal(nextSelection([{ id: 1 }], 1), null);
});

test('notification link and query parsing', () => {
    assert.deepEqual(notificationLocation({ link: { query: { payment: 7 } } }), {
        path: '/super-admin/billing',
        query: { payment: '7' },
    });
    assert.deepEqual(notificationLocation({}), { path: '/super-admin/billing' });
    assert.equal(paymentIdFromQuery('12'), 12);
    assert.equal(paymentIdFromQuery(['5']), 5);
    assert.equal(paymentIdFromQuery('abc'), null);
    assert.equal(paymentIdFromQuery(undefined), null);
});

test('invoice type label uses the plan names only when the API sends them', () => {
    assert.equal(invoiceTypeLabel({ invoice_type: 'renewal' }).key, 'billing.console.verify.invoice_renewal');
    assert.deepEqual(
        invoiceTypeLabel({ invoice_type: 'upgrade', invoice_plan_change: { from: 'A', to: 'B' } }).params,
        {
            old: 'A',
            new: 'B',
        }
    );
    assert.equal(invoiceTypeLabel({ invoice_type: 'upgrade' }).key, 'billing.console.verify.invoice_upgrade_plain');
    assert.equal(invoiceTypeLabel({ invoice_type: 'zzz' }), null);
});

test('method status mirrors the server rules', () => {
    assert.equal(methodStatus('vodafone_cash', {}).status, 'empty');
    assert.equal(methodStatus('vodafone_cash', { billing_vodafone_cash_note: 'x' }).status, 'missing');
    assert.equal(methodStatus('vodafone_cash', { billing_vodafone_cash_number: '01000000001' }).visible, true);
    const bank = methodStatus('bank_transfer', { billing_bank_name: 'B', billing_bank_iban: 'EG1' });
    assert.equal(bank.status, 'missing');
    assert.deepEqual(bank.missing, ['billing_bank_account_holder', 'billing_bank_account_number']);
    assert.equal(methodStatus('bank_transfer', { billing_bank_branch: 'x' }).status, 'missing');
});
