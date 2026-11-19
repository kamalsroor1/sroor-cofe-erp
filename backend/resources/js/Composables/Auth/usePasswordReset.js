import { computed, onBeforeUnmount, onMounted, reactive, ref } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import api from '../../Services/api';
import { useTrans } from '../useTrans';
import { PASSWORD_MIN_LENGTH, isThrottled } from '../../helpers/passwordChange';
import { isCentralHost } from '../../helpers/platformHosts';

const queryString = (value) => (typeof value === 'string' ? value : '');

/**
 * SIGN-15: on the platform host the shop comes from the connected workspace (X-Tenant). Without
 * one there is no shop to reset a password in, so the user picks the workspace first.
 */
function useWorkspaceGuard() {
    const router = useRouter();
    onMounted(() => {
        const connected = localStorage.getItem('active_tenant') || localStorage.getItem('tenant_id');
        if (isCentralHost(window.location.hostname) && !connected) router.replace({ name: 'workspace.connect' });
    });
}

/** SIGN-15: "forgot password" page: one email field, always the same generic answer. */
export function useForgotPassword() {
    const { t } = useTrans();
    const route = useRoute();
    useWorkspaceGuard();

    const email = ref(queryString(route.query.email));
    const isSubmitting = ref(false);
    const sentMessage = ref('');
    const error = ref('');

    const submit = async () => {
        if (isSubmitting.value || !email.value.trim()) return;
        isSubmitting.value = true;
        error.value = '';
        try {
            const response = await api.post('/auth/forgot-password', { email: email.value.trim() });
            sentMessage.value = response.data?.message || t('auth.password_reset.link_sent');
        } catch (e) {
            error.value = isThrottled(e)
                ? t('auth.password_reset.throttled')
                : e.userMessage || t('common.unexpected_error');
        } finally {
            isSubmitting.value = false;
        }
    };

    return { email, isSubmitting, sentMessage, error, submit };
}

const emptyFields = () => ({ password: '', password_confirmation: '' });

/**
 * SIGN-15: reset page opened from the emailed link (?token=…&email=…). The single-use token is
 * read once and removed from the address bar; passwords are wiped on success and on unmount.
 */
export function useResetPassword() {
    const { t } = useTrans();
    const route = useRoute();
    useWorkspaceGuard();

    const token = queryString(route.query.token);
    const email = queryString(route.query.email);
    const hasLink = computed(() => token !== '' && email !== '');

    const form = reactive(emptyFields());
    const errors = reactive(emptyFields());
    const formError = ref('');
    const doneMessage = ref('');
    const isSubmitting = ref(false);

    const wipe = () => Object.assign(form, emptyFields());

    // Not a router navigation on purpose: App.vue keys the page by fullPath and would remount it.
    onMounted(() => {
        if (hasLink.value) window.history.replaceState(window.history.state, '', route.path);
    });
    onBeforeUnmount(wipe);

    const validate = () => {
        Object.assign(errors, emptyFields());
        formError.value = '';
        if (form.password.length < PASSWORD_MIN_LENGTH) {
            errors.password = t('auth.password_reset.too_short', { min: PASSWORD_MIN_LENGTH });
        }
        if (form.password_confirmation !== form.password) {
            errors.password_confirmation = t('auth.password_reset.mismatch');
        }
        return !errors.password && !errors.password_confirmation;
    };

    const submit = async () => {
        if (isSubmitting.value || !validate()) return;
        isSubmitting.value = true;
        try {
            const response = await api.post('/auth/reset-password', { token, email, ...form });
            wipe();
            doneMessage.value = response.data?.message || t('auth.password_reset.success');
        } catch (e) {
            const fieldErrors = e.response?.data?.errors || {};
            const first = (value) => (Array.isArray(value) ? value[0] : value) || '';
            if (isThrottled(e)) {
                formError.value = t('auth.password_reset.throttled');
            } else if (fieldErrors.password || fieldErrors.password_confirmation) {
                errors.password = first(fieldErrors.password);
                errors.password_confirmation = first(fieldErrors.password_confirmation);
            } else {
                formError.value = e.userMessage || t('common.unexpected_error');
            }
        } finally {
            isSubmitting.value = false;
        }
    };

    return {
        email,
        hasLink,
        form,
        errors,
        formError,
        doneMessage,
        isSubmitting,
        minLength: PASSWORD_MIN_LENGTH,
        submit,
    };
}
