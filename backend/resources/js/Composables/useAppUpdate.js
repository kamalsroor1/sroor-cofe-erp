import { ref, computed } from 'vue';
import { Capacitor } from '@capacitor/core';
import { App as CapacitorApp } from '@capacitor/app';
import api from '../services/api';
import Swal from 'sweetalert2';
import versionData from '../version.json';

// Global singleton state so any component can listen to or trigger updates
const currentVersionName = ref(versionData?.version || '1.0.108');
const currentVersionCode = ref(versionData?.build_number || 108);
const isChecking = ref(false);
const hasCheckedThisSession = ref(false);
const hasUpdate = ref(false);
const isForceUpdate = ref(false);
const latestVersionData = ref(null);
const isModalOpen = ref(false);
const isDownloading = ref(false);
const isDownloaded = ref(false);
const downloadProgress = ref(0);

// Detect if running inside actual Android/iOS native mobile shell
const isNativePlatform = () => {
    return typeof window !== 'undefined' && Capacitor.isNativePlatform();
};

// Detect if running inside Electron Desktop App
const isDesktopPlatform = () => {
    return typeof window !== 'undefined' && (!!window.electronAPI?.isElectron || window.navigator.userAgent.includes('Electron'));
};

// Determine active client platform
const getClientPlatform = () => {
    if (isDesktopPlatform()) return 'windows';
    if (isNativePlatform()) return 'android';
    return 'web';
};

// Initialize native app version from Capacitor or Electron runtime if available
const syncClientVersionInfo = async () => {
    if (isNativePlatform()) {
        try {
            const info = await CapacitorApp.getInfo();
            if (info) {
                if (info.version) currentVersionName.value = info.version;
                if (info.build) currentVersionCode.value = parseInt(info.build) || 1;
            }
        } catch (e) {
            // Error reading native build info
        }
    } else if (isDesktopPlatform()) {
        try {
            if (window.electronAPI?.getAppVersion) {
                const ver = await window.electronAPI.getAppVersion();
                if (ver) currentVersionName.value = ver;
            }
            if (versionData?.build_number) {
                currentVersionCode.value = versionData.build_number;
            }
            if (versionData?.version) {
                currentVersionName.value = versionData.version;
            }
        } catch (e) {
            // Fallback to versionData
        }
    }
};

// Initial sync
syncClientVersionInfo();

export function useAppUpdate() {
    const isEligible = computed(() => isNativePlatform() || isDesktopPlatform());
    const platform = computed(() => getClientPlatform());

    /**
     * Check for newer app release from the server
     */
    const checkForUpdates = async (isManual = false) => {
        const clientPlatform = getClientPlatform();

        // 🛑 Web Browser Check Gate: Web apps are updated automatically via cloud server deployments!
        if (clientPlatform === 'web') {
            if (isManual) {
                Swal.fire({
                    icon: 'info',
                    title: 'نسخة الويب السحابية 🌐',
                    text: `أنت تستخدم نسخة الويب المحدثة تلقائياً عبر السيرفر السحابي (v${currentVersionName.value}). تنزيل وتثبيت الحزم مخصص لتطبيق الموبايل (APK) وبرنامج سطح المكتب (EXE).`,
                    confirmButtonText: 'حسناً',
                    confirmButtonColor: '#f59e0b',
                });
            }
            return;
        }

        if (isChecking.value) return;
        if (!isManual && (hasCheckedThisSession.value || sessionStorage.getItem('app_update_dismissed') || localStorage.getItem('app_update_dismissed_code') === String(currentVersionCode.value))) {
            return;
        }

        isChecking.value = true;
        if (!isManual) {
            hasCheckedThisSession.value = true;
        }

        try {
            await syncClientVersionInfo();

            const res = await api.get('/app/check-update', {
                params: {
                    platform: clientPlatform,
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
                const platformLabel = clientPlatform === 'windows' ? 'برنامج سطح المكتب' : 'تطبيق الموبايل';
                Swal.fire({
                    icon: 'success',
                    title: `${platformLabel} محدث بالكامل 🚀`,
                    text: `أنت تستخدم أحدث إصدار متوفر حالياً (v${currentVersionName.value})`,
                    confirmButtonText: 'ممتاز',
                    confirmButtonColor: '#10b981',
                });
            }
        } catch (e) {
            console.error('Failed to check for updates:', e);
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
     * Start downloading and installing the package (APK for Android / EXE for Desktop)
     */
    const startDownloadAndInstall = async () => {
        if (isDownloading.value) return;
        isDownloading.value = true;
        isDownloaded.value = false;
        downloadProgress.value = 0;

        const clientPlatform = getClientPlatform();

        // Smooth simulated progress bar
        const interval = setInterval(() => {
            if (downloadProgress.value < 90) {
                downloadProgress.value += Math.floor(Math.random() * 14) + 6;
            }
        }, 150);

        try {
            const defaultDownloadUrl = `/api/v1/app/download-apk?platform=${clientPlatform}`;
            const downloadUrl = latestVersionData.value?.download_url || defaultDownloadUrl;

            // Mark update as processed in this session
            sessionStorage.setItem('app_update_dismissed', '1');
            if (latestVersionData.value?.latest_version_code) {
                localStorage.setItem('app_update_dismissed_code', String(latestVersionData.value.latest_version_code));
            }

            // Create download anchor and trigger
            const filename = clientPlatform === 'windows'
                ? `Sroor-ERP-POS-Setup-v${latestVersionData.value?.latest_version || 'latest'}.exe`
                : `sroor-coffee-erp-v${latestVersionData.value?.latest_version || 'latest'}.apk`;

            const link = document.createElement('a');
            link.href = downloadUrl;
            link.setAttribute('download', filename);
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);

            clearInterval(interval);
            downloadProgress.value = 100;

            setTimeout(() => {
                isDownloading.value = false;
                isDownloaded.value = true;

                // For desktop, show quick notice that setup is downloading
                if (clientPlatform === 'windows') {
                    Swal.fire({
                        icon: 'success',
                        title: 'جاري تنزيل ملف التثبيت 💻',
                        text: 'تم بدء تحميل ملف التثبيت المحدث. بمجرد اكتمال التنزيل، قم بفتحه لتحديث البرنامج تلقائياً.',
                        confirmButtonText: 'حسناً',
                        confirmButtonColor: '#10b981',
                    });
                } else {
                    // Auto-close modal after 2 seconds on mobile to let Android installer take over
                    setTimeout(() => {
                        isModalOpen.value = false;
                    }, 2000);
                }
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
        isNative: isNativePlatform(),
        isDesktop: isDesktopPlatform(),
        isEligible,
        platform,
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
