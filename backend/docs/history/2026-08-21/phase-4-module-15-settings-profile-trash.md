# سجل تعديل: المرحلة 4 - Module 15: الإعدادات والملف الشخصي وسلة المهملات (Settings, Profile & Trash)
* **التاريخ والوقت:** 2026-08-21 15:43
* **الدور المفعل:** Backend Architect & Frontend UI Agent
* **الهدف:** تحويل إعدادات النظام والمؤسسة والهوية والطباعة، وإدارة الملف الشخصي للمستخدم، وسلة المهملات واسترجاع المحذوفات من Inertia.js إلى Pure API بالكامل مع تطبيق معايير SOLID، Single Action Pattern، DTOs، Form Requests، وبناء الشاشات الثلاث في Vue 3 SPA.

## 1. الملفات المنشأة / المعدلة:
* `[NEW]` `backend/app/Actions/Settings/UpdateSettingsAction.php` - Single Action لحفظ وتحديث إعدادات النظام وتفريغ كاش الإعدادات.
* `[NEW]` `backend/app/Actions/Profile/UpdateProfileAction.php` - Single Action لتحديث الملف الشخصي وتفضيلات الثيم وتغيير كلمة المرور.
* `[NEW]` `backend/app/Actions/Trash/GetTrashRecordsAction.php` - Single Action لجلب وتصنيف بيانات سلة المحذوفات للأقسام المختلفة.
* `[NEW]` `backend/app/Actions/Trash/RestoreTrashRecordAction.php` - Single Action لاسترجاع السجلات المحذوفة.
* `[NEW]` `backend/app/Actions/Trash/ForceDeleteTrashRecordAction.php` - Single Action للحذف النهائي لسجلات سلة المهملات.
* `[REFACTORED]` `backend/app/Http/Requests/UpdateSettingsRequest.php` & `backend/app/Http/Requests/UpdateProfileRequest.php` - Form Requests للتحقق الصارم ومنع أي `$request->validate()` في الكنترولرز.
* `[REFACTORED]` `backend/app/Http/Controllers/Api/SettingController.php` - متحكم API نقي خالي تماماً من أي `$request->validate()`.
* `[NEW]` `backend/app/Http/Controllers/Api/ProfileController.php` - متحكم API للملف الشخصي.
* `[NEW]` `backend/app/Http/Controllers/Api/TrashController.php` - متحكم API لسلة المهملات.
* `[MODIFIED]` `backend/routes/api.php` - تسجيل مسارات الإعدادات، الملف الشخصي، وسلة المهملات.
* `[NEW]` `backend/resources/js/views/Settings/SettingsView.vue` - شاشة إعدادات النظام والمؤسسة، تبويبات الهوية، الطباعة والفواتير الحرارية، ربط بوت تلجرام، ومعلومات الخادم.
* `[NEW]` `backend/resources/js/views/Profile/ProfileView.vue` - شاشة الملف الشخصي، تعديل الاسم والهاتف والبريد، تغيير كلمة المرور، وتفضيل المظهر الفاتح/الداكن.
* `[NEW]` `backend/resources/js/views/Trash/TrashView.vue` - سلة المهملات الموحدة بـ 6 تبويبات للأصناف والعملاء والموردين والفروع والمصروفات والمرتجعات مع عدادات لحظية واسترجاع وحذف نهائي.
* `[MODIFIED]` `backend/resources/js/router/index.js` - تسجيل مسارات `/settings`, `/profile`, `/trash`.
* `[MODIFIED]` `backend/resources/js/Layouts/SpaLayout.vue` - تفعيل روابط الإعدادات وسلة المهملات والملف الشخصي في القائمة والهيدر.
* `[NEW]` `backend/tests/Feature/Api/SettingsProfileTrashApiTest.php` - حزمة 3 اختبارات Feature شاملة بنسبة نجاح 100%.

## 2. القرارات التقنية:
* التحقق التام عبر Form Requests وفصلها تماماً عن الكنترولر.
* استخدام `Setting::allCached()` والتفريغ الفوري لكاش الإعدادات عند التعديل.
* تشفير كلمات المرور باستخدام `Hash::make()` والتحقق من كلمة المرور السابقة قبل التغيير.
* دعم الاسترجاع والحذف النهائي لـ 6 أنواع من الموديلات الحيوية (Items, Customers, Suppliers, Stores, Expenses, Returns).

## 3. التحقق والاختبار:
* [x] اجتياز كافة اختبارات `SettingsProfileTrashApiTest` بنجاح 100% (3/3 tests passed, 27 assertions).
* [x] اجتياز إجمالي الاختبارات التراكمية للمراحل (99/99 tests passed, 611 assertions).
* [x] اجتياز بناء Vite بالكامل (`npm run build` في 4.84 ثانية).
