// SET-02: the idle-lock clock. Framework-free so it can be tested with fake timers.
//
// It never counts ticks: the deadline is always "last activity + timeout" compared against the wall
// clock. A timer that was throttled or paused (hidden tab, laptop lid closed for an hour) therefore
// cannot postpone the lock: the first check after waking sees the real gap and locks at once.

/** Activity events closer together than this are ignored (one cheap timestamp write per second at most). */
export const ACTIVITY_THROTTLE_MS = 1000;

/** While a save request is in flight the lock waits, then looks again after this delay. */
export const BUSY_RETRY_MS = 2000;

/** Longest delay a browser timer can hold (2^31 - 1 ms); longer waits are re-armed in steps. */
const MAX_TIMER_MS = 2_147_483_647;

/** Minutes the settings form stores -> milliseconds for the timer; anything not a positive number = off. */
export function idleTimeoutMs(minutes) {
    const value = Number(minutes);
    return Number.isFinite(value) && value > 0 ? Math.round(value * 60_000) : 0;
}

/**
 * @param {Object} options
 * @param {() => number} options.getTimeoutMs idle period in ms; 0 = the lock is off (read on every check)
 * @param {() => void} options.onIdle called once when the idle period elapsed; the timer then stops until activity()/start()
 * @param {() => boolean} [options.isBusy] true while a save request is in flight: the lock is postponed
 * @param {() => number} [options.now] wall clock in ms
 * @param {(fn: Function, ms: number) => any} [options.setTimer]
 * @param {(id: any) => void} [options.clearTimer]
 */
export function createIdleLockTimer({
    getTimeoutMs,
    onIdle,
    isBusy = () => false,
    now = () => Date.now(),
    setTimer = (fn, ms) => globalThis.setTimeout(fn, ms),
    clearTimer = (id) => globalThis.clearTimeout(id),
}) {
    let running = false;
    let lastActivity = 0;
    let timerId = null;

    const disarm = () => {
        if (timerId !== null) clearTimer(timerId);
        timerId = null;
    };

    const arm = () => {
        disarm();
        if (!running) return;
        const timeout = getTimeoutMs();
        if (!(timeout > 0)) return;
        const remaining = Math.max(lastActivity + timeout - now(), 0);
        timerId = setTimer(check, Math.min(remaining, MAX_TIMER_MS));
    };

    function check() {
        timerId = null;
        if (!running) return;
        const timeout = getTimeoutMs();
        if (!(timeout > 0)) return;

        const current = now();
        // The system clock moved backwards: count from now instead of waiting for the old deadline.
        if (current < lastActivity) lastActivity = current;

        if (current - lastActivity < timeout) {
            arm();
            return;
        }
        if (isBusy()) {
            timerId = setTimer(check, BUSY_RETRY_MS);
            return;
        }
        onIdle();
    }

    return {
        /** Begin counting from now. */
        start() {
            running = true;
            lastActivity = now();
            arm();
        },

        stop() {
            running = false;
            disarm();
        },

        /** Re-read the timeout (the setting changed) without resetting the idle period. */
        refresh() {
            arm();
        },

        /**
         * Record user activity. `at` lets another browser tab report its own activity time.
         * Throttled: the armed timer re-checks the stored timestamp when it fires, so nothing is rescheduled here.
         */
        activity(at = now()) {
            if (!running) return;
            if (at > lastActivity && at - lastActivity >= ACTIVITY_THROTTLE_MS) lastActivity = at;
            if (timerId === null) arm();
        },

        /** Page visibility: pause while hidden, and compare timestamps the moment it is visible again. */
        visibilityChanged(visible) {
            if (!running) return;
            if (!visible) {
                disarm();
                return;
            }
            disarm();
            check();
        },

        get isRunning() {
            return running;
        },

        get lastActivityAt() {
            return lastActivity;
        },
    };
}
