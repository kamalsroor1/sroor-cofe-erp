import { computed, onBeforeUnmount, ref } from 'vue';
import api from '../Services/api';
import { useTrans } from './useTrans';
import { notifyError, notifySuccess } from '../helpers/alert';
import { businessToday } from './useBusinessDate';

const POLL_MS = 4000;
const ACTIVE = ['pending', 'processing'];
const API_PREFIX = '/api/v1';

/**
 * PRD-17: "Export my data" (Settings, admin only).
 * Loads the latest export, asks for a new one, polls while it is being prepared, and downloads
 * the ZIP through the short-lived signed link the API returns (relative, so it goes through
 * the shared api client and keeps the Bearer token / X-Tenant headers).
 */
export function useDataExport() {
    const { t } = useTrans();

    const current = ref(null);
    const isLoading = ref(true);
    const isRequesting = ref(false);
    const isDownloading = ref(false);
    let timer = null;

    const status = computed(() => current.value?.status ?? null);
    const isActive = computed(() => ACTIVE.includes(status.value));
    const canDownload = computed(() => Boolean(current.value?.is_downloadable && current.value?.download_url));

    const stopPolling = () => {
        if (timer) {
            clearTimeout(timer);
            timer = null;
        }
    };

    const schedulePoll = () => {
        stopPolling();
        if (isActive.value) {
            timer = setTimeout(() => load(true), POLL_MS);
        }
    };

    const load = async (silent = false) => {
        if (!silent) isLoading.value = true;
        try {
            const { data } = await api.get('/settings/data-export');
            current.value = data?.data ?? null;
        } catch {
            // api.js shows 401/403 dialogs; a silent poll failure just stops polling.
            if (!silent) current.value = null;
        } finally {
            isLoading.value = false;
            schedulePoll();
        }
    };

    const requestExport = async () => {
        if (isRequesting.value || isActive.value) return;
        isRequesting.value = true;
        try {
            const { data } = await api.post('/settings/data-export');
            current.value = data?.data ?? null;
            notifySuccess(data?.message || t('settings.data_export.requested'));
        } catch (e) {
            const status = e?.response?.status;
            if (status === 422) {
                notifyError(e.response.data?.errors?.export?.[0] || t('settings.data_export.already_running'));
                await load(true);
            } else if (status !== 403 && status !== 423 && status !== 429) {
                notifyError(t('settings.data_export.request_failed'));
            }
        } finally {
            isRequesting.value = false;
            schedulePoll();
        }
    };

    const download = async () => {
        if (!canDownload.value || isDownloading.value) return;
        isDownloading.value = true;
        try {
            let url = current.value.download_url;
            if (url.startsWith(API_PREFIX)) url = url.slice(API_PREFIX.length);
            const res = await api.get(url, { responseType: 'blob', timeout: 0 });
            const href = URL.createObjectURL(res.data);
            const link = document.createElement('a');
            link.href = href;
            link.download = `shop-data-${businessToday()}.zip`;
            document.body.appendChild(link);
            link.click();
            link.remove();
            setTimeout(() => URL.revokeObjectURL(href), 1000);
        } catch {
            notifyError(t('settings.data_export.download_failed'));
            await load(true);
        } finally {
            isDownloading.value = false;
        }
    };

    onBeforeUnmount(stopPolling);

    return {
        current,
        status,
        isLoading,
        isRequesting,
        isDownloading,
        isActive,
        canDownload,
        load,
        requestExport,
        download,
    };
}

export default useDataExport;
