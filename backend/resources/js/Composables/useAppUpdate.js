import { computed } from 'vue';
import { Capacitor, registerPlugin } from '@capacitor/core';
import { App as CapacitorApp } from '@capacitor/app';
import api from '../Services/api';
import { notifyError, notifySuccess, Toast } from '../helpers/alert';
import { trans } from '../helpers/trans';
import { stageKey } from '../helpers/appUpdate';
import { createAppUpdater } from './appUpdate/createAppUpdater';
import versionData from '../version.json';

// Native Android plugin (android/app/src/main/java/.../AppUpdaterPlugin.java):
// streams the APK, verifies SHA-256 + package name + signing certificate, then opens the system installer.
const AppUpdaterPlugin = registerPlugin('AppUpdater');

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
        const name = await window.electronAPI.getAppVersion();
        return { name: name || versionData?.version, code: versionData?.build_number };
    }
    return { name: versionData?.version, code: versionData?.build_number };
};

const notify = (type, key, params = {}) => {
    const message = trans(key, params);
    if (type === 'error') return notifyError(message);
    if (type === 'success') return notifySuccess(message);
    return Toast.fire({ icon: 'info', title: message });
};

// APKs released before the AppUpdater plugin still load this JS from the server (capacitor server.url). Navigating to
// the APK URL hands it to their native WebView DownloadListener, the only install path those builds have.
const hasNativeUpdater = () => Capacitor.isPluginAvailable('AppUpdater');

const legacyDownload = (url) => {
    window.location.href = url;
};

const nativeBridge = {
    downloadAndInstall: (options) => AppUpdaterPlugin.downloadAndInstall(options),
    addProgressListener: async (callback) => {
        const handle = await AppUpdaterPlugin.addListener('downloadProgress', callback);
        return () => handle.remove();
    },
};

// Global singleton so the modal, titlebar, sidebar and settings share one updater state.
const platform = getClientPlatform();
const updater = createAppUpdater({
    platform,
    getCurrentVersion,
    fetchUpdate: async (params) => (await api.get('/app/check-update', { params })).data,
    native: platform === 'android' && hasNativeUpdater() ? nativeBridge : null,
    legacyDownload: platform === 'android' ? legacyDownload : null,
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
