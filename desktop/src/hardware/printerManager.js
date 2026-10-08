const fs = require('fs');
const path = require('path');
const { BrowserWindow, dialog, app } = require('electron');
const { buildReceiptDocument } = require('./printJob');
const { printWithFallback } = require('./printFallback');

const MICRONS_PER_INCH = 25400;
const PDF_PAGE_HEIGHT_INCHES = 11.69;
const PAPER_WIDTH_INCHES = { '58mm': 58000 / MICRONS_PER_INCH, '80mm': 80000 / MICRONS_PER_INCH };

function createWorkerWindow() {
    return new BrowserWindow({
        show: false,
        width: 400,
        height: 800,
        webPreferences: {
            nodeIntegration: false,
            contextIsolation: true,
            sandbox: true,
            // Receipts are static markup; nothing in them should ever execute.
            javascript: false,
        },
    });
}

function loadDocument(worker, html) {
    return new Promise((resolve) => {
        worker.webContents.once('did-finish-load', () => resolve(true));
        worker.webContents.once('did-fail-load', () => resolve(false));
        worker.loadURL(`data:text/html;charset=utf-8,${encodeURIComponent(html)}`);
    });
}

function runPrint(worker, options) {
    return new Promise((resolve) => {
        worker.webContents.print(options, (success, failureReason) => {
            resolve(success ? { success: true } : { success: false, error: failureReason || 'print_failed' });
        });
    });
}

async function savePdf(worker, parentWindow, paperWidth) {
    const buffer = await worker.webContents.printToPDF({
        printBackground: true,
        margins: { top: 0, bottom: 0, left: 0, right: 0 },
        pageSize: {
            width: PAPER_WIDTH_INCHES[paperWidth] || PAPER_WIDTH_INCHES['80mm'],
            height: PDF_PAGE_HEIGHT_INCHES,
        },
    });
    const stamp = new Date().toISOString().replace(/[:.]/g, '-');
    const options = {
        defaultPath: path.join(app.getPath('documents'), `receipt-${stamp}.pdf`),
        filters: [{ name: 'PDF', extensions: ['pdf'] }],
    };
    const parent = parentWindow && !parentWindow.isDestroyed() ? parentWindow : null;
    const choice = parent ? await dialog.showSaveDialog(parent, options) : await dialog.showSaveDialog(options);
    if (choice.canceled || !choice.filePath) {
        return { success: false, cancelled: true };
    }
    await fs.promises.writeFile(choice.filePath, buffer);
    return { success: true, filePath: choice.filePath };
}

class PrinterManager {
    /**
     * Installed printers. Electron no longer reports a default flag or status.
     */
    async getPrinters(mainWindow) {
        try {
            if (!mainWindow || !mainWindow.webContents) return [];
            const printers = await mainWindow.webContents.getPrintersAsync();
            return printers.map((printer) => ({
                name: printer.name,
                displayName: printer.displayName || printer.name,
                description: printer.description || '',
            }));
        } catch (error) {
            console.error('[PrinterManager] Error getting printers:', error);
            return [];
        }
    }

    /**
     * Silent print only (no dialog, no PDF). Used for the drawer driver pulse.
     * An empty printerName means the system default printer.
     */
    async printThermalSilent(htmlReceipt, options = {}) {
        const worker = createWorkerWindow();
        try {
            if (!(await loadDocument(worker, buildReceiptDocument(htmlReceipt, options.paperWidth)))) {
                return { success: false, error: 'load_failed' };
            }
            const printOptions = {
                silent: true,
                printBackground: true,
                copies: options.copies || 1,
                margins: { marginType: 'none' },
            };
            if (options.printerName) printOptions.deviceName = options.printerName;
            const result = await runPrint(worker, printOptions);
            return result.success ? result : { success: false, error: 'print_failed' };
        } finally {
            if (!worker.isDestroyed()) worker.close();
        }
    }

    /**
     * Receipt printing with fallbacks: silent -> print dialog (choose printer / PDF printer) -> save PDF.
     * @param {string} htmlReceipt receipt body markup
     * @param {{printerName: string, paperWidth: string, copies: number}} job
     * @param {BrowserWindow|null} parentWindow owner of the save dialog
     */
    async printThermal(htmlReceipt, job, parentWindow) {
        const printers = await this.getPrinters(parentWindow);
        const worker = createWorkerWindow();
        try {
            if (!(await loadDocument(worker, buildReceiptDocument(htmlReceipt, job.paperWidth)))) {
                return { success: false, error: 'load_failed' };
            }
            const base = { printBackground: true, copies: job.copies || 1, margins: { marginType: 'none' } };
            return await printWithFallback(
                { printers, requestedPrinter: job.printerName },
                {
                    printSilent: (printerName) =>
                        runPrint(worker, {
                            ...base,
                            silent: true,
                            ...(printerName ? { deviceName: printerName } : {}),
                        }),
                    printWithDialog: () => runPrint(worker, { ...base, silent: false }),
                    saveAsPdf: () => savePdf(worker, parentWindow, job.paperWidth),
                }
            );
        } catch (error) {
            console.error('[PrinterManager] Print failed:', error);
            return { success: false, error: 'print_failed' };
        } finally {
            if (!worker.isDestroyed()) worker.close();
        }
    }
}

module.exports = new PrinterManager();
