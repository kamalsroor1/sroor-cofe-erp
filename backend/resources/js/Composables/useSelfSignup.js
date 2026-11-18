import { ref, computed, watch } from 'vue';
import { useRoute } from 'vue-router';
import api from '../Services/api';
import { useTrans } from './useTrans';
import { usePublicPlans } from './usePublicPlans';
import { useCountdown } from './useCountdown';
import { firstError, retryAfterSeconds } from '../helpers/signupHttp';
import { sanitizeSubdomain, suggestSubdomain } from '../helpers/subdomainSuggestion';

export const SIGNUP_BUSINESS_TYPES = Object.freeze(['retail', 'wholesale', 'weighted', 'other']);
export const SIGNUP_STEPS = Object.freeze({
    resuming: 'resuming',
    form: 'form',
    resumeFailed: 'resume_failed',
    verify: 'verify',
    provisioning: 'provisioning',
});

// The signup token lives only in this tab's sessionStorage for the life of the flow, never in a URL.
const TOKEN_KEY = 'self_signup_token';
const readToken = () => sessionStorage.getItem(TOKEN_KEY) || '';

const emptyForm = () => ({
    business_name: '',
    owner_name: '',
    email: '',
    phone: '',
    password: '',
    password_confirmation: '',
    business_type: '',
    city: '',
    subdomain: '',
    plan_code: '',
    terms_accepted: false,
    website: '', // honeypot: real users never see it, so it must stay empty
});

/**
 * Public self-signup flow (POST /signup, /signup/verify, /signup/resend, GET /signup/status):
 * form -> emailed code -> provisioning. Refreshing the page resumes from the server-side state.
 */
