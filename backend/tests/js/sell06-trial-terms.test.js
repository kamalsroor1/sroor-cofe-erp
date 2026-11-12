// SELL-06: approve-dialog trial terms and the read-only (423) notice helpers.
// Run from backend/: node --test tests/js/sell06-trial-terms.test.js
import { test } from 'node:test';
import assert from 'node:assert/strict';
import { resolveApprovalTerms, trialDaysFor } from '../../resources/js/helpers/signupApprovalPreview.js';
import {
    contactSupportHtml,
    describeReadOnly,
    safeActionPath,
    supportFooter,
} from '../../resources/js/helpers/readOnlyNotice.js';

const preview = {
    plan: { id: 2, code: 'basic', name: 'Basic', trial_days: 0 },
    default_trial_days: 14,
    trial_days: 14,
    tenant_status: { value: 'trial', label: 'Trial' },
    subscription_status: { value: 'trialing', label: 'On trial' },
    trial_ends_at: '2026-11-26T00:00:00Z',
};
const plans = [
    { value: 'basic', label: 'Basic', trialDays: 0 },
    { value: 'pro', label: 'Pro', trialDays: 0 },
    { value: 'free', label: 'Free', trialDays: 30 },
];
const now = new Date('2026-11-12T00:00:00Z');

test('trialDaysFor uses the plan trial, else the default', () => {
    assert.equal(trialDaysFor(30, 14), 30);
    assert.equal(trialDaysFor(0, 14), 14);
    assert.equal(trialDaysFor(null, 14), 14);
});

test('no preview means no terms', () => {
    assert.equal(resolveApprovalTerms(null, 'basic', plans, now), null);
});

test('the requested plan keeps the server preview', () => {
    const terms = resolveApprovalTerms(preview, 'basic', plans, now);
    assert.equal(terms.trialDays, 14);
    assert.equal(terms.endsAt, '2026-11-26T00:00:00Z');
    assert.equal(terms.tenantStatus, 'Trial');
});

test('another plan recomputes days and end date', () => {
    const pro = resolveApprovalTerms(preview, 'pro', plans, now);
    assert.equal(pro.trialDays, 14);
    assert.equal(pro.planName, 'Pro');
    assert.equal(pro.endsAt, '2026-11-26T00:00:00.000Z');
    const free = resolveApprovalTerms(preview, 'free', plans, now);
    assert.equal(free.trialDays, 30);
    assert.equal(free.endsAt, '2026-12-12T00:00:00.000Z');
});

test('read-only details become an action, a safe path and support contacts', () => {
    const notice = describeReadOnly({
        error_code: 'subscription.read_only',
        details: { action: 'request_invoice', action_path: '/billing', support: { email: 'a@b.c', phone: '' } },
    });
    assert.equal(notice.actionKey, 'request_invoice');
    assert.equal(notice.actionPath, '/billing');
    assert.deepEqual(notice.supportParams, { email: 'a@b.c', phone: '' });
    assert.deepEqual(describeReadOnly({ error_code: 'other' }).supportParams, null);
});

test('unsafe action paths are dropped', () => {
    assert.equal(safeActionPath('//evil.com'), null);
    assert.equal(safeActionPath('https://evil.com'), null);
    assert.equal(safeActionPath('/billing'), '/billing');
});

test('support footer escapes HTML', () => {
    const html = supportFooter({ email: '<b>x</b>', phone: '' }, () => 'Support:');
    assert.ok(!html.includes('<b>'));
    assert.equal(
        supportFooter(null, () => ''),
        ''
    );
});

test('contact_support has no navigation but keeps the support contacts', () => {
    const notice = describeReadOnly({
        error_code: 'subscription.read_only',
        details: { reason: 'operator', action: 'contact_support', support: { email: 'a@b.c', phone: '010' } },
    });
    assert.equal(notice.contactSupport, true);
    assert.equal(notice.canNavigate, false);
    assert.equal(notice.actionKey, null);
    assert.deepEqual(notice.supportParams, { email: 'a@b.c', phone: '010' });
    const html = contactSupportHtml('<x>', notice.supportParams, () => 'Support:');
    assert.ok(html.includes('a@b.c') && html.includes('010') && !html.includes('<x>'));
});

test('a read-only answer without reason or action falls back to the message only', () => {
    for (const body of [
        { error_code: 'subscription.read_only', details: { status: 'suspended' } },
        { error_code: 'subscription.read_only' },
    ]) {
        const notice = describeReadOnly(body);
        assert.equal(notice.canNavigate, false);
        assert.equal(notice.contactSupport, false);
        assert.equal(notice.supportParams, null);
    }
    assert.equal(
        describeReadOnly({ error_code: 'subscription.read_only', details: { action: 'pay_invoice' } }).canNavigate,
        true
    );
});

test('matching tenants reach the approval terms, empty when absent', () => {
    const tenants = [{ id: 1, name: 'Old', status: { value: 'active', label: 'Active' }, created_at: '2026-01-01' }];
    assert.deepEqual(
        resolveApprovalTerms({ ...preview, matching_tenants: tenants }, 'basic', plans, now).matchingTenants,
        tenants
    );
    assert.deepEqual(resolveApprovalTerms(preview, 'basic', plans, now).matchingTenants, []);
});
