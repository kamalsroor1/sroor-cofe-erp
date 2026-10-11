const { app, dialog, shell } = require('electron');
const fs = require('fs');
const path = require('path');
const https = require('https');
const crypto = require('crypto');
const { spawn } = require('child_process');
const settingsStore = require('../config/settingsStore');
const updatePolicy = require('../config/updatePolicy');
const { CENTRAL_ORIGIN, isAllowedAppUrl } = require('../security/urlPolicy');
const {
    DOWNLOAD_MAX_BYTES,
    digestEquals,
    manualDownloadUrl,
    releaseFromManifest,
    resolveRedirect,
    rollbackFromManifest,
    toRendererCode,
    updaterError,
} = require('./updateVerify');
const { semverCode } = require('./versionCode');
const { evaluateSignature, readAuthenticodeSignature } = require('./authenticode');
const updateState = require('./updateState');

const USER_AGENT = 'Sroor-ERP-Desktop-Updater';
const MANIFEST_TIMEOUT_MS = 15000;
const MANIFEST_MAX_BYTES = 1024 * 1024;
const DOWNLOAD_TIMEOUT_MS = 120000;
const MAX_REDIRECTS = 5;
const STATE_KEY = 'updateState';

// Native dialog of the boot-crash rollback offer. The Electron main process has no access to the
// SPA translations (the renderer may be the thing that crashes), so this one dialog carries its
// own two locales, picked from the OS language.
const ROLLBACK_DIALOG_TEXT = {
    ar: {
        title: 'مشكلة في تشغيل البرنامج',
        message: (from, to) => `الإصدار ${from} مقفلش التشغيل كذا مرة. ترجع للإصدار السابق ${to}؟`,
        detail: 'الإصدار السابق هيتنزّل ويتأكد من بصمته قبل التثبيت. بياناتك محفوظة على السيرفر.',
        install: 'تثبيت الإصدار السابق',
        keep: 'الاستمرار على الإصدار الحالي',
    },
    en: {
        title: 'The app is not starting correctly',
        message: (from, to) => `Version ${from} failed to start several times. Go back to the previous version ${to}?`,
        detail: 'The previous version is downloaded and its checksum verified before it is installed. Your data stays on the server.',
        install: 'Install previous version',
        keep: 'Keep the current version',
    },
};

let inProgress = false;

const now = () => Date.now();

function loadState() {
    return updateState.normalizeState(settingsStore.get(STATE_KEY), now());
}

function saveState(state) {
    settingsStore.set(STATE_KEY, state);
    return state;
}

function installedVersionCode() {
    return semverCode(app.getVersion());
}

// The manifest is fetched by the main process from the official central host;
// nothing the renderer sends is trusted.
function fetchManifest(versionCode) {
    const query = new URLSearchParams({
        platform: 'windows',
        version_name: app.getVersion(),
        version_code: String(versionCode),
    });
    const manifestUrl = `${CENTRAL_ORIGIN}/api/v1/app/check-update?${query.toString()}`;

    return new Promise((resolve, reject) => {
        if (!isAllowedAppUrl(manifestUrl)) {
            return reject(updaterError('app_update.error_missing_url', 'update_manifest_untrusted_url'));
        }

        const request = https.get(
            manifestUrl,
            { headers: { 'User-Agent': USER_AGENT, Accept: 'application/json' } },
            (response) => {
                if (response.statusCode !== 200) {
                    response.resume();
                    return reject(
                        updaterError('app_update.error_download_failed', `update_manifest_http_${response.statusCode}`)
                    );
                }

                const chunks = [];
                let size = 0;
                response.on('data', (chunk) => {
                    size += chunk.length;
                    if (size > MANIFEST_MAX_BYTES) {
                        request.destroy(updaterError('app_update.error_manifest_invalid', 'update_manifest_too_large'));
                        return;
                    }
                    chunks.push(chunk);
                });
                response.on('end', () => {
                    try {
                        resolve(JSON.parse(Buffer.concat(chunks).toString('utf8')));
                    } catch {
                        reject(updaterError('app_update.error_manifest_invalid', 'update_manifest_invalid_json'));
                    }
                });
                response.on('error', reject);
            }
        );

        request.on('error', (err) => reject(asUpdaterError(err)));
        request.setTimeout(MANIFEST_TIMEOUT_MS, () => {
            request.destroy(updaterError('app_update.error_download_failed', 'update_manifest_timeout'));
        });
    });
}

