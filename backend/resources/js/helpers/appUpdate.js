/**
 * Pure helpers for the in-app updater (APP-3 / APP-7). No Vue / Capacitor imports so they are unit-testable in Node.
 */

const SHA256_RE = /^[a-f0-9]{64}$/i;
const SEMVER_RE = /^(\d{1,3})\.(\d{1,3})\.(\d{1,3})$/;

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

// Hosts that only exist on a developer machine: the one place a plain-http manual page is accepted.
const isDevHost = (hostname) =>
    hostname === 'localhost' || hostname === '127.0.0.1' || hostname === '[::1]' || hostname.endsWith('.test');

/**
 * L3: the manual download page is opened in the system browser and tells users which file to install,
 * so it must be https. Plain http is accepted only for local development hosts (localhost, 127.0.0.1,
 * *.test), never for a real domain where it could be rewritten on the way.
 */
const safeManualUrl = (value) => {
    const safe = safeHttpUrl(value);
    if (safe === null) return null;
    const url = new URL(safe);
    if (url.protocol === 'https:') return safe;
    return isDevHost(url.hostname) ? safe : null;
};

/**
 * Desktop release code from its semantic version, identical to the server's SemverCode:
 * "1.2.3" -> 1002003. Returns 0 when the version is not MAJOR.MINOR.PATCH (parts 0..999).
 */
export const semverCode = (version) => {
    const match = typeof version === 'string' ? SEMVER_RE.exec(version.trim()) : null;
    if (!match) return 0;
    const [major, minor, patch] = match.slice(1, 4).map((part) => Number.parseInt(part, 10));
    return major * 1000000 + minor * 1000 + patch;
};

/**
 * Shape the `/app/check-update` response into what the updater needs.
 * The server is the source of truth for the download URL and checksum; nothing is hardcoded here.
 * A release without a valid SHA-256 can never be installed, so it can never be forced either: a broken
 * release must not lock the POS behind a modal that has no way forward.
 */
export const normalizeUpdateResponse = (data, currentVersionCode) => {
    const payload = data && typeof data === 'object' ? data : {};
    const versionCode = toInt(payload.latest_version_code);
    const hasUpdate = !!payload.has_update && versionCode > toInt(currentVersionCode);
    const checksum = isValidSha256(payload.checksum) ? payload.checksum.toLowerCase() : null;

    return {
        hasUpdate,
        isForce: hasUpdate && !!payload.force_update && checksum !== null,
        versionName: typeof payload.latest_version === 'string' ? payload.latest_version : null,
        versionCode,
        downloadUrl: safeHttpUrl(payload.download_url),
        manualDownloadUrl: safeManualUrl(payload.manual_download_url),
        checksum,
        sizeBytes: Math.max(0, toInt(payload.file_size_bytes)),
        fileSize: typeof payload.file_size === 'string' ? payload.file_size : null,
        releaseNotes: Array.isArray(payload.release_notes) ? payload.release_notes.filter(Boolean) : [],
        publishedAt: typeof payload.published_at === 'string' ? payload.published_at : null,
    };
};

/**
 * A release for the public manual-download page. Only releases with a download link AND a published SHA-256
 * are shown: the page tells users to compare that checksum before installing.
 */
export const normalizeDownloadRelease = (data, platform) => {
    const payload = data && typeof data === 'object' ? data : {};
    const checksum = isValidSha256(payload.checksum) ? payload.checksum.toLowerCase() : null;
    const downloadUrl = safeHttpUrl(payload.download_url);
    const versionCode = toInt(payload.latest_version_code);
    if (!checksum || !downloadUrl || versionCode <= 0) return null;

    return {
        platform,
        versionName: typeof payload.latest_version === 'string' ? payload.latest_version : null,
        versionCode,
        downloadUrl,
        checksum,
        sizeBytes: Math.max(0, toInt(payload.file_size_bytes)),
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
    SIGNATURE_UNVERIFIABLE: 'app_update.error_signature_unverifiable',
    PACKAGE_MISMATCH: 'app_update.error_package_mismatch',
    INSTALL_PERMISSION_REQUIRED: 'app_update.error_install_permission',
    INSECURE_URL: 'app_update.error_missing_url',
    INVALID_ARGS: 'app_update.error_missing_checksum',
    FILE_TOO_LARGE: 'app_update.error_download_failed',
    DOWNLOAD_FAILED: 'app_update.error_download_failed',
    INSTALL_FAILED: 'app_update.error_install_failed',
};

export const nativeErrorKey = (code) => NATIVE_ERROR_KEYS[code] || 'app_update.error_download_failed';

/**
 * The Electron main process only ever sends `app_update.*` codes. Anything outside this list (an older desktop
 * build, a raw exception message) is shown as a generic download failure, never echoed to the user.
 */
const DESKTOP_ERROR_KEYS = new Set([
    'app_update.error_checksum_mismatch',
    'app_update.error_size_mismatch',
    'app_update.error_missing_checksum',
    'app_update.error_missing_url',
    'app_update.error_manifest_invalid',
    'app_update.error_installer_signature',
    'app_update.error_update_attempts_exhausted',
    'app_update.error_update_in_progress',
    'app_update.error_updater_unavailable',
    'app_update.error_install_failed',
    'app_update.error_download_failed',
]);

export const DESKTOP_ALREADY_LATEST = 'app_update.already_latest';

export const desktopErrorKey = (code) =>
    typeof code === 'string' && DESKTOP_ERROR_KEYS.has(code) ? code : 'app_update.error_download_failed';

/** Errors after which the user is offered the manual download page instead of a dead end. */
const MANUAL_DOWNLOAD_ERRORS = new Set([
    'app_update.error_updater_unavailable',
    'app_update.error_signature_unverifiable',
    'app_update.error_update_attempts_exhausted',
]);

export const offersManualDownload = (key) => MANUAL_DOWNLOAD_ERRORS.has(key);

const STAGES = ['downloading', 'verifying', 'installer_opened', 'error'];

export const stageKey = (stage) => (STAGES.includes(stage) ? `app_update.stage_${stage}` : null);

/** Megabytes with one decimal for progress labels (e.g. "12.4"). */
export const bytesToMegabytes = (bytes) => (Math.max(0, Number(bytes) || 0) / 1048576).toFixed(1);
