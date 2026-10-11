import { ref } from 'vue';
import api from '../Services/centralApi';
import { useTrans } from './useTrans';
import { notifyError, notifySuccess } from '../helpers/alert';
import {
    buildTenantStatusPayload,
    emptyTenantStatusForm,
    mapTenantStatusErrors,
    validateTenantStatusForm,
} from '../helpers/tenantStatusForm';

/**
 * State + submit of the super-admin "change tenant status" modal, shared by the tenants
 * list and the tenant page. Field errors (client checks and the server's 409/422 messages)
 * land in `statusErrors`; anything not tied to a field becomes an error toast.
 */
export function useTenantStatusForm() {
    const { t } = useTrans();

    const statusForm = ref(emptyTenantStatusForm());
    const statusErrors = ref({});
    const isSubmittingStatus = ref(false);

    const resetStatusForm = (currentStatus = null) => {
        statusForm.value = emptyTenantStatusForm(currentStatus);
        statusErrors.value = {};
    };

    const updateStatusField = (field, value) => {
        statusForm.value[field] = value;
        if (field === 'status') {
            statusErrors.value = {};
            return;
        }
        if (statusErrors.value[field]) {
            const { [field]: _removed, ...rest } = statusErrors.value;
            statusErrors.value = rest;
        }
    };

    /** @returns {Promise<object|null>} the updated tenant summary, or null when refused */
    const submitStatus = async (tenantId) => {
        if (!tenantId || isSubmittingStatus.value) return null;

        const clientErrors = validateTenantStatusForm(statusForm.value);
        if (Object.keys(clientErrors).length) {
            statusErrors.value = Object.fromEntries(
                Object.entries(clientErrors).map(([field, key]) => [field, t(key)])
            );
            return null;
        }

        statusErrors.value = {};
        isSubmittingStatus.value = true;
        try {
            const res = await api.post(
                `/super-admin/tenants/${tenantId}/toggle-status`,
                buildTenantStatusPayload(statusForm.value)
            );
            notifySuccess(t('common.success'), res.data?.message || t('super.status_updated_msg'));
            return res.data?.data || { id: tenantId, status: statusForm.value.status };
        } catch (e) {
            const fieldErrors = mapTenantStatusErrors(e);
            if (Object.keys(fieldErrors).length) {
                statusErrors.value = fieldErrors;
            } else {
                notifyError(t('common.error'), e?.userMessage || t('super.status_update_failed'));
            }
            return null;
        } finally {
            isSubmittingStatus.value = false;
        }
    };

    return {
        statusForm,
        statusErrors,
        isSubmittingStatus,
        resetStatusForm,
        updateStatusField,
        submitStatus,
    };
}
