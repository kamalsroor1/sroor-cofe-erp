const { contextBridge, ipcRenderer } = require('electron');

contextBridge.exposeInMainWorld('electronAPI', {
    isElectron: true,
    platform: process.platform,

    // 🪟 Custom Frameless Window Controls
    minimize: () => ipcRenderer.invoke('window:minimize'),
    maximize: () => ipcRenderer.invoke('window:maximize'),
    close: () => ipcRenderer.invoke('window:close'),
    isMaximized: () => ipcRenderer.invoke('window:is-maximized'),
    onMaximizeChange: (callback) => {
        ipcRenderer.on('window:maximize-change', (event, isMax) => callback(isMax));
    },
    toggleFullscreen: () => ipcRenderer.invoke('window:toggle-fullscreen'),
    toggleKiosk: () => ipcRenderer.invoke('window:toggle-kiosk'),
    reloadApp: () => ipcRenderer.invoke('window:reload'),

    // 🖨️ Hardware: Direct Thermal Printing & Cash Drawer
    getPrinters: () => ipcRenderer.invoke('hardware:get-printers'),
    printThermal: (data) => ipcRenderer.invoke('hardware:print-thermal', data),
    printPdf: (pdfUrl) => ipcRenderer.invoke('hardware:print-pdf', pdfUrl),
    kickDrawer: (printerName) => ipcRenderer.invoke('hardware:kick-drawer', printerName),

    // 📶 Network & Latency Ping
    pingServer: () => ipcRenderer.invoke('network:ping'),

    // ⚙️ Configuration & Desktop Settings
    getSettings: () => ipcRenderer.invoke('config:get-settings'),
    saveSettings: (settings) => ipcRenderer.invoke('config:save-settings', settings),

    // ℹ️ App Information
    getAppVersion: () => ipcRenderer.invoke('app:get-version'),
    getSystemInfo: () => ipcRenderer.invoke('app:get-system-info'),
});
