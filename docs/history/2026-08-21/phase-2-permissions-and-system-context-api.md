# سجل تعديل: المرحلة 2 - Permissions & System Context API
* **التاريخ والوقت:** 2026-08-21 14:41
* **الدور المفعل:** Backend Architect Agent
* **الهدف:** بناء نقاط النهاية للصلاحيات والأدوار وسياق النظام الكامل للـ SPA (`/api/v1/permissions`, `/api/v1/system/context`, `/api/v1/system/translations`) كبديل معماري لـ `Inertia::share()`، مع هندلة استثناءات `UnauthorizedException` بصيغة 403 JSON واختبارها عبر `PermissionsAndContextApiTest`.

## 1. الملفات المنشأة / المعدلة:
* `[NEW]` `backend/app/Actions/Permissions/GetPermissionsTreeAction.php` - Single Action لاستخراج شجرة الصلاحيات وصلاحيات وأدوار المستخدم.
* `[NEW]` `backend/app/Actions/System/GetTranslationsAction.php` - Single Action لتحميل قواميس الترجمة الكاملة للغات المعتمدة.
* `[NEW]` `backend/app/Actions/System/GetSystemContextAction.php` - Single Action لتجميع سياق النظام الشامل (المستخدم، الفرع النشط، الوردية، التنبيهات، الثيم، الهوية).
* `[NEW]` `backend/app/Http/Controllers/Api/PermissionApiController.php` - متحكم استعلام شجرة وصلاحيات المستخدم.
* `[NEW]` `backend/app/Http/Controllers/Api/SystemContextApiController.php` - متحكم سياق النظام والترجمات.
* `[MODIFIED]` `backend/routes/api.php` - تسجيل مسارات الصلاحيات والسياق والترجمات.
* `[MODIFIED]` `backend/bootstrap/app.php` - معالجة استثناءات الصلاحيات `UnauthorizedException` بصيغة JSON 403.
* `[NEW]` `backend/tests/Feature/Api/PermissionsAndContextApiTest.php` - اختبارات الـ Feature لسياق النظام والصلاحيات والترجمات.

## 2. القرارات التقنية:
* توفير نقطة نهاية موحدة `/api/v1/system/context` تتيح للـ Vue 3 SPA تحميل كافة بيانات الجلسة، والفرع المختار، والوردية النشطة، والتنبيهات، والترجمات بطلب واحد سريع.
* توفير شجرة الصلاحيات المفصلة من خلال `/api/v1/permissions` لتمكين واجهات إدارة الأدوار من العمل بنظام Pure API.

## 3. التحقق والاختبار:
* [x] اجتياز كافة اختبارات `PermissionsAndContextApiTest` بنجاح 100% (5/5 tests passed, 55 assertions).
* [x] اجتياز إجمالي اختبارات المرحلتين 1 و 2 (12/12 tests passed, 103 assertions).
* [x] الخلو التام من أي نصوص ثابتة واستخدام قواميس الترجمة المعتمدة.
