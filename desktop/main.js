const { app, BrowserWindow, ipcMain, Menu, Tray, nativeImage, shell } = require('electron');
const path = require('path');
const http = require('http');
const https = require('https');
const settingsStore = require('./src/config/settingsStore');
const printerManager = require('./src/hardware/printerManager');
const cashDrawer = require('./src/hardware/cashDrawer');
const { createApplicationMenu } = require('./src/menu/appMenu');

let mainWindow = null;
let splashWindow = null;
let appTray = null;

function createSplashWindow() {
    splashWindow = new BrowserWindow({
        width: 500,
        height: 340,
        transparent: true,
        frame: false,
        alwaysOnTop: true,
        center: true,
        resizable: false,
        show: true,
        backgroundColor: '#00000000',
        icon: path.join(__dirname, 'build', 'icon.png'),
        webPreferences: {
            nodeIntegration: false,
            contextIsolation: true
        }
    });

    splashWindow.loadFile(path.join(__dirname, 'src', 'splash.html'));
    splashWindow.on('closed', () => {
        splashWindow = null;
    });
}

function createMainWindow() {
    const savedBounds = settingsStore.get('windowBounds') || { width: 1400, height: 900 };
    const kioskMode = settingsStore.get('kioskMode') || false;

    mainWindow = new BrowserWindow({
        width: savedBounds.width,
        height: savedBounds.height,
        x: savedBounds.x,
        y: savedBounds.y,
        minWidth: 1024,
        minHeight: 680,
        title: 'ERP & POS System',
        frame: false, // 🚀 Frameless window for custom native titlebar
        titleBarStyle: 'hidden',
        show: false, // Hidden until ready and splash completes
        backgroundColor: '#020617', // Dark slate 950
        autoHideMenuBar: true,
        kiosk: kioskMode,
        icon: path.join(__dirname, 'build', 'icon.png'),
        webPreferences: {
            preload: path.join(__dirname, 'preload.js'),
            nodeIntegration: false,
            contextIsolation: true,
            spellcheck: false,
            webSecurity: true
        }
    });

    if (savedBounds.isMaximized) {
        mainWindow.maximize();
    } else {
        mainWindow.center();
    }

    mainWindow.focus();

    // Window bounds persistence
    const saveState = () => {
        if (!mainWindow.isFullScreen() && !mainWindow.isMinimized()) {
            const bounds = mainWindow.getBounds();
            bounds.isMaximized = mainWindow.isMaximized();
            settingsStore.set('windowBounds', bounds);
        }
    };
    mainWindow.on('resize', saveState);
    mainWindow.on('move', saveState);

    // Notify renderer on maximize / unmaximize
    mainWindow.on('maximize', () => {
        mainWindow.webContents.send('window:maximize-change', true);
    });
    mainWindow.on('unmaximize', () => {
        mainWindow.webContents.send('window:maximize-change', false);
    });

    // Native Context Menu (Right Click)
    mainWindow.webContents.on('context-menu', (e, params) => {
        const contextMenu = Menu.buildFromTemplate([
            { label: 'قص (Cut)', role: 'cut', enabled: params.editFlags.canCut },
            { label: 'نسخ (Copy)', role: 'copy', enabled: params.editFlags.canCopy },
            { label: 'لصق (Paste)', role: 'paste', enabled: params.editFlags.canPaste },
            { label: 'تحديد الكل (Select All)', role: 'selectAll', enabled: params.editFlags.canSelectAll },
            { type: 'separator' },
            {
                label: 'نقطة البيع السريعة (POS)',
                click: () => {
                    const url = settingsStore.get('serverUrl') || 'https://2m.baraa-solutions.com';
                    mainWindow.loadURL(`${url}/pos`);
                }
            },
            {
                label: 'فتح درج النقدية (F12)',
                click: async () => {
                    const defaultPrinter = settingsStore.get('thermalPrinterName');
                    await cashDrawer.kickDrawer(defaultPrinter);
                }
            },
            { type: 'separator' },
            {
                label: 'إعادة تحميل وتحديث الكاش الفوري (Hard Reload)',
                accelerator: 'CmdOrCtrl+Shift+R',
                click: () => mainWindow.webContents.reloadIgnoringCache()
            },
            {
                label: 'إعادة تحميل الصفحة (Reload)',
                accelerator: 'CmdOrCtrl+R',
                click: () => mainWindow.webContents.reload()
            },
            {
                label: 'الشاشة الكاملة (Fullscreen)',
                accelerator: 'F11',
                click: () => mainWindow.setFullScreen(!mainWindow.isFullScreen())
            },
            { type: 'separator' },
            {
                label: 'أدوات المطورين (DevTools)',
                click: () => mainWindow.webContents.toggleDevTools()
            }
        ]);
        contextMenu.popup();
    });

    // Setup Application Menu (Hidden/Keyboard access)
    createApplicationMenu(mainWindow);

    // Setup System Tray
    createSystemTray();

    // Prevent drag-and-drop navigation
    mainWindow.webContents.on('will-navigate', (event) => {
        // Allow internal navigation
    });

    // Target URL (Remote cloud tenant or local)
    const targetUrl = settingsStore.get('serverUrl') || 'https://2m.baraa-solutions.com';

    console.log('[Electron] Loading application URL:', targetUrl);
    mainWindow.loadURL(targetUrl);

    mainWindow.once('ready-to-show', () => {
        // Smooth transition from splash to main window
        setTimeout(() => {
            if (splashWindow && !splashWindow.isDestroyed()) {
                splashWindow.close();
                splashWindow = null;
            }
            if (savedBounds.isMaximized) {
                mainWindow.maximize();
            }
            mainWindow.show();
            mainWindow.focus();
        }, 1200);
    });

    // Handle connection failures with clean retry page
    mainWindow.webContents.on('did-fail-load', (event, errorCode, errorDescription) => {
        console.error('[Electron] Failed to load:', errorCode, errorDescription);
        const retryHtml = `
            <!DOCTYPE html>
            <html dir="rtl" lang="ar">
            <head>
                <meta charset="utf-8">
                <title>خطأ في الاتصال بالخادم</title>
                <style>
                    body {
                        font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
                        background-color: #090d16;
                        color: #f1f5f9;
                        display: flex;
                        align-items: center;
                        justify-content: center;
                        height: 100vh;
                        margin: 0;
                        text-align: center;
                    }
                    .card {
                        background: #0f172a;
                        border: 1px solid #1e293b;
                        padding: 2.5rem;
                        border-radius: 1.5rem;
                        max-width: 480px;
                        box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.5);
                    }
                    h1 { font-size: 1.5rem; color: #f43f5e; margin-bottom: 0.5rem; }
                    p { font-size: 0.9rem; color: #94a3b8; margin-bottom: 1.5rem; }
                    .btn {
                        background: #0ea5e9;
                        color: #fff;
                        border: none;
                        padding: 0.75rem 1.5rem;
                        font-size: 0.9rem;
                        font-weight: bold;
                        border-radius: 0.75rem;
                        cursor: pointer;
                        transition: all 0.2s;
                    }
                    .btn:hover { background: #0284c7; }
                </style>
            </head>
            <body>
                <div class="card">
                    <div style="font-size: 3rem; margin-bottom: 1rem;">📡</div>
                    <h1>تعذر الاتصال بالخادم</h1>
                    <p>يرجى التأكد من اتصال الإنترنت أو صحة رابط الخادم: <br><b style="color:#38bdf8;">${targetUrl}</b></p>
                    <button class="btn" onclick="window.location.reload()">🔄 إعادة المحاولة</button>
                </div>
            </body>
            </html>
        `;
        mainWindow.loadURL(`data:text/html;charset=utf-8,${encodeURIComponent(retryHtml)}`);
    });

    mainWindow.on('closed', () => {
        mainWindow = null;
    });
}

