// SET-02: the idle-lock clock. Fake timers (node:test mock.timers also fakes Date), no real waiting.
// Run: node --test tests/js/idle-lock.test.js   (part of `npm run test:js`)
import { test, describe, beforeEach, afterEach, mock } from 'node:test';
import assert from 'node:assert/strict';

import {
    ACTIVITY_THROTTLE_MS,
    BUSY_RETRY_MS,
    createIdleLockTimer,
    idleTimeoutMs,
} from '../../resources/js/helpers/idleLock.js';
import { parseLockMarker } from '../../resources/js/helpers/screenLockStorage.js';
import { hasPendingWrites, writeFinished, writeStarted } from '../../resources/js/helpers/pendingWrites.js';

const MINUTE = 60_000;

describe('idleTimeoutMs', () => {
    test('minutes become milliseconds; 0, empty and garbage mean off', () => {
        assert.equal(idleTimeoutMs(5), 5 * MINUTE);
        assert.equal(idleTimeoutMs('15'), 15 * MINUTE);
        assert.equal(idleTimeoutMs(0), 0);
        assert.equal(idleTimeoutMs(''), 0);
        assert.equal(idleTimeoutMs(null), 0);
        assert.equal(idleTimeoutMs(undefined), 0);
        assert.equal(idleTimeoutMs('abc'), 0);
        assert.equal(idleTimeoutMs(-3), 0);
    });
});

