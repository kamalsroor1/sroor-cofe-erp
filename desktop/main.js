const { app, BrowserWindow, ipcMain, Menu, Tray, nativeImage, shell } = require('electron');
const path = require('path');
const http = require('http');
const https = require('https');
const settingsStore = require('./src/config/settingsStore');
const printerManager = require('./src/hardware/printerManager');
const cashDrawer = require('./src/hardware/cashDrawer');
const { normalizePrintJob } = require('./src/hardware/printJob');
const { createApplicationMenu } = require('./src/menu/appMenu');
const { downloadAndApplyUpdate } = require('./src/updater/nativeUpdater');
const urlPolicy = require('./src/security/urlPolicy');
const { sanitizeSettings } = require('./src/security/settingsSanitizer');

const urlPolicyOptions = { allowDev: !app.isPackaged && process.argv.includes('--dev') };

let mainWindow = null;
let splashWindow = null;
let appTray = null;

// ══════════════════════════════════════════════════════════════════════════
// 🔗 DEEP LINKING PROTOCOL (sroor://connect?tenant=2m)
// ══════════════════════════════════════════════════════════════════════════
if (process.defaultApp) {
    if (process.argv.length >= 2) {
        app.setAsDefaultProtocolClient('sroor', process.execPath, [path.resolve(process.argv[1])]);
    }
} else {
    app.setAsDefaultProtocolClient('sroor');
}

function applyDeepLinkTenant(tenantCode) {
    const serverUrl = urlPolicy.buildTenantOrigin(tenantCode);
    if (!serverUrl) return;
    console.log(`[Electron] Applying deep link workspace: ${tenantCode} -> ${serverUrl}`);
    settingsStore.set('tenantId', tenantCode);
    settingsStore.set('serverUrl', serverUrl);
    if (mainWindow) {
        mainWindow.loadURL(serverUrl + '/login');
        if (mainWindow.isMinimized()) mainWindow.restore();
        mainWindow.show();
        mainWindow.focus();
    }
}

function extractDeepLinkFromArgs(argv) {
    if (!Array.isArray(argv)) return null;
    for (const arg of argv) {
        if (arg && typeof arg === 'string' && arg.startsWith('sroor://')) {
            return urlPolicy.parseDeepLink(arg);
        }
    }
    return null;
}

function getTargetAppUrl() {
    const coldTenant = extractDeepLinkFromArgs(process.argv);
    const coldOrigin = coldTenant ? urlPolicy.buildTenantOrigin(coldTenant) : null;
    if (coldOrigin) {
        settingsStore.set('tenantId', coldTenant);
        settingsStore.set('serverUrl', coldOrigin);
        return `${coldOrigin}/login`;
    }

    const rawUrl = settingsStore.get('serverUrl');
    const rawTenant = settingsStore.get('tenantId');
    const savedUrl = rawUrl ? urlPolicy.sanitizeServerUrl(rawUrl, urlPolicyOptions) : null;
    const savedTenant = rawTenant ? urlPolicy.normalizeTenantSlug(rawTenant) : null;

    // Self-heal a poisoned config: anything outside the origin policy is wiped.
    if ((rawUrl && !savedUrl) || (rawTenant && !savedTenant)) {
        console.warn('[Electron] Saved server settings failed the URL policy; resetting.');
        settingsStore.set('serverUrl', '');
        settingsStore.set('tenantId', '');
        return urlPolicy.CONNECT_URL;
    }

    if (savedUrl && savedUrl !== urlPolicy.CENTRAL_ORIGIN) {
        return savedUrl;
    }

    const tenantOrigin = savedTenant ? urlPolicy.buildTenantOrigin(savedTenant) : null;
    if (tenantOrigin) {
        return `${tenantOrigin}/login`;
    }

    return urlPolicy.CONNECT_URL;
}

