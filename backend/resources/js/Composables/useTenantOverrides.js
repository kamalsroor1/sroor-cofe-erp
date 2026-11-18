import { computed, ref } from 'vue';
import centralApi from '../Services/centralApi';
import { useCentralAuthStore } from '../stores/centralAuth';
import { CENTRAL_PERMISSIONS } from '../helpers/centralPermissions';
import { confirmDialog, notifyError, notifySuccess, notifyWarning } from '../helpers/alert';
import { useTrans } from './useTrans';
import { toTenantDateString } from './useBusinessDate';

/**
 * PLAN-12: per-shop limit overrides and feature grant / revoke in the console.
 *   GET    /super-admin/tenants/{id}/overrides                          -> data { limits[], features[] }
 *   PUT    /super-admin/tenants/{id}/limit-overrides/{resource}         { value|null, reason, expires_at|null }
 *   DELETE /super-admin/tenants/{id}/limit-overrides/{resource}
 *   PUT    /super-admin/tenants/{id}/feature-overrides/{feature}        { mode: grant|revoke, reason, expires_at|null }
 *   DELETE /super-admin/tenants/{id}/feature-overrides/{feature}
 * Writes need super_admin.tenants.manage plus a fresh 2FA proof; centralApi opens the step-up prompt and retries.
 * A null plan_value / effective means unlimited. The server owns every rule; the UI only mirrors it.
 */

export const FEATURE_MODES = Object.freeze({ grant: 'grant', revoke: 'revoke' });

const emptyForm = () => ({ value: '', unlimited: false, mode: FEATURE_MODES.grant, reason: '', expires_at: '' });

export function useTenantOverrides(tenantId) {
    const { t } = useTrans();
    const centralAuth = useCentralAuthStore();

    const limits = ref([]);
    const features = ref([]);
    const isLoading = ref(true);
    const loadError = ref('');

    const canView = computed(() => centralAuth.can(CENTRAL_PERMISSIONS.tenantsView));
    const canManage = computed(() => centralAuth.can(CENTRAL_PERMISSIONS.tenantsManage));

    const fetchOverrides = async () => {
        if (!canView.value) return;
        isLoading.value = true;
        loadError.value = '';
        try {
            const response = await centralApi.get(`/super-admin/tenants/${tenantId}/overrides`);
            limits.value = response.data?.data?.limits || [];
            features.value = response.data?.data?.features || [];
        } catch (error) {
            loadError.value = error.userMessage || t('plan_overrides.ui.load_failed');
        } finally {
            isLoading.value = false;
        }
    };

    /** Features grouped by their module, in the order the server sent them. */
    const featureGroups = computed(() => {
        const groups = new Map();
        for (const feature of features.value) {
            const key = feature.module || '';
            if (!groups.has(key)) groups.set(key, []);
            groups.get(key).push(feature);
        }
        return [...groups.entries()].map(([module, items]) => ({ module, items }));
    });

    // ----- edit dialog (limit or feature) ---------------------------------------------------
    const dialog = ref(null); // { kind: 'limit'|'feature', target, mode? }
    const form = ref(emptyForm());
    const errors = ref({});
    const isSubmitting = ref(false);
    const removingKey = ref('');

    /** First date a new override may expire on: tomorrow (the server demands a future date). */
    const minExpiry = computed(() => {
        const next = new Date();
        next.setDate(next.getDate() + 1);
        return toTenantDateString(next);
    });

    const openLimit = (row) => {
        const current = row.override;
        form.value = {
            ...emptyForm(),
            value: current && current.value !== null ? String(current.value) : '',
            unlimited: !!current && current.value === null,
            reason: current?.reason || '',
            expires_at: current?.is_expired ? '' : current?.expires_at?.slice(0, 10) || '',
        };
        errors.value = {};
        dialog.value = { kind: 'limit', target: row };
    };

    const openFeature = (feature, mode) => {
        form.value = { ...emptyForm(), mode };
        errors.value = {};
        dialog.value = { kind: 'feature', target: feature, mode };
    };

    const closeDialog = () => {
        if (isSubmitting.value) return;
        dialog.value = null;
    };

    const setField = (key, value) => {
        form.value = { ...form.value, [key]: value };
        if (errors.value[key]) {
            const next = { ...errors.value };
            delete next[key];
            errors.value = next;
        }
    };

    const buildLimitPayload = () => ({
        // The API needs `value` on every request: an explicit null is "unlimited".
        value: form.value.unlimited || String(form.value.value).trim() === '' ? null : Number(form.value.value),
        reason: form.value.reason.trim(),
        expires_at: form.value.expires_at || null,
    });

    const buildFeaturePayload = () => ({
        mode: dialog.value.mode,
        reason: form.value.reason.trim(),
        expires_at: form.value.expires_at || null,
    });

    const reportFailure = (error, titleKey) => {
        if (error.stepUpCancelled) {
            notifyWarning(error.userMessage);
        } else if (error.response?.status === 403) {
            // centralApi already showed the permission warning.
        } else {
            notifyError(t(titleKey), error.userMessage || '');
        }
    };

    const submit = async () => {
        if (isSubmitting.value || !dialog.value) return;
        const { kind, target } = dialog.value;
        isSubmitting.value = true;
        errors.value = {};
        try {
            const url =
                kind === 'limit'
                    ? `/super-admin/tenants/${tenantId}/limit-overrides/${target.resource}`
                    : `/super-admin/tenants/${tenantId}/feature-overrides/${target.key}`;
            const response = await centralApi.put(url, kind === 'limit' ? buildLimitPayload() : buildFeaturePayload());
            notifySuccess(response.data?.message || '');
            dialog.value = null;
            await fetchOverrides();
        } catch (error) {
            if (error.response?.status === 422 && error.response.data?.errors) {
                errors.value = error.response.data.errors;
            } else {
                reportFailure(error, 'plan_overrides.ui.save_failed');
            }
        } finally {
            isSubmitting.value = false;
        }
    };

    const remove = async (kind, target) => {
        const key = kind === 'limit' ? target.resource : target.key;
        if (removingKey.value) return;
        const confirmed = await confirmDialog({
            title: t('plan_overrides.ui.remove_confirm_title'),
            text: t('plan_overrides.ui.remove_confirm_text'),
            confirmButtonText: t('plan_overrides.ui.remove_confirm_button'),
            cancelButtonText: t('common.cancel'),
            icon: 'warning',
            isDanger: true,
        });
        if (!confirmed) return;

        removingKey.value = `${kind}:${key}`;
        try {
            const segment = kind === 'limit' ? 'limit-overrides' : 'feature-overrides';
            const response = await centralApi.delete(`/super-admin/tenants/${tenantId}/${segment}/${key}`);
            notifySuccess(response.data?.message || '');
            await fetchOverrides();
        } catch (error) {
            reportFailure(error, 'plan_overrides.ui.remove_failed');
        } finally {
            removingKey.value = '';
        }
    };

    return {
        limits,
        features,
        featureGroups,
        isLoading,
        loadError,
        canView,
        canManage,
        dialog,
        form,
        errors,
        isSubmitting,
        removingKey,
        minExpiry,
        fetchOverrides,
        openLimit,
        openFeature,
        closeDialog,
        setField,
        submit,
        removeLimit: (row) => remove('limit', row),
        removeFeature: (feature) => remove('feature', feature),
    };
}
