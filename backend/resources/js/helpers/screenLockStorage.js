// SET-02: what survives a page reload for the idle lock. Only a "locked since" marker and the last
// activity time (so several tabs of one session share one idle clock). No secret is ever stored here.
// Every access is wrapped: private mode / disabled storage must not break the app.

export const LOCK_KEY = 'screen_lock_state';
export const ACTIVITY_KEY = 'screen_lock_activity';

function storage() {
    try {
        return globalThis.localStorage ?? null;
    } catch {
        return null;
    }
}

/** Parse a raw marker; null when it is missing or malformed. */
export function parseLockMarker(raw) {
    if (!raw) return null;
    try {
        const parsed = JSON.parse(raw);
        const since = Number(parsed?.since);
        if (!Number.isFinite(since) || since <= 0) return null;
        return { since, userId: parsed.userId ?? null };
    } catch {
        return null;
    }
}

export function readLockMarker() {
    try {
        return parseLockMarker(storage()?.getItem(LOCK_KEY));
    } catch {
        return null;
    }
}

export function writeLockMarker(marker) {
    try {
        storage()?.setItem(LOCK_KEY, JSON.stringify({ since: marker.since, userId: marker.userId ?? null }));
    } catch {
        // storage unavailable: the lock still works for this page, it just will not survive a reload
    }
}

export function clearLockMarker() {
    try {
        storage()?.removeItem(LOCK_KEY);
    } catch {
        // nothing to clear
    }
}

export function readSharedActivity() {
    try {
        const value = Number(storage()?.getItem(ACTIVITY_KEY));
        return Number.isFinite(value) && value > 0 ? value : 0;
    } catch {
        return 0;
    }
}

export function writeSharedActivity(at) {
    try {
        storage()?.setItem(ACTIVITY_KEY, String(at));
    } catch {
        // see above
    }
}

export function clearSharedActivity() {
    try {
        storage()?.removeItem(ACTIVITY_KEY);
    } catch {
        // see above
    }
}
