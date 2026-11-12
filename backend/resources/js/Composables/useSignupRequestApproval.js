import { onBeforeUnmount, ref, watch } from 'vue';
import api from '../Services/centralApi';
import { useTrans } from './useTrans';
import { notifySuccess, notifyWarning } from '../helpers/alert';
import { useWhatsAppCredentials } from './useWhatsAppCredentials';

const PASSWORD_LETTERS = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnpqrstuvwxyz';
const PASSWORD_DIGITS = '23456789';
const PASSWORD_ALPHABET = PASSWORD_LETTERS + PASSWORD_DIGITS;
const PASSWORD_LENGTH = 14;

/** Unbiased random index in [0, max) via rejection sampling. */
function randomIndex(max) {
    const limit = Math.floor(0x100000000 / max) * max;
    const buf = new Uint32Array(1);
    do {
        crypto.getRandomValues(buf);
    } while (buf[0] >= limit);
    return buf[0] % max;
}

/**
 * Cryptographically random owner password (no look-alike characters). Always holds at least
 * one letter and one digit so it passes the backend rule (min 10, letters, numbers).
 */
export function generateOwnerPassword() {
    const chars = [
        PASSWORD_LETTERS[randomIndex(PASSWORD_LETTERS.length)],
        PASSWORD_DIGITS[randomIndex(PASSWORD_DIGITS.length)],
    ];
    while (chars.length < PASSWORD_LENGTH) {
        chars.push(PASSWORD_ALPHABET[randomIndex(PASSWORD_ALPHABET.length)]);
    }
    for (let i = chars.length - 1; i > 0; i--) {
        const j = randomIndex(i + 1);
        [chars[i], chars[j]] = [chars[j], chars[i]];
    }
    return chars.join('');
}

/**
 * Approve flow of one signup request: prefilled modal form, plan list, step-up aware POST.
 * @param {() => void} onChanged called after the list must be refreshed (approved or already handled)
 */
export function useSignupRequestApproval(onChanged) {
    const { t } = useTrans();

    const request = ref(null);
    const plans = ref([]);
    // generate_password: the server creates the owner's temporary password (SELL-01 default).
    const form = ref({ subdomain: '', plan_code: '', owner_email: '', owner_password: '', generate_password: true });
    // Owner sign-in details returned once by the approval: { login, password, phone }.
    const credentials = ref(null);
    const whatsapp = useWhatsAppCredentials();
    const errors = ref({});
    const isSubmitting = ref(false);
    const isPreparing = ref(false);

    const loadPlans = async () => {
        if (plans.value.length) return;
        try {
            const res = await api.get('/super-admin/plans');
            const list = res.data?.data?.plans || res.data?.plans || [];
            plans.value = list
                .filter((plan) => plan.is_active !== false)
                .map((p) => ({ value: p.slug, label: p.name }));
        } catch {
            plans.value = [];
        }
    };

    const open = (signup) => {
        request.value = signup;
        errors.value = {};
        isPreparing.value = false;
        credentials.value = null;
        form.value = {
            subdomain: signup.preferred_subdomain || '',
            plan_code: signup.plan_code || '',
            owner_email: signup.email || '',
            owner_password: '',
            generate_password: true,
        };
        loadPlans();
    };

    // Typing the password yourself starts from a strong suggestion; going back to automatic drops it.
    watch(
        () => form.value.generate_password,
        (automatic) => {
            if (automatic) form.value.owner_password = '';
            else if (!form.value.owner_password) form.value.owner_password = generateOwnerPassword();
        }
    );

    const close = () => {
        const wasPreparing = isPreparing.value;
        request.value = null;
        isPreparing.value = false;
        credentials.value = null;
        if (wasPreparing) onChanged();
    };

    const regenerate = () => {
        form.value.owner_password = generateOwnerPassword();
    };

    const copyPassword = async () => {
        try {
            await navigator.clipboard.writeText(form.value.owner_password);
            notifySuccess(t('signup.console.copied'));
        } catch {
            notifyWarning(t('signup.console.action_failed'));
        }
    };

    const submit = async () => {
        if (!request.value || isSubmitting.value) return;
        isSubmitting.value = true;
        errors.value = {};
        try {
            const { generate_password: automatic, ...payload } = form.value;
            if (!payload.plan_code) delete payload.plan_code;
            if (automatic) {
                delete payload.owner_password;
            } else if (!String(payload.owner_password || '').trim()) {
                errors.value = { owner_password: t('signup.console.password_manual_required') };
                return;
            }
            const response = await api.post(`/super-admin/signup-requests/${request.value.id}/approve`, payload);
            const issued = response.data?.owner_credentials;
            if (issued?.temporary_password) {
                credentials.value = {
                    login: issued.login || payload.owner_email,
                    password: issued.temporary_password,
                    phone: request.value.phone || '',
                };
                whatsapp.prepare();
            }
            isPreparing.value = true;
        } catch (e) {
            const status = e.response?.status;
            if (e.stepUpCancelled) {
                notifyWarning(t('signup.console.action_failed'), e.userMessage);
            } else if (status === 409) {
                notifyWarning(t('signup.console.already_handled'), e.userMessage);
                request.value = null;
                onChanged();
            } else if (status === 422) {
                errors.value = e.response.data?.errors || {};
            } else if (status !== 403) {
                notifyWarning(t('signup.console.action_failed'), e.userMessage);
            }
        } finally {
            isSubmitting.value = false;
        }
    };

    const sendWhatsApp = () => {
        if (!credentials.value) return;
        const { phone, login, password } = credentials.value;
        whatsapp.send({ phone, login, password });
    };

    onBeforeUnmount(() => {
        credentials.value = null;
    });

    return {
        credentials,
        canSendWhatsApp: whatsapp.isReady,
        sendWhatsApp,
        request,
        plans,
        form,
        errors,
        isSubmitting,
        isPreparing,
        open,
        close,
        regenerate,
        copyPassword,
        submit,
    };
}