function createSystemTray() {
    if (appTray) return;
    try {
        const iconPath = path.join(__dirname, 'build', 'icon.png');
        const trayIcon = nativeImage.createFromPath(iconPath).resize({ width: 16, height: 16 });
        appTray = new Tray(trayIcon);
        appTray.setToolTip('ERP & POS System');

        const contextMenu = Menu.buildFromTemplate([
            {
                label: 'فتح النافذة الرئيسية',
                click: () => {
                    if (mainWindow) {
                        if (mainWindow.isMinimized()) mainWindow.restore();
                        mainWindow.show();
                        mainWindow.focus();
                    }
                }
            },
            {
                label: 'نقطة البيع (POS)',
                click: () => {
                    if (mainWindow) {
                        const url = settingsStore.get('serverUrl') || 'https://2m.baraa-solutions.com';
                        mainWindow.loadURL(`${url}/pos`);
                        mainWindow.show();
                        mainWindow.focus();
                    }
                }
            },
            {
                label: 'فتح درج النقدية',
                click: async () => {
                    const defaultPrinter = settingsStore.get('thermalPrinterName');
                    await cashDrawer.kickDrawer(defaultPrinter);
                }
            },
            { type: 'separator' },
            {
                label: 'إغلاق التطبيق نهائياً',
                click: () => app.quit()
            }
        ]);

        appTray.setContextMenu(contextMenu);
        appTray.on('double-click', () => {
            if (mainWindow) {
                if (mainWindow.isMinimized()) mainWindow.restore();
                mainWindow.show();
                mainWindow.focus();
            }
        });
    } catch (e) {
        console.warn('[SystemTray] Could not initialize tray:', e);
    }
}

// ══════════════════════════════════════════════════════════════════════════
// 📡 IPC HANDLERS (Hardware, Display, Window Controls, Network Ping)
// ══════════════════════════════════════════════════════════════════════════

