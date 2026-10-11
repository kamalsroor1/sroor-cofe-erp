import { ref, computed, onMounted } from 'vue';

const isDesktop = ref(typeof window !== 'undefined' && !!window.electronAPI?.isElectron);
const availablePrinters = ref([]);
const isPrinting = ref(false);
const isKioskMode = ref(false);
const isFullscreen = ref(false);
const isMaximized = ref(false);
const serverPingMs = ref(null);
const isOnline = ref(true);
const drawerPin = ref(2);

/** Drawer connector pins the desktop shell accepts (ESC p m: pin 2 -> m=0, pin 5 -> m=1). */
export const DRAWER_PINS = Object.freeze([2, 5]);

const DRAWER_ERROR_KEYS = {
    no_printer: 'desktop_hardware.drawer_error_no_printer',
    printer_not_found: 'desktop_hardware.drawer_error_printer_not_found',
    drawer_failed: 'desktop_hardware.drawer_error_failed',
};

/** Translation key describing a real kick result from the main process ({ success, method, error }). */
export const drawerResultKey = (result) => {
    if (result?.success) {
        return result.method === 'driver'
            ? 'desktop_hardware.drawer_test_success_driver'
            : 'desktop_hardware.drawer_test_success_escpos';
    }
    return DRAWER_ERROR_KEYS[result?.error] || 'desktop_hardware.drawer_error_failed';
};

export function useDesktopHardware() {
    const isElectron = computed(() => isDesktop.value);

    /**
     * Fetch connected system printers via Electron IPC
     */
    const loadPrinters = async () => {
        if (!isDesktop.value || !window.electronAPI?.getPrinters) return [];
        try {
            const list = await window.electronAPI.getPrinters();
            availablePrinters.value = list || [];
            return availablePrinters.value;
        } catch (error) {
            console.error('[DesktopHardware] Failed to list printers:', error);
            return [];
        }
    };

    /**
     * Direct Silent ESC/POS Thermal Printing
     */
    const printThermalReceipt = async (htmlContent, options = {}) => {
        if (!isDesktop.value || !window.electronAPI?.printThermal) return false;

        isPrinting.value = true;
        try {
            const savedPrinter = localStorage.getItem('desktop_thermal_printer') || '';
            const paperWidth = localStorage.getItem('desktop_paper_width') || '80mm';

            return await window.electronAPI.printThermal({
                html: htmlContent,
                printerName: options.printerName || savedPrinter,
                paperWidth: options.paperWidth || paperWidth,
                copies: options.copies || 1,
            });
        } catch (err) {
            console.error('[DesktopHardware] Silent thermal print error:', err);
            return { success: false, error: err.message };
        } finally {
            isPrinting.value = false;
        }
    };

    /**
     * Kick Open Cash Drawer
     */
    const openCashDrawer = async (printerName = '') => {
        if (!isDesktop.value || !window.electronAPI?.kickDrawer) return false;
        try {
            const targetPrinter = printerName || localStorage.getItem('desktop_thermal_printer') || '';
            return await window.electronAPI.kickDrawer(targetPrinter);
        } catch (err) {
            console.error('[DesktopHardware] Kick drawer error:', err);
            return false;
        }
    };

    /**
     * Cash drawer pin is a per-device setting stored by the desktop shell (not in the tenant settings).
     */
    const loadDrawerPin = async () => {
        if (!isDesktop.value || !window.electronAPI?.getSettings) return drawerPin.value;
        try {
            const settings = await window.electronAPI.getSettings();
            drawerPin.value = DRAWER_PINS.includes(settings?.cashDrawerPin) ? settings.cashDrawerPin : 2;
        } catch {
            // keep the last known pin
        }
        return drawerPin.value;
    };

    /** @returns {Promise<boolean>} true when the shell saved the pin */
    const saveDrawerPin = async (pin) => {
        if (!isDesktop.value || !window.electronAPI?.saveSettings || !DRAWER_PINS.includes(pin)) return false;
        try {
            const res = await window.electronAPI.saveSettings({ cashDrawerPin: pin });
            if (res?.success) drawerPin.value = pin;
            return !!res?.success;
        } catch {
            return false;
        }
    };

    /**
     * Fires the drawer and returns the shell's real result ({ success, method, error }).
     */
    const testCashDrawer = async (printerName = '') => {
        if (!isDesktop.value || !window.electronAPI?.kickDrawer) return { success: false, error: 'drawer_failed' };
        try {
            const res = await window.electronAPI.kickDrawer(printerName || '');
            return res && typeof res === 'object' ? res : { success: false, error: 'drawer_failed' };
        } catch {
            return { success: false, error: 'drawer_failed' };
        }
    };

    /**
     * Custom Frameless Window Controls
     */
    const minimizeWindow = () => {
        if (window.electronAPI?.minimize) window.electronAPI.minimize();
    };

    const maximizeWindow = async () => {
        if (window.electronAPI?.maximize) {
            isMaximized.value = await window.electronAPI.maximize();
        }
    };

    const closeWindow = () => {
        if (window.electronAPI?.close) window.electronAPI.close();
    };

    const toggleFullscreen = async () => {
        if (!isDesktop.value || !window.electronAPI?.toggleFullscreen) return;
        isFullscreen.value = await window.electronAPI.toggleFullscreen();
    };

    const toggleKiosk = async () => {
        if (!isDesktop.value || !window.electronAPI?.toggleKiosk) return;
        isKioskMode.value = await window.electronAPI.toggleKiosk();
    };

    /**
     * Network Latency Check
     */
    const checkServerPing = async () => {
        if (!isDesktop.value || !window.electronAPI?.pingServer) return;
        try {
            const res = await window.electronAPI.pingServer();
            isOnline.value = res.online;
            serverPingMs.value = res.latency;
        } catch (e) {
            isOnline.value = false;
        }
    };

    onMounted(() => {
        if (isDesktop.value) {
            document.body.classList.add('electron-app');
            loadPrinters();
            checkServerPing();
            if (window.electronAPI?.onMaximizeChange) {
                window.electronAPI.onMaximizeChange((max) => {
                    isMaximized.value = max;
                });
            }
        }
    });

    return {
        isDesktop: isElectron,
        availablePrinters,
        isPrinting,
        isKioskMode,
        isFullscreen,
        isMaximized,
        serverPingMs,
        isOnline,
        drawerPin,
        loadPrinters,
        loadDrawerPin,
        saveDrawerPin,
        testCashDrawer,
        printThermalReceipt,
        openCashDrawer,
        minimizeWindow,
        maximizeWindow,
        closeWindow,
        toggleFullscreen,
        toggleKiosk,
        checkServerPing,
    };
}
