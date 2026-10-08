import { ref } from 'vue';
import api from '../../Services/api';
import { useAuthStore } from '../../stores/auth';
import { useTrans } from '../useTrans';

/**
 * Testing-only quick login (pick a user, no password).
 *
 * The option is offered only when the public GET /auth/options reports quick_login === true.
 * The user list is fetched lazily (only when the panel is opened) and is never persisted.
 */
export function useQuickLogin() {
    const authStore = useAuthStore();
    const { t } = useTrans();

    const showQuickLogin = ref(false);
    const users = ref([]);
    const usersLoaded = ref(false);
    const isLoadingUsers = ref(false);
    const usersError = ref('');
    const loggingInUserId = ref(null);

    const disable = () => {
        showQuickLogin.value = false;
        users.value = [];
        usersLoaded.value = false;
    };

    const messageFor = (error) => {
        const status = error?.response?.status;
        if (status === 429) return t('auth.too_many_requests');
        if (status === 404) return t('auth.quick_login_unavailable');
        return error?.userMessage || t('auth.failed');
    };

    const fetchOptions = async () => {
        try {
            const response = await api.get('/auth/options');
            showQuickLogin.value = response.data?.data?.quick_login === true;
        } catch {
            showQuickLogin.value = false;
        }
    };

    const loadUsers = async (force = false) => {
        if (!showQuickLogin.value || isLoadingUsers.value || (usersLoaded.value && !force)) return;

        isLoadingUsers.value = true;
        usersError.value = '';
        try {
            const response = await api.get('/auth/quick-login/users');
            users.value = Array.isArray(response.data?.data) ? response.data.data : [];
            usersLoaded.value = true;
        } catch (error) {
            if (error?.response?.status === 404) {
                disable();
            }
            usersError.value = messageFor(error);
        } finally {
            isLoadingUsers.value = false;
        }
    };

    /**
     * Resolves to { ok, message }. On 404 the option is hidden (feature switched off mid-session).
     */
    const quickLogin = async (userId, deviceName) => {
        if (loggingInUserId.value !== null) return { ok: false, message: '' };

        loggingInUserId.value = userId;
        try {
            await authStore.quickLogin(userId, deviceName);
            users.value = [];
            usersLoaded.value = false;
            return { ok: true, message: '' };
        } catch (error) {
            if (error?.response?.status === 404) {
                disable();
            }
            return { ok: false, message: messageFor(error) };
        } finally {
            loggingInUserId.value = null;
        }
    };

    return {
        showQuickLogin,
        users,
        isLoadingUsers,
        usersError,
        loggingInUserId,
        fetchOptions,
        loadUsers,
        quickLogin,
    };
}

export default useQuickLogin;
