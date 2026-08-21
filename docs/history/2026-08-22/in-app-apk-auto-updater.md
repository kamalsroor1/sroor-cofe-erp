# سجل تعديل: بناء نظام التحديث التلقائي لتطبيق الأندرويد (In-App APK Auto-Update System)
* **التاريخ والوقت:** 2026-08-22 02:26
* **الدور المفعل:** Backend Architect & Frontend UI Specialist
* **الهدف:** توفير نظام تحديثات هوائي (OTA) لتطبيق الأندرويد يتيح فحص الإصدارات الجديدة، عرض سجل التغييرات بالعربية، وتحميل وتثبيت حزمة الـ APK مباشرة من داخل التطبيق مع إدارة كاملة للإصدارات من لوحة السوبر أدمن.

## 1. الملفات المنشأة والمعدلة:
* `[NEW]` `backend/database/migrations/2026_08_22_000001_create_app_versions_table.php` - جدول إصدارات التطبيق وحزم الـ APK.
* `[NEW]` `backend/app/Models/AppVersion.php` - موديل إصدارات التطبيق مع Scopes والـ Accessors.
* `[NEW]` `backend/app/DTOs/AppVersions/CheckUpdateDTO.php` & `StoreAppVersionDTO.php` - كائنات نقل البيانات الصارمة.
* `[NEW]` `backend/app/Actions/AppVersions/CheckAppUpdateAction.php` - فحص توافر إصدار أحدث وتحديد التحديث الإجباري.
* `[NEW]` `backend/app/Actions/AppVersions/CreateAppVersionAction.php` - رفع وتخزين ملف الـ APK وحساب الـ Checksum.
* `[NEW]` `backend/app/Actions/AppVersions/DownloadLatestApkAction.php` - تحميل مباشر لحزمة الـ APK مع زيادة عداد التحميل.
* `[NEW]` `backend/app/Http/Controllers/Api/V1/SuperAdmin/SuperAdminAppVersionController.php` - إدارة الإصدارات للسوبر أدمن.
* `[MODIFIED]` `backend/app/Http/Controllers/Api/AppUpdateController.php` - كنترولر فحص وتحميل التحديثات.
* `[NEW]` `backend/resources/js/Composables/useAppUpdate.js` - محرك فحص وتنزيل التحديثات في الواجهة.
* `[NEW]` `backend/resources/js/Components/AppUpdateModal.vue` - نافذة التحديث التفاعلية مع شريط التقدم وسجل التغييرات.
* `[NEW]` `backend/resources/js/views/SuperAdmin/SuperAdminAppVersionsView.vue` - شاشة لوحة السوبر أدمن لرفع وإدارة حزم الـ APK.
* `[MODIFIED]` `backend/resources/js/App.vue` - تضمين نافذة التحديث وفحص الإصدار تلقائياً عند الإقلاع.
* `[MODIFIED]` `backend/resources/js/views/Settings/SettingsView.vue` - إضافة بطاقة فحص التحديثات اليدوي في قسم معلومات النظام.

## 2. القرارات التقنية:
* نمط الإجراء الفردي (Single Action Pattern) لجميع عمليات التحديث.
* دعم التحديث الإجباري (Force Update) للأجهزة التي يقل إصدارها عن `min_version_code`.
* تصميم شريط تقدم انسيابي متدرج مع دعم الوضعين الليلي والفاتح وتوافق كامل مع RTL.

## 3. التحقق والاختبار:
* [x] تنفيذ Migration وإنشاء الجدول بنجاح.
* [x] اختبار Action فحص التحديثات بنجاح.
* [x] بناء الحزم بنجاح بـ `vite build` دون أي أخطاء.
