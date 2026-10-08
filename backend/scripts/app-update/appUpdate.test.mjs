// APP-3: real Android update (download + SHA-256 + signer check) without the forced-update loop.
// Run: node --test scripts/app-update/appUpdate.test.mjs   (also picked up by `npm run test:scripts`)
import { test, describe } from 'node:test';
import assert from 'node:assert/strict';

import {
    isValidSha256,
    normalizeUpdateResponse,
    progressPercent,
    nativeErrorKey,
    stageKey,
    bytesToMegabytes,
} from '../../resources/js/helpers/appUpdate.js';
import { createAppUpdater } from '../../resources/js/Composables/appUpdate/createAppUpdater.js';

const SHA = 'a'.repeat(64);

const serverUpdate = (overrides = {}) => ({
    success: true,
    has_update: true,
    force_update: false,
    latest_version: '1.0.5',
    latest_version_code: 6,
    download_url: 'https://central.example.test/api/v1/app/download-latest-apk?platform=android',
    file_size: '20 MB',
    file_size_bytes: 20971520,
    checksum: SHA,
    release_notes: ['note'],
    release_notes_ar: 'note',
    published_at: '2026-10-01 10:00:00',
    ...overrides,
});

const memoryStorage = () => {
    const map = new Map();
    return {
        getItem: (k) => (map.has(k) ? map.get(k) : null),
        setItem: (k, v) => map.set(k, String(v)),
        removeItem: (k) => map.delete(k),
        dump: () => Object.fromEntries(map),
    };
};

const fakeNative = (impl) => {
    const listeners = [];
    const calls = [];
    return {
        calls,
        emit: (p) => listeners.forEach((cb) => cb(p)),
        addProgressListener: async (cb) => {
            listeners.push(cb);
            return () => listeners.splice(listeners.indexOf(cb), 1);
        },
        downloadAndInstall: async (opts) => {
            calls.push(opts);
            return impl ? impl(opts, (p) => listeners.forEach((cb) => cb(p))) : { status: 'installer_opened' };
        },
    };
};

const makeUpdater = ({
    platform = 'android',
    current = { name: '1.0.2', code: 3 },
    response = serverUpdate(),
    native = fakeNative(),
    legacyDownload = null,
    desktop = null,
    storage = memoryStorage(),
    session = memoryStorage(),
} = {}) => {
    const requests = [];
    const notices = [];
    const updater = createAppUpdater({
        platform,
        getCurrentVersion: async () => current,
        fetchUpdate: async (params) => {
            requests.push(params);
            if (response instanceof Error) throw response;
            return response;
        },
        native,
        legacyDownload,
        desktop,
        storage,
        session,
        notify: (type, key, params) => notices.push({ type, key, params }),
    });
    return { updater, requests, notices, native, storage, session };
};

describe('helpers/appUpdate', () => {
    test('isValidSha256 accepts only 64 hex chars', () => {
        assert.equal(isValidSha256(SHA), true);
        assert.equal(isValidSha256('A'.repeat(64)), true);
        assert.equal(isValidSha256('a'.repeat(63)), false);
        assert.equal(isValidSha256('z'.repeat(64)), false);
        assert.equal(isValidSha256(null), false);
    });

    test('normalizeUpdateResponse only reports an update when the server code is newer', () => {
        const n = normalizeUpdateResponse(serverUpdate(), 3);
        assert.equal(n.hasUpdate, true);
        assert.equal(n.versionCode, 6);
        assert.equal(n.checksum, SHA);
        assert.equal(n.sizeBytes, 20971520);
        assert.equal(n.downloadUrl, serverUpdate().download_url);

        const same = normalizeUpdateResponse(serverUpdate({ latest_version_code: 3 }), 3);
        assert.equal(same.hasUpdate, false);
        assert.equal(same.isForce, false);
    });

    test('normalizeUpdateResponse drops non-http download urls and malformed checksums', () => {
        const n = normalizeUpdateResponse(serverUpdate({ download_url: 'javascript:alert(1)', checksum: 'xyz' }), 3);
        assert.equal(n.downloadUrl, null);
        assert.equal(n.checksum, null);
    });

    test('progressPercent reflects real bytes and clamps', () => {
        assert.equal(progressPercent(0, 200), 0);
        assert.equal(progressPercent(50, 200), 25);
        assert.equal(progressPercent(300, 200), 100);
        assert.equal(progressPercent(10, 0), null);
        assert.equal(progressPercent(10, undefined), null);
    });

    test('native error codes map to translation keys', () => {
        assert.equal(nativeErrorKey('CHECKSUM_MISMATCH'), 'app_update.error_checksum_mismatch');
        assert.equal(nativeErrorKey('SIGNATURE_MISMATCH'), 'app_update.error_signature_mismatch');
        assert.equal(nativeErrorKey('PACKAGE_MISMATCH'), 'app_update.error_package_mismatch');
        assert.equal(nativeErrorKey('INSTALL_PERMISSION_REQUIRED'), 'app_update.error_install_permission');
        assert.equal(nativeErrorKey('SOMETHING_ELSE'), 'app_update.error_download_failed');
        assert.equal(stageKey('verifying'), 'app_update.stage_verifying');
        assert.equal(stageKey('idle'), null);
    });

    test('bytesToMegabytes formats real byte counts', () => {
        assert.equal(bytesToMegabytes(0), '0.0');
        assert.equal(bytesToMegabytes(1048576 * 12.4), '12.4');
        assert.equal(bytesToMegabytes(-5), '0.0');
    });
});

