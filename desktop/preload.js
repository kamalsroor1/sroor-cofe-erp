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
    hardReload: () => ipcRenderer.invoke('window:hard-reload'),
    clearCache: () => ipcRenderer.invoke('window:clear-cache'),

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

    // 🚀 Native In-App Auto-Updater
    updater: {
        downloadAndInstall: (data) => ipcRenderer.invoke('updater:download-and-install', data),
        onProgress: (callback) => {
            const listener = (event, progress) => callback(progress);
            ipcRenderer.on('updater:progress', listener);
            return () => ipcRenderer.removeListener('updater:progress', listener);
        },
        onComplete: (callback) => {
            const listener = (event, res) => callback(res);
            ipcRenderer.on('updater:complete', listener);
            return () => ipcRenderer.removeListener('updater:complete', listener);
        },
        onError: (callback) => {
            const listener = (event, err) => callback(err);
            ipcRenderer.on('updater:error', listener);
            return () => ipcRenderer.removeListener('updater:error', listener);
        }
    }
});
