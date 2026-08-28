const { app } = require('electron');
const fs = require('fs');
const path = require('path');
const https = require('https');
const http = require('http');
const { spawn } = require('child_process');

function downloadFile(fileUrl, destPath, onProgress) {
    return new Promise((resolve, reject) => {
        const fileStream = fs.createWriteStream(destPath);
        const protocol = fileUrl.startsWith('https') ? https : http;

        const request = protocol.get(fileUrl, { headers: { 'User-Agent': 'Sroor-ERP-Desktop-Updater' } }, (response) => {
            // Handle HTTP 301/302/307 Redirects
            if (response.statusCode >= 300 && response.statusCode < 400 && response.headers.location) {
                fileStream.close();
                fs.unlink(destPath, () => {});
                return resolve(downloadFile(response.headers.location, destPath, onProgress));
            }

            if (response.statusCode !== 200) {
                fileStream.close();
                fs.unlink(destPath, () => {});
                return reject(new Error(`Failed to download update. HTTP Status: ${response.statusCode}`));
            }

            const totalBytes = parseInt(response.headers['content-length'] || '0', 10);
            let downloadedBytes = 0;

            response.on('data', (chunk) => {
                downloadedBytes += chunk.length;
                if (totalBytes > 0 && typeof onProgress === 'function') {
                    const percent = Math.min(99, Math.round((downloadedBytes / totalBytes) * 100));
                    onProgress({
                        percent,
                        transferred: downloadedBytes,
                        total: totalBytes
                    });
                }
            });

            response.pipe(fileStream);

            fileStream.on('finish', () => {
                fileStream.close(() => {
                    if (typeof onProgress === 'function') {
                        onProgress({ percent: 100, transferred: downloadedBytes, total: totalBytes || downloadedBytes });
                    }
                    resolve(destPath);
                });
            });
        });

        request.on('error', (err) => {
            fileStream.close();
            fs.unlink(destPath, () => {});
            reject(err);
        });

        request.setTimeout(120000, () => {
            request.destroy();
            fileStream.close();
            fs.unlink(destPath, () => {});
            reject(new Error('Download timeout after 120 seconds.'));
        });
    });
}

async function downloadAndApplyUpdate(updateUrl, mainWindow) {
    if (!updateUrl) {
        throw new Error('Update URL is missing.');
    }

    const tempDir = app.getPath('temp');
    const updateExePath = path.join(tempDir, `sroor-erp-setup-${Date.now()}.exe`);

    console.log(`[NativeUpdater] Downloading update from: ${updateUrl} to ${updateExePath}`);

    try {
        await downloadFile(updateUrl, updateExePath, (progress) => {
            if (mainWindow && !mainWindow.isDestroyed()) {
                mainWindow.webContents.send('updater:progress', progress);
            }
        });

        console.log(`[NativeUpdater] Update downloaded successfully to ${updateExePath}`);

        if (mainWindow && !mainWindow.isDestroyed()) {
            mainWindow.webContents.send('updater:complete', { success: true, filePath: updateExePath });
        }

        // Wait 1.5 seconds for UI to show complete state, then launch installer and exit
        setTimeout(() => {
            try {
                if (process.platform === 'win32') {
                    console.log('[NativeUpdater] Launching Windows Installer silently / with restart...');
                    // Launch installer detached so it continues after app exits
                    const installer = spawn(updateExePath, ['/S'], {
                        detached: true,
                        stdio: 'ignore'
                    });
                    installer.unref();
                } else {
                    app.relaunch();
                }

                // Exit current Electron instance
                app.exit(0);
            } catch (spawnError) {
                console.error('[NativeUpdater] Failed to launch installer, falling back to relaunch:', spawnError);
                app.relaunch();
                app.exit(0);
            }
        }, 1500);

        return { success: true };
    } catch (error) {
        console.error('[NativeUpdater] Update process failed:', error);
        if (mainWindow && !mainWindow.isDestroyed()) {
            mainWindow.webContents.send('updater:error', { message: error.message });
        }
        throw error;
    }
}

module.exports = {
    downloadAndApplyUpdate,
};
