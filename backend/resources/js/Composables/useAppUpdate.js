import { ref } from 'vue';
import { App as CapacitorApp } from '@capacitor/app';
import api from '../services/api';
import Swal from 'sweetalert2';

// Global singleton state so any component can listen to or trigger updates
const currentVersionName = ref('1.0.0');
const currentVersionCode = ref(1);
const isChecking = ref(false);
const hasCheckedThisSession = ref(false);
const hasUpdate = ref(false);
const isForceUpdate = ref(false);
const latestVersionData = ref(null);
const isModalOpen = ref(false);
const isDownloading = ref(false);
const isDownloaded = ref(false);
const downloadProgress = ref(0);

// Initialize native app version from Capacitor runtime if available
const syncNativeVersionInfo = async () => {
    if (typeof window !== 'undefined') {
        try {
            const info = await CapacitorApp.getInfo();
            if (info) {
                if (info.version) currentVersionName.value = info.version;
                if (info.build) currentVersionCode.value = parseInt(info.build) || 1;
            }
        } catch (e) {
            // Not in native Capacitor runtime
        }
    }
};

// Initial sync
syncNativeVersionInfo();

export function useAppUpdate() {
    /**
     * Check for newer app release from the server
     */
    const checkForUpdates = async (isManual = false) => {
        if (isChecking.value) return;
        if (!isManual && (hasCheckedThisSession.value || sessionStorage.getItem('app_update_dismissed') || localStorage.getItem('app_update_dismissed_code') === String(currentVersionCode.value))) return;
        
        isChecking.value = true;
        if (!isManual) {
            hasCheckedThisSession.value = true;
        }

        try {
            await syncNativeVersionInfo();

            const res = await api.get('/app/check-update', {
                params: {
                    platform: 'android',
                    version_code: currentVersionCode.value,
                    version_name: currentVersionName.value,
                }
            });

            const data = res.data || {};
            const serverLatestCode = parseInt(data.latest_version_code) || 1;
            const requiresUpdate = !!data.has_update && serverLatestCode > currentVersionCode.value;

            hasUpdate.value = requiresUpdate;
            isForceUpdate.value = requiresUpdate && !!data.force_update;

            if (requiresUpdate && data.latest_version) {
                latestVersionData.value = data;
                isDownloaded.value = false;
                downloadProgress.value = 0;
                isModalOpen.value = true;
            } else if (isManual) {
                Swal.fire({
                    icon: 'success',
                    title: 'التطبيق محدث بالكامل 🚀',
                    text: `أنت تستخدم أحدث إصدار متوفر حالياً (v${currentVersionName.value})`,
                    confirmButtonText: 'ممتاز',
                    confirmButtonColor: '#f59e0b',
                });
            }
        } catch (e) {
            console.error('Failed to check for app updates:', e);
            if (isManual) {
                Swal.fire({
                    icon: 'error',
                    title: 'تعذر التحقق',
                    text: 'تعذر الاتصال بسيرفر التحديثات، يرجى التأكد من اتصالك بالإنترنت.',
                    confirmButtonText: 'حسناً',
                });
            }
        } finally {
            isChecking.value = false;
        }
    };

    /**
     * Start downloading and installing the APK
     */
    const startDownloadAndInstall = async () => {
        if (isDownloading.value) return;
        isDownloading.value = true;
        isDownloaded.value = false;
        downloadProgress.value = 0;

        // Smooth simulated progress bar
        const interval = setInterval(() => {
            if (downloadProgress.value < 90) {
                downloadProgress.value += Math.floor(Math.random() * 14) + 6;
            }
        }, 150);

        try {
            const downloadUrl = latestVersionData.value?.download_url || '/api/v1/app/download-latest-apk';

            // Mark update as processed in this session
            sessionStorage.setItem('app_update_dismissed', '1');
            if (latestVersionData.value?.latest_version_code) {
                localStorage.setItem('app_update_dismissed_code', String(latestVersionData.value.latest_version_code));
            }

            // Create download anchor and trigger
            const link = document.createElement('a');
            link.href = downloadUrl;
            link.setAttribute('download', `sroor-coffee-erp-v${latestVersionData.value?.latest_version || 'latest'}.apk`);
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);

            clearInterval(interval);
            downloadProgress.value = 100;

            setTimeout(() => {
                isDownloading.value = false;
                isDownloaded.value = true;
                // Auto-close modal after 2 seconds to let Android native installer dialog take over
                setTimeout(() => {
                    isModalOpen.value = false;
                }, 2000);
            }, 500);
        } catch (e) {
            clearInterval(interval);
            isDownloading.value = false;
            isDownloaded.value = false;
            Swal.fire({
                icon: 'error',
                title: 'فشل التحميل',
                text: 'حدث خطأ أثناء تحميل ملف التحديث، يرجى المحاولة مرة أخرى.',
            });
        }
    };

    const closeModal = () => {
        if (!isForceUpdate.value) {
            isModalOpen.value = false;
            isDownloaded.value = false;
            downloadProgress.value = 0;
            sessionStorage.setItem('app_update_dismissed', '1');
            if (latestVersionData.value?.latest_version_code) {
                localStorage.setItem('app_update_dismissed_code', String(latestVersionData.value.latest_version_code));
            }
        }
    };

    return {
        currentVersionName,
        currentVersionCode,
        isChecking,
        hasUpdate,
        isForceUpdate,
        latestVersionData,
        isModalOpen,
        isDownloading,
        isDownloaded,
        downloadProgress,
        checkForUpdates,
        startDownloadAndInstall,
        closeModal,
    };
}
