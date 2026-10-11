// APP-3 ext / APP-7 (W2): checksum mandatory, optional update once per launch, forced update not
// closable, manual download fallback, signer error mapping, desktop error codes.
// Run: node --test tests/js/appUpdater.test.js   (part of `npm run test:js`)
import { test, describe } from 'node:test';
import assert from 'node:assert/strict';

import {
    desktopErrorKey,
    nativeErrorKey,
    normalizeDownloadRelease,
    normalizeUpdateResponse,
    offersManualDownload,
    semverCode,
} from '../../resources/js/helpers/appUpdate.js';
import { createAppUpdater } from '../../resources/js/Composables/appUpdate/createAppUpdater.js';

const SHA = 'b'.repeat(64);
const APK_URL = 'https://baraa-solutions.com/api/v1/app/download-apk?platform=android&version_code=6';
const MANUAL_URL = 'https://baraa-solutions.com/download';

const serverUpdate = (overrides = {}) => ({
    success: true,
    has_update: true,
    force_update: false,
    latest_version: '1.0.5',
    latest_version_code: 6,
    download_url: APK_URL,
    manual_download_url: MANUAL_URL,
    file_size: '20 MB',
    file_size_bytes: 20971520,
    checksum: SHA,
    checksum_algorithm: 'sha256',
    release_notes: ['note'],
    published_at: '2026-10-01T10:00:00+00:00',
    rollback: null,
    ...overrides,
});

const memoryStorage = (initial = {}) => {
    const map = new Map(Object.entries(initial));
    return {
        getItem: (k) => (map.has(k) ? map.get(k) : null),
        setItem: (k, v) => map.set(k, String(v)),
        removeItem: (k) => map.delete(k),
        has: (k) => map.has(k),
    };
};

const rejectingNative = (code) => ({
    calls: [],
    addProgressListener: async () => () => {},
    async downloadAndInstall(opts) {
        this.calls.push(opts);
        throw Object.assign(new Error(code), { code });
    },
});

const makeUpdater = ({
    platform = 'android',
    current = { name: '1.0.2', code: 3 },
    response = serverUpdate(),
    native = { calls: [], addProgressListener: async () => () => {}, downloadAndInstall: async () => ({}) },
    openManualDownload = null,
    manualDownloadFallback = null,
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
            return typeof response === 'function' ? response(params) : response;
        },
        native,
        openManualDownload,
        manualDownloadFallback,
        desktop,
        storage,
        session,
        notify: (type, key, params) => notices.push({ type, key, params }),
    });
    return { updater, requests, notices, storage, session };
};

