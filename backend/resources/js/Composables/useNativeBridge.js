import { ref, onMounted } from 'vue';
import { Capacitor } from '@capacitor/core';
import { Haptics, ImpactStyle, NotificationType } from '@capacitor/haptics';
import { StatusBar, Style } from '@capacitor/status-bar';
import { Toast } from '@capacitor/toast';
import { Network } from '@capacitor/network';

/**
 * useNativeBridge - Unified Hardware & Native Bridge Composable for Cloud ERP & POS
 * Enables Vue 3 components to seamlessly interact with Android/iOS native hardware
 * with automatic graceful fallbacks when running in standard Web browsers.
 */
export function useNativeBridge() {
    const isNative = ref(Capacitor.isNativePlatform());
    const platform = ref(Capacitor.getPlatform());
    const isOnline = ref(true);
    const connectionType = ref('unknown');

    // Initialize Network listener
    onMounted(async () => {
        try {
            const status = await Network.getStatus();
            isOnline.value = status.connected;
            connectionType.value = status.connectionType;

            Network.addListener('networkStatusChange', (status) => {
                isOnline.value = status.connected;
                connectionType.value = status.connectionType;
            });
        } catch (e) {
            // Web browser fallback
            isOnline.value = navigator.onLine;
            window.addEventListener('online', () => { isOnline.value = true; });
            window.addEventListener('offline', () => { isOnline.value = false; });
        }
    });

    /**
     * Trigger tactile/haptic vibration feedback
     * @param {'light'|'medium'|'heavy'|'success'|'warning'|'error'} type
     */
    const triggerHaptic = async (type = 'light') => {
        try {
            if (isNative.value) {
                if (type === 'light') {
                    await Haptics.impact({ style: ImpactStyle.Light });
                } else if (type === 'medium') {
                    await Haptics.impact({ style: ImpactStyle.Medium });
                } else if (type === 'heavy') {
                    await Haptics.impact({ style: ImpactStyle.Heavy });
                } else if (type === 'success') {
                    await Haptics.notification({ type: NotificationType.Success });
                } else if (type === 'warning') {
                    await Haptics.notification({ type: NotificationType.Warning });
                } else if (type === 'error') {
                    await Haptics.notification({ type: NotificationType.Error });
                }
            } else if (typeof navigator !== 'undefined' && navigator.vibrate) {
                // Web Vibration API fallback
                const duration = type === 'heavy' ? 80 : (type === 'medium' ? 40 : 20);
                navigator.vibrate(duration);
            }
        } catch (e) {
            // Silently ignore if vibration is not supported
        }
    };

    /**
     * Show native Toast notification
     * @param {string} text
     * @param {'short'|'long'} duration
     */
    const showToast = async (text, duration = 'short') => {
        try {
            if (isNative.value) {
                await Toast.show({
                    text,
                    duration,
                    position: 'bottom',
                });
            }
        } catch (e) {
            // Web fallback silently handled by UI alerts
        }
    };

    /**
     * Configure Android/iOS Status Bar color
     * @param {string} colorHex - e.g. '#0f172a' or '#10b981'
     * @param {boolean} isDarkContent - dark text or white text
     */
    const setStatusBar = async (colorHex = '#0f172a', isDarkContent = false) => {
        try {
            if (isNative.value) {
                await StatusBar.setBackgroundColor({ color: colorHex });
                await StatusBar.setStyle({
                    style: isDarkContent ? Style.Dark : Style.Light,
                });
            }
        } catch (e) {}
    };

    /**
     * Bluetooth Thermal Print Bridge
     * Sends ESC/POS receipt data directly to a paired Bluetooth thermal printer
     * @param {Object} invoiceData
     */
    const printThermalBluetooth = async (invoiceData) => {
        try {
            triggerHaptic('success');
            // If running native and custom print plugin is bound:
            if (window.BluetoothPrinter) {
                return await window.BluetoothPrinter.printReceipt(invoiceData);
            }
            // Standard web window.print fallback
            window.open(`/invoices/${invoiceData.id || invoiceData}/print-thermal`, '_blank');
        } catch (e) {
            console.error('Thermal print error:', e);
        }
    };

    return {
        isNative,
        platform,
        isOnline,
        connectionType,
        triggerHaptic,
        showToast,
        setStatusBar,
        printThermalBluetooth,
    };
}
