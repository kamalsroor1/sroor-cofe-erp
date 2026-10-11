import { computed, ref } from 'vue';
import { Capacitor, registerPlugin } from '@capacitor/core';
import { App as CapacitorApp } from '@capacitor/app';
import api from '../Services/api';
import { notifyError, notifySuccess, Toast } from '../helpers/alert';
import { trans } from '../helpers/trans';
import { normalizeDownloadRelease, semverCode, stageKey } from '../helpers/appUpdate';
import { createAppUpdater } from './appUpdate/createAppUpdater';
import versionData from '../version.json';

// Native Android plugin (android/app/src/main/java/.../AppUpdaterPlugin.java):
// streams the APK, verifies SHA-256 + package name + signing certificate, then opens the system installer.
const AppUpdaterPlugin = registerPlugin('AppUpdater');

/** Route name of the public manual download page (router/index.js). The update modal never covers it. */
export const APP_DOWNLOAD_ROUTE = 'app.download';

const isNativePlatform = () => typeof window !== 'undefined' && Capacitor.isNativePlatform();

const isDesktopPlatform = () =>
    typeof window !== 'undefined' &&
    (!!window.electronAPI?.isElectron || window.navigator.userAgent.includes('Electron'));

const getClientPlatform = () => {
    if (isDesktopPlatform()) return 'windows';
    if (isNativePlatform() && Capacitor.getPlatform() === 'android') return 'android';
    return 'web';
};

const getCurrentVersion = async () => {
    if (isNativePlatform()) {
        const info = await CapacitorApp.getInfo();
        return { name: info?.version, code: info?.build };
    }
    if (isDesktopPlatform() && window.electronAPI?.getAppVersion) {
        // Desktop releases are numbered by SemverCode(version), exactly like the server and the main process.
        const name = (await window.electronAPI.getAppVersion()) || versionData?.version;
        return { name, code: semverCode(name) };
    }
    return { name: versionData?.version, code: versionData?.build_number };
};

const notify = (type, key, params = {}) => {
    const message = trans(key, params);
    if (type === 'error') return notifyError(message);
    if (type === 'success') return notifySuccess(message);
    return Toast.fire({ icon: 'info', title: message });
};

const hasNativeUpdater = () => Capacitor.isPluginAvailable('AppUpdater');

const nativeBridge = {
    downloadAndInstall: (options) => AppUpdaterPlugin.downloadAndInstall(options),
    addProgressListener: async (callback) => {
        const handle = await AppUpdaterPlugin.addListener('downloadProgress', callback);
        return () => handle.remove();
    },
};

// Android only: the WebView navigates to the download page (system browser for another host). The desktop shell
// opens that page in the default browser from the main process itself.
const openManualDownload = (url) => {
    window.location.assign(url);
};

const manualDownloadFallback = typeof window !== 'undefined' ? `${window.location.origin}/download` : null;

// Global singleton so the modal, titlebar, sidebar and settings share one updater state.
const platform = getClientPlatform();
const updater = createAppUpdater({
    platform,
    getCurrentVersion,
    fetchUpdate: async (params) => (await api.get('/app/check-update', { params })).data,
    native: platform === 'android' && hasNativeUpdater() ? nativeBridge : null,
    openManualDownload: platform === 'android' ? openManualDownload : null,
    manualDownloadFallback,
    desktop: platform === 'windows' ? (window.electronAPI?.updater ?? null) : null,
    storage: typeof localStorage !== 'undefined' ? localStorage : null,
    session: typeof sessionStorage !== 'undefined' ? sessionStorage : null,
    notify,
});

updater.syncVersion();

export function useAppUpdate() {
    const downloadStageText = computed(() => {
        const key = stageKey(updater.stage.value);
        return key ? trans(key) : '';
    });

    return {
        ...updater,
        isNative: platform === 'android',
        isDesktop: platform === 'windows',
        platform: computed(() => platform),
        downloadStageText,
    };
}

// The check-update API rejects version_code < 1; 1 is "older than any published release".
const DOWNLOAD_PAGE_VERSION_CODE = 1;
const DOWNLOAD_PLATFORMS = ['android', 'windows'];

/**
 * Latest published release per platform for the public manual download page (/download).
 * Only releases with a download link and a SHA-256 are listed.
 */
export function useAppDownloads() {
    const releases = ref([]);
    const isLoading = ref(false);
    const hasError = ref(false);

    const load = async () => {
        isLoading.value = true;
        hasError.value = false;
        try {
            const responses = await Promise.all(
                DOWNLOAD_PLATFORMS.map((p) =>
                    api.get('/app/check-update', { params: { platform: p, version_code: DOWNLOAD_PAGE_VERSION_CODE } })
                )
            );
            releases.value = responses
                .map((res, i) => normalizeDownloadRelease(res.data, DOWNLOAD_PLATFORMS[i]))
                .filter(Boolean);
        } catch {
            hasError.value = true;
            releases.value = [];
        } finally {
            isLoading.value = false;
        }
    };

    return { releases, isLoading, hasError, load, currentPlatform: platform };
}
