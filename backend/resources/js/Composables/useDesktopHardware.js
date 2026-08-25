import { ref, computed, onMounted } from 'vue';

const isDesktop = ref(typeof window !== 'undefined' && !!window.electronAPI?.isElectron);
const availablePrinters = ref([]);
const isPrinting = ref(false);
const isKioskMode = ref(false);
const isFullscreen = ref(false);
const isMaximized = ref(false);
const serverPingMs = ref(null);
const isOnline = ref(true);

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
        loadPrinters,
        printThermalReceipt,
        openCashDrawer,
        minimizeWindow,
        maximizeWindow,
        closeWindow,
        toggleFullscreen,
        toggleKiosk,
        checkServerPing
    };
}
