'use strict';

// Pure verification helpers for the native updater. Must not require electron
// so they can be unit-tested with node:test.

const crypto = require('crypto');
const fs = require('fs');
const { CENTRAL_ORIGIN, isAllowedAppUrl } = require('../security/urlPolicy');

const SHA256_HEX_PATTERN = /^[a-f0-9]{64}$/i;

// Fails closed: a manifest without a well-formed SHA-256 digest is refused.
function extractChecksum(manifest) {
    if (!manifest || typeof manifest !== 'object') {
        throw new Error('update_manifest_missing_checksum');
    }
    const raw = manifest.checksum ?? manifest.sha256 ?? manifest.latest_version_checksum;
    if (typeof raw !== 'string' || !SHA256_HEX_PATTERN.test(raw.trim())) {
        throw new Error('update_manifest_missing_checksum');
    }
    return raw.trim().toLowerCase();
}

// Returns the absolute redirect target only when it stays on an allowed
// https official host; null otherwise.
function resolveRedirect(currentUrl, location) {
    if (typeof location !== 'string' || location.trim() === '') return null;
    let resolved;
    try {
        resolved = new URL(location, currentUrl).toString();
    } catch {
        return null;
    }
    return isAllowedAppUrl(resolved) ? resolved : null;
}

function digestEquals(actualHex, expectedHex) {
    if (typeof expectedHex !== 'string' || !SHA256_HEX_PATTERN.test(expectedHex)) return false;
    if (typeof actualHex !== 'string' || !SHA256_HEX_PATTERN.test(actualHex)) return false;
    const a = Buffer.from(actualHex.toLowerCase(), 'hex');
    const b = Buffer.from(expectedHex.toLowerCase(), 'hex');
    return a.length === b.length && crypto.timingSafeEqual(a, b);
}

function sha256File(filePath) {
    return new Promise((resolve, reject) => {
        const hash = crypto.createHash('sha256');
        const stream = fs.createReadStream(filePath);
        stream.on('error', reject);
        stream.on('data', (chunk) => hash.update(chunk));
        stream.on('end', () => resolve(hash.digest('hex')));
    });
}

async function verifySha256(filePath, expectedHex) {
    if (typeof expectedHex !== 'string' || !SHA256_HEX_PATTERN.test(expectedHex)) return false;
    try {
        return digestEquals(await sha256File(filePath), expectedHex);
    } catch {
        return false;
    }
}

// Hard ceiling for any installer download, whatever the manifest says.
const DOWNLOAD_MAX_BYTES = 300 * 1024 * 1024;

// The only messages the renderer ever receives from the updater (translation keys).
const RENDERER_CODES = new Set([
    'app_update.already_latest',
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

/** Error carrying a renderer code; anything else is reported as a generic download failure. */
function updaterError(code, detail) {
    const err = new Error(detail || code);
    err.code = code;
    return err;
}

function toRendererCode(err) {
    const code = err && err.code;
    return typeof code === 'string' && RENDERER_CODES.has(code) ? code : 'app_update.error_download_failed';
}

function positiveInt(value) {
    const n = typeof value === 'string' && /^\d+$/.test(value.trim()) ? Number(value) : value;
    return Number.isInteger(n) && n > 0 ? n : null;
}

function safeVersionLabel(value) {
    return (
        String(value || '')
            .replace(/[^0-9A-Za-z.+-]/g, '')
            .slice(0, 32) || '?'
    );
}

function releaseTarget(raw) {
    const sha256 = (() => {
        try {
            return extractChecksum(raw);
        } catch {
            return null;
        }
    })();
    if (!sha256) return { ok: false, code: 'app_update.error_missing_checksum' };
    if (!isAllowedAppUrl(raw.download_url)) return { ok: false, code: 'app_update.error_missing_url' };
    const sizeBytes = positiveInt(raw.file_size_bytes);
    if (!sizeBytes || sizeBytes > DOWNLOAD_MAX_BYTES) return { ok: false, code: 'app_update.error_manifest_invalid' };
    return { ok: true, sha256, sizeBytes, downloadUrl: raw.download_url };
}

/**
 * The release to install from a check-update manifest, or the reason not to.
 * Fails closed: no checksum, no size, or a URL outside the allow-list refuses the update.
 * @returns {{ok: true, release: object} | {ok: false, code: string}}
 */
function releaseFromManifest(manifest, currentVersionCode) {
    if (!manifest || typeof manifest !== 'object' || Array.isArray(manifest)) {
        return { ok: false, code: 'app_update.error_manifest_invalid' };
    }
    const versionCode = positiveInt(manifest.latest_version_code);
    if (!manifest.has_update || !versionCode || versionCode <= currentVersionCode) {
        return { ok: false, code: 'app_update.already_latest' };
    }
    const target = releaseTarget(manifest);
    if (!target.ok) return target;
    return {
        ok: true,
        release: {
            version: safeVersionLabel(manifest.latest_version),
            versionCode,
            downloadUrl: target.downloadUrl,
            sha256: target.sha256,
            sizeBytes: target.sizeBytes,
        },
    };
}

/** The previous verified release advertised by the server (manifest.rollback), or null. */
function rollbackFromManifest(manifest, currentVersionCode) {
    const raw = manifest && typeof manifest === 'object' ? manifest.rollback : null;
    if (!raw || typeof raw !== 'object' || Array.isArray(raw)) return null;
    const versionCode = positiveInt(raw.version_code);
    if (!versionCode || versionCode >= currentVersionCode) return null;
    const target = releaseTarget(raw);
    if (!target.ok) return null;
    return {
        version: safeVersionLabel(raw.version),
        versionCode,
        downloadUrl: target.downloadUrl,
        sha256: target.sha256,
        sizeBytes: target.sizeBytes,
    };
}

/** The public manual download page; only an allow-listed https URL is ever opened. */
function manualDownloadUrl(manifest) {
    const url = manifest && typeof manifest === 'object' ? manifest.manual_download_url : null;
    return isAllowedAppUrl(url) ? url : `${CENTRAL_ORIGIN}/download`;
}

module.exports = {
    SHA256_HEX_PATTERN,
    DOWNLOAD_MAX_BYTES,
    RENDERER_CODES,
    updaterError,
    toRendererCode,
    releaseFromManifest,
    rollbackFromManifest,
    manualDownloadUrl,
    extractChecksum,
    resolveRedirect,
    digestEquals,
    sha256File,
    verifySha256,
};
