'use strict';

// APP-7: integer version code of a desktop build, identical to the server's
// App\Support\AppReleases\SemverCode: "1.2.3" -> 1002003 (each part 0..999).
// Pure module: must not require electron.

const SEMVER_PATTERN = /^(\d{1,3})\.(\d{1,3})\.(\d{1,3})$/;

/** @returns {number|null} the code, or null when the version is not MAJOR.MINOR.PATCH */
function semverCode(version) {
    if (typeof version !== 'string') return null;
    const match = SEMVER_PATTERN.exec(version.trim());
    if (!match) return null;
    const [major, minor, patch] = match.slice(1, 4).map((part) => Number.parseInt(part, 10));
    return major * 1000000 + minor * 1000 + patch;
}

/** Inverse of semverCode(): 1002003 -> "1.2.3"; null outside 0..999999999. */
function versionFromCode(code) {
    if (!Number.isInteger(code) || code < 0 || code > 999999999) return null;
    return `${Math.floor(code / 1000000)}.${Math.floor((code % 1000000) / 1000)}.${code % 1000}`;
}

module.exports = { SEMVER_PATTERN, semverCode, versionFromCode };
