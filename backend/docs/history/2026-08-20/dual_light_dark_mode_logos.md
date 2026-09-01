# سجل تعديل: إضافة دعم اللوجو المزدوج للوضعين الفاتح والداكن (Dual Light & Dark Mode Logos)
* **التاريخ والوقت:** 2026-08-20 18:22
* **الدور المفعل:** Frontend UI & Backend Architect Agent
* **الهدف:** إضافة دعم الشعار المزدوج المنفصل (لوجو مخصص للوضع الفاتح ولوجو مخصص للوضع الداكن)، مع التبديل اللحظي التلقائي عبر CSS وفئات Tailwind، ودعم رفع وتحديث الشعارين بشكل مستقل من لوحة الإعدادات.

## 1. الملفات المعدلة:
* `[NEW]` `backend/public/logo-light.png` & `backend/public/logo-dark.png` - الشعارين الأساسيين للوضعين.
* `[MODIFIED]` `backend/app/Http/Controllers/SettingController.php` - دعم رفع وحفظ شعار الوضع الفاتح وشعار الوضع الداكن والتحديث اللحظي للكاش.
* `[MODIFIED]` `backend/app/Http/Middleware/HandleInertiaRequests.php` - مشاركة مسارات الشعارين مع خاصية منع الكاش (Cache Busting).
* `[MODIFIED]` `backend/resources/js/Layouts/AppLayout.vue` - عرض الشعار المناسب تلقائياً حسب وضع الشاشة (`dark:hidden` و `hidden dark:block`).
* `[MODIFIED]` `backend/resources/js/Pages/Auth/Login.vue` - دعم الشعار المزدوج في شاشة تسجيل الدخول.
* `[MODIFIED]` `backend/resources/js/Pages/Settings/Index.vue` - إضافة كرتين منفصلين لرفع ومعاينة شعار الوضع الفاتح والداكن.
* `[MODIFIED]` `backend/lang/ar/settings.php` & `backend/lang/en/settings.php` - إضافة الترجمات العربية والإنجليزية للخاصية.

## 2. التحقق والاختبار:
* [x] بناء الأصول بنجاح تام (`npm run build` - Code 0).
* [x] التبديل اللحظي التلقائي بنسبة 100% بدون أي تأخير عند النقر على زر الثيم (☀️ / 🌙).
* [x] دفع التعديلات للمستودعين `origin` و `erp-hub`.
