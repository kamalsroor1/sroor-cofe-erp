import { ref } from 'vue';

/** Pre-APP-5 global vault: unprotected (readable without a fingerprint) and shared by every workspace. */
export const LEGACY_VAULT_KEY = 'erp_secure_vault';
const LEGACY_STORAGE_KEYS = [
    'erp_biometric_enabled',
    'erp_biometric_user',
    'sroor_biometric_enabled',
    'sroor_biometric_user',
];

// Plugin error codes (BiometricAuthError): user/system cancel keep the enrollment.
const CANCEL_CODES = new Set(['10', '15', '16', '17']);

const normalizeScope = (scope) =>
    String(scope || '')
        .trim()
        .toLowerCase()
        .replace(/[^a-z0-9._-]/g, '-') || 'default';

export const vaultKeyFor = (scope) => `erp_biometric_vault:${normalizeScope(scope)}`;

/**
 * Biometric quick login (Android). Side effects are injected so the flow is unit-testable:
 *
 * - plugin: NativeBiometric (@capgo/capacitor-native-biometric)
 * - isNative(): true only inside the Capacitor shell (the plugin's web stub always claims support)
 * - storage: Storage-like (flags + display username per workspace; never the password)
 * - getScope(): current workspace (tenant) id, so each shop gets its own vault
 * - accessControl: AccessControl.BIOMETRY_CURRENT_SET — the keystore key needs a live biometric
 *   check for every read and is invalidated when a new fingerprint is enrolled
 * - prompt(key): translated text for the native prompt
 * - notify(type, key): user feedback after enable/disable
 */
export function createBiometricAuth({ plugin, isNative, storage, getScope, accessControl, prompt, notify }) {
    const isAvailable = ref(false);
    const biometryType = ref(null);
    const isBiometricEnabled = ref(false);
    const biometricUser = ref(null);
    const isAuthenticating = ref(false);

    const scope = () => normalizeScope(getScope());
    const enabledKey = () => `erp_biometric_enabled:${scope()}`;
    const userKey = () => `erp_biometric_user:${scope()}`;
    const vaultKey = () => vaultKeyFor(scope());

    const setEnabled = (login) => {
        storage.setItem(enabledKey(), '1');
        storage.setItem(userKey(), login);
        isBiometricEnabled.value = true;
        biometricUser.value = login;
    };

    const clearEnabled = () => {
        storage.removeItem(enabledKey());
        storage.removeItem(userKey());
        isBiometricEnabled.value = false;
        biometricUser.value = null;
    };

    const purgeLegacyVault = async () => {
        if (!LEGACY_STORAGE_KEYS.some((key) => storage.getItem(key) !== null)) return;
        try {
            await plugin.deleteCredentials({ server: LEGACY_VAULT_KEY });
        } catch {
            // Nothing stored under the legacy key.
        }
        LEGACY_STORAGE_KEYS.forEach((key) => storage.removeItem(key));
    };

    const checkAvailability = async () => {
        isAvailable.value = false;
        biometryType.value = null;
        if (!isNative()) {
            clearEnabledState();
            return;
        }
        try {
            await purgeLegacyVault();
            const result = await plugin.isAvailable({ useFallback: false });
            isAvailable.value = !!result?.isAvailable && !!result?.strongBiometryIsAvailable;
            biometryType.value = result?.biometryType ?? null;

            const flagged = storage.getItem(enabledKey()) === '1' && !!storage.getItem(userKey());
            const saved = flagged ? (await plugin.isCredentialsSaved({ server: vaultKey() }))?.isSaved : false;
            if (isAvailable.value && flagged && saved) {
                isBiometricEnabled.value = true;
                biometricUser.value = storage.getItem(userKey());
            } else {
                clearEnabled();
            }
        } catch {
            isAvailable.value = false;
            clearEnabledState();
        }
    };

    // In-memory only: a transient plugin failure must not wipe a valid enrollment.
    function clearEnabledState() {
        isBiometricEnabled.value = false;
        biometricUser.value = null;
    }

    const registerBiometrics = async (login, password) => {
        if (!isNative() || !isAvailable.value || !login || !password) return false;
        try {
            await plugin.setCredentials({
                server: vaultKey(),
                username: login,
                password,
                accessControl,
                title: prompt('auth.biometric_prompt_enable_title'),
                negativeButtonText: prompt('auth.biometric_prompt_cancel'),
            });
            setEnabled(login);
            notify('success', 'auth.biometric_enabled_success');
            return true;
        } catch {
            return false;
        }
    };

    const loginWithBiometrics = async () => {
        if (!isNative() || !isBiometricEnabled.value) return null;
        isAuthenticating.value = true;
        try {
            const credentials = await plugin.getSecureCredentials({
                server: vaultKey(),
                reason: prompt('auth.biometric_prompt_login_reason'),
                title: prompt('auth.biometric_prompt_login_title'),
                subtitle: prompt('auth.biometric_prompt_login_subtitle'),
                negativeButtonText: prompt('auth.biometric_prompt_cancel'),
            });
            if (credentials?.username && credentials?.password) {
                return { login: credentials.username, password: credentials.password };
            }
            clearEnabled();
            return null;
        } catch (error) {
            if (!CANCEL_CODES.has(String(error?.code ?? ''))) {
                // Vault missing or invalidated (e.g. new fingerprint enrolled): re-enrollment is required.
                await plugin.deleteCredentials({ server: vaultKey() }).catch(() => {});
                clearEnabled();
            }
            return null;
        } finally {
            isAuthenticating.value = false;
        }
    };

    const disableBiometrics = async () => {
        try {
            await plugin.deleteCredentials({ server: vaultKey() });
        } catch {
            // Already gone.
        }
        clearEnabled();
        notify('info', 'auth.biometric_disabled');
    };

    return {
        isAvailable,
        biometryType,
        isBiometricEnabled,
        biometricUser,
        isAuthenticating,
        checkAvailability,
        registerBiometrics,
        loginWithBiometrics,
        disableBiometrics,
    };
}