// Streams to disk while hashing; the size must equal the published size exactly.
function downloadFile(fileUrl, destPath, expectedBytes, onProgress, redirectsLeft = MAX_REDIRECTS) {
    return new Promise((resolve, reject) => {
        if (!isAllowedAppUrl(fileUrl)) {
            return reject(updaterError('app_update.error_missing_url', 'update_download_untrusted_url'));
        }

        const request = https.get(fileUrl, { headers: { 'User-Agent': USER_AGENT } }, (response) => {
            if (response.statusCode >= 300 && response.statusCode < 400) {
                response.resume();
                if (redirectsLeft <= 0) {
                    return reject(
                        updaterError('app_update.error_download_failed', 'update_download_too_many_redirects')
                    );
                }
                const next = resolveRedirect(fileUrl, response.headers.location);
                if (!next) {
                    return reject(updaterError('app_update.error_missing_url', 'update_download_untrusted_redirect'));
                }
                return resolve(downloadFile(next, destPath, expectedBytes, onProgress, redirectsLeft - 1));
            }

            if (response.statusCode !== 200) {
                response.resume();
                return reject(
                    updaterError('app_update.error_download_failed', `update_download_http_${response.statusCode}`)
                );
            }

            const declared = parseInt(response.headers['content-length'] || '0', 10);
            if (declared > 0 && declared !== expectedBytes) {
                response.resume();
                return reject(updaterError('app_update.error_size_mismatch', 'update_download_length_mismatch'));
            }

            const hash = crypto.createHash('sha256');
            const fileStream = fs.createWriteStream(destPath, { flags: 'wx' });
            let downloadedBytes = 0;
            let failed = false;

            const fail = (err) => {
                if (failed) return;
                failed = true;
                response.destroy();
                fileStream.destroy();
                fs.unlink(destPath, () => {});
                reject(err);
            };

            response.on('data', (chunk) => {
                downloadedBytes += chunk.length;
                if (downloadedBytes > expectedBytes || downloadedBytes > DOWNLOAD_MAX_BYTES) {
                    fail(updaterError('app_update.error_size_mismatch', 'update_download_too_large'));
                    return;
                }
                hash.update(chunk);
                if (typeof onProgress === 'function') {
                    const percent = Math.min(99, Math.floor((downloadedBytes / expectedBytes) * 100));
                    onProgress({ percent, transferred: downloadedBytes, total: expectedBytes });
                }
            });
            response.on('error', fail);
            fileStream.on('error', fail);

            response.pipe(fileStream);

            fileStream.on('finish', () => {
                if (failed) return;
                fileStream.close(() => {
                    if (downloadedBytes !== expectedBytes) {
                        fs.unlink(destPath, () => {});
                        return reject(updaterError('app_update.error_size_mismatch', 'update_download_truncated'));
                    }
                    resolve({ filePath: destPath, sha256: hash.digest('hex'), bytes: downloadedBytes });
                });
            });
        });

        request.on('error', (err) => reject(asUpdaterError(err)));
        request.setTimeout(DOWNLOAD_TIMEOUT_MS, () => {
            request.destroy(updaterError('app_update.error_download_failed', 'update_download_timeout'));
        });
    });
}

// Socket errors carry Node codes (ECONNRESET...); only our own app_update.* codes pass through.
function asUpdaterError(err) {
    if (err && typeof err.code === 'string' && err.code.startsWith('app_update.')) return err;
    return updaterError('app_update.error_download_failed', err && err.message);
}

function removeQuietly(dir) {
    if (!dir) return;
    try {
        fs.rmSync(dir, { recursive: true, force: true });
    } catch (err) {
        console.error('[NativeUpdater] Failed to remove update temp dir:', err.message);
    }
}

function openManualDownload(manifest) {
    const url = manualDownloadUrl(manifest);
    if (!isAllowedAppUrl(url)) return;
    shell
        .openExternal(url)
        .catch((err) => console.error('[NativeUpdater] Could not open the download page:', err.message));
}

function waitForSpawn(child) {
    return new Promise((resolve) => {
        child.once('spawn', () => resolve(null));
        child.once('error', (err) => resolve(err));
    });
}

/**
 * Download -> SHA-256 + exact size -> Authenticode policy -> pending state -> visible NSIS wizard.
 * Same path for a forward update and for the boot-crash rollback.
 */
async function installRelease(release, { kind, fromVersionCode, send }) {
    let tempDir = null;
    try {
        tempDir = fs.mkdtempSync(path.join(app.getPath('temp'), 'sroor-update-'));
        const installerPath = path.join(tempDir, 'Sroor-ERP-POS-Setup.exe');

        const { sha256 } = await downloadFile(release.downloadUrl, installerPath, release.sizeBytes, (progress) =>
            send('updater:progress', progress)
        );

        send('updater:progress', { percent: 100, stage: 'verifying' });
        if (!digestEquals(sha256, release.sha256)) {
            throw updaterError('app_update.error_checksum_mismatch', 'update_checksum_mismatch');
        }

        const signature = await readAuthenticodeSignature(installerPath);
        const verdict = evaluateSignature(signature, updatePolicy);
        if (!verdict.ok) {
            throw updaterError('app_update.error_installer_signature', `update_signature_${verdict.reason}`);
        }

        saveState(
            updateState.markPending(loadState(), { versionCode: release.versionCode, fromVersionCode, kind }, now())
        );

        // Visible NSIS wizard (oneClick:false) — never silent /S.
        const installer = spawn(installerPath, [], { detached: true, stdio: 'ignore' });
        const spawnError = await waitForSpawn(installer);
        if (spawnError) {
            saveState({ ...loadState(), pending: null });
            throw updaterError('app_update.error_install_failed', spawnError.message);
        }

        send('updater:complete', { success: true });
        installer.unref();
        tempDir = null;
        setTimeout(() => app.exit(0), 500);
    } catch (error) {
        removeQuietly(tempDir);
        saveState(updateState.recordFailedAttempt(loadState(), now()));
        throw error;
    }
}

