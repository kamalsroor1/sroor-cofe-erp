import { defineStore } from 'pinia';
import {
    LOCK_KEY,
    clearLockMarker,
    clearSharedActivity,
    parseLockMarker,
    readLockMarker,
    writeLockMarker,
} from '../helpers/screenLockStorage';

/**
 * SET-02: the idle lock screen's state. Global on purpose: the overlay, the shortcut guard and the
 * other browser tabs all read the same "locked since" marker. The marker (never a secret) lives in
 * localStorage, so a reload cannot bypass the lock and a second tab follows the first one.
 * The user's token stays valid while locked; the server only re-checks the password / PIN on unlock.
 */
export const useScreenLockStore = defineStore('screenLock', {
    state: () => {
        const marker = readLockMarker();
        return { lockedSince: marker?.since ?? null, lockedUserId: marker?.userId ?? null };
    },

    getters: {
        isLocked: (state) => state.lockedSince !== null,
    },

    actions: {
        lock(userId) {
            if (this.isLocked) return;
            this.lockedSince = Date.now();
            this.lockedUserId = userId ?? null;
            writeLockMarker({ since: this.lockedSince, userId: this.lockedUserId });
        },

        unlock() {
            this.lockedSince = null;
            this.lockedUserId = null;
            clearLockMarker();
        },

        /** Sign-out / user change: forget the lock and the shared idle clock completely. */
        reset() {
            this.unlock();
            clearSharedActivity();
        },

        /** A marker that belongs to somebody else's session must not lock this one. */
        discardIfNotOwnedBy(userId) {
            if (this.isLocked && this.lockedUserId !== null && String(this.lockedUserId) !== String(userId)) {
                this.unlock();
            }
        },

        /** Another tab locked / unlocked: follow it without writing back (that would echo forever). */
        applyExternalChange(key, newValue) {
            if (key !== LOCK_KEY) return;
            const marker = parseLockMarker(newValue);
            this.lockedSince = marker?.since ?? null;
            this.lockedUserId = marker?.userId ?? null;
        },
    },
});
