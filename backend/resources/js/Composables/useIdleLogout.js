import { watch } from 'vue';
import { useIdle, useIntervalFn } from '@vueuse/core';
import { useRouter } from 'vue-router';
import { useCentralAuthStore, IDLE_TIMEOUT_MINUTES } from '../stores/centralAuth';

const SESSION_CHECK_INTERVAL_MS = 30 * 1000;

/**
 * Platform console session limits (IDEN-1.9, CTO Q-B4):
 *  - idle logout after IDLE_TIMEOUT_MINUTES without pointer / keyboard / touch activity;
 *  - hard cap: the session ends at the earlier of the server token expiry and
 *    SESSION_MAX_MINUTES after sign-in, even for an active operator.
 * Mount once, in SuperAdminLayout.
 */
export function useIdleLogout() {
    const centralAuth = useCentralAuthStore();
    const router = useRouter();
    const { idle } = useIdle(IDLE_TIMEOUT_MINUTES * 60 * 1000);

    let isLeaving = false;

    const endSession = async (reason) => {
        if (isLeaving || !centralAuth.isAuthenticated) return;
        isLeaving = true;
        try {
            await centralAuth.logout(reason);
            await router.replace({ name: 'super_admin.login' });
        } finally {
            isLeaving = false;
        }
    };

    watch(idle, (isIdle) => {
        if (isIdle) endSession('idle');
    });

    useIntervalFn(() => {
        if (centralAuth.isAuthenticated && centralAuth.isSessionExpired()) endSession('expired');
    }, SESSION_CHECK_INTERVAL_MS);
}
