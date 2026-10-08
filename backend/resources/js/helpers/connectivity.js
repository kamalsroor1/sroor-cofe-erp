import { computed, reactive, readonly, watch } from 'vue';

// Framework-free connectivity core (OFFL-1). `useConnectivity()` wires it to the browser
// (`useOnline`) and to `/api/v1/ping`; `Services/api.js` feeds it every axios outcome.

export const HEARTBEAT_INTERVAL_MS = 30000;
export const RECOVERY_INTERVAL_MS = 5000;
export const PROBE_TIMEOUT_MS = 5000;

const CANCEL_CODES = ['ERR_CANCELED'];
const TIMEOUT_CODES = ['ECONNABORTED', 'ETIMEDOUT'];

const state = reactive({ serverReachable: true });

export const connectivityState = readonly(state);

const isCanceled = (error) => CANCEL_CODES.includes(error.code) || error.name === 'CanceledError';

/**
 * A request failed without any HTTP response (DNS, refused, dropped link).
 * HTTP errors, cancellations and slow-request timeouts are not connectivity loss.
 */
export function isNetworkError(error) {
    if (!error || error.response) return false;
    if (isCanceled(error)) return false;
    return !TIMEOUT_CODES.includes(error.code);
}

export function reportRequestFailure(error) {
    if (isNetworkError(error)) state.serverReachable = false;
}

export function reportServerReachable() {
    state.serverReachable = true;
}

/**
 * @param {object} options
 * @param {import('vue').Ref<boolean>} options.browserOnline  navigator online flag
 * @param {() => Promise<unknown>} options.probe              heartbeat request (rejects on failure)
 * @param {(fn: Function, ms: number) => unknown} options.schedule
 * @param {(id: unknown) => void} options.cancel
 */
export function createConnectivityMonitor({
    browserOnline,
    probe,
    schedule,
    cancel,
    heartbeatMs = HEARTBEAT_INTERVAL_MS,
    recoveryMs = RECOVERY_INTERVAL_MS,
}) {
    const isBrowserOnline = computed(() => Boolean(browserOnline.value));
    const isServerReachable = computed(() => state.serverReachable);
    const isOnline = computed(() => isBrowserOnline.value && isServerReachable.value);

    let timer = null;
    let running = false;
    let inFlight = null;
    const stopHandles = [];

    const clearTimer = () => {
        if (timer !== null) cancel(timer);
        timer = null;
    };

    // No polling while the browser itself is offline: its `online` event re-triggers a check.
    const reschedule = () => {
        clearTimer();
        if (!running || !isBrowserOnline.value) return;
        timer = schedule(check, state.serverReachable ? heartbeatMs : recoveryMs);
    };

    async function runProbe() {
        try {
            await probe();
            state.serverReachable = true;
        } catch (error) {
            // Any HTTP answer proves the server is reachable; only a missing response (or probe timeout) does not.
            state.serverReachable = Boolean(error?.response) || isCanceled(error ?? {});
        }
    }

    function check() {
        if (!isBrowserOnline.value) {
            clearTimer();
            return Promise.resolve(false);
        }
        if (!inFlight) {
            inFlight = runProbe().finally(() => {
                inFlight = null;
                reschedule();
            });
        }
        return inFlight.then(() => isOnline.value);
    }

    function whenIdle() {
        return inFlight ? inFlight.then(() => undefined) : Promise.resolve();
    }

    function start() {
        if (running) return whenIdle();
        running = true;
        stopHandles.push(
            watch(
                isBrowserOnline,
                (online) => {
                    if (online) check();
                    else clearTimer();
                },
                { flush: 'sync' }
            ),
            // An axios network error elsewhere: retry on the fast interval instead of waiting a full heartbeat.
            watch(
                () => state.serverReachable,
                () => {
                    if (!inFlight) reschedule();
                },
                { flush: 'sync' }
            )
        );
        return check().then(() => undefined);
    }

    function stop() {
        running = false;
        clearTimer();
        stopHandles.splice(0).forEach((stopWatch) => stopWatch());
    }

    return { isOnline, isBrowserOnline, isServerReachable, check, start, stop, whenIdle };
}
