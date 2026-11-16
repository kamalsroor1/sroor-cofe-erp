import { ref, computed } from 'vue';
import api from '../Services/centralApi';
import { useTrans } from './useTrans';
import { notifySuccess, notifyError, notifyWarning } from '../helpers/alert';
import {
    clearPromotion,
    buildPlanForm,
    buildPlanPayload,
    tabOfErrorKey,
    PLAN_EDITOR_TABS,
} from '../helpers/planEditor';

/**
 * Create / edit state of the super-admin plan editor.
 * POST|PUT /super-admin/plans: step-up (recent 2FA) is handled by the centralApi interceptor.
 */
const PROMO_ERROR_KEYS = ['compare_at_price_monthly', 'compare_at_price_yearly', 'promo_starts_at', 'promo_ends_at'];

export function usePlanEditor({ registry, catalog, taxRate, onSaved }) {
    const { t } = useTrans();

    const show = ref(false);
    const editingPlan = ref(null);
    const form = ref(buildPlanForm(null, [], []));
    const errors = ref({});
    const activeTab = ref(PLAN_EDITOR_TABS[0]);
    const isSubmitting = ref(false);

    const isEditing = computed(() => editingPlan.value !== null);

    const tabsWithErrors = computed(() => {
        const tabs = new Set();
        for (const key of Object.keys(errors.value)) tabs.add(tabOfErrorKey(key));
        return tabs;
    });

    const open = (plan = null) => {
        editingPlan.value = plan;
        form.value = buildPlanForm(plan, registry.value, catalog.value);
        errors.value = {};
        activeTab.value = PLAN_EDITOR_TABS[0];
        show.value = true;
    };

    const close = () => {
        if (isSubmitting.value) return;
        show.value = false;
    };

    const clearError = (...keys) => {
        if (!keys.some((k) => errors.value[k])) return;
        const rest = { ...errors.value };
        for (const k of keys) delete rest[k];
        errors.value = rest;
    };

    const setField = (field, value) => {
        form.value[field] = value;
        clearError(field);
    };

    const setTab = (tab) => {
        activeTab.value = tab;
    };

    const setFeature = (key, value) => {
        form.value.features[key] = value;
        clearError('features');
    };

    const setAddon = (index, field, value) => {
        form.value.addons[index][field] = value;
        clearError(`addons.${index}.${field}`);
    };

    const clearOffer = () => {
        clearPromotion(form.value);
        clearError(...PROMO_ERROR_KEYS);
    };

    const showErrors = (found) => {
        errors.value = found;
        const first = PLAN_EDITOR_TABS.find((tab) => Object.keys(found).some((k) => tabOfErrorKey(k) === tab));
        if (first) activeTab.value = first;
    };

    const submit = async () => {
        const { payload, errors: clientErrors } = buildPlanPayload(form.value);
        if (Object.keys(clientErrors).length) {
            showErrors(clientErrors);
            return;
        }

        isSubmitting.value = true;
        try {
            const res = isEditing.value
                ? await api.put(`/super-admin/plans/${editingPlan.value.id}`, payload)
                : await api.post('/super-admin/plans', payload);

            notifySuccess(t('common.success'), res.data?.message || '');
            const ignored = res.data?.ignored_features || [];
            if (ignored.length) {
                notifyWarning(t('super.plan_ignored_features_title'), ignored.join(', '));
            }
            show.value = false;
            onSaved?.();
        } catch (e) {
            if (e.response?.status === 422) {
                showErrors(e.response.data?.errors || {});
            } else if (!e.stepUpCancelled) {
                notifyError(t('common.error'), e.response?.data?.message || e.userMessage || '');
            }
        } finally {
            isSubmitting.value = false;
        }
    };

    return {
        show,
        editingPlan,
        taxRate,
        form,
        errors,
        activeTab,
        isSubmitting,
        isEditing,
        tabsWithErrors,
        open,
        close,
        clearError,
        setField,
        setTab,
        setFeature,
        setAddon,
        clearOffer,
        submit,
    };
}
