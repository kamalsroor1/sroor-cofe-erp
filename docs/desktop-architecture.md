# 🖥️ دليل وهيكلية تطبيق سطح المكتب (Electron Desktop POS & ERP)

> **الإصدار:** v1.0.0  
> **البيئة المستهدفة:** Windows (x64 / ia32) / macOS / Linux  
> **الموقع:** `desktop/`

---

## 1. الغرض المعماري (Architecture Purpose)

تطبيق **Electron Desktop Shell** مخصص لمحطات نقاط البيع (POS Cashier Stations) وإدارة الفروع على أجهزة الكمبيوتر والمكاتب لتوفير قدرات عتادية متقدمة غير متاحة في المتصفح العادي:

1. **الطباعة الحرارية المباشرة والصامتة (Direct Silent ESC/POS):** إرسال أمر الطباعة لطابعات الإيصالات (USB / Network / Bluetooth) بدون نافذة متصفح أو تأخير.
2. **التحكم التلقائي في درج النقدية (Cash Drawer Kick):** إرسال نبضة كهربائية (`ESC p 0 25 250`) لفتح الدرج آلياً مع المبيعات النقدية.
3. **وضع الكاشير المحكم (Kiosk Mode):** قفل الشاشة لمنع الخروج غير المصرح به أثناء وردية الكاشير.
4. **حفظ حالة الشاشة والنافذة (Window State Persistence):** تذكر المقاس، الموضع، وحالة التكبير `maximize`.
5. **جسر أمان عالي (Context Isolation & Preload Bridge):** عزل تام لـ Node.js عن كود الـ Web وحصر التخاطب عبر `window.electronAPI`.

---

## 2. الهيكل البرمجي لمجلد `desktop/`

```text
desktop/
├── package.json                   <-- تبعيات Electron و Electron Builder و أوامر التشغيل
├── main.js                        <-- المعالج الرئيسي (Main Process) وإدارة النوافذ وقنوات IPC
├── preload.js                     <-- جسر الأمان الموحد (ContextBridge Exposer)
├── src/
│   ├── config/
│   │   └── settingsStore.js       <-- تخزين وحفظ إعدادات الديسكتوب والفرع والطابعة
│   ├── hardware/
│   │   ├── printerManager.js      <-- إدارة الطابعات والطباعة الصامتة بمقاسي 80mm و 58mm
│   │   └── cashDrawer.js          <-- إرسال نبضات فتح درج النقدية
│   └── menu/
│       └── appMenu.js             <-- القائمة الأصلية للديسكتوب باللغة العربية (ملف، عرض، نقاط البيع، مساعدة)
└── build/
    ├── icon.ico                   <-- أيقونة الويندوز بدقات متعددة (16x16 حتى 256x256)
    └── icon.png                   <-- أيقونة التطبيق الشفافة عالية الدقة
```

---

## 3. قنوات التخاطب الآمنة (IPC Channels)

| القناة (IPC Channel) | المعالج (Handler) | الوصف |
| :--- | :--- | :--- |
| `hardware:get-printers` | `printerManager.getPrinters()` | قراءة كافة الطابعات المعرفة في نظام التشغيل |
| `hardware:print-thermal` | `printerManager.printThermalSilent()` | طباعة صامتة فورية لإيصال البيع |
| `hardware:kick-drawer` | `cashDrawer.kickDrawer()` | فتح درج الكاشير عبر نبضة الطابعة |
| `window:toggle-fullscreen` | `mainWindow.setFullScreen()` | التبديل بين ملء الشاشة والوضع العادي |
| `window:toggle-kiosk` | `mainWindow.setKiosk()` | وضع الكاشير المقفل بدون إطارات |
| `config:get-settings` | `settingsStore.settings` | جلب إعدادات رابط الخادم والطابعة |
| `config:save-settings` | `settingsStore.saveSettings()` | حفظ إعدادات المستخدم والعتاد |

---

## 4. تكامل الواجهة الأمامية (Vue 3 Frontend Integration)

* **الـ Composable المخصص:** `resources/js/Composables/useDesktopHardware.js`
* **المودال التفاعلي:** `resources/js/Components/Common/DesktopPrinterSettingsModal.vue`
* **الشريط العلوي:** زر هاردوير مخصص `🖨️` يظهر تلقائياً فقط عند تشغيل التطبيق عبر Electron.

---

## 5. أوامر التشغيل والبناء (Commands)

```bash
# تشغيل التطبيق في وضع التطوير (Development):
cd desktop
npm start

# استخراج ملف تثبيت ويندوز كامل Setup (.exe):
npm run dist:win

# استخراج نسخة محمولة بدون تثبيت Portable (.exe):
npm run dist:portable
```