describe('helpers', () => {
    test('semverCode matches the server SemverCode (1.2.3 -> 1002003) and rejects other shapes', () => {
        assert.equal(semverCode('1.2.3'), 1002003);
        assert.equal(semverCode('1.0.0'), 1000000);
        assert.equal(semverCode('999.999.999'), 999999999);
        assert.equal(semverCode('1.2'), 0);
        assert.equal(semverCode('1.2.3-beta'), 0);
        assert.equal(semverCode(undefined), 0);
    });

    test('a release without checksum is never forced (cannot lock the app behind an uninstallable update)', () => {
        const n = normalizeUpdateResponse(serverUpdate({ force_update: true, checksum: null }), 3);
        assert.equal(n.hasUpdate, true);
        assert.equal(n.isForce, false);
        assert.equal(n.checksum, null);
        assert.equal(normalizeUpdateResponse(serverUpdate({ force_update: true }), 3).isForce, true);
    });

    test('manual_download_url is kept only when it is https (http only on local dev hosts)', () => {
        const manual = (url) =>
            normalizeUpdateResponse(serverUpdate({ manual_download_url: url }), 3).manualDownloadUrl;
        assert.equal(normalizeUpdateResponse(serverUpdate(), 3).manualDownloadUrl, MANUAL_URL);
        assert.equal(manual('javascript:alert(1)'), null);
        assert.equal(manual('http://baraa-solutions.com/download'), null);
        assert.equal(manual('http://evil.example/download'), null);
        assert.equal(manual('http://localhost.evil.example/download'), null);
        assert.equal(manual('http://127.0.0.1.evil.example/download'), null);
        assert.equal(manual('ftp://localhost/download'), null);
        assert.equal(manual('http://localhost:8000/download'), 'http://localhost:8000/download');
        assert.equal(manual('http://127.0.0.1:8000/download'), 'http://127.0.0.1:8000/download');
        assert.equal(manual('http://sroor.test/download'), 'http://sroor.test/download');
    });

    test('signer errors: unverifiable offers the manual page, mismatch is a hard refusal', () => {
        assert.equal(nativeErrorKey('SIGNATURE_UNVERIFIABLE'), 'app_update.error_signature_unverifiable');
        assert.equal(nativeErrorKey('SIGNATURE_MISMATCH'), 'app_update.error_signature_mismatch');
        assert.equal(offersManualDownload('app_update.error_signature_unverifiable'), true);
        assert.equal(offersManualDownload('app_update.error_signature_mismatch'), false);
        assert.equal(offersManualDownload('app_update.error_checksum_mismatch'), false);
        assert.equal(offersManualDownload('app_update.error_updater_unavailable'), true);
        assert.equal(offersManualDownload('app_update.error_update_attempts_exhausted'), true);
    });

    test('desktop codes pass an allow-list; raw messages never reach the UI', () => {
        assert.equal(desktopErrorKey('app_update.error_installer_signature'), 'app_update.error_installer_signature');
        assert.equal(desktopErrorKey('app_update.error_size_mismatch'), 'app_update.error_size_mismatch');
        assert.equal(desktopErrorKey('update_manifest_http_500'), 'app_update.error_download_failed');
        assert.equal(desktopErrorKey('ENOENT C:/Users/x/setup.exe'), 'app_update.error_download_failed');
        assert.equal(desktopErrorKey(undefined), 'app_update.error_download_failed');
    });

    test('download page lists only releases with a link and a checksum', () => {
        const ok = normalizeDownloadRelease(serverUpdate(), 'android');
        assert.deepEqual(ok, {
            platform: 'android',
            versionName: '1.0.5',
            versionCode: 6,
            downloadUrl: APK_URL,
            checksum: SHA,
            sizeBytes: 20971520,
            publishedAt: '2026-10-01T10:00:00+00:00',
        });
        assert.equal(normalizeDownloadRelease(serverUpdate({ checksum: null }), 'android'), null);
        assert.equal(normalizeDownloadRelease(serverUpdate({ download_url: null }), 'windows'), null);
        assert.equal(normalizeDownloadRelease(null, 'windows'), null);
    });
});

describe('once per launch', () => {
    test('a dismissed optional update stays hidden for this launch and is shown again on the next launch', async () => {
        const storage = memoryStorage();
        const session = memoryStorage();
        const first = makeUpdater({ storage, session });
        await first.updater.checkForUpdates();
        assert.equal(first.updater.isModalOpen.value, true);
        first.updater.closeModal();
        assert.equal(first.updater.isModalOpen.value, false);

        // reload inside the same launch (same sessionStorage): no new prompt, no new request
        const reloaded = makeUpdater({ storage, session });
        await reloaded.updater.checkForUpdates();
        assert.equal(reloaded.requests.length, 0);
        assert.equal(reloaded.updater.isModalOpen.value, false);

        // next launch (fresh sessionStorage, same localStorage): asked again
        const relaunched = makeUpdater({ storage, session: memoryStorage() });
        await relaunched.updater.checkForUpdates();
        assert.equal(relaunched.requests.length, 1);
        assert.equal(relaunched.updater.isModalOpen.value, true);
    });

    test('the old permanent dismissal flag is removed and ignored', async () => {
        const storage = memoryStorage({ 'app_update_dismissed:android': '6' });
        const { updater } = makeUpdater({ storage });
        assert.equal(storage.has('app_update_dismissed:android'), false);
        await updater.checkForUpdates();
        assert.equal(updater.isModalOpen.value, true);
    });

    test('closing never writes a permanent flag', async () => {
        const storage = memoryStorage();
        const { updater } = makeUpdater({ storage });
        await updater.checkForUpdates();
        updater.closeModal();
        assert.equal(storage.has('app_update_dismissed:android'), false);
    });

    test('forced update: not closable, restored after a reload without asking the server again', async () => {
        const session = memoryStorage();
        const first = makeUpdater({ response: serverUpdate({ force_update: true }), session });
        await first.updater.checkForUpdates();
        first.updater.closeModal();
        assert.equal(first.updater.isModalOpen.value, true);

        const reloaded = makeUpdater({ response: serverUpdate({ force_update: true }), session });
        await reloaded.updater.checkForUpdates();
        assert.equal(reloaded.requests.length, 0);
        assert.equal(reloaded.updater.isModalOpen.value, true);
        assert.equal(reloaded.updater.isForceUpdate.value, true);
        reloaded.updater.closeModal();
        assert.equal(reloaded.updater.isModalOpen.value, true);
    });
});

