// #232 UI §3: the 48-hour temporary-access banner states, countdown and dismissal.
import { test } from 'node:test';
import assert from 'node:assert/strict';
import {
    describeTemporaryAccess,
    dismiss,
    formatCountdown,
    isDismissed,
    secondsLeft,
} from '../../resources/js/helpers/temporaryAccess.js';

const NOW = Date.parse('2026-11-26T10:00:00Z');
const active = (seconds) => ({
    state: 'active',
    seconds_left: seconds,
    ends_at: new Date(NOW + seconds * 1000).toISOString(),
});

test('countdown is HH:MM and never negative', () => {
    assert.equal(formatCountdown(47 * 3600 + 59 * 60 + 59), '47:59');
    assert.equal(formatCountdown(65), '00:01');
    assert.equal(formatCountdown(-5), '00:00');
});

test('seconds left uses the server value minus the time since the fetch', () => {
    assert.equal(secondsLeft({ seconds_left: 3600 }, NOW + 120000, NOW), 3480);
    assert.equal(secondsLeft(active(3600), NOW, null), 3600);
    assert.equal(secondsLeft(null, NOW), 0);
});

test('more than six hours left is the primary active state with a countdown', () => {
    const d = describeTemporaryAccess(active(20 * 3600), { nowMs: NOW, fetchedAtMs: NOW });
    assert.equal(d.state, 'active');
    assert.equal(d.tone, 'info');
    assert.equal(d.countdown, '20:00');
    assert.equal(d.action, 'details');
});

test('six hours or less turns amber', () => {
    const d = describeTemporaryAccess(active(6 * 3600), { nowMs: NOW, fetchedAtMs: NOW });
    assert.equal(d.state, 'last_hours');
    assert.equal(d.tone, 'warning');
});

test('an active window whose time ran out locally falls back to expired', () => {
    const d = describeTemporaryAccess(active(60), { nowMs: NOW + 120000, fetchedAtMs: NOW });
    assert.equal(d.state, 'expired');
    assert.equal(d.countdown, null);
});

test('rejected carries the reason and the upload-again action', () => {
    const d = describeTemporaryAccess({ state: 'rejected', rejection_reason: 'amount short' }, { nowMs: NOW });
    assert.equal(d.tone, 'danger');
    assert.deepEqual(d.params, { reason: 'amount short' });
    assert.equal(d.action, 'upload_again');
});

test('confirmed is dismissible, names plan and end, differs for an upgrade, and hides once dismissed', () => {
    const ctx = { nowMs: NOW, planName: 'Pro', endsAt: '2026-12-11' };
    const up = describeTemporaryAccess({ state: 'verified', invoice_type: 'upgrade' }, ctx);
    const renewal = describeTemporaryAccess({ state: 'verified', invoice_type: 'renewal' }, ctx);
    assert.equal(up.textKey, 'subscription.billing.grace.confirmed_upgrade');
    assert.equal(renewal.textKey, 'subscription.billing.grace.confirmed_renewal');
    assert.equal(up.dismissible, true);
    assert.deepEqual(up.params, { plan: 'Pro', end: '2026-12-11' });
    assert.equal(describeTemporaryAccess({ state: 'verified' }, { ...ctx, dismissed: true }), null);
});

test('confirmed names the plan and the period end the server sent, ahead of the fallbacks', () => {
    const window = {
        state: 'verified',
        invoice_type: 'upgrade',
        plan: { id: 4, name: 'Pro' },
        period_end: '2026-12-11',
    };
    const d = describeTemporaryAccess(window, { nowMs: NOW, planName: 'Old', endsAt: '2026-01-01' });
    assert.deepEqual(d.params, { plan: 'Pro', end: '2026-12-11' });
});

test('no window, voided or none state show nothing', () => {
    assert.equal(describeTemporaryAccess(null, { nowMs: NOW }), null);
    assert.equal(describeTemporaryAccess({ state: 'none' }, { nowMs: NOW }), null);
    assert.equal(describeTemporaryAccess({ state: 'voided' }, { nowMs: NOW }), null);
});

test('dismissal is stored per invoice and survives a broken storage', () => {
    const data = new Map();
    const storage = { getItem: (k) => data.get(k) ?? null, setItem: (k, v) => data.set(k, v) };
    assert.equal(isDismissed(7, storage), false);
    dismiss(7, storage);
    assert.equal(isDismissed(7, storage), true);
    assert.equal(isDismissed(8, storage), false);
    const broken = {
        getItem: () => {
            throw new Error('denied');
        },
        setItem: () => {
            throw new Error('denied');
        },
    };
    assert.equal(isDismissed(7, broken), false);
    assert.doesNotThrow(() => dismiss(7, broken));
});
