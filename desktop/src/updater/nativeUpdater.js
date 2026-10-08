const { app, dialog } = require('electron');
const fs = require('fs');
const path = require('path');
const https = require('https');
const crypto = require('crypto');
const { spawn } = require('child_process');
const { CENTRAL_ORIGIN, isAllowedAppUrl } = require('../security/urlPolicy');
const { extractChecksum, resolveRedirect, digestEquals } = require('./updateVerify');

const USER_AGENT = 'Sroor-ERP-Desktop-Updater';
const MANIFEST_TIMEOUT_MS = 15000;
const MANIFEST_MAX_BYTES = 1024 * 1024;
const DOWNLOAD_TIMEOUT_MS = 120000;
const DOWNLOAD_MAX_BYTES = 300 * 1024 * 1024;
const MAX_REDIRECTS = 5;

// The manifest is fetched by the main process from the official central host;
// nothing the renderer sends is trusted.
function fetchManifest() {
    const query = new URLSearchParams({
        platform: 'windows',
        version_name: app.getVersion(),
        version_code: '0',
    });
    const manifestUrl = `${CENTRAL_ORIGIN}/api/v1/app/check-update?${query.toString()}`;

    return new Promise((resolve, reject) => {
        if (!isAllowedAppUrl(manifestUrl)) {
            return reject(new Error('update_manifest_untrusted_url'));
        }

        const request = https.get(
            manifestUrl,
            { headers: { 'User-Agent': USER_AGENT, Accept: 'application/json' } },
            (response) => {
                if (response.statusCode !== 200) {
                    response.resume();
                    return reject(new Error(`update_manifest_http_${response.statusCode}`));
                }

                const chunks = [];
                let size = 0;
                response.on('data', (chunk) => {
                    size += chunk.length;
                    if (size > MANIFEST_MAX_BYTES) {
                        request.destroy(new Error('update_manifest_too_large'));
                        return;
                    }
                    chunks.push(chunk);
                });
                response.on('end', () => {
                    try {
                        resolve(JSON.parse(Buffer.concat(chunks).toString('utf8')));
                    } catch {
                        reject(new Error('update_manifest_invalid_json'));
                    }
                });
                response.on('error', reject);
            }
        );

        request.on('error', reject);
        request.setTimeout(MANIFEST_TIMEOUT_MS, () => {
            request.destroy(new Error('update_manifest_timeout'));
        });
    });
}

function downloadFile(fileUrl, destPath, onProgress, redirectsLeft = MAX_REDIRECTS) {
    return new Promise((resolve, reject) => {
        if (!isAllowedAppUrl(fileUrl)) {
            return reject(new Error('update_download_untrusted_url'));
        }

        const request = https.get(fileUrl, { headers: { 'User-Agent': USER_AGENT } }, (response) => {
            if (response.statusCode >= 300 && response.statusCode < 400) {
                response.resume();
                if (redirectsLeft <= 0) {
                    return reject(new Error('update_download_too_many_redirects'));
                }
                const next = resolveRedirect(fileUrl, response.headers.location);
                if (!next) {
                    return reject(new Error('update_download_untrusted_redirect'));
                }
                return resolve(downloadFile(next, destPath, onProgress, redirectsLeft - 1));
            }

            if (response.statusCode !== 200) {
                response.resume();
                return reject(new Error(`update_download_http_${response.statusCode}`));
            }

            const totalBytes = parseInt(response.headers['content-length'] || '0', 10);
            if (totalBytes > DOWNLOAD_MAX_BYTES) {
                response.resume();
                return reject(new Error('update_download_too_large'));
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
                if (downloadedBytes > DOWNLOAD_MAX_BYTES) {
                    fail(new Error('update_download_too_large'));
                    return;
                }
                hash.update(chunk);
                if (totalBytes > 0 && typeof onProgress === 'function') {
                    const percent = Math.min(99, Math.round((downloadedBytes / totalBytes) * 100));
                    onProgress({ percent, transferred: downloadedBytes, total: totalBytes });
                }
            });
            response.on('error', fail);
            fileStream.on('error', fail);

            response.pipe(fileStream);

            fileStream.on('finish', () => {
                if (failed) return;
                fileStream.close(() => {
                    if (typeof onProgress === 'function') {
                        onProgress({
                            percent: 100,
                            transferred: downloadedBytes,
                            total: totalBytes || downloadedBytes,
                        });
                    }
                    resolve({ filePath: destPath, sha256: hash.digest('hex') });
                });
            });
        });

        request.on('error', reject);
        request.setTimeout(DOWNLOAD_TIMEOUT_MS, () => {
            request.destroy(new Error('update_download_timeout'));
        });
    });
}

