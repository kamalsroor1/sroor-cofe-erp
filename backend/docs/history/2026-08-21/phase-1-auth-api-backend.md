# سجل تعديل: المرحلة 1 - بناء Auth API (Backend)
* **التاريخ والوقت:** 2026-08-21 14:38
* **الدور المفعل:** Backend Architect Agent
* **الهدف:** تجهيز Laravel Sanctum، ترحيل جداول `personal_access_tokens` في القواعد المركزية والمستأجرين، بناء `ApiLoginDTO` و `ApiLoginRequest` و `ApiLoginAction` و `ApiLogoutAction` و `ApiMeAction`، وربطها في `AuthController`، مع توحيد استجابات JSON والخلو التام من النصوص الثابتة واختبارها عبر `AuthApiTest`.

## 1. الملفات المنشأة / المعدلة:
* `[NEW]` `backend/database/migrations/2019_12_14_000001_create_personal_access_tokens_table.php` - تهجير جدول توكنات Sanctum المركزي.
* `[NEW]` `backend/database/migrations/tenant/2019_12_14_000001_create_personal_access_tokens_table.php` - تهجير جدول توكنات Sanctum للمستأجرين.
* `[NEW]` `backend/app/DTOs/Auth/ApiLoginDTO.php` - كائن نقل البيانات لطلب تسجيل الدخول.
* `[NEW]` `backend/app/Http/Requests/Auth/ApiLoginRequest.php` - التحقق من صحة المدخلات ومحدد المعدل RateLimiter.
* `[NEW]` `backend/app/Actions/Auth/ApiLoginAction.php` - Single Action لإتمام المصادقة وإصدار توكن Sanctum.
* `[NEW]` `backend/app/Actions/Auth/ApiLogoutAction.php` - Single Action لإلغاء التوكن وتسجيل الخروج.
* `[NEW]` `backend/app/Actions/Auth/ApiMeAction.php` - Single Action لاسترجاع بيانات المستخدم وسياق المتجر والنظام.
* `[NEW]` `backend/app/Http/Middleware/ResolveApiTenancy.php` - التعرف الديناميكي على المستأجر عبر الدومين أو الهيدر `X-Tenant`.
* `[MODIFIED]` `backend/app/Models/User.php` - إضافة `HasApiTokens`.
* `[MODIFIED]` `backend/app/Http/Resources/UserResource.php` - تنسيق مخرجات المستخدم والصلاحيات.
* `[MODIFIED]` `backend/app/Http/Middleware/ApiTokenAuth.php` - دعم مصادقة Sanctum والتوكنات القديمة.
* `[MODIFIED]` `backend/app/Http/Controllers/Api/AuthController.php` - إعادة الهيكلة وفق مبادئ SOLID و Form Requests.
* `[MODIFIED]` `backend/routes/api.php` - تطبيق ResolveApiTenancy.
* `[MODIFIED]` `backend/bootstrap/app.php` - معالجة استثناءات المصادقة والتحقق الموحدة.
* `[MODIFIED]` `backend/lang/ar/auth.php` و `backend/lang/en/auth.php` - ترجمة جميع رسائل المصادقة بدون نصوص ثابتة.
* `[NEW]` `backend/tests/Feature/Api/AuthApiTest.php` - اختبارات الـ Feature للـ Auth API بالكامل.

## 2. القرارات التقنية:
* اعتماد Sanctum Token-based مع إمكانية تمرير الهيدر `X-Tenant` و `X-Store-Id`.
* فصل المنطق بالكامل داخل كلاسات Actions مستقلة في `app/Actions/Auth/`.
* منع كسر التوافق مع تطبيق الموبايل الحالي مع توفير دعم كامل لـ Vue 3 SPA.

## 3. التحقق والاختبار:
* [x] اجتياز كافة اختبارات `AuthApiTest` بنجاح 100% (7/7 tests passed, 48 assertions).
* [x] التأكد من حماية Rate Limiter وتشفير كلمات المرور.
* [x] الخلو التام من النصوص الثابتة واستخدام `trans()` و `__()`.
