import { ref, onBeforeUnmount } from 'vue';
import api from '../Services/api';
import { retryAfterSeconds } from '../helpers/signupHttp';

export const PROVISIONING_PHASES = Object.freeze({
    working: 'working',
    ready: 'ready',
    queued: 'queued',
    delayed: 'delayed',
    failed: 'failed',
});

const FIRST_DELAY_MS = 3000;
const MAX_DELAY_MS = 10000;
const BACKOFF = 1.3;
const MAX_DURATION_MS = 3 * 60 * 1000;
const MAX_CONSECUTIVE_ERRORS = 5;

/**
 * "Preparing your shop": polls GET /signup/status (token in the X-Signup-Token header) every ~3s,
 * slowing down to 10s, for at most 3 minutes. It stops on ready / queued / failed, and always when
 * the component unmounts.
 *
 * @param {() => string} getToken
 * @param {() => void} onFinished called once the flow has an outcome (the token is no longer needed)
 */
export function useSignupProvisioning(getToken, onFinished = () => {}) {
    const phase = ref(PROVISIONING_PHASES.working);
    const status = ref('');
    const loginUrl = ref('');
    const businessName = ref('');
    const emailMasked = ref('');

    let timer = null;
    let startedAt = 0;
    let delay = FIRST_DELAY_MS;
    let errorsInARow = 0;
    let stopped = true;

    const clearTimer = () => {
        if (timer) clearTimeout(timer);
        timer = null;
    };

    const finish = (next) => {
        phase.value = next;
        stop();
        onFinished();
    };

    const schedule = (ms) => {
        if (stopped) return;
        if (Date.now() - startedAt + ms > MAX_DURATION_MS) {
            phase.value = PROVISIONING_PHASES.delayed;
            stop();
            return;
        }
        timer = setTimeout(poll, ms);
    };

    const apply = (state) => {
        status.value = state.status || '';
        businessName.value = state.business_name || '';
        emailMasked.value = state.email_masked || emailMasked.value;
        if (state.ready && /^https?:\/\//.test(state.login_url || '')) {
            loginUrl.value = state.login_url;
            finish(PROVISIONING_PHASES.ready);
        } else if (status.value === 'pending') {
            // Daily cap reached: a person releases it from the console and the account-ready email follows.
            finish(PROVISIONING_PHASES.queued);
        } else if (status.value === 'failed' || status.value === 'rejected') {
            finish(PROVISIONING_PHASES.failed);
        } else {
            const wait = delay;
            delay = Math.min(Math.round(delay * BACKOFF), MAX_DELAY_MS);
            schedule(wait);
        }
    };

    async function poll() {
        if (stopped) return;
        try {
            const { data } = await api.get('/signup/status', { headers: { 'X-Signup-Token': getToken() } });
            if (stopped) return;
            errorsInARow = 0;
            apply(data?.data || {});
        } catch (e) {
            if (stopped) return;
            if (e.response?.status === 422) {
                finish(PROVISIONING_PHASES.failed);
                return;
            }
            errorsInARow += 1;
            if (errorsInARow >= MAX_CONSECUTIVE_ERRORS) {
                phase.value = PROVISIONING_PHASES.delayed;
                stop();
                return;
            }
            const wait = retryAfterSeconds(e) * 1000;
            schedule(Math.max(wait, delay));
        }
    }

    function stop() {
        stopped = true;
        clearTimer();
    }

    const start = () => {
        stop();
        stopped = false;
        phase.value = PROVISIONING_PHASES.working;
        startedAt = Date.now();
        delay = FIRST_DELAY_MS;
        errorsInARow = 0;
        poll();
    };

    onBeforeUnmount(stop);

    return { phase, status, loginUrl, businessName, emailMasked, start, stop };
}
