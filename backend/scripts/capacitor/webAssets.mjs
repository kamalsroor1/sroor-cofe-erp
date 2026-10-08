// APP-2: Capacitor webDir is an allow-listed copy of the native shell, never Laravel's public/.
// public/ also holds server-only files (update_webhook.php, index.php, .htaccess, APK downloads,
// the Vite `hot` file) that must not be packaged into the APK.
import {
    closeSync,
    cpSync,
    existsSync,
    fstatSync,
    mkdirSync,
    openSync,
    readSync,
    readdirSync,
    rmSync,
    statSync,
} from 'node:fs';
import path from 'node:path';

/** Capacitor `webDir`, relative to backend/. Generated, git-ignored. */
export const WEB_DIR = 'capacitor-www';

/**
 * The only files from public/ that ship inside the app. The shell index.html redirects the
 * WebView to the server, so no Vite bundle or Laravel entry point is needed locally.
 */
export const ALLOWED_FILES = Object.freeze([
    'index.html',
    'favicon.ico',
    'logo.png',
    'logo-dark.png',
    'logo-light.png',
    'manifest.json',
]);

/** Files Capacitor itself injects into assets/public during `cap copy`. */
const CAPACITOR_INJECTED = Object.freeze(['cordova.js', 'cordova_plugins.js']);

const APK_WEB_PREFIX = 'assets/public/';
const ALWAYS_FORBIDDEN = /(^|\/)(\.env(\..*)?|\.htaccess|hot|[^/]+\.(php|apk|aab|sql|key|jks|keystore|pem|p12|pfx))$/i;

/**
 * Rebuild `outDir` from scratch with only ALLOWED_FILES from `sourceDir`.
 * @returns {string[]} the copied file names
 */
export function buildCapacitorWeb({ sourceDir, outDir }) {
    if (!existsSync(path.join(sourceDir, 'index.html'))) {
        throw new Error(`Capacitor shell entry point missing: ${path.join(sourceDir, 'index.html')}`);
    }
    rmSync(outDir, { recursive: true, force: true });
    mkdirSync(outDir, { recursive: true });

    const copied = [];
    for (const name of ALLOWED_FILES) {
        const from = path.join(sourceDir, name);
        if (!existsSync(from) || !statSync(from).isFile()) continue;
        cpSync(from, path.join(outDir, name));
        copied.push(name);
    }
    return copied;
}

/**
 * Entries (APK/zip-style forward-slash paths) that must not be in the app:
 * anything under assets/public/ outside the allow-list, plus secrets/server files anywhere.
 */
export function findForbiddenEntries(entries) {
    const allowedWeb = new Set([...ALLOWED_FILES, ...CAPACITOR_INJECTED].map((name) => APK_WEB_PREFIX + name));
    return entries.filter((entry) => {
        if (ALWAYS_FORBIDDEN.test(entry)) return true;
        return entry.startsWith(APK_WEB_PREFIX) && !allowedWeb.has(entry);
    });
}

/** List entry names of a zip/APK by reading its central directory (no extraction). */
export function listZipEntries(file) {
    const fd = openSync(file, 'r');
    try {
        const size = fstatSync(fd).size;
        const tailLength = Math.min(size, 22 + 0xffff);
        const tail = Buffer.alloc(tailLength);
        readSync(fd, tail, 0, tailLength, size - tailLength);

        let eocd = -1;
        for (let i = tailLength - 22; i >= 0; i--) {
            if (tail.readUInt32LE(i) === 0x06054b50) {
                eocd = i;
                break;
            }
        }
        if (eocd < 0) throw new Error(`Not a zip/APK file: ${file}`);

        let count = tail.readUInt16LE(eocd + 10);
        let cdSize = tail.readUInt32LE(eocd + 12);
        let cdOffset = tail.readUInt32LE(eocd + 16);

        // ZIP64 (APKs over 4 GB or with > 65535 entries).
        if (cdOffset === 0xffffffff || count === 0xffff) {
            const locator = eocd - 20;
            if (locator < 0 || tail.readUInt32LE(locator) !== 0x07064b50) throw new Error(`Unsupported zip: ${file}`);
            const z64 = Buffer.alloc(56);
            readSync(fd, z64, 0, 56, Number(tail.readBigUInt64LE(locator + 8)));
            count = Number(z64.readBigUInt64LE(32));
            cdSize = Number(z64.readBigUInt64LE(40));
            cdOffset = Number(z64.readBigUInt64LE(48));
        }

        const cd = Buffer.alloc(cdSize);
        readSync(fd, cd, 0, cdSize, cdOffset);
        const names = [];
        let p = 0;
        for (let i = 0; i < count; i++) {
            if (cd.readUInt32LE(p) !== 0x02014b50) throw new Error(`Corrupt central directory: ${file}`);
            const nameLength = cd.readUInt16LE(p + 28);
            const extraLength = cd.readUInt16LE(p + 30);
            const commentLength = cd.readUInt16LE(p + 32);
            names.push(cd.toString('utf8', p + 46, p + 46 + nameLength));
            p += 46 + nameLength + extraLength + commentLength;
        }
        return names;
    } finally {
        closeSync(fd);
    }
}

/** List files under `dir` recursively as `<prefix>/<relative/path>` (APK-style). */
export function listDirEntries(dir, prefix) {
    const out = [];
    const walk = (current, rel) => {
        for (const dirent of readdirSync(current, { withFileTypes: true })) {
            const relPath = rel ? `${rel}/${dirent.name}` : dirent.name;
            if (dirent.isDirectory()) walk(path.join(current, dirent.name), relPath);
            else out.push(`${prefix}/${relPath}`);
        }
    };
    walk(dir, '');
    return out;
}
