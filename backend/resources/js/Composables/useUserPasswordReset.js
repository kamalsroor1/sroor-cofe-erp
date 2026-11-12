import { computed, onBeforeUnmount, ref } from 'vue';
import api from '../Services/api';
import { useAuthStore } from '../stores/auth';
import { confirmDialog, notifyError } from '../helpers/alert';
import { useTrans } from './useTrans';

/**
 * SELL-01: an admin resets another user's forgotten password. The server answers with a temporary
 * password exactly once; it is held in this ref only while the one-time dialog is open (never in a
 * store, never in storage) and dropped on close / unmount.
 */
export function useUserPasswordReset() {
    const { t } = useTrans();
    const authStore = useAuthStore();

    const revealed = ref(null); // { userId, name, password }
    const resettingId = ref(null);

    const canResetPasswords = computed(() => authStore.hasPermission('roles.manage'));

    // Hidden for yourself (use the profile screen) and for admins unless you are one (server enforces both).
    const canResetFor = (user) =>
        canResetPasswords.value &&
        !!user &&
        user.id !== authStore.user?.id &&
        (user.primary_role !== 'admin' || authStore.isAdmin);

    const requestReset = async (user) => {
        if (!canResetFor(user) || resettingId.value) return;

        const confirmed = await confirmDialog({
            title: t('users.reset_password.confirm_title', { name: user.name }),
            text: t('users.reset_password.confirm_text'),
            confirmButtonText: t('users.reset_password.confirm_button'),
            cancelButtonText: t('common.cancel'),
            icon: 'warning',
            isDanger: true,
        });
        if (!confirmed) return;

        resettingId.value = user.id;
        try {
            const response = await api.post(`/users/${user.id}/reset-password`);
            const password = response.data?.data?.temporary_password;
            if (!password) throw new Error(t('users.reset_password.failed'));
            revealed.value = { userId: user.id, name: user.name, password };
        } catch (error) {
            // 403 already got the permission alert from the axios layer.
            if (error.response?.status !== 403) {
                notifyError(t('users.reset_password.failed'), error.userMessage || '');
            }
        } finally {
            resettingId.value = null;
        }
    };

    const closeReveal = () => {
        revealed.value = null;
    };

    onBeforeUnmount(closeReveal);

    return { revealed, resettingId, canResetFor, requestReset, closeReveal };
}
