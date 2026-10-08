const { BrowserWindow } = require('electron');
const printerManager = require('./printerManager');
const settingsStore = require('../config/settingsStore');
const { sendRaw } = require('./rawPrinter');
const { createDrawerKicker } = require('./drawerKick');

// A near-empty page: drivers configured to "open drawer after print" fire the pulse.
const DRIVER_PULSE_HTML = '<div style="font-size:1px;height:1px;color:transparent">.</div>';

function anyLiveWindow() {
    const focused = BrowserWindow.getFocusedWindow();
    if (focused && !focused.isDestroyed()) return focused;
    return BrowserWindow.getAllWindows().find((win) => !win.isDestroyed()) || null;
}

// Main-process wiring only; the decision logic lives in drawerKick.js (unit tested).
const kicker = createDrawerKicker({
    listPrinters: () => printerManager.getPrinters(anyLiveWindow()),
    getConfiguredPrinter: () => settingsStore.get('thermalPrinterName'),
    sendRaw: (printerName, bytes) => sendRaw(printerName, bytes),
    printDriverPulse: (printerName) =>
        printerManager.printThermalSilent(DRIVER_PULSE_HTML, { printerName, paperWidth: '80mm', copies: 1 }),
});

/**
 * Opens the cash drawer: ESC/POS pulse first, driver job as fallback.
 * @param {string} [printerName] receipt printer; falls back to the saved thermal printer
 * @returns {Promise<{success: boolean, method?: 'escpos'|'driver', printerName?: string, error?: string}>}
 */
function kickDrawer(printerName = '') {
    return kicker.kick(printerName);
}

module.exports = { kickDrawer };