export function useSelfSignup() {
    const { t } = useTrans();
    const route = useRoute();
    const {
        plans,
        brand,
        founder,
        currency,
        taxRate,
        load: loadPlans,
        hasError: plansFailed,
        isLoading: plansLoading,
    } = usePublicPlans();
    const resendTimer = useCountdown();

    const step = ref(SIGNUP_STEPS.form);
    const token = ref(readToken());
    const emailMasked = ref('');
    const form = ref(emptyForm());
    const errors = ref({});
    const formError = ref('');
    const isSubmitting = ref(false);
    const codeError = ref('');
    const refusedMessage = ref('');
    const resumeError = ref('');
    const isVerifying = ref(false);
    const isResending = ref(false);
    const subdomainEdited = ref(false);

    const businessTypeOptions = computed(() =>
        SIGNUP_BUSINESS_TYPES.map((value) => ({ value, label: t(`signup.business_types.${value}`) }))
    );
    const planOptions = computed(() => plans.value.map((plan) => ({ value: plan.code, label: plan.name })));
    const selectedPlan = computed(() => plans.value.find((plan) => plan.code === form.value.plan_code) || null);

    // ?plan=<code> wins when the public catalog offers it, otherwise the free plan is the default.
    const preselectPlan = () => {
        if (form.value.plan_code) return;
        const wanted = String(route.query.plan || '');
        const chosen =
            plans.value.find((plan) => plan.code === wanted) || plans.value.find((plan) => plan.is_free) || null;
        if (chosen) form.value.plan_code = chosen.code;
    };
    watch(plans, preselectPlan);

    // The shop address follows the shop name until the user edits it by hand.
    watch(
        () => form.value.business_name,
        (name) => {
            if (!subdomainEdited.value) form.value.subdomain = suggestSubdomain(name);
        }
    );
    const onSubdomainInput = (value) => {
        subdomainEdited.value = true;
        form.value.subdomain = sanitizeSubdomain(value);
    };

    const waitLabel = (seconds) =>
        seconds >= 60
            ? t('self_signup.ui.time.minutes', { count: Math.ceil(seconds / 60) })
            : t('self_signup.ui.time.seconds', { count: Math.max(1, seconds) });
    const throttledMessage = (error) => {
        const seconds = retryAfterSeconds(error);
        return seconds
            ? t('self_signup.ui.errors.throttled', { wait: waitLabel(seconds) })
            : t('self_signup.ui.errors.failed');
    };
    const transportMessage = (error) => {
        const status = error.response?.status;
        if (status === 429) return throttledMessage(error);
        if (status === 503) return t('self_signup.ui.errors.disabled');
        return error.userMessage || t('self_signup.ui.errors.failed');
    };

    const rememberToken = (value) => {
        token.value = value;
        sessionStorage.setItem(TOKEN_KEY, value);
    };
    const clearToken = () => {
        token.value = '';
        sessionStorage.removeItem(TOKEN_KEY);
    };

    const buildPayload = () => {
        const payload = { locale: localStorage.getItem('app_locale') === 'en' ? 'en' : 'ar' };
        Object.entries(form.value).forEach(([key, value]) => {
            const text =
                typeof value === 'string' && key !== 'password' && key !== 'password_confirmation'
                    ? value.trim()
                    : value;
            if (['website', 'terms_accepted', 'password', 'password_confirmation'].includes(key) || text !== '') {
                payload[key] = text;
            }
        });
        return payload;
    };

    const submit = async () => {
        if (isSubmitting.value) return;
        isSubmitting.value = true;
        errors.value = {};
        formError.value = '';
        try {
            const { data } = await api.post('/signup', buildPayload());
            const started = data?.data || {};
            rememberToken(started.signup_token);
            emailMasked.value = started.email_masked || '';
            form.value.password = '';
            form.value.password_confirmation = '';
            resendTimer.start(started.resend_available_in);
            codeError.value = '';
            step.value = SIGNUP_STEPS.verify;
        } catch (e) {
            if (e.response?.status === 422) errors.value = e.response.data?.errors || {};
            else formError.value = transportMessage(e);
        } finally {
            isSubmitting.value = false;
        }
    };

    const startOver = () => {
        clearToken();
        resendTimer.stop();
        errors.value = {};
        codeError.value = '';
        refusedMessage.value = '';
        step.value = SIGNUP_STEPS.form;
    };

    const verify = async (code) => {
        if (isVerifying.value) return;
        if (!/^\d{6}$/.test(code)) {
            codeError.value = t('self_signup.ui.errors.code_required');
            return;
        }
        isVerifying.value = true;
        codeError.value = '';
        try {
            await api.post('/signup/verify', { token: token.value, code });
            resendTimer.stop();
            step.value = SIGNUP_STEPS.provisioning;
        } catch (e) {
            const bag = e.response?.data?.errors;
            if (e.response?.status === 422 && bag?.token) {
                startOver();
                formError.value = t('self_signup.ui.errors.session_ended');
            } else if (e.response?.status === 422 && bag?.email) {
                // The email is proven but already owns a shop: the answer is mailed, nothing more to do here.
                clearToken();
                refusedMessage.value = firstError(bag, 'email');
                step.value = SIGNUP_STEPS.provisioning;
            } else if (e.response?.status === 422) {
                codeError.value = firstError(bag, 'code') || t('self_signup.ui.errors.failed');
            } else {
                codeError.value = transportMessage(e);
            }
        } finally {
            isVerifying.value = false;
        }
    };

    const resend = async () => {
        if (isResending.value || resendTimer.isRunning.value) return;
        isResending.value = true;
        codeError.value = '';
        try {
            const { data } = await api.post('/signup/resend', { token: token.value });
            resendTimer.start(data?.data?.resend_available_in ?? 60);
        } catch (e) {
            const status = e.response?.status;
            if (status === 429) resendTimer.start(retryAfterSeconds(e));
            if (status === 409) step.value = SIGNUP_STEPS.provisioning;
            else if (status === 422 && e.response.data?.errors?.token) {
                startOver();
                formError.value = t('self_signup.ui.errors.session_ended');
            } else codeError.value = transportMessage(e);
        } finally {
            isResending.value = false;
        }
    };

    // Page refresh: ask the server where this signup stands instead of trusting the browser.
    const resume = async () => {
        if (!token.value) return;
        resumeError.value = '';
        step.value = SIGNUP_STEPS.resuming;
        try {
            const { data } = await api.get('/signup/status', { headers: { 'X-Signup-Token': token.value } });
            const state = data?.data || {};
            emailMasked.value = state.email_masked || '';
            step.value = state.status === 'awaiting_verification' ? SIGNUP_STEPS.verify : SIGNUP_STEPS.provisioning;
        } catch (e) {
            if (e.response?.status === 422) {
                // The server no longer knows this signup (expired / unknown): only now is the token useless.
                clearToken();
                step.value = SIGNUP_STEPS.form;
            } else {
                // Network error, 429 or 5xx: the signup is probably fine, so keep the token and offer a retry.
                resumeError.value = transportMessage(e);
                step.value = SIGNUP_STEPS.resumeFailed;
            }
        }
    };

    const init = async () => {
        await Promise.all([loadPlans(), resume()]);
        preselectPlan();
    };

    return {
        step,
        token,
        emailMasked,
        form,
        errors,
        formError,
        isSubmitting,
        codeError,
        refusedMessage,
        resumeError,
        isVerifying,
        isResending,
        resendLabel: resendTimer.label,
        canResend: computed(() => !resendTimer.isRunning.value),
        businessTypeOptions,
        planOptions,
        plansFailed,
        plansLoading,
        selectedPlan,
        founder,
        currency,
        taxRate,
        brand,
        init,
        resume,
        submit,
        verify,
        resend,
        startOver,
        clearToken,
        onSubdomainInput,
    };
}
