// #232 UI §1: plan choice, default action and preview query of the /billing upgrade / renew screen.
import { test } from 'node:test';
import assert from 'node:assert/strict';
import {
    apiAction,
    changeInvoiceBody,
    changeOutcome,
    ctaKey,
    canPreview,
    defaultAction,
    previewNoteKey,
    previewQuery,
    timelineSteps,
    upgradeCandidates,
} from '../../resources/js/helpers/billingChange.js';

const plans = [
    { id: 1, code: 'free', is_free: true, is_current: false, price_monthly: '0.000', price_yearly: '0.000' },
    { id: 2, code: 'starter', is_free: false, is_current: false, price_monthly: '499.000', price_yearly: '4990.000' },
    { id: 3, code: 'business', is_free: false, is_current: true, price_monthly: '999.000', price_yearly: '9990.000' },
    { id: 4, code: 'pro', is_free: false, is_current: false, price_monthly: '1499.000', price_yearly: '14990.000' },
];
const paid = (extra = {}) => ({
    available_plans: plans,
    subscription: { has_paid_term: true, can_request_renewal: true, billing_cycle: { value: 'monthly' }, ...extra },
});

test('only plans priced above the current one are upgrade candidates', () => {
    assert.deepEqual(
        upgradeCandidates(paid()).map((p) => p.id),
        [4]
    );
});

test('the yearly cycle compares yearly prices', () => {
    assert.deepEqual(
        upgradeCandidates(paid({ billing_cycle: { value: 'yearly' } })).map((p) => p.id),
        [4]
    );
});

test('without a paid term every paid plan is offered, the shops own included (subscribe)', () => {
    const data = paid({ has_paid_term: false, can_request_renewal: false });
    assert.deepEqual(
        upgradeCandidates(data).map((p) => p.id),
        [2, 3, 4]
    );
});

test('on the top plan there is nothing to upgrade to and the default is renew', () => {
    const top = { ...paid(), available_plans: plans.map((p) => ({ ...p, is_current: p.id === 4 })) };
    assert.equal(upgradeCandidates(top).length, 0);
    assert.equal(defaultAction(top), 'renew');
});

test('default is upgrade when a higher plan exists, and in the subscribe flow', () => {
    assert.equal(defaultAction(paid()), 'upgrade');
    assert.equal(defaultAction(paid({ has_paid_term: false, can_request_renewal: false })), 'upgrade');
});

test('preview query carries plan and renew only for an upgrade', () => {
    assert.deepEqual(previewQuery({ action: 'upgrade', planId: 4, cycle: 'monthly', renew: true }), {
        action: 'upgrade',
        plan_id: 4,
        cycle: 'monthly',
        renew: 1,
    });
    assert.deepEqual(previewQuery({ action: 'renew', planId: 4, cycle: 'yearly', renew: true }), {
        action: 'renew',
        cycle: 'yearly',
    });
});

test('an upgrade needs a plan before a preview is requested', () => {
    assert.equal(canPreview({ action: 'upgrade', planId: null }), false);
    assert.equal(canPreview({ action: 'upgrade', planId: 4 }), true);
    assert.equal(canPreview({ action: 'renew', planId: null }), true);
});

test('timeline: upgrade only keeps one fixed end, a renewal adds the new end', () => {
    assert.deepEqual(
        timelineSteps({ period_end: '2026-12-11', new_period_end: null }).map((s) => s.key),
        ['today', 'end_fixed']
    );
    assert.deepEqual(
        timelineSteps({ period_end: '2026-12-11', new_period_end: '2027-01-11' }).map((s) => s.key),
        ['today', 'end_fixed', 'new_end']
    );
    assert.deepEqual(
        timelineSteps({ period_end: null, new_period_end: '2027-01-11' }).map((s) => s.key),
        ['today', 'new_end']
    );
    assert.deepEqual(timelineSteps({ period_end: null, new_period_end: null }), []);
});

