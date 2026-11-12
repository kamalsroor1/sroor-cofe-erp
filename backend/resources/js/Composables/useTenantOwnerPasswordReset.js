import { onBeforeUnmount, ref } from 'vue';
import api from '../Services/centralApi';
import { confirmDialog, notifyWarning } from '../helpers/alert';
import { useTrans } from './useTrans';
import { useWhatsAppCredentials } from './useWhatsAppCredentials';

/**
 * SELL-01 console action: reset the shop owner's forgotten password. The request needs a recent
 * second factor; centralApi opens the existing step-up prompt and retries the call by itself.
 * The temporary password is held here only while the one-time modal is open.
 *
 * @param {() => ({ id: string, name?: string } | null)} getTenant
 */
export function useTenantOwnerPasswordReset(getTenant) {
    const { t } = useTrans();
    const whatsapp = useWhatsAppCredentials();

    const revealed = ref(null); // { tenantName, owner, matchedBy, password }
    const isResetting = ref(false);

    const requestReset = async () => {
        const tenant = getTenant();
        if (!tenant || isResetting.value) return;

        const confirmed = await confirmDialog({
            title: t('super.owner_reset.confirm_title', { name: tenant.name || tenant.id }),
            text: t('super.owner_reset.confirm_text'),
            confirmButtonText: t('super.owner_reset.confirm_button'),
            cancelButtonText: t('common.cancel'),
            icon: 'warning',
            isDanger: true,
        });
        if (!confirmed) return;

        isResetting.value = true;
        try {
            const response = await api.post(`/super-admin/tenants/${tenant.id}/reset-owner-password`);
            const data = response.data?.data;
            if (!data?.temporary_password) throw new Error(t('super.owner_reset.failed'));
            revealed.value = {
                tenantName: tenant.name || tenant.id,
                owner: data.owner || {},
                matchedBy: data.matched_by || 'email',
                password: data.temporary_password,
            };
            whatsapp.prepare();
        } catch (error) {
            // A plain 403 already got the permission warning from centralApi.
            if (error.stepUpCancelled || error.response?.status !== 403) {
                notifyWarning(t('super.owner_reset.failed'), error.userMessage || '');
            }
        } finally {
            isResetting.value = false;
        }
    };

    const sendWhatsApp = () => {
        if (!revealed.value) return;
        const { owner, password } = revealed.value;
        whatsapp.send({ phone: owner.phone, login: owner.email || owner.phone || '', password });
    };

    const closeReveal = () => {
        revealed.value = null;
    };

    onBeforeUnmount(closeReveal);

    return {
        revealed,
        isResetting,
        canSendWhatsApp: whatsapp.isReady,
        requestReset,
        sendWhatsApp,
        closeReveal,
    };
}
