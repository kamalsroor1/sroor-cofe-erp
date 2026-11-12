import { computed, onBeforeUnmount, onMounted, watch } from 'vue';
import { useRoute } from 'vue-router';
import { useAuthStore } from '../stores/auth';
import { useAppConfigStore } from '../stores/appConfig';
import { useScreenLockStore } from '../stores/screenLock';
import { createIdleLockTimer, idleTimeoutMs } from '../helpers/idleLock';
import { hasPendingWrites } from '../helpers/pendingWrites';
import { isCentralAppContext } from '../Services/centralApi';
import { ACTIVITY_KEY, readSharedActivity, writeSharedActivity } from '../helpers/screenLockStorage';

/** One listener per event type, passive, on the window. */
const ACTIVITY_EVENTS = ['pointerdown', 'pointermove', 'keydown', 'touchstart', 'wheel'];

/** How often this tab tells the other tabs "the user is active" (they share one idle clock). */
const SHARE_ACTIVITY_EVERY_MS = 5000;

/**
 * SET-02: lock the screen after `pos_idle_lock_minutes` (from /system/context) without pointer,
 * keyboard or touch activity. Mount once, in App.vue. The lock is shown by ScreenLockOverlay; this
 * composable only decides WHEN, and keeps the tabs of one browser in step.
 *
 * It never locks on the sign-in / password-change / public / print / platform-console screens, and
 * the signed-out state clears everything.
 */
export function useIdleLock() {
    const route = useRoute();
    const authStore = useAuthStore();
    const appConfigStore = useAppConfigStore();
    const screenLock = useScreenLockStore();

    const isPrintRoute = computed(() => {
        const meta = route.meta || {};
        return !!meta.isPrintView || (route.path || '').includes('/print');
    });

    // Screens with no signed-in workspace to protect: sign-in, change-password, public pages, the platform console.
    const isOpenRoute = computed(() => {
        const meta = route.meta || {};
        const path = route.path || '';
        return !!(
            meta.guestOnly ||
            meta.passwordChange ||
            meta.isSuperAdmin ||
            path.startsWith('/super-admin') ||
            (meta.layout === 'blank' && !isPrintRoute.value)
        );
    });

    const hasWorkspaceSession = computed(
        () => !isCentralAppContext() && authStore.isAuthenticated && !authStore.mustChangePassword
    );

    // The idle clock only starts where someone is working. Print views are exempt here: nobody sits idle on one.
    const isEligible = computed(() => hasWorkspaceSession.value && !isOpenRoute.value && !isPrintRoute.value);

    // Once locked, the lock covers every signed-in route, print views included (a typed /invoices/1/print URL must not bypass it).
    const showLock = computed(() => screenLock.isLocked && hasWorkspaceSession.value && !isOpenRoute.value);

    const timeoutMs = computed(() => idleTimeoutMs(appConfigStore.system?.pos_idle_lock_minutes));

    let lastShared = 0;

    const timer = createIdleLockTimer({
        getTimeoutMs: () => timeoutMs.value,
        isBusy: hasPendingWrites,
        onIdle: () => {
            timer.stop();
            screenLock.lock(authStore.user?.id);
        },
    });

    const onActivity = () => {
        const now = Date.now();
        timer.activity(now);
        if (now - lastShared >= SHARE_ACTIVITY_EVERY_MS) {
            lastShared = now;
            writeSharedActivity(now);
        }
    };

    const onVisibility = () => timer.visibilityChanged(document.visibilityState !== 'hidden');

    const onStorage = (event) => {
        if (event.storageArea && event.storageArea !== globalThis.localStorage) return;
        if (event.key === ACTIVITY_KEY) {
            timer.activity(readSharedActivity());
            return;
        }
        screenLock.applyExternalChange(event.key, event.newValue);
    };

    const sync = () => {
        const shouldCount = isEligible.value && timeoutMs.value > 0 && !screenLock.isLocked;
        if (!shouldCount) {
            timer.stop();
            return;
        }
        if (timer.isRunning) {
            timer.refresh();
            return;
        }
        timer.start();
        // A page opened in a second tab inherits the idle time of the first one.
        const shared = readSharedActivity();
        if (shared) timer.activity(shared);
    };

    watch([isEligible, timeoutMs, () => screenLock.isLocked], sync);

    // Signed out (or a different person signed in): the old lock must not follow them.
    watch(
        () => authStore.user?.id ?? null,
        (userId) => {
            if (userId === null) screenLock.reset();
            else screenLock.discardIfNotOwnedBy(userId);
        }
    );

    // After an unlock the idle period starts again from that moment.
    watch(
        () => screenLock.isLocked,
        (locked) => {
            if (!locked && timer.isRunning) onActivity();
        }
    );

    onMounted(() => {
        if (authStore.user?.id) screenLock.discardIfNotOwnedBy(authStore.user.id);
        else if (!authStore.isAuthenticated) screenLock.reset();

        ACTIVITY_EVENTS.forEach((name) => window.addEventListener(name, onActivity, { passive: true }));
        window.addEventListener('scroll', onActivity, { passive: true, capture: true });
        document.addEventListener('visibilitychange', onVisibility);
        window.addEventListener('storage', onStorage);
        sync();
    });

    onBeforeUnmount(() => {
        timer.stop();
        ACTIVITY_EVENTS.forEach((name) => window.removeEventListener(name, onActivity));
        window.removeEventListener('scroll', onActivity, true);
        document.removeEventListener('visibilitychange', onVisibility);
        window.removeEventListener('storage', onStorage);
    });

    return { showLock };
}