describe('manual download fallback', () => {
    test('no AppUpdater plugin: install refused, the manual page (never the APK) is offered', async () => {
        const opened = [];
        const { updater } = makeUpdater({
            response: serverUpdate({ force_update: true }),
            native: null,
            openManualDownload: (url) => opened.push(url),
        });
        await updater.checkForUpdates();
        await updater.startDownloadAndInstall();
        assert.equal(updater.errorKey.value, 'app_update.error_updater_unavailable');
        assert.deepEqual(opened, [], 'nothing is opened before the user asks');
        assert.equal(updater.canDownloadManually.value, true);

        assert.equal(updater.openManualDownloadPage(), true);
        assert.deepEqual(opened, [MANUAL_URL]);
        assert.ok(!opened.includes(APK_URL));
        assert.equal(updater.isModalOpen.value, true, 'a forced update stays visible');
    });

    test('falls back to the local /download page when the server sends no manual url', async () => {
        const opened = [];
        const { updater } = makeUpdater({
            response: serverUpdate({ manual_download_url: null }),
            native: null,
            openManualDownload: (url) => opened.push(url),
            manualDownloadFallback: 'https://2m.baraa-solutions.com/download',
        });
        await updater.checkForUpdates();
        await updater.startDownloadAndInstall();
        updater.openManualDownloadPage();
        assert.deepEqual(opened, ['https://2m.baraa-solutions.com/download']);
    });

    test('SIGNATURE_UNVERIFIABLE refuses and offers the manual page', async () => {
        const { updater } = makeUpdater({
            native: rejectingNative('SIGNATURE_UNVERIFIABLE'),
            openManualDownload: () => {},
        });
        await updater.checkForUpdates();
        await updater.startDownloadAndInstall();
        assert.equal(updater.errorKey.value, 'app_update.error_signature_unverifiable');
        assert.equal(updater.canDownloadManually.value, true);
    });

    test('SIGNATURE_MISMATCH refuses with the support message and no download offer', async () => {
        const { updater } = makeUpdater({
            native: rejectingNative('SIGNATURE_MISMATCH'),
            openManualDownload: () => {},
        });
        await updater.checkForUpdates();
        await updater.startDownloadAndInstall();
        assert.equal(updater.errorKey.value, 'app_update.error_signature_mismatch');
        assert.equal(updater.canDownloadManually.value, false);
        assert.equal(updater.isDownloaded.value, false);
    });

    test('checksum is mandatory: a release without one never reaches the native installer', async () => {
        const native = {
            calls: [],
            addProgressListener: async () => () => {},
            downloadAndInstall: async (o) => native.calls.push(o),
        };
        const { updater } = makeUpdater({ response: serverUpdate({ checksum: 'nope', force_update: true }), native });
        await updater.checkForUpdates();
        assert.equal(updater.isForceUpdate.value, false);
        await updater.startDownloadAndInstall();
        assert.equal(native.calls.length, 0);
        assert.equal(updater.errorKey.value, 'app_update.error_missing_checksum');
    });
});

