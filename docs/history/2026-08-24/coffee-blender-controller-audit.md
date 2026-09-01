# سجل تعديل: تدقيق وريفاكتور CoffeeBlenderController واستئصال الكود الميت
* **التاريخ والوقت:** 2026-08-24 04:39
* **الدور المفعل:** Backend Architect & Financial Inventory Agent
* **الهدف:** مراجعة وتدقيق `CoffeeBlenderController`، إنشاء كلاسات التحقق، تطبيق حسابات الدقة المالية `bcmath`، وحذف الكنترولرز الميتة والمكررة.

## 1. الملفات المعدلة والمنشأة:
* `[NEW]` `backend/app/Http/Requests/CalculateBlendCostRequest.php` - كلاس Form Request للتحقق من نسب وأسعار مكونات التوليفة مع التحقق من الصلاحيات.
* `[MODIFIED]` `backend/app/Http/Controllers/Api/CoffeeBlenderController.php` - ريفاكتور للكنترولر ليصبح Thin Controller منضبط الأنواع والصلاحيات.
* `[MODIFIED]` `backend/tests/Feature/Api/CoffeeBlenderApiTest.php` - حزمة Feature Test خماسية المحاور (6 اختبارات كاملة تشمل الحسابات، الخصم المخزني، الصلاحيات، والـ 422 Validation).
* `[MODIFIED]` `backend/routes/tenant.php` - توجيه المسارات إلى الكنترولر الموحد.
* `[DELETED]` `backend/app/Http/Controllers/Api/BlenderController.php` - حذف المسودة القديمة الميتة.
* `[DELETED]` `backend/app/Http/Controllers/CoffeeBlenderController.php` - حذف الكنترولر القديم في الجذر.
* `[MODIFIED]` `docs/controller-review-log.md` - تحديث سجل التدقيق المركزي.

## 2. القرارات التقنية:
1. توحيد منطق التوليف تحت `CoffeeBlenderController` واستخدام `bcmath` لمعالجة كافة الكميات والأوزان بدقة `DECIMAL(12,3)`.
2. حماية عمليات التوليف بقفل سطري وعملية DB Transaction كاملة عند إنشاء واعتماد فاتورة التوليفة.
3. فرض الصلاحيات عبر Form Requests (`invoices.create`, `items.create`, `pos.access`).

## 3. التحقق والاختبار:
* [x] نجاح 100% لاختبارات CoffeeBlenderApiTest (6/6 Passed, 15 Assertions).
* [x] نجاح الاختبارات التراكمية لكافة الكنترولرز المدققة حتى الآن (36/36 Passed, 166 Assertions).
* [x] خلو الكود من التكرار والملفات الميتة.
