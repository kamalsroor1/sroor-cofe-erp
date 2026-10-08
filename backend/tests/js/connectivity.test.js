// OFFL-1: unit tests for the connectivity monitor behind useConnectivity().
// Run from backend/: node --test "tests/js/*.test.js"
import { test, beforeEach } from 'node:test';
import assert from 'node:assert/strict';
import { ref } from 'vue';
import {
    connectivityState,
    createConnectivityMonitor,
    isNetworkError,
    reportRequestFailure,
    reportServerReachable,
} from '../../resources/js/helpers/connectivity.js';

const networkError = () => Object.assign(new Error('Network Error'), { code: 'ERR_NETWORK' });
const timeoutError = () => Object.assign(new Error('timeout'), { code: 'ECONNABORTED' });
const cancelError = () => Object.assign(new Error('canceled'), { code: 'ERR_CANCELED', name: 'CanceledError' });
const httpError = (status) => Object.assign(new Error('HTTP'), { response: { status } });

function fakeScheduler() {
    const timers = new Map();
    let nextId = 1;
    return {
        timers,
        schedule(fn, ms) {
            const id = nextId++;
            timers.set(id, { fn, ms });
            return id;
        },
        cancel(id) {
            timers.delete(id);
        },
        pending() {
            return [...timers.values()];
        },
        async fireAll() {
            const due = [...timers.entries()];
            timers.clear();
            for (const [, timer] of due) await timer.fn();
        },
    };
}

function makeMonitor({ online = true, probe } = {}) {
    const browserOnline = ref(online);
    const scheduler = fakeScheduler();
    const calls = { probe: 0 };
    const monitor = createConnectivityMonitor({
        browserOnline,
        probe: async () => {
            calls.probe++;
            return probe ? probe() : { status: 200 };
        },
        schedule: scheduler.schedule,
        cancel: scheduler.cancel,
        heartbeatMs: 30000,
        recoveryMs: 5000,
    });
    return { monitor, browserOnline, scheduler, calls };
}

beforeEach(() => {
    reportServerReachable();
});

test('isNetworkError: only response-less, non-cancelled, non-timeout failures count', () => {
    assert.equal(isNetworkError(networkError()), true);
    assert.equal(isNetworkError(httpError(500)), false);
    assert.equal(isNetworkError(httpError(422)), false);
    assert.equal(isNetworkError(cancelError()), false);
    assert.equal(isNetworkError(timeoutError()), false);
    assert.equal(isNetworkError(null), false);
});

test('online: browser online + healthy heartbeat keeps isOnline true and schedules the slow heartbeat', async () => {
    const { monitor, scheduler, calls } = makeMonitor();
    await monitor.start();

    assert.equal(calls.probe, 1);
    assert.equal(monitor.isOnline.value, true);
    assert.deepEqual(
        scheduler.pending().map((t) => t.ms),
        [30000]
    );
    monitor.stop();
    assert.equal(scheduler.pending().length, 0);
});

test('offline: browser going offline flips isOnline immediately without probing', async () => {
    const { monitor, browserOnline, scheduler, calls } = makeMonitor();
    await monitor.start();

    browserOnline.value = false;
    assert.equal(monitor.isOnline.value, false);
    assert.equal(monitor.isBrowserOnline.value, false);

    // While the browser reports offline, the heartbeat does not hit the network.
    await scheduler.fireAll();
    assert.equal(calls.probe, 1);
    assert.equal(monitor.isOnline.value, false);
    monitor.stop();
});

test('back online: browser reconnect re-probes the server and restores isOnline', async () => {
    const { monitor, browserOnline, calls } = makeMonitor({ online: false });
    await monitor.start();
    assert.equal(calls.probe, 0);
    assert.equal(monitor.isOnline.value, false);

    browserOnline.value = true;
    await monitor.whenIdle();
    assert.equal(calls.probe, 1);
    assert.equal(monitor.isOnline.value, true);
    monitor.stop();
});

test('failed heartbeat: no response from /ping marks the server unreachable and switches to fast recovery polling', async () => {
    let fail = true;
    const { monitor, scheduler } = makeMonitor({
        probe: () => {
            if (fail) throw networkError();
            return { status: 200 };
        },
    });
    await monitor.start();

    assert.equal(monitor.isServerReachable.value, false);
    assert.equal(monitor.isOnline.value, false);
    assert.deepEqual(
        scheduler.pending().map((t) => t.ms),
        [5000]
    );

    fail = false;
    await scheduler.fireAll();
    assert.equal(monitor.isOnline.value, true);
    assert.deepEqual(
        scheduler.pending().map((t) => t.ms),
        [30000]
    );
    monitor.stop();
});

test('failed heartbeat: a probe timeout counts as unreachable', async () => {
    const { monitor } = makeMonitor({
        probe: () => {
            throw timeoutError();
        },
    });
    await monitor.start();
    assert.equal(monitor.isOnline.value, false);
    monitor.stop();
});

test('heartbeat answered with an HTTP error still means the server is reachable', async () => {
    const { monitor } = makeMonitor({
        probe: () => {
            throw httpError(503);
        },
    });
    await monitor.start();
    assert.equal(monitor.isOnline.value, true);
    monitor.stop();
});

test('network error from axios: marks offline and the next heartbeat runs on the fast recovery interval', async () => {
    const { monitor, scheduler } = makeMonitor();
    await monitor.start();
    assert.equal(monitor.isOnline.value, true);

    reportRequestFailure(networkError());
    assert.equal(connectivityState.serverReachable, false);
    assert.equal(monitor.isOnline.value, false);
    assert.deepEqual(
        scheduler.pending().map((t) => t.ms),
        [5000]
    );

    await scheduler.fireAll();
    assert.equal(monitor.isOnline.value, true);
    monitor.stop();
});

test('non-network axios failures (HTTP errors, cancels, timeouts) never flip connectivity', () => {
    reportRequestFailure(httpError(500));
    reportRequestFailure(cancelError());
    reportRequestFailure(timeoutError());
    assert.equal(connectivityState.serverReachable, true);
});

test('any successful response restores reachability', () => {
    reportRequestFailure(networkError());
    assert.equal(connectivityState.serverReachable, false);
    reportServerReachable();
    assert.equal(connectivityState.serverReachable, true);
});

test('concurrent checks are coalesced into one probe', async () => {
    let resolveProbe;
    const { monitor, calls } = makeMonitor({
        probe: () =>
            new Promise((resolve) => {
                resolveProbe = resolve;
            }),
    });
    const first = monitor.check();
    const second = monitor.check();
    resolveProbe({ status: 200 });
    await Promise.all([first, second]);
    assert.equal(calls.probe, 1);
    monitor.stop();
});