describe('createAppUpdater', () => {
    test('opens the modal when a newer version exists', async () => {
        const { updater, requests } = makeUpdater();
        await updater.checkForUpdates();
        assert.equal(requests.length, 1);
        assert.deepEqual(requests[0], { platform: 'android', version_code: 3, version_name: '1.0.2' });
        assert.equal(updater.hasUpdate.value, true);
        assert.equal(updater.isModalOpen.value, true);
    });

    test('forced update: automatic check runs at most once per session for the same installed version', async () => {
        const session = memoryStorage();
        const first = makeUpdater({ response: serverUpdate({ force_update: true }), session });
        await first.updater.checkForUpdates();
        assert.equal(first.requests.length, 1);
        assert.equal(first.updater.isForceUpdate.value, true);

        // simulate a page reload: new updater instance, same session storage
        const second = makeUpdater({ response: serverUpdate({ force_update: true }), session });
        await second.updater.checkForUpdates();
        assert.equal(second.requests.length, 0);
        // ...but the reload must not unlock the outdated build: the forced modal is restored from the session
        assert.equal(second.updater.isModalOpen.value, true);
        assert.equal(second.updater.isForceUpdate.value, true);
        assert.equal(second.updater.hasUpdate.value, true);
        second.updater.closeModal();
        assert.equal(second.updater.isModalOpen.value, true);

        // a manual check is always allowed
        await second.updater.checkForUpdates(true);
        assert.equal(second.requests.length, 1);
    });

    test('optional update is not restored as forced after a reload', async () => {
        const session = memoryStorage();
        const first = makeUpdater({ session });
        await first.updater.checkForUpdates();
        const second = makeUpdater({ session });
        await second.updater.checkForUpdates();
        assert.equal(second.requests.length, 0);
        assert.equal(second.updater.isForceUpdate.value, false);
        assert.equal(second.updater.isModalOpen.value, false);
    });

    test('a manual check that lifts the forced flag also clears the stored forced state', async () => {
        const session = memoryStorage();
        const first = makeUpdater({ response: serverUpdate({ force_update: true }), session });
        await first.updater.checkForUpdates();
        const relaxed = makeUpdater({ response: serverUpdate({ force_update: false }), session });
        await relaxed.updater.checkForUpdates(true);
        const reloaded = makeUpdater({ session });
        await reloaded.updater.checkForUpdates();
        assert.equal(reloaded.requests.length, 0);
        assert.equal(reloaded.updater.isForceUpdate.value, false);
        assert.equal(reloaded.updater.isModalOpen.value, false);
    });

    test('native plugin unavailable + force update leads to the legacy download path, not a dead end', async () => {
        const opened = [];
        const { updater } = makeUpdater({
            response: serverUpdate({ force_update: true }),
            native: null,
            legacyDownload: (url) => opened.push(url),
        });
        await updater.checkForUpdates();
        assert.equal(updater.isModalOpen.value, true);
        await updater.startDownloadAndInstall();
        assert.deepEqual(opened, [serverUpdate().download_url]);
        assert.equal(updater.errorKey.value, 'app_update.error_updater_unavailable');
        assert.equal(updater.isDownloading.value, false);

        // retrying re-opens the legacy download instead of locking the POS behind a network error
        await updater.startDownloadAndInstall();
        assert.equal(opened.length, 2);
    });

    test('forced update cannot be dismissed', async () => {
        const { updater } = makeUpdater({ response: serverUpdate({ force_update: true }) });
        await updater.checkForUpdates();
        updater.closeModal();
        assert.equal(updater.isModalOpen.value, true);
    });

    test('dismissed optional update is not auto-prompted again for that version', async () => {
        const storage = memoryStorage();
        const a = makeUpdater({ storage });
        await a.updater.checkForUpdates();
        a.updater.closeModal();
        assert.equal(a.updater.isModalOpen.value, false);

        const b = makeUpdater({ storage });
        await b.updater.checkForUpdates();
        assert.equal(b.updater.hasUpdate.value, true);
        assert.equal(b.updater.isModalOpen.value, false);
    });

    test('passes url + checksum + size from the API to the native installer', async () => {
        const { updater, native } = makeUpdater();
        await updater.checkForUpdates();
        await updater.startDownloadAndInstall();
        assert.equal(native.calls.length, 1);
        assert.deepEqual(native.calls[0], {
            url: serverUpdate().download_url,
            sha256: SHA,
            expectedSize: 20971520,
            versionCode: 6,
        });
        assert.equal(updater.stage.value, 'installer_opened');
        assert.equal(updater.isDownloaded.value, true);
        assert.equal(updater.errorKey.value, null);
    });

    test('progress reflects the bytes reported by the native downloader', async () => {
        const seen = [];
        const native = fakeNative(async (_opts, emit) => {
            emit({ bytes: 0, total: 1000 });
            seen.push(updaterRef.downloadProgress.value);
            emit({ bytes: 250, total: 1000 });
            seen.push(updaterRef.downloadProgress.value);
            emit({ bytes: 1000, total: 1000 });
            seen.push(updaterRef.downloadProgress.value);
            return { status: 'installer_opened' };
        });
        const { updater } = makeUpdater({ native });
        const updaterRef = updater;
        await updater.checkForUpdates();
        await updater.startDownloadAndInstall();
        assert.deepEqual(seen, [0, 25, 100]);
        assert.equal(updater.downloadedBytes.value, 1000);
        assert.equal(updater.totalBytes.value, 1000);
    });

    test('checksum mismatch from native refuses the install with a translated message key', async () => {
        const native = fakeNative(async () => {
            const err = new Error('checksum');
            err.code = 'CHECKSUM_MISMATCH';
            throw err;
        });
        const { updater } = makeUpdater({ native });
        await updater.checkForUpdates();
        await updater.startDownloadAndInstall();
        assert.equal(updater.stage.value, 'error');
        assert.equal(updater.errorKey.value, 'app_update.error_checksum_mismatch');
        assert.equal(updater.isDownloaded.value, false);
        assert.equal(updater.isDownloading.value, false);
    });

    test('signer mismatch from native surfaces a translated message key', async () => {
        const native = fakeNative(async () => {
            throw Object.assign(new Error('sig'), { code: 'SIGNATURE_MISMATCH' });
        });
        const { updater } = makeUpdater({ native });
        await updater.checkForUpdates();
        await updater.startDownloadAndInstall();
        assert.equal(updater.errorKey.value, 'app_update.error_signature_mismatch');
    });

    test('a missing or malformed checksum never reaches the installer', async () => {
        const { updater, native } = makeUpdater({ response: serverUpdate({ checksum: null }) });
        await updater.checkForUpdates();
        await updater.startDownloadAndInstall();
        assert.equal(native.calls.length, 0);
        assert.equal(updater.errorKey.value, 'app_update.error_missing_checksum');
    });

    test('a missing download url never reaches the installer (no hardcoded fallback)', async () => {
        const { updater, native } = makeUpdater({ response: serverUpdate({ download_url: null }) });
        await updater.checkForUpdates();
        await updater.startDownloadAndInstall();
        assert.equal(native.calls.length, 0);
        assert.equal(updater.errorKey.value, 'app_update.error_missing_url');
    });

    test('concurrent taps start only one download', async () => {
        let release;
        const native = fakeNative(
            () =>
                new Promise((resolve) => {
                    release = () => resolve({ status: 'installer_opened' });
                })
        );
        const { updater } = makeUpdater({ native });
        await updater.checkForUpdates();
        const p1 = updater.startDownloadAndInstall();
        const p2 = updater.startDownloadAndInstall();
        await new Promise((r) => setTimeout(r, 0));
        release();
        await Promise.all([p1, p2]);
        assert.equal(native.calls.length, 1);
    });

    test('install never spoofs the current version (no fake bump, no reload loop)', async () => {
        const { updater, storage } = makeUpdater({ response: serverUpdate({ force_update: true }) });
        await updater.checkForUpdates();
        await updater.startDownloadAndInstall();
        assert.equal(updater.currentVersionCode.value, 3);
        assert.equal(updater.currentVersionName.value, '1.0.2');
        assert.equal(storage.getItem('sroor_app_version_code'), null);
    });

    test('web platform never calls the update API', async () => {
        const { updater, requests, notices } = makeUpdater({ platform: 'web' });
        await updater.checkForUpdates();
        await updater.checkForUpdates(true);
        assert.equal(requests.length, 0);
        assert.equal(notices.length, 1);
        assert.equal(notices[0].key, 'app_update.web_auto_updated');
    });

    test('manual check failure notifies a translated error', async () => {
        const { updater, notices } = makeUpdater({ response: new Error('offline') });
        await updater.checkForUpdates(true);
        assert.equal(notices.at(-1).type, 'error');
        assert.equal(notices.at(-1).key, 'app_update.check_failed');
        assert.equal(updater.isChecking.value, false);
    });

    test('desktop without the native updater bridge reports an error instead of faking progress', async () => {
        const { updater } = makeUpdater({ platform: 'windows', desktop: null });
        await updater.checkForUpdates();
        await updater.startDownloadAndInstall();
        assert.equal(updater.errorKey.value, 'app_update.error_updater_unavailable');
        assert.equal(updater.downloadProgress.value, 0);
    });
});
