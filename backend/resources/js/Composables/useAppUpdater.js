import { ref } from 'vue';
import api from '../services/api';
import Swal from 'sweetalert2';
import { trans } from '../helpers/trans';

const hasUpdate = ref(false);
const isForceUpdate = ref(false);
const updateData = ref(null);
const isChecking = ref(false);
const showUpdateModal = ref(false);

// App current version
const CURRENT_VERSION_NAME = '1.0.0';
const CURRENT_VERSION_CODE = 1;

export function useAppUpdater() {
    const checkForUpdates = async (manual = false) => {
        if (isChecking.value) return;
        isChecking.value = true;

        try {
            const res = await api.get('/app/check-version', {
                params: {
                    platform: 'android',
                    version_name: CURRENT_VERSION_NAME,
                    version_code: CURRENT_VERSION_CODE,
                },
            });

            const data = res.data;
            if (data?.has_update) {
                hasUpdate.value = true;
                isForceUpdate.value = !!data.force_update;
                updateData.value = data;
                showUpdateModal.value = true;
            } else if (manual) {
                Swal.fire({
                    icon: 'success',
                    title: trans('settings.app_up_to_date'),
                    text: `${trans('settings.you_are_using_latest_version')} (v${CURRENT_VERSION_NAME})`,
                    confirmButtonText: trans('common.ok'),
                    confirmButtonColor: '#10b981',
                    background: document.documentElement.classList.contains('dark') ? '#0f172a' : '#ffffff',
                    color: document.documentElement.classList.contains('dark') ? '#f8fafc' : '#0f172a',
                });
            }
        } catch (e) {
            if (manual) {
                console.error('Failed to check for app updates:', e);
            }
        } finally {
            isChecking.value = false;
        }
    };

    const downloadAndInstall = () => {
        const url = updateData.value?.download_url || '/app.apk';
        window.open(url, '_system');
    };

    const dismissUpdate = () => {
        if (!isForceUpdate.value) {
            showUpdateModal.value = false;
        }
    };

    return {
        hasUpdate,
        isForceUpdate,
        updateData,
        isChecking,
        showUpdateModal,
        currentVersion: CURRENT_VERSION_NAME,
        checkForUpdates,
        downloadAndInstall,
        dismissUpdate,
    };
}
