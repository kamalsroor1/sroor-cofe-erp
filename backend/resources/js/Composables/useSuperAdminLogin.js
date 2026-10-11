import { ref, computed } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { useCentralAuthStore, IDLE_TIMEOUT_MINUTES } from '../stores/centralAuth';
import { useTrans } from './useTrans';

/**
 * Step machine of the platform console sign-in (IDEN-1.9):
 * credentials -> challenge (2FA code / recovery code)
 *             -> setup (QR + confirm) -> recovery (codes shown once) -> console
 *             -> reset_required (migrated account: email a reset link)
 */
export function safeCentralRedirect(value) {
    return typeof value === 'string' && value.startsWith('/super-admin/') && !value.startsWith('//')
        ? value
        : '/super-admin/dashboard';
}

export function useSuperAdminLogin() {
    const { t } = useTrans();
    const route = useRoute();
    const router = useRouter();
    const centralAuth = useCentralAuthStore();

    const step = ref('credentials');
    const email = ref('');
    const isSubmitting = ref(false);
    const error = ref('');
    const sentMessage = ref('');

    const setup = ref(null);
    const isSetupLoading = ref(false);
    const setupLoadError = ref('');
    const recoveryCodes = ref([]);

    const notice = computed(() => {
        if (centralAuth.notice === 'idle') {
            return t('super.central_auth.notice_idle', { minutes: IDLE_TIMEOUT_MINUTES });
        }
        if (centralAuth.notice === 'expired') return t('super.central_auth.notice_expired');
        return '';
    });

    const titles = {
        credentials: ['super.central_auth.login_title', 'super.central_auth.login_subtitle'],
        challenge: ['super.central_auth.challenge_title', 'super.central_auth.challenge_subtitle'],
        challenge_recovery: ['super.central_auth.challenge_title', 'super.central_auth.recovery_subtitle'],
        setup: ['super.central_auth.setup_title', 'super.central_auth.setup_subtitle'],
        recovery: ['super.central_auth.recovery_title', 'super.central_auth.recovery_subtitle'],
        reset_required: ['super.central_auth.reset_required_title', ''],
    };
    const challengeMode = ref('code');
    const titleKeys = computed(() =>
        step.value === 'challenge' && challengeMode.value === 'recovery'
            ? titles.challenge_recovery
            : titles[step.value]
    );
    const title = computed(() => t(titleKeys.value[0]));
    const subtitle = computed(() => (titleKeys.value[1] ? t(titleKeys.value[1]) : ''));

    const run = async (task) => {
        isSubmitting.value = true;
        error.value = '';
        try {
            return await task();
        } catch (e) {
            error.value = e.userMessage || t('common.unexpected_error');
            return null;
        } finally {
            isSubmitting.value = false;
        }
    };

    const enterConsole = () => router.replace(safeCentralRedirect(route.query.redirect));

    const loadSetup = async () => {
        isSetupLoading.value = true;
        setupLoadError.value = '';
        try {
            setup.value = await centralAuth.startTwoFactorSetup();
        } catch (e) {
            setup.value = null;
            if (e.response?.status === 401) return restart(t('super.central_auth.setup_session_expired'));
            setupLoadError.value = e.userMessage || '';
        } finally {
            isSetupLoading.value = false;
        }
    };

    const goTo = async (outcome) => {
        if (!outcome) return;
        if (outcome === 'authenticated') return enterConsole();
        step.value = outcome === 'password_reset_required' ? 'reset_required' : outcome;
        if (outcome === 'setup') await loadSetup();
    };

    const submitCredentials = async (credentials) => {
        email.value = credentials.email;
        const outcome = await run(() => centralAuth.login(credentials));
        await goTo(outcome);
    };

    const submitChallenge = async (payload) => {
        const outcome = await run(() => centralAuth.completeChallenge(payload));
        await goTo(outcome);
    };

    const confirmSetup = async (code) => {
        const codes = await run(() => centralAuth.confirmTwoFactorSetup(code));
        if (codes === null) {
            if (!centralAuth.setupUser && !centralAuth.isAuthenticated) {
                restart(t('super.central_auth.setup_session_expired'));
            }
            return;
        }
        recoveryCodes.value = codes;
        if (codes.length) step.value = 'recovery';
        else enterConsole();
    };

    const finishRecovery = () => {
        centralAuth.acknowledgeRecoveryCodes();
        recoveryCodes.value = [];
        enterConsole();
    };

    const setChallengeMode = (mode) => {
        challengeMode.value = mode;
        error.value = '';
    };

    const sendResetLink = async () => {
        const message = await run(() => centralAuth.forgotPassword(email.value));
        if (message !== null) sentMessage.value = message;
    };

    function restart(message = '') {
        centralAuth.clearSession();
        step.value = 'credentials';
        setup.value = null;
        challengeMode.value = 'code';
        sentMessage.value = '';
        error.value = message;
    }

    return {
        step,
        email,
        title,
        subtitle,
        notice,
        isSubmitting,
        error,
        sentMessage,
        setup,
        isSetupLoading,
        setupLoadError,
        recoveryCodes,
        setChallengeMode,
        submitCredentials,
        submitChallenge,
        loadSetup,
        confirmSetup,
        finishRecovery,
        sendResetLink,
        restart,
    };
}
