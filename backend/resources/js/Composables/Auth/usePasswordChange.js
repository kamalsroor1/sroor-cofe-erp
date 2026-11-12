import { computed, onBeforeUnmount, onMounted, reactive, ref } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { useAuthStore } from '../../stores/auth';
import { useAppConfigStore } from '../../stores/appConfig';
import { useTrans } from '../useTrans';
import {
    PASSWORD_MIN_LENGTH,
    isThrottled,
    passwordChangeFieldErrors,
    safeInternalRedirect,
} from '../../helpers/passwordChange';

const emptyFields = () => ({ current_password: '', password: '', password_confirmation: '' });

/**
 * SELL-01 forced change screen: form state, client-side checks, the request and the way out.
 * Passwords live only in this component's reactive form and are wiped on success and on unmount.
 */
export function usePasswordChange() {
    const { t } = useTrans();
    const router = useRouter();
    const route = useRoute();
    const authStore = useAuthStore();
    const appConfigStore = useAppConfigStore();

    const form = reactive(emptyFields());
    const errors = reactive(emptyFields());
    const formError = ref('');
    const isSubmitting = ref(false);
    const isVerifying = ref(true);

    const destination = computed(() => safeInternalRedirect(route.query.redirect) || '/');

    const clearErrors = () => {
        Object.assign(errors, emptyFields());
        formError.value = '';
    };

    const wipe = () => Object.assign(form, emptyFields());

    // A bad answer here must not trap the user, so a failure keeps whatever the store already knows.
    const verifyFlag = async () => {
        try {
            await authStore.fetchMe();
            if (!authStore.mustChangePassword) await router.replace(destination.value);
        } catch {
            // 401 is handled by the axios layer; anything else leaves the form usable.
        } finally {
            isVerifying.value = false;
        }
    };

    const validate = () => {
        clearErrors();
        if (!form.current_password) errors.current_password = t('auth.change_password.current_required');
        if (form.password.length < PASSWORD_MIN_LENGTH) errors.password = t('auth.change_password.too_short');
        else if (form.password === form.current_password) errors.password = t('auth.change_password.must_differ');
        if (form.password_confirmation !== form.password) {
            errors.password_confirmation = t('auth.change_password.mismatch');
        }
        return !errors.current_password && !errors.password && !errors.password_confirmation;
    };

    const submit = async () => {
        if (isSubmitting.value || !validate()) return;
        isSubmitting.value = true;
        try {
            await authStore.changePassword({ ...form });
            wipe();
            // The context loaded while flagged lacks the business extras: load it again on the way in.
            appConfigStore.isLoaded = false;
            await router.replace(destination.value);
        } catch (error) {
            if (isThrottled(error)) {
                formError.value = t('auth.change_password.throttled');
            } else if (error.response?.status === 422) {
                Object.assign(errors, passwordChangeFieldErrors(error));
                if (!errors.current_password && !errors.password && !errors.password_confirmation) {
                    formError.value = error.userMessage || t('common.unexpected_error');
                }
            } else if (!error.passwordChangeRequired) {
                formError.value = error.userMessage || t('common.unexpected_error');
            }
        } finally {
            isSubmitting.value = false;
        }
    };

    const logout = async () => {
        wipe();
        await authStore.logout();
        appConfigStore.isLoaded = false;
        await router.replace({ name: 'login' });
    };

    onMounted(verifyFlag);
    onBeforeUnmount(wipe);

    return { form, errors, formError, isSubmitting, isVerifying, submit, logout };
}
