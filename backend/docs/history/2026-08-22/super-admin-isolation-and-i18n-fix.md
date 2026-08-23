# 🚀 سجل تعديل: فصل المنصة المركزية وحل مشكلة الترجمة في شاشة الدخول (Pure API SPA v1.0.1)

* **التاريخ والوقت:** 2026-08-22 16:35
* **الدور المفعل:** Backend Architect & Frontend UI Agent
* **الهدف:** 
  1. عزل وفصل المنصة المركزية (Super Admin Central Hub) حصرياً على الدومين الرئيسي ومنع ظهورها في لوحات الكاشير والمستأجرين.
  2. حل مشكلة ظهور مفاتيح الترجمة (Keys) على شاشة الدخول وحقن الترجمة مسبقاً مع توفير قاموس احتياطي مدمج.
  3. ترقية إصدار الواجهة إلى **Pure API SPA v1.0.1**.

---

## 1. الملفات المعدلة:
* `[MODIFIED]` `backend/resources/views/app.blade.php` - حقن `window.spaTranslations` و `window.spaLocale` مباشرة في رأس الصفحة قبل تحميل السكربتات.
* `[NEW]` `backend/resources/js/helpers/defaultTranslations.js` - قاموس ترجمة عربي احتياطي مدمج لحماية الواجهة من أي تأخير في استجابة الـ API.
* `[MODIFIED]` `backend/resources/js/helpers/trans.js` - دمج القاموس الاحتياطي لمنع ظهور أي مفاتيح خام.
* `[MODIFIED]` `backend/resources/js/views/Auth/LoginView.vue` - جلب الترجمة تلقائياً عبر `onMounted` في حال عدم توفرها.
* `[MODIFIED]` `backend/resources/js/Layouts/SpaLayout.vue` - قصر قسم المنصة المركزية على `authStore.isSuperAdmin` وتحديث الإصدار إلى `v1.0.1`.
* `[MODIFIED]` `backend/resources/js/Layouts/AppLayout.vue` - قصر زر السوبر أدمن على `isSuperAdmin`.
* `[MODIFIED]` `backend/resources/js/router/index.js` - حماية مسارات `/super-admin/*` بـ `superAdminOnly: true`.
* `[MODIFIED]` `backend/routes/api.php` - حماية مسارات API السوبر أدمن بـ `middleware('can:super_admin.access')`.
* `[MODIFIED]` `backend/app/Providers/AppServiceProvider.php` - ضبط `Gate::before` لمنح السوبر أدمن فقط صلاحية `super_admin.access`.
* `[MODIFIED]` `backend/database/seeders/PermissionsSeeder.php` & `DatabaseSeeder.php` - إنشاء وتعيين صلاحية ودور `super_admin`.

---

## 2. القرارات التقنية:
1. **الفصل التام للمنظومة:**
   * **الدومين الرئيسي (`baraa-solutions.com`):** مخصص فقط لإدارة المشتركين، الباقات، إصدارات الـ APK، وإعدادات المنصة.
   * **الدومينات الفرعية (`*.baraa-solutions.com`):** مخصصة لنقاط البيع والكاشير والفروع والمخازن.
2. **الترجمة الفورية دون وميض (Zero-Flicker Instant i18n):**
   * حقن مسبق في الـ Blade + تحميل فوري للقاموس المدمج.

---

## 3. التحقق والاختبار:
* [x] خلو الكود من الأخطاء ونجاح 254 اختباراً من أصل 254 (`php artisan test`).
* [x] نجاح بناء الـ Assets بـ Vite (`npm run build`).
* [x] النشر الحي والتحقق المباشر على `https://baraa-solutions.com/login` (Status 200).
* [x] خلو الكود 100% من أي نصوص ثابتة ودعم العربية والوضعين الفاتح والداكن.
