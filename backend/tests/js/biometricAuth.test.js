// APP-5: Android biometric quick login.
// Run from backend/: node --test tests/js/biometricAuth.test.js
//
// Fixed bugs covered here:
//  - the web/Electron build reported biometrics as available (the plugin's web stub always says yes);
//  - credentials sat in one global, unprotected vault ('erp_secure_vault') that any script could read
//    without a fingerprint, and that was shared across workspaces (tenants);
//  - a stale "enabled" flag survived after the vault was invalidated, so login silently failed forever.
import { test } from 'node:test';
import assert from 'node:assert/strict';
import {
    createBiometricAuth,
    vaultKeyFor,
    LEGACY_VAULT_KEY,
} from '../../resources/js/Composables/biometric/createBiometricAuth.js';

const PROTECTED = 1;

const memoryStorage = (initial = {}) => {
    const map = new Map(Object.entries(initial));
    return {
        getItem: (k) => (map.has(k) ? map.get(k) : null),
        setItem: (k, v) => map.set(k, String(v)),
        removeItem: (k) => map.delete(k),
        dump: () => Object.fromEntries(map),
    };
};

function fakePlugin(overrides = {}) {
    const vault = new Map();
    const calls = [];
    const plugin = {
        vault,
        calls,
        isAvailable: async () => ({ isAvailable: true, strongBiometryIsAvailable: true, biometryType: 3 }),
        isCredentialsSaved: async ({ server }) => ({ isSaved: vault.has(server) }),
        setCredentials: async (opts) => {
            calls.push(['set', opts]);
            vault.set(opts.server, { username: opts.username, password: opts.password, ac: opts.accessControl });
        },
        getSecureCredentials: async (opts) => {
            calls.push(['getSecure', opts]);
            const entry = vault.get(opts.server);
            if (!entry || entry.ac !== PROTECTED) {
                throw Object.assign(new Error('No protected credentials found'), { code: '21' });
            }
            return { username: entry.username, password: entry.password };
        },
        getCredentials: async () => {
            throw new Error('unprotected read must never be used');
        },
        deleteCredentials: async ({ server }) => {
            calls.push(['delete', server]);
            vault.delete(server);
        },
        ...overrides,
    };
    return plugin;
}

function setup({ native = true, plugin = fakePlugin(), storage = memoryStorage(), scope = 'shop-a' } = {}) {
    const notices = [];
    const auth = createBiometricAuth({
        plugin,
        isNative: () => native,
        storage,
        getScope: () => scope,
        accessControl: PROTECTED,
        prompt: (key) => `t:${key}`,
        notify: (type, key) => notices.push([type, key]),
    });
    return { auth, plugin, storage, notices };
}

test('vault keys are scoped per workspace and sanitised', () => {
    assert.equal(vaultKeyFor('shop-a'), 'erp_biometric_vault:shop-a');
    assert.equal(vaultKeyFor('Shop A/../x'), 'erp_biometric_vault:shop-a-..-x');
    assert.equal(vaultKeyFor(''), 'erp_biometric_vault:default');
    assert.notEqual(vaultKeyFor('shop-a'), vaultKeyFor('shop-b'));
});

test('web and Electron never report biometrics as available', async () => {
    const plugin = fakePlugin();
    const { auth } = setup({ native: false, plugin });
    await auth.checkAvailability();
    assert.equal(auth.isAvailable.value, false);
    assert.equal(auth.isBiometricEnabled.value, false);
    assert.equal(await auth.registerBiometrics('u', 'p'), false);
    assert.deepEqual(plugin.calls, []);
});

test('weak-only biometrics (cannot unlock a keystore key) are not offered', async () => {
    const plugin = fakePlugin({
        isAvailable: async () => ({ isAvailable: true, strongBiometryIsAvailable: false }),
    });
    const { auth } = setup({ plugin });
    await auth.checkAvailability();
    assert.equal(auth.isAvailable.value, false);
});

test('register stores biometric-protected credentials in the workspace vault', async () => {
    const { auth, plugin, storage, notices } = setup();
    await auth.checkAvailability();
    assert.equal(auth.isAvailable.value, true);
    assert.equal(await auth.registerBiometrics('cashier@shop', 'secret'), true);
    const [, opts] = plugin.calls.find((c) => c[0] === 'set');
    assert.equal(opts.server, 'erp_biometric_vault:shop-a');
    assert.equal(opts.accessControl, PROTECTED);
    assert.equal(opts.title, 't:auth.biometric_prompt_enable_title');
    assert.equal(opts.negativeButtonText, 't:auth.biometric_prompt_cancel');
    assert.equal(auth.isBiometricEnabled.value, true);
    assert.equal(auth.biometricUser.value, 'cashier@shop');
    assert.equal(storage.getItem('erp_biometric_user:shop-a'), 'cashier@shop');
    assert.deepEqual(notices, [['success', 'auth.biometric_enabled_success']]);
});

