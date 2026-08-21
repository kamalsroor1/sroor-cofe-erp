# سجل تعديل: المرحلة 4 - Module 01: الفروع والمخازن والأرصدة (Stores & Stocks)
* **التاريخ والوقت:** 2026-08-21 14:49
* **الدور المفعل:** Backend Architect & Frontend UI Agent
* **الهدف:** تحويل موديول الفروع والمخازن والأرصدة من Inertia.js إلى Pure API بالكامل، مع تطبيق معايير SOLID، Single Action Pattern، Strictly Typed DTOs، API Resources، وبناء شاشات StoresView و StoreStocksView في Vue 3 SPA.

## 1. الملفات المنشأة / المعدلة:
* `[NEW]` `backend/app/DTOs/Stores/StoreDTO.php` - DTO لإنشاء وتعديل الفروع.
* `[NEW]` `backend/app/Actions/Stores/CreateStoreAction.php` - Single Action لإنشاء الفرع وإدارة الفرع الرئيسي داخل Transaction.
* `[NEW]` `backend/app/Actions/Stores/UpdateStoreAction.php` - Single Action لتحديث بيانات الفرع.
* `[NEW]` `backend/app/Actions/Stores/DeleteStoreAction.php` - Single Action لحذف الفرع مع فحص الموانع التشغيلية.
* `[NEW]` `backend/app/Actions/Stores/ToggleStoreActiveAction.php` - Single Action لتفعيل/تعطيل الفرع مع حماية الفرع الرئيسي.
* `[NEW]` `backend/app/Actions/Stores/AssignStoreUsersAction.php` - Single Action لتعيين الموظفين للفروع.
* `[NEW]` `backend/app/Actions/Stores/GetStoreStocksAction.php` - Single Action لاستعلام أرصدة المخازن مع الفلترة والترقيم.
* `[MODIFIED]` `backend/app/Http/Resources/StoreResource.php` - تنسيق بيانات الفرع والمستخدمين والإحصائيات.
* `[NEW]` `backend/app/Http/Resources/StoreStockResource.php` - تنسيق أرصدة المخازن وحساب التقييم بدقة bcmath.
* `[MODIFIED]` `backend/app/Http/Controllers/Api/StoreController.php` - متحكم API كامل للفروع والأرصدة.
* `[MODIFIED]` `backend/routes/api.php` - تسجيل مسارات الفروع الكاملة.
* `[NEW]` `backend/resources/js/views/Stores/StoresView.vue` - شاشة إدارة الفروع في Vue 3 SPA.
* `[NEW]` `backend/resources/js/views/Stores/StoreStocksView.vue` - شاشة تتبع أرصدة ونواقص المخازن.
* `[MODIFIED]` `backend/resources/js/router/index.js` - تسجيل مسارات الفروع في Vue Router.
* `[MODIFIED]` `backend/resources/js/Layouts/SpaLayout.vue` - إضافة روابط الفروع في القائمة الجانبية.
* `[NEW]` `backend/tests/Feature/Api/StoresApiTest.php` - اختبارات Feature شاملة لكافة عمليات الفروع والأرصدة.

## 2. القرارات التقنية:
* استخدام `bcmul` في `StoreStockResource` لتقييم الأرصدة بدقة مالية متناهية.
* تأمين عمليات الفروع داخل `DB::transaction()` وإدارة حالة `is_main` لضمان وجود فرع رئيسي واحد دائمًا.
* بناء شاشات SPA متوافقة تماماً مع شاشات اللمس وهوية النظام (Dark Slate & Amber).

## 3. التحقق والاختبار:
* [x] اجتياز كافة اختبارات `StoresApiTest` بنجاح 100% (9/9 tests passed, 39 assertions).
* [x] اجتياز إجمالي الاختبارات التراكمية للمراحل (23/23 tests passed, 148 assertions).
* [x] اجتياز بناء Vite بالكامل (`npm run build` في 6.34 ثانية).