describe('desktop', () => {
    const desktopBridge = (result) => {
        const listeners = {};
        return {
            calls: 0,
            onProgress: (cb) => (listeners.progress = cb),
            onComplete: (cb) => (listeners.complete = cb),
            onError: (cb) => (listeners.error = cb),
            emit: (name, payload) => listeners[name]?.(payload),
            async downloadAndInstall() {
                this.calls += 1;
                return typeof result === 'function' ? result(this) : result;
            },
        };
    };

    test('sends the SemverCode of the installed build as version_code', async () => {
        const { updater, requests } = makeUpdater({
            platform: 'windows',
            current: { name: '1.2.3', code: semverCode('1.2.3') },
            response: serverUpdate({ latest_version: '1.3.0', latest_version_code: 1003000 }),
            desktop: desktopBridge({ success: true }),
        });
        await updater.checkForUpdates();
        assert.deepEqual(requests[0], { platform: 'windows', version_code: 1002003, version_name: '1.2.3' });
        assert.equal(updater.hasUpdate.value, true);
    });

    test('already_latest from the main process unlocks the app instead of looping', async () => {
        const session = memoryStorage();
        const { updater, notices } = makeUpdater({
            platform: 'windows',
            current: { name: '1.2.3', code: 1002003 },
            response: serverUpdate({ latest_version_code: 1003000, force_update: true }),
            desktop: desktopBridge({ success: false, error: 'app_update.already_latest' }),
            session,
        });
        await updater.checkForUpdates();
        assert.equal(updater.isForceUpdate.value, true);
        await updater.startDownloadAndInstall();
        assert.equal(updater.isModalOpen.value, false);
        assert.equal(updater.hasUpdate.value, false);
        assert.equal(updater.errorKey.value, null);
        assert.equal(notices.at(-1).key, 'app_update.up_to_date_desc');

        const reloaded = makeUpdater({ platform: 'windows', current: { name: '1.2.3', code: 1002003 }, session });
        await reloaded.updater.checkForUpdates();
        assert.equal(reloaded.updater.isModalOpen.value, false, 'the forced state was cleared');
    });

    test('desktop error codes are shown; unknown messages become a generic failure', async () => {
        const make = (error) =>
            makeUpdater({
                platform: 'windows',
                current: { name: '1.2.3', code: 1002003 },
                response: serverUpdate({ latest_version_code: 1003000 }),
                desktop: desktopBridge({ success: false, error }),
            }).updater;

        const signed = make('app_update.error_installer_signature');
        await signed.checkForUpdates();
        await signed.startDownloadAndInstall();
        assert.equal(signed.errorKey.value, 'app_update.error_installer_signature');

        const raw = make('update_download_http_404');
        await raw.checkForUpdates();
        await raw.startDownloadAndInstall();
        assert.equal(raw.errorKey.value, 'app_update.error_download_failed');
    });

    test('attempts exhausted on desktop: the shell opens the page itself, the modal shows no extra button', async () => {
        const { updater } = makeUpdater({
            platform: 'windows',
            current: { name: '1.2.3', code: 1002003 },
            response: serverUpdate({ latest_version_code: 1003000 }),
            desktop: desktopBridge({ success: false, error: 'app_update.error_update_attempts_exhausted' }),
        });
        await updater.checkForUpdates();
        await updater.startDownloadAndInstall();
        assert.equal(updater.errorKey.value, 'app_update.error_update_attempts_exhausted');
        assert.equal(updater.canDownloadManually.value, false);
    });

    test('progress events with the verifying stage are reflected', async () => {
        const bridge = desktopBridge((self) => {
            self.emit('progress', { percent: 42 });
            self.emit('progress', { percent: 100, stage: 'verifying' });
            return new Promise(() => {});
        });
        const { updater } = makeUpdater({
            platform: 'windows',
            current: { name: '1.2.3', code: 1002003 },
            response: serverUpdate({ latest_version_code: 1003000 }),
            desktop: bridge,
        });
        await updater.checkForUpdates();
        updater.startDownloadAndInstall();
        await Promise.resolve();
        assert.equal(updater.downloadProgress.value, 100);
        assert.equal(updater.stage.value, 'verifying');
    });
});