function rendererSender(mainWindow) {
    return (channel, payload) => {
        if (mainWindow && !mainWindow.isDestroyed()) {
            mainWindow.webContents.send(channel, payload);
        }
    };
}

/**
 * IPC updater:download-and-install. Returns { success } or { success: false, error: <app_update.* code> };
 * the renderer never sees raw exception messages, paths or URLs.
 */
async function downloadAndApplyUpdate(mainWindow) {
    const send = rendererSender(mainWindow);
    if (inProgress) return { success: false, error: 'app_update.error_update_in_progress' };
    inProgress = true;

    let manifest = null;
    try {
        const versionCode = installedVersionCode();
        if (process.platform !== 'win32' || !versionCode) {
            throw updaterError('app_update.error_updater_unavailable', 'update_unsupported_platform');
        }

        if (!updateState.canAttempt(loadState(), now()).ok) {
            openManualDownload(null);
            throw updaterError('app_update.error_update_attempts_exhausted');
        }

        manifest = await fetchManifest(versionCode);
        const parsed = releaseFromManifest(manifest, versionCode);
        if (!parsed.ok) {
            if (parsed.code === 'app_update.already_latest') return { success: false, error: parsed.code };
            throw updaterError(parsed.code);
        }

        await installRelease(parsed.release, { kind: 'update', fromVersionCode: versionCode, send });
        return { success: true };
    } catch (error) {
        let code = toRendererCode(error);
        if (code !== 'app_update.error_update_attempts_exhausted' && !updateState.canAttempt(loadState(), now()).ok) {
            // That failure used the last attempt of the window: hand over to the manual download page.
            openManualDownload(manifest);
            code = 'app_update.error_update_attempts_exhausted';
        }
        console.error('[NativeUpdater] Update failed:', error.message);
        send('updater:error', { code });
        return { success: false, error: code };
    } finally {
        inProgress = false;
    }
}

/**
 * Startup (before the main window): resolve the installer started last time and start the boot guard.
 * @returns {{outcome: null|'success'|'failed', offerRollback: boolean}}
 */
function initUpdateGuard() {
    const resolved = updateState.resolvePendingAtStartup(loadState(), installedVersionCode(), now());
    const state = saveState(updateState.beginBoot(resolved.state));
    return { outcome: resolved.outcome, offerRollback: updateState.shouldOfferRollback(state) };
}

/** BOOT_CONFIRM_DELAY_MS after the page loaded: this build is healthy. */
function markBootHealthy() {
    saveState(updateState.confirmBoot(loadState()));
}

/** Clean quit before the boot was confirmed: not a crash. */
function markCleanExit() {
    saveState(updateState.endBootCleanly(loadState()));
}

function getRendererState() {
    return updateState.summarize(loadState(), now());
}

function dialogText() {
    const locale = String(app.getLocale() || '').toLowerCase();
    return locale.startsWith('ar') ? ROLLBACK_DIALOG_TEXT.ar : ROLLBACK_DIALOG_TEXT.en;
}

/**
 * After MAX_FAILED_BOOTS failed boots of a freshly updated build: OFFER the previous verified
 * release advertised by the server (manifest.rollback). Never installs without the user's yes.
 */
async function offerRollbackIfNeeded(mainWindow) {
    if (!updateState.shouldOfferRollback(loadState()) || inProgress || process.platform !== 'win32') return;
    saveState(updateState.rollbackOffered(loadState()));

    const versionCode = installedVersionCode();
    let rollback = null;
    try {
        rollback = rollbackFromManifest(await fetchManifest(versionCode), versionCode);
    } catch (err) {
        console.error('[NativeUpdater] Rollback manifest unavailable:', err.message);
    }
    if (!rollback) return;

    const text = dialogText();
    const options = {
        type: 'warning',
        buttons: [text.install, text.keep],
        defaultId: 0,
        cancelId: 1,
        noLink: true,
        title: text.title,
        message: text.message(app.getVersion(), rollback.version),
        detail: text.detail,
    };
    const parent = mainWindow && !mainWindow.isDestroyed() ? mainWindow : undefined;
    const { response } = parent ? await dialog.showMessageBox(parent, options) : await dialog.showMessageBox(options);
    if (response !== 0) return;

    inProgress = true;
    try {
        await installRelease(rollback, {
            kind: 'rollback',
            fromVersionCode: versionCode,
            send: rendererSender(mainWindow),
        });
    } catch (err) {
        console.error('[NativeUpdater] Rollback failed:', err.message);
        openManualDownload(null);
    } finally {
        inProgress = false;
    }
}

module.exports = {
    downloadAndApplyUpdate,
    fetchManifest,
    initUpdateGuard,
    markBootHealthy,
    markCleanExit,
    getRendererState,
    offerRollbackIfNeeded,
    BOOT_CONFIRM_DELAY_MS: updateState.BOOT_CONFIRM_DELAY_MS,
};
