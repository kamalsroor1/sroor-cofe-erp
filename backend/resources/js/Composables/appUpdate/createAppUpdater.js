import { ref, computed } from 'vue';
import { isValidSha256, normalizeUpdateResponse, progressPercent, nativeErrorKey } from '../../helpers/appUpdate.js';

/**
 * Platform-agnostic updater state machine (APP-3). All side effects are injected so it can be unit-tested:
 *
 * - platform: 'android' | 'windows' | 'web'
 * - getCurrentVersion(): Promise<{ name, code }> — always the INSTALLED binary, never a stored/spoofed value
 * - fetchUpdate(params): Promise<checkUpdateResponse>
 * - native: { downloadAndInstall(opts), addProgressListener(cb) } (Android AppUpdater plugin) or null
 * - legacyDownload(url): for APKs built before the AppUpdater plugin existed — hands the URL to the WebView so the
 *   old native DownloadListener installs it. Only used when `native` is null.
 * - desktop: window.electronAPI.updater or null
 * - storage / session: Storage-like (getItem / setItem)
 * - notify(type, key, params): user feedback for manual checks
 */
export function createAppUpdater({
    platform,
    getCurrentVersion,
    fetchUpdate,
    native,
    legacyDownload,
    desktop,
    storage,
    session,
    notify,
}) {
    const currentVersionName = ref('');
    const currentVersionCode = ref(0);
    const isChecking = ref(false);
    const hasUpdate = ref(false);
    const isForceUpdate = ref(false);
    const latestVersionData = ref(null);
    const update = ref(null);
    const isModalOpen = ref(false);
    const isDownloading = ref(false);
    const isDownloaded = ref(false);
    const downloadedBytes = ref(0);
    const totalBytes = ref(0);
    const desktopPercent = ref(0);
    const stage = ref('idle');
    const errorKey = ref(null);

    const isEligible = computed(() => platform === 'android' || platform === 'windows');

    const downloadProgress = computed(() => {
        if (platform === 'windows') return desktopPercent.value;
        return progressPercent(downloadedBytes.value, totalBytes.value) ?? 0;
    });
    const isProgressIndeterminate = computed(
        () =>
            isDownloading.value &&
            platform === 'android' &&
            progressPercent(downloadedBytes.value, totalBytes.value) === null
    );

    const checkedKey = () => `app_update_checked:${platform}:${currentVersionCode.value}`;
    const forcedKey = () => `app_update_forced:${platform}:${currentVersionCode.value}`;
    const dismissedKey = `app_update_dismissed:${platform}`;

    const safeGet = (store, key) => {
        try {
            return store?.getItem(key) ?? null;
        } catch {
            return null;
        }
    };
    const safeSet = (store, key, value) => {
        try {
            store?.setItem(key, String(value));
        } catch {
            // storage unavailable (private mode / quota) — degrade to in-memory behaviour
        }
    };

    const syncVersion = async () => {
        try {
            const info = (await getCurrentVersion()) || {};
            if (info.name) currentVersionName.value = String(info.name);
            const code = Number.parseInt(info.code, 10);
            if (Number.isFinite(code)) currentVersionCode.value = code;
        } catch {
            // keep the last known version
        }
    };

    const resetDownloadState = () => {
        isDownloading.value = false;
        isDownloaded.value = false;
        downloadedBytes.value = 0;
        totalBytes.value = 0;
        desktopPercent.value = 0;
        stage.value = 'idle';
        errorKey.value = null;
    };

    const applyUpdate = (data, normalized) => {
        hasUpdate.value = normalized.hasUpdate;
        isForceUpdate.value = normalized.isForce;
        latestVersionData.value = data;
        update.value = normalized;
        if (!isDownloading.value) resetDownloadState();
    };

    /**
     * A forced update seen earlier in this session is re-applied from sessionStorage, so a reload never unlocks
     * an outdated build even though the server is not asked again.
     */
    const restoreForcedUpdate = () => {
        try {
            const raw = safeGet(session, forcedKey());
            if (!raw) return;
            const data = JSON.parse(raw);
            const normalized = normalizeUpdateResponse(data, currentVersionCode.value);
            if (!normalized.hasUpdate || !normalized.isForce) return;
            applyUpdate(data, normalized);
            isModalOpen.value = true;
        } catch {
            // corrupt session entry — the next session re-checks with the server
        }
    };

    /**
     * Automatic checks run at most once per session for the same installed version (survives page reloads),
     * which is what stops a forced update from re-prompting in a loop. Manual checks always hit the server.
     */
    const checkForUpdates = async (isManual = false) => {
        if (!isEligible.value) {
            if (isManual) notify('info', 'app_update.web_auto_updated', { version: currentVersionName.value });
            return;
        }
        if (isChecking.value) return;

        isChecking.value = true;
        try {
            await syncVersion();
            if (!isManual) {
                if (safeGet(session, checkedKey())) {
                    restoreForcedUpdate();
                    return;
                }
                safeSet(session, checkedKey(), '1');
            }

            const data = await fetchUpdate({
                platform,
                version_code: currentVersionCode.value,
                version_name: currentVersionName.value,
            });
            const normalized = normalizeUpdateResponse(data, currentVersionCode.value);

            hasUpdate.value = normalized.hasUpdate;
            isForceUpdate.value = normalized.isForce;
            safeSet(session, forcedKey(), normalized.hasUpdate && normalized.isForce ? JSON.stringify(data) : '');

            if (!normalized.hasUpdate) {
                if (isManual) notify('success', 'app_update.up_to_date_desc', { version: currentVersionName.value });
                return;
            }

            applyUpdate(data, normalized);

            const dismissedCode = safeGet(storage, dismissedKey);
            const dismissed = !normalized.isForce && dismissedCode === String(normalized.versionCode);
            if (isManual || !dismissed) isModalOpen.value = true;
        } catch {
            if (isManual) notify('error', 'app_update.check_failed');
        } finally {
            isChecking.value = false;
        }
    };

    const fail = (key) => {
        isDownloading.value = false;
        isDownloaded.value = false;
        stage.value = 'error';
        errorKey.value = key;
    };

    const installAndroid = async (target) => {
        if (!target.downloadUrl) return fail('app_update.error_missing_url');
        if (!isValidSha256(target.checksum)) return fail('app_update.error_missing_checksum');
        if (!native) {
            // Old APKs without the AppUpdater plugin: their built-in DownloadListener fetches + installs the APK,
            // so a forced update never dead-ends; the modal explains that the in-app updater is unavailable.
            if (typeof legacyDownload === 'function') {
                try {
                    legacyDownload(target.downloadUrl);
                } catch {
                    // navigation blocked — the message below still tells the user to update manually
                }
            }
            return fail('app_update.error_updater_unavailable');
        }

        totalBytes.value = target.sizeBytes;
        let removeListener = null;
        try {
            removeListener = await native.addProgressListener((p) => {
                downloadedBytes.value = Math.max(0, Number(p?.bytes) || 0);
                const total = Number(p?.total);
                if (Number.isFinite(total) && total > 0) totalBytes.value = total;
                if (p?.stage === 'verifying') stage.value = 'verifying';
            });

            await native.downloadAndInstall({
                url: target.downloadUrl,
                sha256: target.checksum,
                expectedSize: target.sizeBytes,
                versionCode: target.versionCode,
            });

            isDownloading.value = false;
            isDownloaded.value = true;
            stage.value = 'installer_opened';
        } catch (err) {
            fail(nativeErrorKey(err?.code));
        } finally {
            if (typeof removeListener === 'function') removeListener();
        }
    };

    let desktopSubscribed = false;
    const installDesktop = async () => {
        if (!desktop?.downloadAndInstall) return fail('app_update.error_updater_unavailable');
        if (!desktopSubscribed) {
            desktopSubscribed = true;
            desktop.onProgress?.((p) => {
                desktopPercent.value = Math.max(0, Math.min(100, Math.floor(Number(p?.percent) || 0)));
            });
            desktop.onComplete?.(() => {
                desktopPercent.value = 100;
                isDownloading.value = false;
                isDownloaded.value = true;
                stage.value = 'installer_opened';
            });
            desktop.onError?.(() => fail('app_update.error_download_failed'));
        }
        try {
            // TODO(APP-7): the Electron main process resolves the URL/checksum from app_versions itself.
            await desktop.downloadAndInstall();
        } catch {
            fail('app_update.error_download_failed');
        }
    };

    const startDownloadAndInstall = async () => {
        if (isDownloading.value || !update.value) return;
        resetDownloadState();
        isDownloading.value = true;
        stage.value = 'downloading';

        if (platform === 'android') return installAndroid(update.value);
        if (platform === 'windows') return installDesktop();
        return fail('app_update.error_updater_unavailable');
    };

    const closeModal = () => {
        if (isForceUpdate.value || isDownloading.value) return;
        isModalOpen.value = false;
        if (update.value?.versionCode) safeSet(storage, dismissedKey, update.value.versionCode);
        resetDownloadState();
    };

    return {
        isEligible,
        currentVersionName,
        currentVersionCode,
        isChecking,
        hasUpdate,
        isForceUpdate,
        latestVersionData,
        update,
        isModalOpen,
        isDownloading,
        isDownloaded,
        downloadProgress,
        isProgressIndeterminate,
        downloadedBytes,
        totalBytes,
        stage,
        errorKey,
        syncVersion,
        checkForUpdates,
        startDownloadAndInstall,
        closeModal,
    };
}
