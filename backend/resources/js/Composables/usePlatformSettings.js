import { ref, computed, onMounted, onBeforeUnmount } from 'vue';
import { onBeforeRouteLeave } from 'vue-router';
import centralApi from '../Services/centralApi';
import { useCentralAuthStore } from '../stores/centralAuth';
import { CENTRAL_PERMISSIONS } from '../helpers/centralPermissions';
import { confirmDelete, confirmDialog, notifyError, notifySuccess, notifyWarning } from '../helpers/alert';
import { useTrans } from './useTrans';

/**
 * BRND-2 platform settings page (super-admin console), Api\Central\PlatformSettingsController:
 *   GET/PUT     /super-admin/platform-settings               (PUT is partial: absent = unchanged, null = default)
 *   POST/DELETE /super-admin/platform-settings/assets/{slot} (multipart `file`, saved immediately)
 * Step-up (fresh 2FA) is handled by centralApi; this composable only reacts to the outcome.
 */

const ENDPOINT = '/super-admin/platform-settings';

export const PLATFORM_ASSET_SLOTS = Object.freeze(['logo_light', 'logo_dark', 'favicon', 'app_icon']);

const TEXT_FIELDS = [
    'name',
    'short_name',
    'subtitle',
    'legal_name',
    'primary_color',
    'support_email',
    'support_phone',
    'website_url',
];

/** Never blanked server-side: sent trimmed, never as null. */
const REQUIRED_FIELDS = ['name', 'short_name'];

function toForm(data = {}) {
    const form = {};
    TEXT_FIELDS.forEach((key) => {
        form[key] = data[key] ?? '';
    });
    form.powered_by_enabled = data.powered_by_enabled === true;
    return form;
}

function toAssets(data = {}) {
    return Object.fromEntries(
        PLATFORM_ASSET_SLOTS.map((slot) => [
            slot,
            { url: data[slot]?.url ?? null, custom: data[slot]?.custom === true },
        ])
    );
}

/** Only the changed keys; an emptied optional field is sent as null (back to the default). */
function changedPayload(form, baseline) {
    const payload = {};
    Object.keys(form).forEach((key) => {
        if (form[key] === baseline[key]) return;
        if (key === 'powered_by_enabled') {
            payload[key] = form[key];
            return;
        }
        const value = String(form[key] ?? '').trim();
        payload[key] = value === '' && !REQUIRED_FIELDS.includes(key) ? null : value;
    });
    return payload;
}

export function usePlatformSettings() {
    const { t } = useTrans();
    const centralAuth = useCentralAuthStore();

    const form = ref(toForm());
    const baseline = ref(toForm());
    const assets = ref(toAssets());
    const errors = ref({});
    const assetErrors = ref({});
    const busySlot = ref(null);
    const isLoading = ref(true);
    const isSaving = ref(false);
    const loadError = ref('');

    const canManage = computed(() => centralAuth.can(CENTRAL_PERMISSIONS.settingsManage));
    const isDirty = computed(() => JSON.stringify(form.value) !== JSON.stringify(baseline.value));

    const apply = (data) => {
        form.value = toForm(data);
        baseline.value = toForm(data);
        assets.value = toAssets(data?.assets);
    };

    const fetchSettings = async () => {
        isLoading.value = true;
        loadError.value = '';
        try {
            const response = await centralApi.get(ENDPOINT);
            apply(response.data?.data);
        } catch (error) {
            loadError.value = error.userMessage || t('super.platform_settings.load_failed');
        } finally {
            isLoading.value = false;
        }
    };

    const setField = (key, value) => {
        form.value = { ...form.value, [key]: value };
        if (errors.value[key]) {
            const next = { ...errors.value };
            delete next[key];
            errors.value = next;
        }
    };

    const discard = () => {
        form.value = { ...baseline.value };
        errors.value = {};
    };

    const reportFailure = (error, fallbackKey) => {
        if (error.stepUpCancelled) {
            notifyWarning(error.userMessage);
            return;
        }
        // A plain 403 is already toasted by the centralApi interceptor.
        if (error.response?.status === 403 && !error.response?.data?.error_code) return;
        notifyError(t(fallbackKey), error.userMessage || '');
    };

    const save = async () => {
        if (!canManage.value || !isDirty.value || isSaving.value) return;
        isSaving.value = true;
        errors.value = {};
        try {
            const response = await centralApi.put(ENDPOINT, changedPayload(form.value, baseline.value));
            apply(response.data?.data);
            notifySuccess(response.data?.message || t('super.platform_settings_saved_success'));
        } catch (error) {
            if (error.response?.status === 422) {
                errors.value = error.response.data?.errors || {};
                notifyError(t('super.platform_settings.fix_errors'), error.userMessage || '');
            } else {
                reportFailure(error, 'super.platform_settings.save_failed');
            }
        } finally {
            isSaving.value = false;
        }
    };

    const setAssetError = (slot, message) => {
        assetErrors.value = { ...assetErrors.value, [slot]: message };
    };

    const uploadAsset = async (slot, file) => {
        if (!canManage.value || !file || busySlot.value) return;
        busySlot.value = slot;
        setAssetError(slot, '');
        try {
            const body = new FormData();
            body.append('file', file);
            const response = await centralApi.post(`${ENDPOINT}/assets/${slot}`, body, {
                headers: { 'Content-Type': 'multipart/form-data' },
            });
            assets.value = toAssets(response.data?.data?.assets);
            notifySuccess(response.data?.message || t('super.platform_branding.asset_uploaded'));
        } catch (error) {
            if (error.response?.status === 422) {
                setAssetError(slot, error.response.data?.errors?.file?.[0] || error.userMessage);
            } else {
                reportFailure(error, 'super.platform_settings.assets.upload_failed');
            }
        } finally {
            busySlot.value = null;
        }
    };

    const removeAsset = async (slot) => {
        if (!canManage.value || busySlot.value) return;
        const confirmed = await confirmDelete(
            t(`super.platform_settings.assets.${slot}`),
            t('super.platform_settings.assets.remove_confirm_text')
        );
        if (!confirmed) return;
        busySlot.value = slot;
        setAssetError(slot, '');
        try {
            const response = await centralApi.delete(`${ENDPOINT}/assets/${slot}`);
            assets.value = toAssets(response.data?.data?.assets);
            notifySuccess(response.data?.message || t('super.platform_branding.asset_deleted'));
        } catch (error) {
            reportFailure(error, 'super.platform_settings.assets.remove_failed');
        } finally {
            busySlot.value = null;
        }
    };

    onBeforeRouteLeave(async () => {
        if (!isDirty.value) return true;
        return confirmDialog({
            title: t('super.platform_settings.leave_title'),
            text: t('super.platform_settings.leave_text'),
            confirmButtonText: t('super.platform_settings.leave_confirm'),
            cancelButtonText: t('super.platform_settings.leave_cancel'),
            isDanger: true,
        });
    });

    const onBeforeUnload = (event) => {
        if (!isDirty.value) return;
        event.preventDefault();
        event.returnValue = '';
    };

    onMounted(() => {
        window.addEventListener('beforeunload', onBeforeUnload);
        fetchSettings();
    });

    onBeforeUnmount(() => window.removeEventListener('beforeunload', onBeforeUnload));

    return {
        form,
        assets,
        errors,
        assetErrors,
        busySlot,
        isLoading,
        isSaving,
        loadError,
        canManage,
        isDirty,
        fetchSettings,
        setField,
        discard,
        save,
        uploadAsset,
        removeAsset,
    };
}
