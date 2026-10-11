'use strict';

// APP-7: Authenticode check of a downloaded installer, before it is started.
//
//   readAuthenticodeSignature(file)  -> runs Get-AuthenticodeSignature (Windows PowerShell,
//                                       absolute System32 path, file passed via env var, never
//                                       interpolated into the command) and returns
//                                       { status, subject, thumbprint } or null.
//   evaluateSignature(result, policy) -> pure decision, unit tested.
//
// Pure apart from the injected execFile: must not require electron.

const path = require('path');
const childProcess = require('child_process');

// System.Management.Automation.SignatureStatus, in enum order (ConvertTo-Json may emit the number).
const STATUS_NAMES = Object.freeze([
    'Valid',
    'UnknownError',
    'NotSigned',
    'HashMismatch',
    'NotTrusted',
    'NotSupportedFileFormat',
    'Incompatible',
]);

const FILE_ENV = 'SROOR_UPDATE_FILE';
const PS_SCRIPT = [
    "$ErrorActionPreference = 'Stop'",
    `$s = Get-AuthenticodeSignature -LiteralPath $env:${FILE_ENV}`,
    '$c = $s.SignerCertificate',
    '[pscustomobject]@{ Status = $s.Status.ToString(); Subject = $c.Subject; Thumbprint = $c.Thumbprint } | ConvertTo-Json -Compress',
].join('; ');
const DEFAULT_TIMEOUT_MS = 20000;

function normalizeStatus(raw) {
    if (typeof raw === 'number' && Number.isInteger(raw)) return STATUS_NAMES[raw] || 'UnknownError';
    if (typeof raw === 'string') {
        const found = STATUS_NAMES.find((name) => name.toLowerCase() === raw.trim().toLowerCase());
        return found || 'UnknownError';
    }
    return 'UnknownError';
}

function normalizeThumbprint(value) {
    return typeof value === 'string' ? value.replace(/[^0-9a-f]/gi, '').toUpperCase() : '';
}

function textOrNull(value) {
    return typeof value === 'string' && value.trim() !== '' ? value.trim() : null;
}

/** Parses the PowerShell JSON line into { status, subject, thumbprint }; null when unusable. */
function parseSignatureJson(stdout) {
    if (typeof stdout !== 'string' || stdout.trim() === '') return null;
    let raw;
    try {
        raw = JSON.parse(stdout.trim());
    } catch {
        return null;
    }
    if (!raw || typeof raw !== 'object' || Array.isArray(raw)) return null;
    return {
        status: normalizeStatus(raw.Status ?? raw.status),
        subject: textOrNull(raw.Subject ?? raw.subject),
        thumbprint: normalizeThumbprint(raw.Thumbprint ?? raw.thumbprint) || null,
    };
}

function publisherMatches(entry, signature) {
    const thumbprint = normalizeThumbprint(signature.thumbprint);
    if (typeof entry === 'string') {
        const wanted = entry.trim();
        if (wanted === '') return false;
        return normalizeThumbprint(wanted) === thumbprint || wanted === signature.subject;
    }
    if (!entry || typeof entry !== 'object') return false;
    const wantThumb = normalizeThumbprint(entry.thumbprint);
    const wantSubject = textOrNull(entry.subject);
    if (!wantThumb && !wantSubject) return false;
    if (wantThumb && wantThumb !== thumbprint) return false;
    if (wantSubject && wantSubject !== signature.subject) return false;
    return true;
}

/**
 * Decides whether an installer may run.
 * @param {{status: string, subject: string|null, thumbprint: string|null}|null} result
 * @param {{requireAuthenticode?: boolean, trustedPublishers?: Array<string|{thumbprint?: string, subject?: string}>}} policy
 * @returns {{ok: boolean, reason: string}}
 */
function evaluateSignature(result, policy) {
    const status = result ? normalizeStatus(result.status) : null;

    // A signature that exists but no longer matches the file means the file was altered after signing.
    if (status === 'HashMismatch') return { ok: false, reason: 'hash_mismatch' };

    const enforce = Boolean(policy && policy.requireAuthenticode === true);
    if (!enforce) return { ok: true, reason: 'not_enforced' };

    if (!result) return { ok: false, reason: 'signature_unreadable' };
    if (status === 'NotSigned') return { ok: false, reason: 'not_signed' };
    if (status !== 'Valid') return { ok: false, reason: 'invalid_signature' };

    const publishers = Array.isArray(policy.trustedPublishers) ? policy.trustedPublishers : [];
    if (publishers.length === 0) return { ok: false, reason: 'no_trusted_publishers' };
    if (!publishers.some((entry) => publisherMatches(entry, result))) {
        return { ok: false, reason: 'publisher_untrusted' };
    }
    return { ok: true, reason: 'trusted_publisher' };
}

function powershellPath(env) {
    const systemRoot = (env && (env.SystemRoot || env.SYSTEMROOT)) || 'C:\\Windows';
    return path.win32.join(systemRoot, 'System32', 'WindowsPowerShell', 'v1.0', 'powershell.exe');
}

/**
 * @returns {Promise<{status: string, subject: string|null, thumbprint: string|null}|null>}
 *          null when the signature could not be read (not Windows, PowerShell failure, timeout)
 */
function readAuthenticodeSignature(filePath, options = {}) {
    const execFile = options.execFile || childProcess.execFile;
    const platform = options.platform || process.platform;
    const env = options.env || process.env;
    const timeout = options.timeoutMs || DEFAULT_TIMEOUT_MS;

    if (platform !== 'win32' || typeof filePath !== 'string' || filePath === '') return Promise.resolve(null);

    return new Promise((resolve) => {
        try {
            execFile(
                powershellPath(env),
                ['-NoProfile', '-NonInteractive', '-ExecutionPolicy', 'Bypass', '-Command', PS_SCRIPT],
                { env: { ...env, [FILE_ENV]: filePath }, timeout, windowsHide: true, maxBuffer: 64 * 1024 },
                (error, stdout) => resolve(error ? null : parseSignatureJson(String(stdout || '')))
            );
        } catch {
            resolve(null);
        }
    });
}

module.exports = {
    STATUS_NAMES,
    PS_SCRIPT,
    normalizeStatus,
    parseSignatureJson,
    evaluateSignature,
    readAuthenticodeSignature,
    powershellPath,
};
