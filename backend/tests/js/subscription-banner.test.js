// SELL-02: state -> text/tone mapping of the permanent subscription banner.
// Run from backend/: node --test tests/js/subscription-banner.test.js
import { test } from 'node:test';
import assert from 'node:assert/strict';
import { describeSubscriptionBanner } from '../../resources/js/helpers/subscriptionBanner.js';

const make = (over) => ({ state: 'trial', kind: 'trial', days_left: 3, action_path: '/billing', ...over });

test('null or unknown banner renders nothing', () => {
    assert.equal(describeSubscriptionBanner(null), null);
    assert.equal(describeSubscriptionBanner(undefined), null);
    assert.equal(describeSubscriptionBanner({ state: 'weird' }), null);
});

test('trial and expiring pick the key by days left', () => {
    for (const [state, tone] of [
        ['trial', 'info'],
        ['expiring', 'warning'],
    ]) {
        const at = (d) => describeSubscriptionBanner(make({ state, days_left: d }));
        assert.equal(at(3).textKey, `subscription.banner.${state}.days_left`);
        assert.deepEqual(at(3).params, { days: 3 });
        assert.equal(at(1).textKey, `subscription.banner.${state}.tomorrow`);
        assert.equal(at(0).textKey, `subscription.banner.${state}.today`);
        assert.equal(at(3).tone, tone);
    }
});

test('grace is a warning keyed by kind', () => {
    const trial = describeSubscriptionBanner(make({ state: 'grace', kind: 'trial', days_left: 2 }));
    const sub = describeSubscriptionBanner(make({ state: 'grace', kind: 'subscription', days_left: 2 }));
    assert.equal(trial.textKey, 'subscription.banner.grace.trial');
    assert.equal(sub.textKey, 'subscription.banner.grace.subscription');
    assert.equal(trial.tone, 'warning');
    assert.deepEqual(sub.params, { days: 2 });
});

test('read_only is fixed text with the danger tone', () => {
    const b = describeSubscriptionBanner(make({ state: 'read_only', days_left: null }));
    assert.equal(b.textKey, 'subscription.banner.read_only');
    assert.equal(b.tone, 'danger');
    assert.equal(b.state, 'read_only');
});

test('only same-app action paths are kept', () => {
    assert.equal(describeSubscriptionBanner(make()).actionPath, '/billing');
    for (const bad of ['https://evil.test', '//evil.test', 'javascript:alert(1)', 'billing', null, undefined]) {
        assert.equal(describeSubscriptionBanner(make({ action_path: bad })).actionPath, null);
    }
});
