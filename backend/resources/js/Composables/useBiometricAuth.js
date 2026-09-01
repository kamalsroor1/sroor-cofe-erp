import { ref } from 'vue';
import { NativeBiometric } from '@capgo/capacitor-native-biometric';
import Swal from 'sweetalert2';

const isAvailable = ref(false);
const biometryType = ref(null);
const isBiometricEnabled = ref(false);
const biometricUser = ref(null);
const isAuthenticating = ref(false);

const SERVER_KEY = 'erp_secure_vault';

export function useBiometricAuth() {
    const checkAvailability = async () => {
        try {
            if (typeof window !== 'undefined') {
                const result = await NativeBiometric.isAvailable();
                isAvailable.value = !!result?.isAvailable;
                biometryType.value = result?.biometryType;
                
                const saved = localStorage.getItem('erp_biometric_enabled') || localStorage.getItem('sroor_biometric_enabled');
                const savedUser = localStorage.getItem('erp_biometric_user') || localStorage.getItem('sroor_biometric_user');
                if (saved === '1' && savedUser) {
                    isBiometricEnabled.value = true;
                    biometricUser.value = savedUser;
                } else {
                    isBiometricEnabled.value = false;
                    biometricUser.value = null;
                }
            }
        } catch (e) {
            isAvailable.value = false;
            isBiometricEnabled.value = false;
        }
    };

    const registerBiometrics = async (login, password) => {
        try {
            await NativeBiometric.verifyIdentity({
                reason: 'تأكيد البصمة لتفعيل الدخول السريع',
                title: 'تأكيد البصمة',
                subtitle: 'قم بمسح بصمة الإصبع أو الوجه لتفعيل الدخول السريع',
                description: 'سيتم تشفير بيانات الدخول بأمان في معالج الهاتف',
                negativeButtonText: 'إلغاء',
            });

            await NativeBiometric.setCredentials({
                server: SERVER_KEY,
                username: login,
                password: password,
            });

            localStorage.setItem('erp_biometric_enabled', '1');
            localStorage.setItem('erp_biometric_user', login);
            localStorage.removeItem('sroor_biometric_enabled');
            localStorage.removeItem('sroor_biometric_user');
            isBiometricEnabled.value = true;
            biometricUser.value = login;

            Swal.fire({
                icon: 'success',
                title: 'تم تفعيل البصمة بنجاح ⚡',
                text: 'يمكنك الآن تسجيل الدخول في المرات القادمة بلمسة واحدة.',
                confirmButtonText: 'ممتاز',
                confirmButtonColor: '#10b981',
            });

            return true;
        } catch (e) {
            console.error('Failed to register biometrics:', e);
            return false;
        }
    };

    const loginWithBiometrics = async () => {
        if (!isBiometricEnabled.value) return null;
        isAuthenticating.value = true;

        try {
            await NativeBiometric.verifyIdentity({
                reason: 'تسجيل الدخول السريع بالبصمة',
                title: 'تسجيل الدخول بالبصمة',
                subtitle: 'قم بتأكيد هويتك لمتابعة الدخول',
                description: 'الدخول السريع لمنظومة ERP',
                negativeButtonText: 'إلغاء',
            });

            const credentials = await NativeBiometric.getCredentials({
                server: SERVER_KEY,
            });

            if (credentials && credentials.username && credentials.password) {
                return {
                    login: credentials.username,
                    password: credentials.password,
                };
            }
            return null;
        } catch (e) {
            console.error('Biometric authentication failed or cancelled:', e);
            return null;
        } finally {
            isAuthenticating.value = false;
        }
    };

    const disableBiometrics = async () => {
        try {
            await NativeBiometric.deleteCredentials({
                server: SERVER_KEY,
            });
        } catch (ignored) {}

        localStorage.removeItem('erp_biometric_enabled');
        localStorage.removeItem('erp_biometric_user');
        localStorage.removeItem('sroor_biometric_enabled');
        localStorage.removeItem('sroor_biometric_user');
        isBiometricEnabled.value = false;
        biometricUser.value = null;

        Swal.fire({
            icon: 'info',
            title: 'تم تعطيل الدخول بالبصمة',
            text: 'تمت إزالة البصمة المشفرة من هذا الجهاز.',
            confirmButtonText: 'حسناً',
            confirmButtonColor: '#64748b',
        });
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