// 1. Window Controls for Custom Frameless Titlebar
ipcMain.handle('window:minimize', () => {
    if (mainWindow) mainWindow.minimize();
});

ipcMain.handle('window:maximize', () => {
    if (mainWindow) {
        if (mainWindow.isMaximized()) {
            mainWindow.unmaximize();
            return false;
        } else {
            mainWindow.maximize();
            return true;
        }
    }
    return false;
});

ipcMain.handle('window:is-maximized', () => {
    return mainWindow ? mainWindow.isMaximized() : false;
});

ipcMain.handle('window:close', () => {
    if (mainWindow) mainWindow.close();
});

ipcMain.handle('window:toggle-fullscreen', () => {
    if (mainWindow) {
        const nextState = !mainWindow.isFullScreen();
        mainWindow.setFullScreen(nextState);
        return nextState;
    }
    return false;
});

ipcMain.handle('window:toggle-kiosk', () => {
    if (mainWindow) {
        const nextKiosk = !mainWindow.isKiosk();
        mainWindow.setKiosk(nextKiosk);
        settingsStore.set('kioskMode', nextKiosk);
        return nextKiosk;
    }
    return false;
});

ipcMain.handle('window:reload', () => {
    if (mainWindow) mainWindow.webContents.reload();
});

ipcMain.handle('window:hard-reload', () => {
    if (mainWindow) mainWindow.webContents.reloadIgnoringCache();
});

ipcMain.handle('window:clear-cache', async () => {
    if (mainWindow) {
        try {
            await mainWindow.webContents.session.clearCache();
            await mainWindow.webContents.session.clearStorageData({
                storages: ['cachestorage', 'serviceworkers']
            });
            mainWindow.webContents.reloadIgnoringCache();
            return true;
        } catch (e) {
            console.error('[Cache] Error clearing cache:', e);
        }
    }
    return false;
});

// 2. Hardware: Printers
ipcMain.handle('hardware:get-printers', async () => {
    return await printerManager.getPrinters(mainWindow);
});

ipcMain.handle('hardware:print-thermal', async (event, data) => {
    const { html, printerName, paperWidth, copies } = data;
    const targetPrinter = printerName || settingsStore.get('thermalPrinterName');
    const targetWidth = paperWidth || settingsStore.get('paperWidth') || '80mm';
    return await printerManager.printThermalSilent(html, {
        printerName: targetPrinter,
        paperWidth: targetWidth,
        copies: copies || 1
    });
});

// 3. Hardware: Cash Drawer Kick
ipcMain.handle('hardware:kick-drawer', async (event, printerName) => {
    const targetPrinter = printerName || settingsStore.get('thermalPrinterName');
    return await cashDrawer.kickDrawer(targetPrinter);
});

// 4. Network Ping (Latency Check)
ipcMain.handle('network:ping', async () => {
    const startTime = Date.now();
    const serverUrl = settingsStore.get('serverUrl') || 'https://2m.baraa-solutions.com';
    return new Promise((resolve) => {
        try {
            const urlObj = new URL(serverUrl);
            const client = urlObj.protocol === 'https:' ? https : http;
            const req = client.get(`${serverUrl}/api/v1/ping`, { timeout: 3000 }, (res) => {
                const latency = Date.now() - startTime;
                resolve({ online: true, latency: latency });
            });
            req.on('error', () => {
                resolve({ online: false, latency: 0 });
            });
            req.on('timeout', () => {
                req.destroy();
                resolve({ online: false, latency: 0 });
            });
        } catch (e) {
            resolve({ online: false, latency: 0 });
        }
    });
});

// 5. Configuration
ipcMain.handle('config:get-settings', () => {
    return settingsStore.loadSettings();
});

ipcMain.handle('config:save-settings', (event, newSettings) => {
    const res = settingsStore.saveSettings(newSettings);
    if (newSettings.serverUrl && newSettings.serverUrl !== settingsStore.get('serverUrl')) {
        if (mainWindow) mainWindow.loadURL(newSettings.serverUrl);
    }
    return res;
});

// 6. App Info
ipcMain.handle('app:get-version', () => {
    return app.getVersion();
});

ipcMain.handle('app:get-system-info', () => {
    return {
        version: app.getVersion(),
        electronVersion: process.versions.electron,
        chromeVersion: process.versions.chrome,
        nodeVersion: process.versions.node,
        platform: process.platform,
        arch: process.arch
    };
});

// ══════════════════════════════════════════════════════════════════════════
// 🚀 APP LIFECYCLE
// ══════════════════════════════════════════════════════════════════════════

app.whenReady().then(() => {
    createSplashWindow();
    createMainWindow();

    app.on('activate', () => {
        if (BrowserWindow.getAllWindows().length === 0) {
            createSplashWindow();
            createMainWindow();
        }
    });
});

app.on('window-all-closed', () => {
    if (process.platform !== 'darwin') {
        app.quit();
    }
});
