'use strict';

// APP-7: no update loop (3 failed attempts per 24h, then the manual download page), pending
// install resolved at startup, and the boot-crash rollback offer after 3 failed boots.

const test = require('node:test');
const assert = require('node:assert/strict');
const path = require('node:path');

const S = require(path.join(__dirname, '..', 'src', 'updater', 'updateState.js'));

const HOUR = 60 * 60 * 1000;
const T0 = Date.UTC(2026, 9, 10, 8, 0, 0);

test('a fresh or corrupt state normalizes to the empty state', () => {
    assert.deepEqual(S.normalizeState(undefined, T0), S.emptyState());
    assert.deepEqual(S.normalizeState('garbage', T0), S.emptyState());
    assert.deepEqual(
        S.normalizeState({ failedAttempts: 'x', failedBoots: -4, pending: { versionCode: 'a' } }, T0),
        S.emptyState()
    );
});

test('three failed attempts within 24h exhaust the updater; they expire after 24h', () => {
    let state = S.emptyState();
    assert.deepEqual(S.canAttempt(state, T0), { ok: true, remaining: 3 });
    state = S.recordFailedAttempt(state, T0);
    state = S.recordFailedAttempt(state, T0 + HOUR);
    assert.deepEqual(S.canAttempt(state, T0 + HOUR), { ok: true, remaining: 1 });
    state = S.recordFailedAttempt(state, T0 + 2 * HOUR);
    assert.deepEqual(S.canAttempt(state, T0 + 2 * HOUR), { ok: false, remaining: 0 });
    assert.equal(S.summarize(state, T0 + 2 * HOUR).attemptsExhausted, true);

    // the first failure leaves the 24h window
    assert.deepEqual(S.canAttempt(state, T0 + 24 * HOUR), { ok: true, remaining: 1 });
    assert.equal(S.normalizeState(state, T0 + 27 * HOUR).failedAttempts.length, 0);
});

test('pending install resolved as success when the new version is running', () => {
    let state = S.markPending(S.emptyState(), { versionCode: 1001000, fromVersionCode: 1000000 }, T0);
    assert.equal(state.pending.kind, 'update');
    state = S.recordFailedAttempt(state, T0 - HOUR);

    const { state: next, outcome } = S.resolvePendingAtStartup(state, 1001000, T0 + 60000);
    assert.equal(outcome, 'success');
    assert.equal(next.pending, null);
    assert.deepEqual(next.failedAttempts, []);
    assert.equal(next.lastResult.status, 'success');
    assert.deepEqual(next.postUpdateWatch, { versionCode: 1001000, fromVersionCode: 1000000, since: T0 + 60000 });
});

test('pending install resolved as a failed attempt when the old version is still running', () => {
    const state = S.markPending(S.emptyState(), { versionCode: 1001000, fromVersionCode: 1000000 }, T0);
    const { state: next, outcome } = S.resolvePendingAtStartup(state, 1000000, T0 + 60000);
    assert.equal(outcome, 'failed');
    assert.equal(next.pending, null);
    assert.equal(next.failedAttempts.length, 1);
    assert.equal(next.lastResult.status, 'failed');
    assert.equal(next.postUpdateWatch, null);
});

test('no pending install: startup changes nothing', () => {
    const state = S.emptyState();
    assert.deepEqual(S.resolvePendingAtStartup(state, 1000000, T0), { state, outcome: null });
});

test('the boot that starts the installer is not counted as a failed boot', () => {
    let state = S.beginBoot(S.emptyState());
    state = S.markPending(state, { versionCode: 1001000, fromVersionCode: 1000000 }, T0);
    assert.equal(state.bootInProgress, false);
    state = S.resolvePendingAtStartup(state, 1001000, T0 + 1000).state;
    state = S.beginBoot(state);
    assert.equal(state.failedBoots, 0);
});

test('three unconfirmed boots of an updated build offer the rollback; a healthy boot clears it', () => {
    let state = S.markPending(S.emptyState(), { versionCode: 1001000, fromVersionCode: 1000000 }, T0);
    state = S.resolvePendingAtStartup(state, 1001000, T0).state;

    state = S.beginBoot(state); // boot 1 crashes (never confirmed)
    state = S.beginBoot(state); // boot 2 -> 1 failed
    state = S.beginBoot(state); // boot 3 -> 2 failed
    assert.equal(S.shouldOfferRollback(state), false);
    state = S.beginBoot(state); // boot 4 -> 3 failed
    assert.equal(state.failedBoots, 3);
    assert.equal(S.shouldOfferRollback(state), true);

    const declined = S.rollbackOffered(state);
    assert.equal(S.shouldOfferRollback(declined), false);
    assert.ok(declined.postUpdateWatch, 'declining keeps watching the update');

    const healthy = S.confirmBoot(state);
    assert.equal(healthy.failedBoots, 0);
    assert.equal(healthy.postUpdateWatch, null);
    assert.equal(S.shouldOfferRollback(S.beginBoot(S.beginBoot(S.beginBoot(S.beginBoot(healthy))))), false);
});

test('a clean quit is not a crash', () => {
    let state = S.markPending(S.emptyState(), { versionCode: 1001000, fromVersionCode: 1000000 }, T0);
    state = S.resolvePendingAtStartup(state, 1001000, T0).state;
    for (let i = 0; i < 5; i += 1) {
        state = S.endBootCleanly(S.beginBoot(state));
    }
    assert.equal(state.failedBoots, 0);
});

test('crashes without a recent update never offer a rollback', () => {
    let state = S.emptyState();
    for (let i = 0; i < 6; i += 1) state = S.beginBoot(state);
    assert.equal(state.failedBoots, 0);
    assert.equal(S.shouldOfferRollback(state), false);
});

test('a successful rollback is not itself watched', () => {
    const state = S.markPending(
        S.emptyState(),
        { versionCode: 1000000, fromVersionCode: 1001000, kind: 'rollback' },
        T0
    );
    const { state: next, outcome } = S.resolvePendingAtStartup(state, 1000000, T0);
    assert.equal(outcome, 'success');
    assert.equal(next.lastResult.kind, 'rollback');
    assert.equal(next.postUpdateWatch, null);
});

test('summarize exposes counters only', () => {
    const summary = S.summarize(S.recordFailedAttempt(S.emptyState(), T0), T0);
    assert.deepEqual(summary, {
        pending: false,
        attemptsRemaining: 2,
        attemptsExhausted: false,
        lastResult: null,
        failedBoots: 0,
        watchingUpdate: false,
    });
});
