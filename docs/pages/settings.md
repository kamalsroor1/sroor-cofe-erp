# ⚙️ وثيقة المكون والصفحة: إعدادات النظام والتحكم الشامل (`SettingsView.vue`)

> **المسار (Route):** `/settings`  
> **الملف الرئيسي:** `resources/js/views/Settings/SettingsView.vue` (Thin Orchestrator: ~75 سطر)  
> **تاريخ المراجعة الشاملة:** 2026-08-24  
> **الحالة:** ✅ مكتملة وموثقة 100% عبر المحاور الأربعة المتزامنة (الصفحة رقم 25 والأخيرة في المنظومة).

---

## 1. التحليل التشغيلي والمعماري (Operational & Architectural Analysis)

### 🎯 الغرض من الصفحة:
تُمثل شاشة **إعدادات النظام والتحكم الشامل (System Settings & Customization)** لوحة القيادة العليا لتخصيص سلوك وهوية المنظومة:
1. **إعدادات الهوية المؤسسية (Branding & Organization Profile):** اسم المؤسسة، الشعار، العنوان، الهاتف، والوصف التجاري.
2. **تخصيص المظهر ونظام الألوان (Appearance & Color Themes):** باليتات ألوان جاهزة (Amber, Emerald, Blue, Purple, Rose, Orange, Teal, Indigo) + أداة اختيار لون سداسي مخصص (HEX) مع قطارة الألوان المباشرة من الشعار (EyeDropper API) وتبديل الوضع الداكن/الفاتح.
3. **تخصيص الطباعة الحرارية والفواتير (Thermal Printing & Invoices):** خيارات إظهار اسم الشركة، الشعار، رصيد العميل السابق، رمز الاستجابة السريعة (QR Code)، وخاتمة الفاتورة.
4. **ربط بوت تيليجرام للإشعارات الفورية (Telegram Bot Integration):** توكن البوت ومعرف المحادثة (Chat ID) مع زر فحص الإرسال المباشر.
5. **إدارة وحدات القياس المعتمدة للمخزون (Inventory Units):** تفعيل وحذف وإضافة وحدات القياس المخصصة أو الاختيار من القوالب الجاهزة.

---

## 2. هيكلية وشجرة المكونات (Component Tree & Architecture)

تم تفكيك الصفحة من ملف بـ 823 سطرًا إلى **Thin Orchestrator** ومكونات أحادية المسؤولية:

```text
resources/js/
├── views/Settings/
│   └── SettingsView.vue                       <-- Thin Orchestrator (~75 lines)
├── Components/Settings/
│   ├── SettingsNavigationSidebar.vue          <-- القائمة الجانبية للشاشات الكبيرة
│   ├── SettingsMobileHub.vue                  <-- شبكة بطاقات الهب اللمسية للهواتف
│   ├── SettingsBrandingSection.vue            <-- قسم الهوية المؤسسية وبيانات الاتصال
│   ├── SettingsAppearanceSection.vue          <-- قسم باليتات الألوان واللون المخصص والوضع الليلي
│   ├── SettingsPrintingSection.vue            <-- قسم خيارات الطباعة الحرارية وتذييل الفاتورة
│   ├── SettingsTelegramSection.vue            <-- قسم إعدادات بوت التيليجرام وفحص الاتصال
│   └── SettingsUnitsSection.vue               <-- قسم وحدات القياس المعتمدة للأصناف
└── Composables/
    └── useSettings.js                         <-- كبسولة المنطق والاتصال بالـ API و EyeDropper والثيم
```

---

## 3. عناصر النماذج والواجهات المشتركة المستخدمة

* `PageHeader.vue`: ترويسة الصفحة وزر حفظ التعديلات ورجوع الهواتف.
* `BaseButton.vue`: أزرار الحفظ والإجراءات مع مؤشرات التحميل.
* `BaseInput.vue`: حقول إدخال النصوص والأرقام والهواتف.

---

## 4. الاعتماديات والـ APIs المرتبطة

| العملية | الـ Endpoint | الطلب (Request Payload) | الاستجابة |
| :--- | :--- | :--- | :--- |
| **جلب إعدادات المنظومة** | `GET /api/v1/settings` | - | كائن الإعدادات الكامل للمؤسسة |
| **حفظ وتحديث الإعدادات** | `POST /api/v1/settings` | `form` (branding, appearance, printing, telegram, units) | حفظ التغييرات وتحديث Pinia Store |
| **اختبار اتصال بوت تيليجرام** | `POST /api/v1/settings/telegram/test` | `bot_token`, `chat_id` | نتيجة الإرسال الفوري للرسالة التجريبية |

---

## 5. فحص التجاوب وتجربة اللمس والوضعين (Responsive & Touch Ergonomics)

* **📱 هواتف (360px - 430px):**
  * نمط Drill-Down ذكي: شبكة بطاقات رئيسية (Settings Hub) سهلة النقر بالإبهام، وعند النقر على قسم يتم فتح شاشة الإعدادات المخصصة مع زر رجوع `ArrowRight` أعلى الصفحة ومفاتيح تبديل Toggles بارتفاع $\ge 44	ext{px}$.
* **💻 تابلت وديسكتوب (768px - 1280px+):**
  * تخطيط مقسوم أنيق (Split Layout) بقائمة جانبية ذات أيقونات ملونة وعمود تفصيلي للمحتوى النشط.
* **🌓 الوضع الداكن والفاتح:** تباين كامل للبطاقات والصفوف وحقول الإدخال.

---

## 6. قاموس الترجمة (100% Zero Hardcoded Localization)

كافة النصوص تستند إلى ملفات الترجمة المركزية في `lang/ar/settings.php` و `lang/en/settings.php`:
* `settings.title`: إعدادات النظام والتحكم الشامل / System & Organization Settings
* `settings.sec_branding_label`: الهوية والمؤسسة / Branding & Organization
* `settings.sec_appearance_label`: المظهر وثيم الألوان / Appearance & Theme
* `settings.sec_printing_label`: الفواتير والطباعة / Invoices & Thermal Printing
* `settings.sec_telegram_label`: إشعارات تلجرام / Telegram Notifications
* `settings.sec_units_label`: وحدات القياس للأصناف / Item Measurement Units
* `settings.settings_saved_success`: تم حفظ وتحديث إعدادات النظام بنجاح / System settings saved and updated successfully

---

## 7. سجل الاختبارات والتحقق (Test Results)

* ✅ **Playwright E2E Test:** `e2e/flows/settings-full-page-audit.spec.js` -> نجاح 7/7 اختبارات عبر كافة مقاسات الشاشات الـ 5 بدون أي خطأ Console.
* ✅ **Build Verification:** `npm run build` -> تم البناء بنجاح 100%.