function escapeHtml(value) {
    return String(value).replace(
        /[&<>"']/g,
        (ch) =>
            ({
                '&': '&amp;',
                '<': '&lt;',
                '>': '&gt;',
                '"': '&quot;',
                "'": '&#39;',
            })[ch]
    );
}

function getSafeServerOrigin() {
    return urlPolicy.getSafeServerOrigin(settingsStore.get('serverUrl'), urlPolicyOptions);
}

const gotTheLock = app.requestSingleInstanceLock();
if (!gotTheLock) {
    console.log('[Electron] Another instance is already running. Quitting.');
    app.quit();
} else {
    app.on('second-instance', (event, commandLine) => {
        if (mainWindow) {
            if (mainWindow.isMinimized()) mainWindow.restore();
            mainWindow.show();
            mainWindow.focus();
        }
        const tenantCode = extractDeepLinkFromArgs(commandLine);
        if (tenantCode) {
            applyDeepLinkTenant(tenantCode);
        }
    });
}

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
            contextIsolation: true,
            sandbox: true,
        },
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
            sandbox: true,
            spellcheck: false,
            webSecurity: true,
        },
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
                    mainWindow.loadURL(`${getSafeServerOrigin()}/pos`);
                },
            },
            {
                label: 'فتح درج النقدية (F12)',
                click: async () => {
                    const defaultPrinter = settingsStore.get('thermalPrinterName');
                    await cashDrawer.kickDrawer(defaultPrinter);
                },
            },
            { type: 'separator' },
            {
                label: 'إعادة تحميل وتحديث الكاش الفوري (Hard Reload)',
                accelerator: 'CmdOrCtrl+Shift+R',
                click: () => mainWindow.webContents.reloadIgnoringCache(),
            },
            {
                label: 'إعادة تحميل الصفحة (Reload)',
                accelerator: 'CmdOrCtrl+R',
                click: () => mainWindow.webContents.reload(),
            },
            {
                label: 'الشاشة الكاملة (Fullscreen)',
                accelerator: 'F11',
                click: () => mainWindow.setFullScreen(!mainWindow.isFullScreen()),
            },
            { type: 'separator' },
            {
                label: 'أدوات المطورين (DevTools)',
                click: () => mainWindow.webContents.toggleDevTools(),
            },
        ]);
        contextMenu.popup();
    });

    // Setup Application Menu (Hidden/Keyboard access)
    createApplicationMenu(mainWindow);

    // Setup System Tray
    createSystemTray();

    // Target URL (Remote cloud tenant or universal workspace connect)
    const targetUrl = getTargetAppUrl();

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
        // -3 = ERR_ABORTED: produced by our own navigation guard; keep the current page.
        if (errorCode === -3) return;
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
                    <p>يرجى التأكد من اتصال الإنترنت أو صحة رابط الخادم: <br><b style="color:#38bdf8;">${escapeHtml(targetUrl)}</b></p>
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
                },
            },
            {
                label: 'نقطة البيع (POS)',
                click: () => {
                    if (mainWindow) {
                        mainWindow.loadURL(`${getSafeServerOrigin()}/pos`);
                        mainWindow.show();
                        mainWindow.focus();
                    }
                },
            },
            {
                label: 'فتح درج النقدية',
                click: async () => {
                    const defaultPrinter = settingsStore.get('thermalPrinterName');
                    await cashDrawer.kickDrawer(defaultPrinter);
                },
            },
            { type: 'separator' },
            {
                label: 'إغلاق التطبيق نهائياً',
                click: () => app.quit(),
            },
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

// Only the top frame of the main window, on an allowed app origin, may call IPC.
function isTrustedSender(event) {
    if (!mainWindow || mainWindow.isDestroyed()) return false;
    if (!event || event.sender !== mainWindow.webContents) return false;
    const frame = event.senderFrame;
    if (!frame || frame !== event.sender.mainFrame) return false;
    return urlPolicy.isAllowedAppUrl(frame.url, urlPolicyOptions);
}

function handleTrusted(channel, fn) {
    ipcMain.handle(channel, (event, ...args) => {
        if (!isTrustedSender(event)) {
            console.warn('[Electron] Rejected IPC from untrusted sender on', channel);
            return Promise.reject(new Error('Untrusted IPC sender'));
        }
        return fn(event, ...args);
    });
}

// 1. Window Controls for Custom Frameless Titlebar
handleTrusted('window:minimize', () => {
    if (mainWindow) mainWindow.minimize();
});