test('login reads through the biometric-bound API only', async () => {
    const { auth, plugin } = setup();
    await auth.checkAvailability();
    await auth.registerBiometrics('cashier@shop', 'secret');
    assert.deepEqual(await auth.loginWithBiometrics(), { login: 'cashier@shop', password: 'secret' });
    const [, opts] = plugin.calls.find((c) => c[0] === 'getSecure');
    assert.equal(opts.server, 'erp_biometric_vault:shop-a');
    assert.equal(opts.title, 't:auth.biometric_prompt_login_title');
    assert.equal(auth.isAuthenticating.value, false);
});

test('another workspace on the same device does not see the first one', async () => {
    const plugin = fakePlugin();
    const storage = memoryStorage();
    const a = setup({ plugin, storage, scope: 'shop-a' });
    await a.auth.checkAvailability();
    await a.auth.registerBiometrics('owner-a', 'pa');

    const b = setup({ plugin, storage, scope: 'shop-b' });
    await b.auth.checkAvailability();
    assert.equal(b.auth.isBiometricEnabled.value, false);
    assert.equal(await b.auth.loginWithBiometrics(), null);
});

test('an invalidated vault (new fingerprint enrolled) self-heals the enabled flag', async () => {
    const { auth, plugin, storage } = setup();
    await auth.checkAvailability();
    await auth.registerBiometrics('cashier', 'secret');
    plugin.vault.clear();
    assert.equal(await auth.loginWithBiometrics(), null);
    assert.equal(auth.isBiometricEnabled.value, false);
    assert.equal(storage.getItem('erp_biometric_enabled:shop-a'), null);
});

test('a stale flag without a saved vault entry is cleared on check', async () => {
    const storage = memoryStorage({ 'erp_biometric_enabled:shop-a': '1', 'erp_biometric_user:shop-a': 'x' });
    const { auth } = setup({ storage });
    await auth.checkAvailability();
    assert.equal(auth.isBiometricEnabled.value, false);
    assert.equal(storage.getItem('erp_biometric_enabled:shop-a'), null);
});

test('user cancel keeps biometric login enabled', async () => {
    const plugin = fakePlugin();
    const { auth } = setup({ plugin });
    await auth.checkAvailability();
    await auth.registerBiometrics('cashier', 'secret');
    plugin.getSecureCredentials = async () => {
        throw Object.assign(new Error('cancel'), { code: '16' });
    };
    assert.equal(await auth.loginWithBiometrics(), null);
    assert.equal(auth.isBiometricEnabled.value, true);
});

test('legacy global unprotected vault is wiped and must be re-enrolled', async () => {
    const plugin = fakePlugin();
    plugin.vault.set(LEGACY_VAULT_KEY, { username: 'old', password: 'plain', ac: 0 });
    const storage = memoryStorage({
        erp_biometric_enabled: '1',
        erp_biometric_user: 'old',
        sroor_biometric_enabled: '1',
        sroor_biometric_user: 'old',
    });
    const { auth } = setup({ plugin, storage });
    await auth.checkAvailability();
    assert.equal(plugin.vault.has(LEGACY_VAULT_KEY), false);
    assert.equal(auth.isBiometricEnabled.value, false);
    for (const key of [
        'erp_biometric_enabled',
        'erp_biometric_user',
        'sroor_biometric_enabled',
        'sroor_biometric_user',
    ]) {
        assert.equal(storage.getItem(key), null, key);
    }
});

test('a failed or cancelled enrollment leaves nothing enabled', async () => {
    const plugin = fakePlugin({
        setCredentials: async () => {
            throw Object.assign(new Error('cancel'), { code: '16' });
        },
    });
    const { auth, notices } = setup({ plugin });
    await auth.checkAvailability();
    assert.equal(await auth.registerBiometrics('u', 'p'), false);
    assert.equal(auth.isBiometricEnabled.value, false);
    assert.deepEqual(notices, []);
});

test('disable removes the workspace vault and flags', async () => {
    const { auth, plugin, storage, notices } = setup();
    await auth.checkAvailability();
    await auth.registerBiometrics('cashier', 'secret');
    await auth.disableBiometrics();
    assert.equal(plugin.vault.size, 0);
    assert.equal(auth.isBiometricEnabled.value, false);
    assert.equal(storage.getItem('erp_biometric_user:shop-a'), null);
    assert.deepEqual(notices.at(-1), ['info', 'auth.biometric_disabled']);
});

test('a plugin that throws on availability reports unavailable', async () => {
    const plugin = fakePlugin({
        isAvailable: async () => {
            throw new Error('not implemented');
        },
    });
    const { auth } = setup({ plugin });
    await auth.checkAvailability();
    assert.equal(auth.isAvailable.value, false);
    assert.equal(auth.isBiometricEnabled.value, false);
});