function removeQuietly(dir) {
    if (!dir) return;
    try {
        fs.rmSync(dir, { recursive: true, force: true });
    } catch (err) {
        console.error('[NativeUpdater] Failed to remove update temp dir:', err.message);
    }
}

async function confirmInstall(mainWindow, version, sha256) {
    const options = {
        type: 'question',
        buttons: ['تثبيت التحديث (Install)', 'إلغاء (Cancel)'],
        defaultId: 1,
        cancelId: 1,
        noLink: true,
        title: 'تحديث المنظومة (Update)',
        message: `تثبيت الإصدار ${version}؟ (Install version ${version}?)`,
        detail:
            `تم التحقق من سلامة الملف. سيتم فتح معالج التثبيت وإغلاق التطبيق.\n` +
            `File verified. The setup wizard will open and the app will close.\n\n` +
            `SHA-256: ${sha256.slice(0, 12)}…`,
    };
    const parent = mainWindow && !mainWindow.isDestroyed() ? mainWindow : undefined;
    const { response } = parent ? await dialog.showMessageBox(parent, options) : await dialog.showMessageBox(options);
    return response === 0;
}

async function downloadAndApplyUpdate(mainWindow) {
    const send = (channel, payload) => {
        if (mainWindow && !mainWindow.isDestroyed()) {
            mainWindow.webContents.send(channel, payload);
        }
    };

    let tempDir = null;
    try {
        if (process.platform !== 'win32') {
            throw new Error('update_unsupported_platform');
        }

        const manifest = await fetchManifest();
        const expectedSha256 = extractChecksum(manifest);
        const downloadUrl = manifest && manifest.download_url;
        if (!isAllowedAppUrl(downloadUrl)) {
            throw new Error('update_download_untrusted_url');
        }
        const version =
            String(manifest.latest_version || '')
                .replace(/[^0-9A-Za-z.+-]/g, '')
                .slice(0, 32) || '?';

        tempDir = fs.mkdtempSync(path.join(app.getPath('temp'), 'sroor-update-'));
        const updateExePath = path.join(tempDir, 'Sroor-ERP-POS-Setup.exe');

        const { sha256 } = await downloadFile(downloadUrl, updateExePath, (progress) =>
            send('updater:progress', progress)
        );

        if (!digestEquals(sha256, expectedSha256)) {
            removeQuietly(tempDir);
            tempDir = null;
            send('updater:error', { message: 'checksum_mismatch' });
            return { success: false, error: 'checksum_mismatch' };
        }

        const confirmed = await confirmInstall(mainWindow, version, sha256);
        if (!confirmed) {
            removeQuietly(tempDir);
            tempDir = null;
            send('updater:error', { message: 'update_cancelled', cancelled: true });
            return { success: false, cancelled: true };
        }

        send('updater:complete', { success: true });

        // Visible NSIS wizard (oneClick:false) — never silent /S.
        const installer = spawn(updateExePath, [], { detached: true, stdio: 'ignore' });
        installer.on('error', (err) => console.error('[NativeUpdater] Installer failed to start:', err.message));
        installer.unref();
        tempDir = null;
        setTimeout(() => app.exit(0), 500);

        return { success: true };
    } catch (error) {
        removeQuietly(tempDir);
        console.error('[NativeUpdater] Update process failed:', error.message);
        send('updater:error', { message: error.message });
        return { success: false, error: error.message };
    }
}

module.exports = {
    downloadAndApplyUpdate,
    fetchManifest,
};