handleTrusted('window:maximize', () => {
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

handleTrusted('window:is-maximized', () => {
    return mainWindow ? mainWindow.isMaximized() : false;
});

handleTrusted('window:close', () => {
    if (mainWindow) mainWindow.close();
});

handleTrusted('window:toggle-fullscreen', () => {
    if (mainWindow) {
        const nextState = !mainWindow.isFullScreen();
        mainWindow.setFullScreen(nextState);
        return nextState;
    }
    return false;
});

handleTrusted('window:toggle-kiosk', () => {
    if (mainWindow) {
        const nextKiosk = !mainWindow.isKiosk();
        mainWindow.setKiosk(nextKiosk);
        settingsStore.set('kioskMode', nextKiosk);
        return nextKiosk;
    }
    return false;
});

handleTrusted('window:reload', () => {
    if (mainWindow) mainWindow.webContents.reload();
});

handleTrusted('window:hard-reload', () => {
    if (mainWindow) mainWindow.webContents.reloadIgnoringCache();
});

handleTrusted('window:clear-cache', async () => {
    if (mainWindow) {
        try {
            await mainWindow.webContents.session.clearCache();
            await mainWindow.webContents.session.clearStorageData({
                storages: ['cachestorage', 'serviceworkers'],
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
handleTrusted('hardware:get-printers', async () => {
    return await printerManager.getPrinters(mainWindow);
});

// Silent print first; if the printer fails or is missing, the print dialog (choose a printer
// or "Microsoft Print to PDF") and then a PDF save are offered. Results carry codes only.
handleTrusted('hardware:print-thermal', async (event, data) => {
    const job = normalizePrintJob(data, {
        thermalPrinterName: settingsStore.get('thermalPrinterName'),
        paperWidth: settingsStore.get('paperWidth'),
    });
    if (!job) {
        return { success: false, error: 'invalid_print_job' };
    }
    return await printerManager.printThermal(job.html, job, mainWindow);
});

// 3. Hardware: Cash Drawer Kick
// ESC/POS pulse to the receipt printer, driver job as fallback (see drawerKick.js).
handleTrusted('hardware:kick-drawer', async (event, printerName) => {
    return await cashDrawer.kickDrawer(typeof printerName === 'string' ? printerName : '');
});

// 4. Network Ping (Latency Check)
handleTrusted('network:ping', async () => {
    const startTime = Date.now();
    const serverUrl = getSafeServerOrigin();
    const client = serverUrl.startsWith('http:') ? http : https;
    return new Promise((resolve) => {
        try {
            const req = client.get(`${serverUrl}/api/v1/ping`, { timeout: 3000 }, (res) => {
                res.resume();
                resolve({ online: true, latency: Date.now() - startTime });
            });
            req.on('error', () => {
                resolve({ online: false, latency: 0 });
            });
            req.on('timeout', () => {
                req.destroy();
                resolve({ online: false, latency: 0 });
            });
        } catch {
            resolve({ online: false, latency: 0 });
        }
    });
});

// 5. Configuration
handleTrusted('config:get-settings', () => {
    return settingsStore.loadSettings();
});

handleTrusted('config:save-settings', (event, newSettings) => {
    const sanitized = sanitizeSettings(newSettings, urlPolicyOptions);
    if (!sanitized.ok) {
        console.warn('[Electron] Rejected invalid settings from renderer.');
        return { success: false, error: 'invalid_settings' };
    }
    const safeSettings = sanitized.settings;
    const oldUrl = settingsStore.get('serverUrl');
    const res = settingsStore.saveSettings(safeSettings);
    const safeUrl = safeSettings.serverUrl;
    if (res.success && safeUrl && safeUrl !== oldUrl && mainWindow) {
        const keepPath = new URL(safeUrl).pathname !== '/';
        mainWindow.loadURL(keepPath ? safeUrl : `${safeUrl}/login`);
    }
    return res;
});

// 6. App Info
handleTrusted('app:get-version', () => {
    return app.getVersion();
});

handleTrusted('app:get-system-info', () => {
    return {
        version: app.getVersion(),
        electronVersion: process.versions.electron,
        chromeVersion: process.versions.chrome,
        nodeVersion: process.versions.node,
        platform: process.platform,
        arch: process.arch,
    };
});

// 7. In-App Native Electron OTA Auto-Updater
// Renderer arguments are ignored: the manifest (URL + SHA-256) is fetched by the main process.
handleTrusted('updater:download-and-install', async () => {
    return await downloadAndApplyUpdate(mainWindow);
});

// ══════════════════════════════════════════════════════════════════════════
// 🚀 APP LIFECYCLE
// ══════════════════════════════════════════════════════════════════════════

// ══════════════════════════════════════════════════════════════════════════
// 🛡️ NAVIGATION / POPUP / WEBVIEW LOCKDOWN (applies to every webContents)
// ══════════════════════════════════════════════════════════════════════════
app.on('web-contents-created', (_event, contents) => {
    const blockForeignNavigation = (event, url) => {
        if (!urlPolicy.isAllowedAppUrl(url, urlPolicyOptions)) {
            console.warn('[Electron] Blocked navigation to disallowed URL:', url);
            event.preventDefault();
        }
    };
    contents.on('will-navigate', blockForeignNavigation);
    contents.on('will-redirect', blockForeignNavigation);
    contents.on('will-attach-webview', (event) => event.preventDefault());

    contents.setWindowOpenHandler(({ url }) => {
        if (urlPolicy.isAllowedAppUrl(url, urlPolicyOptions)) {
            // Same-origin popups (invoice print). No preload bridge in popups.
            return {
                action: 'allow',
                overrideBrowserWindowOptions: {
                    autoHideMenuBar: true,
                    webPreferences: {
                        preload: undefined,
                        sandbox: true,
                        contextIsolation: true,
                        nodeIntegration: false,
                    },
                },
            };
        }
        try {
            const { protocol } = new URL(url);
            if (protocol === 'https:' || protocol === 'http:') {
                shell.openExternal(url);
            }
        } catch {
            // Unparseable URL: deny silently.
        }
        return { action: 'deny' };
    });
});

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
