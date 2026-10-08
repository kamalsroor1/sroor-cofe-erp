import { Capacitor } from '@capacitor/core';
import { AccessControl, NativeBiometric } from '@capgo/capacitor-native-biometric';
import { notifySuccess, Toast } from '../helpers/alert';
import { trans } from '../helpers/trans';
import { createBiometricAuth } from './biometric/createBiometricAuth';

const currentWorkspace = () => localStorage.getItem('tenant_id') || window.location.host;

// One shared instance: the login screen and the settings card see the same state.
// TODO(CTO): the vault still holds the account password (now biometric-bound and per workspace).
// Swapping it for a revocable device token needs a backend endpoint; see the APP-5 report.
const biometricAuth = createBiometricAuth({
    plugin: NativeBiometric,
    isNative: () => Capacitor.isNativePlatform(),
    storage: localStorage,
    getScope: currentWorkspace,
    accessControl: AccessControl.BIOMETRY_CURRENT_SET,
    prompt: (key) => trans(key),
    notify: (type, key) =>
        type === 'success' ? notifySuccess(trans(key)) : Toast.fire({ icon: 'info', title: trans(key) }),
});

export function useBiometricAuth() {
    return biometricAuth;
}
