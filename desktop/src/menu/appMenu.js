const { Menu, app, shell } = require('electron');
const settingsStore = require('../config/settingsStore');
const urlPolicy = require('../security/urlPolicy');
const supportContact = require('../config/supportContact');

function getSafeServerOrigin() {
    const allowDev = !app.isPackaged && process.argv.includes('--dev');
    return urlPolicy.getSafeServerOrigin(settingsStore.get('serverUrl'), { allowDev });
}

function getSupportWhatsappUrl() {
    return supportContact.getSupportWhatsappUrl(
        process.env.SROOR_SUPPORT_WHATSAPP,
        settingsStore.get('supportWhatsapp')
    );
}

function createApplicationMenu(mainWindow) {
    const supportWhatsappUrl = getSupportWhatsappUrl();
    const isMac = process.platform === 'darwin';

    const template = [
        // { role: 'appMenu' } on Mac
        ...(isMac
            ? [
                  {
                      label: 'ERP & POS',
                      submenu: [
                          { role: 'about', label: 'حول المنظومة' },
                          { type: 'separator' },
                          { role: 'services', label: 'الخدمات' },
                          { type: 'separator' },
                          { role: 'hide', label: 'إخفاء' },
                          { role: 'hideOthers', label: 'إخفاء الآخرين' },
                          { role: 'unhide', label: 'إظهار الكل' },
                          { type: 'separator' },
                          { role: 'quit', label: 'إنهاء التطبيق' },
                      ],
                  },
              ]
            : []),

        // File Menu
        {
            label: 'ملف (File)',
            submenu: [
                {
                    label: 'إعادة تحميل الصفحة (Reload)',
                    accelerator: 'CmdOrCtrl+R',
                    click: () => mainWindow.webContents.reload(),
                },
                {
                    label: 'العودة للرئيسية (Home)',
                    accelerator: 'CmdOrCtrl+H',
                    click: () => {
                        mainWindow.loadURL(getSafeServerOrigin());
                    },
                },
                { type: 'separator' },
                {
                    label: 'إغلاق التطبيق (Exit)',
                    accelerator: isMac ? 'Cmd+Q' : 'Alt+F4',
                    click: () => app.quit(),
                },
            ],
        },

        // POS & Fast Actions
        {
            label: 'نقاط البيع (POS)',
            submenu: [
                {
                    label: 'نقطة البيع السريعة (POS)',
                    accelerator: 'F2',
                    click: () => {
                        mainWindow.loadURL(`${getSafeServerOrigin()}/pos`);
                    },
                },
                {
                    label: 'فتح درج النقدية يدويًا (Open Drawer)',
                    accelerator: 'F12',
                    click: async () => {
                        const cashDrawer = require('../hardware/cashDrawer');
                        const defaultPrinter = settingsStore.get('thermalPrinterName');
                        await cashDrawer.kickDrawer(defaultPrinter);
                    },
                },
            ],
        },

        // View Menu
        {
            label: 'عرض (View)',
            submenu: [
                {
                    label: 'ملء الشاشة (Fullscreen)',
                    accelerator: 'F11',
                    click: () => {
                        mainWindow.setFullScreen(!mainWindow.isFullScreen());
                    },
                },
                {
                    label: 'وضع الكاشير المحكم (Kiosk Mode)',
                    accelerator: 'Ctrl+Shift+K',
                    click: () => {
                        const currentKiosk = mainWindow.isKiosk();
                        mainWindow.setKiosk(!currentKiosk);
                        settingsStore.set('kioskMode', !currentKiosk);
                    },
                },
                { type: 'separator' },
                { role: 'resetZoom', label: 'الحجم الطبيعي' },
                { role: 'zoomIn', label: 'تكبير الواجهة' },
                { role: 'zoomOut', label: 'تصغير الواجهة' },
                { type: 'separator' },
                {
                    label: 'أدوات المطورين (DevTools)',
                    accelerator: 'Ctrl+Shift+I',
                    click: () => mainWindow.webContents.toggleDevTools(),
                },
            ],
        },

        // Help Menu
        {
            label: 'مساعدة (Help)',
            submenu: [
                {
                    label: 'موقع المنظومة',
                    click: async () => {
                        await shell.openExternal('https://baraa-solutions.com');
                    },
                },
                {
                    label: 'الدعم الفني والواتساب',
                    visible: Boolean(supportWhatsappUrl),
                    click: async () => {
                        if (supportWhatsappUrl) {
                            await shell.openExternal(supportWhatsappUrl);
                        }
                    },
                },
            ],
        },
    ];

    const menu = Menu.buildFromTemplate(template);
    Menu.setApplicationMenu(menu);
}

module.exports = { createApplicationMenu };
