'use strict';

// Pure verification helpers for the native updater. Must not require electron
// so they can be unit-tested with node:test.

const crypto = require('crypto');
const fs = require('fs');
const { isAllowedAppUrl } = require('../security/urlPolicy');

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

module.exports = {
    SHA256_HEX_PATTERN,
    extractChecksum,
    resolveRedirect,
    digestEquals,
    sha256File,
    verifySha256,
};