describe('createIdleLockTimer', () => {
    let idleCalls;
    let timeout;
    let busy;
    let timer;

    beforeEach(() => {
        mock.timers.enable({ apis: ['setTimeout', 'Date'], now: 1_000_000 });
        idleCalls = 0;
        timeout = 2 * MINUTE;
        busy = false;
        timer = createIdleLockTimer({
            getTimeoutMs: () => timeout,
            isBusy: () => busy,
            onIdle: () => {
                idleCalls += 1;
            },
        });
    });

    afterEach(() => {
        timer.stop();
        mock.timers.reset();
    });

    test('locks once the idle period has passed, not before', () => {
        timer.start();
        mock.timers.tick(2 * MINUTE - 1);
        assert.equal(idleCalls, 0);
        mock.timers.tick(1);
        assert.equal(idleCalls, 1);
    });

    test('activity pushes the deadline back', () => {
        timer.start();
        mock.timers.tick(MINUTE + 30_000);
        timer.activity();
        mock.timers.tick(MINUTE + 29_000);
        assert.equal(idleCalls, 0, 'one minute and a half after the activity is still inside the period');
        mock.timers.tick(31_000);
        assert.equal(idleCalls, 1);
    });

    test('activity bursts inside the throttle window are ignored', () => {
        timer.start();
        const startedAt = timer.lastActivityAt;
        mock.timers.tick(ACTIVITY_THROTTLE_MS - 1);
        timer.activity();
        assert.equal(timer.lastActivityAt, startedAt);
        mock.timers.tick(1);
        timer.activity();
        assert.equal(timer.lastActivityAt, startedAt + ACTIVITY_THROTTLE_MS);
    });

    test('a timeout of 0 never locks', () => {
        timeout = 0;
        timer.start();
        mock.timers.tick(10 * 60 * MINUTE);
        assert.equal(idleCalls, 0);
    });

    test('turning the setting on while running starts counting from the last activity', () => {
        timeout = 0;
        timer.start();
        mock.timers.tick(MINUTE);
        timeout = 2 * MINUTE;
        timer.refresh();
        mock.timers.tick(MINUTE);
        assert.equal(idleCalls, 1);
    });

    test('turning the setting off cancels a pending lock', () => {
        timer.start();
        mock.timers.tick(MINUTE);
        timeout = 0;
        timer.refresh();
        mock.timers.tick(10 * MINUTE);
        assert.equal(idleCalls, 0);
    });

    test('a hidden tab pauses the timer but the gap still counts when it is visible again', () => {
        timer.start();
        mock.timers.tick(30_000);
        timer.visibilityChanged(false);
        // The laptop lid is closed for an hour: no timer can fire, only the clock moves.
        mock.timers.tick(60 * MINUTE);
        assert.equal(idleCalls, 0, 'nothing fires while hidden');
        timer.visibilityChanged(true);
        assert.equal(idleCalls, 1, 'locks the moment the tab is visible again');
    });

    test('a short hidden gap does not lock and the countdown resumes', () => {
        timer.start();
        timer.visibilityChanged(false);
        mock.timers.tick(30_000);
        timer.visibilityChanged(true);
        assert.equal(idleCalls, 0);
        mock.timers.tick(MINUTE + 29_999);
        assert.equal(idleCalls, 0);
        mock.timers.tick(1);
        assert.equal(idleCalls, 1);
    });

    test('waits while a save request is in flight, then locks', () => {
        busy = true;
        timer.start();
        mock.timers.tick(2 * MINUTE);
        assert.equal(idleCalls, 0, 'postponed');
        mock.timers.tick(BUSY_RETRY_MS * 3);
        assert.equal(idleCalls, 0, 'still saving');
        busy = false;
        mock.timers.tick(BUSY_RETRY_MS);
        assert.equal(idleCalls, 1);
    });

    test('stop() cancels everything and activity is ignored while stopped', () => {
        timer.start();
        timer.stop();
        timer.activity();
        mock.timers.tick(10 * MINUTE);
        assert.equal(idleCalls, 0);
        assert.equal(timer.isRunning, false);
    });

    test('after locking the timer stays quiet until it is started again', () => {
        timer.start();
        mock.timers.tick(2 * MINUTE);
        assert.equal(idleCalls, 1);
        mock.timers.tick(10 * MINUTE);
        assert.equal(idleCalls, 1);
        timer.start();
        mock.timers.tick(2 * MINUTE);
        assert.equal(idleCalls, 2);
    });

    test('activity reported by another tab counts too', () => {
        timer.start();
        mock.timers.tick(MINUTE + 30_000);
        timer.activity(Date.now());
        mock.timers.tick(MINUTE + 29_000);
        assert.equal(idleCalls, 0);
    });

    test('a clock that moves backwards re-anchors on the new time instead of waiting for the old deadline', () => {
        let wall = 5_000_000;
        let fired = 0;
        const skewed = createIdleLockTimer({
            getTimeoutMs: () => 2 * MINUTE,
            onIdle: () => {
                fired += 1;
            },
            now: () => wall,
        });
        skewed.start();
        wall -= 10 * MINUTE; // the system clock was corrected backwards
        mock.timers.tick(2 * MINUTE);
        assert.equal(fired, 0, 'counting again from the corrected clock');
        wall += 2 * MINUTE;
        mock.timers.tick(2 * MINUTE);
        assert.equal(fired, 1);
        skewed.stop();
    });
});

describe('lock marker', () => {
    test('only a well-formed marker is accepted', () => {
        assert.deepEqual(parseLockMarker('{"since":1700000000000,"userId":7}'), { since: 1700000000000, userId: 7 });
        assert.equal(parseLockMarker(null), null);
        assert.equal(parseLockMarker(''), null);
        assert.equal(parseLockMarker('not json'), null);
        assert.equal(parseLockMarker('{"since":"x"}'), null);
        assert.equal(parseLockMarker('{"since":0}'), null);
    });
});

describe('pending writes', () => {
    test('counts POST / PUT / PATCH / DELETE and ignores GET; each request is released once', () => {
        const post = { method: 'post' };
        const get = { method: 'get' };
        writeStarted(get);
        assert.equal(hasPendingWrites(), false);
        writeStarted(post);
        assert.equal(hasPendingWrites(), true);
        writeFinished(post);
        writeFinished(post);
        assert.equal(hasPendingWrites(), false);
    });
});
