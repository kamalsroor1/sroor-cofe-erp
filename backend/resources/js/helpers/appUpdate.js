/**
 * Pure helpers for the in-app updater (APP-3). No Vue / Capacitor imports so they are unit-testable in Node.
 */

const SHA256_RE = /^[a-f0-9]{64}$/i;

export const isValidSha256 = (value) => typeof value === 'string' && SHA256_RE.test(value);

const toInt = (value) => {
    const n = Number.parseInt(value, 10);
    return Number.isFinite(n) ? n : 0;
};

const safeHttpUrl = (value) => {
    if (typeof value !== 'string' || value === '') return null;
    try {
        const url = new URL(value);
        return url.protocol === 'https:' || url.protocol === 'http:' ? url.toString() : null;
    } catch {
        return null;
    }
};

/**
 * Shape the `/app/check-update` response into what the updater needs.
 * The server is the source of truth for the download URL and checksum; nothing is hardcoded here.
 */
export const normalizeUpdateResponse = (data, currentVersionCode) => {
    const payload = data && typeof data === 'object' ? data : {};
    const versionCode = toInt(payload.latest_version_code);
    const hasUpdate = !!payload.has_update && versionCode > toInt(currentVersionCode);

    return {
        hasUpdate,
        isForce: hasUpdate && !!payload.force_update,
        versionName: typeof payload.latest_version === 'string' ? payload.latest_version : null,
        versionCode,
        downloadUrl: safeHttpUrl(payload.download_url),
        checksum: isValidSha256(payload.checksum) ? payload.checksum.toLowerCase() : null,
        sizeBytes: Math.max(0, toInt(payload.file_size_bytes)),
        fileSize: typeof payload.file_size === 'string' ? payload.file_size : null,
        releaseNotes: Array.isArray(payload.release_notes) ? payload.release_notes.filter(Boolean) : [],
        publishedAt: typeof payload.published_at === 'string' ? payload.published_at : null,
    };
};

/** Integer 0..100 from real bytes, or null when the total size is unknown. */
export const progressPercent = (bytes, total) => {
    const t = Number(total);
    if (!Number.isFinite(t) || t <= 0) return null;
    const b = Math.max(0, Number(bytes) || 0);
    return Math.min(100, Math.floor((b / t) * 100));
};

const NATIVE_ERROR_KEYS = {
    CHECKSUM_MISMATCH: 'app_update.error_checksum_mismatch',
    SIGNATURE_MISMATCH: 'app_update.error_signature_mismatch',
    SIGNATURE_UNVERIFIABLE: 'app_update.error_signature_mismatch',
    PACKAGE_MISMATCH: 'app_update.error_package_mismatch',
    INSTALL_PERMISSION_REQUIRED: 'app_update.error_install_permission',
    INSECURE_URL: 'app_update.error_missing_url',
    INVALID_ARGS: 'app_update.error_missing_checksum',
    FILE_TOO_LARGE: 'app_update.error_download_failed',
    DOWNLOAD_FAILED: 'app_update.error_download_failed',
    INSTALL_FAILED: 'app_update.error_install_failed',
};

export const nativeErrorKey = (code) => NATIVE_ERROR_KEYS[code] || 'app_update.error_download_failed';

const STAGES = ['downloading', 'verifying', 'installer_opened', 'error'];

export const stageKey = (stage) => (STAGES.includes(stage) ? `app_update.stage_${stage}` : null);

/** Megabytes with one decimal for progress labels (e.g. "12.4"). */
export const bytesToMegabytes = (bytes) => (Math.max(0, Number(bytes) || 0) / 1048576).toFixed(1);
