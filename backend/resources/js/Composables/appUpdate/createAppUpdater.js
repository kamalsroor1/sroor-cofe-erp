import { ref, computed } from 'vue';
import {
    DESKTOP_ALREADY_LATEST,
    desktopErrorKey,
    isValidSha256,
    nativeErrorKey,
    normalizeUpdateResponse,
    offersManualDownload,
    progressPercent,
} from '../../helpers/appUpdate.js';

/** Permanent "dismissed" flag of older builds; optional updates are now prompted once per launch instead. */
const LEGACY_DISMISSED_PREFIX = 'app_update_dismissed:';

/**
 * Platform-agnostic updater state machine (APP-3 / APP-7). All side effects are injected so it can be unit-tested:
 *
 * - platform: 'android' | 'windows' | 'web'
 * - getCurrentVersion(): Promise<{ name, code }> — always the INSTALLED binary, never a stored/spoofed value
 * - fetchUpdate(params): Promise<checkUpdateResponse>
 * - native: { downloadAndInstall(opts), addProgressListener(cb) } (Android AppUpdater plugin) or null
 * - openManualDownload(url): opens the public manual download page. Used when the in-app updater cannot install
 *   safely (APKs without the AppUpdater plugin, unverifiable signer, desktop attempts exhausted). It never hands
 *   an APK URL to the WebView: an install that skips the SHA-256 check is refused.
 * - manualDownloadFallback: URL of the download page when the server did not send manual_download_url
 * - desktop: window.electronAPI.updater or null
 * - storage: Storage-like; only used to clean up the old permanent dismissal flag
 * - session: Storage-like (getItem / setItem) — lives for one app launch
 * - notify(type, key, params): user feedback for manual checks
 */
export function createAppUpdater({
    platform,
    getCurrentVersion,
    fetchUpdate,
    native,
    openManualDownload = null,
    manualDownloadFallback = null,
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

    const manualDownloadUrl = computed(() => update.value?.manualDownloadUrl || manualDownloadFallback || null);
    const canDownloadManually = computed(
        () =>
            stage.value === 'error' &&
            offersManualDownload(errorKey.value) &&
            !!manualDownloadUrl.value &&
            typeof openManualDownload === 'function'
    );

    const checkedKey = () => `app_update_checked:${platform}:${currentVersionCode.value}`;
    const forcedKey = () => `app_update_forced:${platform}:${currentVersionCode.value}`;

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

    try {
        storage?.removeItem?.(`${LEGACY_DISMISSED_PREFIX}${platform}`);
    } catch {
        // nothing to clean up
    }

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
     * A forced update seen earlier in this launch is re-applied from sessionStorage, so a reload never unlocks
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
            // corrupt session entry — the next launch re-checks with the server
        }
    };

    /**
     * Automatic checks run at most once per app launch for the same installed version (sessionStorage survives
     * reloads but not a relaunch). That shows an optional update once per launch and stops a forced update from
     * re-prompting in a loop. Manual checks always hit the server.
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
            isModalOpen.value = true;
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
        // APKs built before the AppUpdater plugin cannot verify the SHA-256 or the signer in-app: refuse and offer
        // the manual download page (it shows the checksum) instead of handing the APK URL to the WebView.
        if (!native) return fail('app_update.error_updater_unavailable');

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
    const subscribeDesktop = () => {
        if (desktopSubscribed) return;
        desktopSubscribed = true;
        desktop.onProgress?.((p) => {
            desktopPercent.value = Math.max(0, Math.min(100, Math.floor(Number(p?.percent) || 0)));
            if (p?.stage === 'verifying') stage.value = 'verifying';
        });
        desktop.onComplete?.(() => {
            desktopPercent.value = 100;
            isDownloading.value = false;
            isDownloaded.value = true;
            stage.value = 'installer_opened';
        });
        desktop.onError?.((payload) => {
            if (payload?.code === DESKTOP_ALREADY_LATEST) return;
            fail(desktopErrorKey(payload?.code));
        });
    };

    const handleDesktopResult = (result) => {
        if (!result || result.success) return;
        if (result.error === DESKTOP_ALREADY_LATEST) {
            // The installed build is already current: nothing to install, unlock the app.
            hasUpdate.value = false;
            isForceUpdate.value = false;
            safeSet(session, forcedKey(), '');
            resetDownloadState();
            isModalOpen.value = false;
            notify('success', 'app_update.up_to_date_desc', { version: currentVersionName.value });
            return;
        }
        fail(desktopErrorKey(result.error));
    };

    // The Electron main process resolves URL, SHA-256, size and signature from the update API itself;
    // nothing the renderer holds is trusted for the install.
    const installDesktop = async () => {
        if (!desktop?.downloadAndInstall) return fail('app_update.error_updater_unavailable');
        subscribeDesktop();
        try {
            handleDesktopResult(await desktop.downloadAndInstall());
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

    const openManualDownloadPage = () => {
        if (!manualDownloadUrl.value || typeof openManualDownload !== 'function') return false;
        try {
            openManualDownload(manualDownloadUrl.value);
            return true;
        } catch {
            return false;
        }
    };

    // Closing only hides the prompt for this launch; the next launch asks again.
    const closeModal = () => {
        if (isForceUpdate.value || isDownloading.value) return;
        isModalOpen.value = false;
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
        manualDownloadUrl,
        canDownloadManually,
        syncVersion,
        checkForUpdates,
        startDownloadAndInstall,
        openManualDownloadPage,
        closeModal,
    };
}