test('note and CTA wording follow the preview kind and the founder hold', () => {
    assert.equal(previewNoteKey({ kind: 'upgrade_renewal' }), 'note_upgrade');
    assert.equal(previewNoteKey({ kind: 'renewal' }), 'note_renew');
    assert.equal(previewNoteKey({ kind: 'subscribe' }), null);
    assert.equal(ctaKey({ held: true, action: 'upgrade', subscribe: false }), 'cta_send_review');
    assert.equal(ctaKey({ held: false, action: 'upgrade', subscribe: true }), 'cta_subscribe');
    assert.equal(ctaKey({ held: false, action: 'renew', subscribe: false }), 'cta_issue');
});

test('plan card status chip: grace beats everything, lapsed is expired, trial and active otherwise', async () => {
    const { planStatus } = await import('../../resources/js/helpers/billingChange.js');
    const tenant = (extra) => ({ tenant_status: { value: 'active', access: 'full', ...extra } });
    assert.deepEqual(planStatus(tenant({ temporary_access: true, access: 'read_only' })), {
        key: 'grace',
        tone: 'warning',
    });
    assert.equal(planStatus(tenant({ value: 'expired', access: 'read_only' })).key, 'expired');
    assert.equal(planStatus(tenant({ value: 'past_due' })).key, 'expired');
    assert.equal(planStatus(tenant({ value: 'trial' })).key, 'trial');
    assert.equal(planStatus(tenant({})).key, 'active');
});

test('the screen upgrade is a subscribe without a paid term, and renew stays renew', () => {
    assert.equal(apiAction({ action: 'upgrade', subscribe: false }), 'upgrade');
    assert.equal(apiAction({ action: 'upgrade', subscribe: true }), 'subscribe');
    assert.equal(apiAction({ action: 'renew', subscribe: true }), 'renew');
    assert.deepEqual(previewQuery({ action: 'upgrade', planId: 3, cycle: 'monthly', renew: true, subscribe: true }), {
        action: 'subscribe',
        plan_id: 3,
        cycle: 'monthly',
        renew: 0,
    });
});

test('the change-invoice body carries action, plan, cycle and renew', () => {
    assert.deepEqual(changeInvoiceBody({ action: 'upgrade', planId: 4, cycle: 'yearly', renew: true }), {
        action: 'upgrade',
        plan_id: 4,
        cycle: 'yearly',
        renew: true,
    });
    assert.deepEqual(
        changeInvoiceBody({ action: 'upgrade', planId: 3, cycle: 'monthly', renew: true, subscribe: true }),
        {
            action: 'subscribe',
            plan_id: 3,
            cycle: 'monthly',
            renew: false,
        }
    );
});

test('201 with an invoice opens its payment screen', () => {
    assert.deepEqual(
        changeOutcome({
            status: 201,
            data: { message: 'ok', data: { held_for_price_review: false, invoice: { id: 9 } } },
        }),
        { type: 'invoice', invoiceId: 9, message: 'ok' }
    );
});

test('201 held for a price review without an invoice is the held state', () => {
    const outcome = changeOutcome({
        status: 201,
        data: { message: 'filed', data: { held_for_price_review: true, invoice: null } },
    });
    assert.equal(outcome.type, 'held');
});

test('409 duplicate points at the existing invoice', () => {
    assert.deepEqual(
        changeOutcome({
            status: 409,
            data: { error_code: 'billing.invoice_duplicate', details: { invoice_id: 5, invoice_number: 'INV-5' } },
        }),
        { type: 'duplicate', invoiceId: 5, number: 'INV-5' }
    );
});

test('each 422 refusal code keeps the server message', () => {
    for (const code of [
        'billing.request_plan_unavailable',
        'billing.free_plan_not_billable',
        'billing.request_same_plan',
        'billing.upgrade_not_higher',
        'billing.request_already_pending',
    ]) {
        assert.deepEqual(changeOutcome({ status: 422, data: { error_code: code, message: 'no' } }), {
            type: 'refused',
            code,
            message: 'no',
        });
    }
});

test('anything else is a plain failure', () => {
    assert.equal(changeOutcome({ status: 422, data: { message: 'bad', errors: {} } }).type, 'failed');
    assert.equal(changeOutcome({ status: 500, data: {} }).type, 'failed');
    assert.equal(changeOutcome({ status: 201, data: { data: { invoice: null } } }).type, 'failed');
    assert.equal(changeOutcome(null).type, 'failed');
});
