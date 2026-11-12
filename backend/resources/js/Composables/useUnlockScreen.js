import { computed, onBeforeUnmount, ref, watch } from 'vue';
import { useRouter } from 'vue-router';
import api from '../Services/api';
import { useAuthStore } from '../stores/auth';
import { useScreenLockStore } from '../stores/screenLock';
import { toWesternDigits } from '../helpers/digits';
import { isManagerPinLocked } from '../helpers/managerApprovalDialog';
import { useTrans } from './useTrans';

const PIN_PATTERN = /^\d{4,6}$/;
const MAX_TIMER_MS = 2_147_483_647;

/**
 * SET-02: unlock the idle lock with the signed-in user's OWN password or POS PIN, checked by
 * POST /auth/unlock-screen (rate limited on the server). The secret lives only in the input's ref
 * for the moment of the request; it is cleared right after and never logged or stored.
 *
 * Unlocking only flips the lock flag: nothing was unmounted, so the POS cart, open tabs and form
 * inputs are exactly as they were.
 */
export function useUnlockScreen() {
    const { t } = useTrans();
    const router = useRouter();
    const authStore = useAuthStore();
    const screenLock = useScreenLockStore();

    const hasPin = computed(() => authStore.user?.has_pos_pin === true);
    const mode = ref(hasPin.value ? 'pin' : 'password');
    const secret = ref('');
    const errorMessage = ref('');
    const isSubmitting = ref(false);
    const isBlocked = ref(false);
    // The PIN is locked for the day on the server: only the password works until it is changed or tomorrow.
    const isPinLocked = ref(false);
    const isConfirmingSwitch = ref(false);
    const isSwitching = ref(false);
    let blockTimer = null;

    watch(hasPin, (value) => {
        if (!value) mode.value = 'password';
    });

    const normalizedSecret = computed(() =>
        mode.value === 'pin' ? toWesternDigits(secret.value).trim() : secret.value
    );
    const canSubmit = computed(
        () => !isSubmitting.value && !isBlocked.value && normalizedSecret.value !== '' && !isSwitching.value
    );

    const setMode = (next) => {
        if (next === 'pin' && (!hasPin.value || isPinLocked.value)) return;
        mode.value = next;
        secret.value = '';
        errorMessage.value = '';
    };

    const blockFor = (seconds) => {
        isBlocked.value = true;
        window.clearTimeout(blockTimer);
        const wait = Number(seconds);
        if (Number.isFinite(wait) && wait > 0) {
            blockTimer = window.setTimeout(
                () => {
                    isBlocked.value = false;
                },
                Math.min(wait * 1000, MAX_TIMER_MS)
            );
        }
    };

    const leaveToLogin = async () => {
        authStore.clearSession();
        await router.replace({ name: 'login' });
    };

    const submit = async () => {
        if (!canSubmit.value) return false;
        errorMessage.value = '';

        if (mode.value === 'pin' && !PIN_PATTERN.test(normalizedSecret.value)) {
            errorMessage.value = t('screen_lock.pin_format');
            return false;
        }

        isSubmitting.value = true;
        try {
            await api.post(
                '/auth/unlock-screen',
                mode.value === 'pin' ? { pin: normalizedSecret.value } : { password: secret.value }
            );
            secret.value = '';
            screenLock.unlock();
            return true;
        } catch (error) {
            const status = error.response?.status;
            const data = error.response?.data;
            secret.value = '';
            if (status === 401) {
                await leaveToLogin();
            } else if (status === 422) {
                errorMessage.value =
                    data?.errors?.pin?.[0] ?? data?.errors?.password?.[0] ?? data?.message ?? t('screen_lock.failed');
            } else if (isManagerPinLocked(error)) {
                isPinLocked.value = true;
                mode.value = 'password';
                errorMessage.value = data?.message || t('screen_lock.pin_locked_today');
            } else if (status === 429) {
                errorMessage.value = data?.message || t('screen_lock.too_many_attempts');
                blockFor(error.response?.headers?.['retry-after']);
            } else {
                errorMessage.value = error.userMessage || t('screen_lock.failed');
            }
            return false;
        } finally {
            isSubmitting.value = false;
        }
    };

    const askSwitchUser = () => {
        isConfirmingSwitch.value = true;
    };

    const cancelSwitchUser = () => {
        isConfirmingSwitch.value = false;
    };

    /** "Switch user": end this session (the existing logout) and go to the sign-in screen. */
    const switchUser = async () => {
        isSwitching.value = true;
        try {
            await authStore.logout();
            screenLock.reset();
            await router.replace({ name: 'login' });
        } finally {
            isSwitching.value = false;
            isConfirmingSwitch.value = false;
        }
    };

    onBeforeUnmount(() => window.clearTimeout(blockTimer));

    return {
        hasPin,
        mode,
        secret,
        errorMessage,
        isSubmitting,
        isBlocked,
        isPinLocked,
        isConfirmingSwitch,
        isSwitching,
        canSubmit,
        setMode,
        submit,
        askSwitchUser,
        cancelSwitchUser,
        switchUser,
    };
}
