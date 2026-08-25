const { BrowserWindow } = require('electron');

class PrinterManager {
    /**
     * Get all connected system printers with detailed capabilities
     */
    async getPrinters(mainWindow) {
        try {
            if (!mainWindow || !mainWindow.webContents) return [];
            const printers = await mainWindow.webContents.getPrintersAsync();
            return printers.map(p => ({
                name: p.name,
                displayName: p.displayName || p.name,
                description: p.description || '',
                isDefault: p.isDefault,
                status: p.status
            }));
        } catch (error) {
            console.error('[PrinterManager] Error getting printers:', error);
            return [];
        }
    }

    /**
     * Silent Direct Thermal Receipt Printing (Bypasses Browser Print Dialog)
     * @param {string} htmlReceipt Full HTML content of the receipt
     * @param {object} options { printerName, paperWidth, silent, copies }
     */
    async printThermalSilent(htmlReceipt, options = {}) {
        return new Promise((resolve) => {
            const paperWidth = options.paperWidth || '80mm';
            const printerName = options.printerName || '';
            const copies = options.copies || 1;

            // Create offscreen silent printing worker window
            const printWorker = new BrowserWindow({
                show: false,
                width: 400,
                height: 800,
                webPreferences: {
                    nodeIntegration: false,
                    contextIsolation: true
                }
            });

            // Wrap receipt with optimal thermal CSS
            const formattedHtml = `
                <!DOCTYPE html>
                <html dir="rtl" lang="ar">
                <head>
                    <meta charset="utf-8">
                    <title>Thermal Receipt</title>
                    <style>
                        @page {
                            margin: 0;
                            size: ${paperWidth === '58mm' ? '58mm auto' : '80mm auto'};
                        }
                        * {
                            box-sizing: border-box;
                            margin: 0;
                            padding: 0;
                            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
                            -webkit-print-color-adjust: exact;
                            print-color-adjust: exact;
                        }
                        body {
                            width: ${paperWidth === '58mm' ? '48mm' : '72mm'};
                            margin: 0 auto;
                            padding: 4px 2px;
                            font-size: 11px;
                            color: #000;
                            background: #fff;
                        }
                        table { width: 100%; border-collapse: collapse; }
                        td, th { padding: 3px 1px; }
                        .text-center { text-align: center; }
                        .text-end { text-align: left; }
                        .text-start { text-align: right; }
                        .font-bold { font-weight: bold; }
                        .border-b { border-bottom: 1px dashed #000; }
                        .border-t { border-top: 1px dashed #000; }
                        .my-1 { margin-top: 4px; margin-bottom: 4px; }
                        .py-1 { padding-top: 4px; padding-bottom: 4px; }
                    </style>
                </head>
                <body>
                    ${htmlReceipt}
                </body>
                </html>
            `;

            printWorker.loadURL(`data:text/html;charset=utf-8,${encodeURIComponent(formattedHtml)}`);

            printWorker.webContents.on('did-finish-load', () => {
                const printOptions = {
                    silent: true,
                    printBackground: true,
                    copies: copies,
                    margins: { marginType: 'none' }
                };

                if (printerName && printerName.trim() !== '') {
                    printOptions.deviceName = printerName;
                }

                printWorker.webContents.print(printOptions, (success, failureReason) => {
                    printWorker.close();
                    if (success) {
                        resolve({ success: true, message: 'تم إرسال أمر الطباعة للطابعة بنجاح' });
                    } else {
                        console.error('[PrinterManager] Print failed:', failureReason);
                        resolve({ success: false, error: failureReason || 'فشل أمر الطباعة' });
                    }
                });
            });

            printWorker.webContents.on('did-fail-load', (e, errCode, errDesc) => {
                printWorker.close();
                resolve({ success: false, error: `Failed to load receipt: ${errDesc}` });
            });
        });
    }
}

module.exports = new PrinterManager();
