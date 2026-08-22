# سجل تعديل: إضافة نظام الفئات وإعادة تصميم شاشة دفع الـ POS
* **التاريخ والوقت:** 2026-08-22 23:00
* **الدور المفعل:** Full-Stack Architect & UI Agent
* **الهدف:** إضافة شريط وفئات الأصناف في POS ولوحة التحكم، وإعادة تصميم نافذة وسداد الدفع بالكامل بناء على المرجع والتوجيه المحاسبي الدقيق.

## 1. الملفات المعدلة والمضافة:
* `[NEW]` `database/migrations/tenant/2026_08_23_000001_create_categories_table.php` - جدول الفئات والتصنيفات
* `[NEW]` `database/migrations/tenant/2026_08_23_000002_add_category_id_to_items_table.php` - ربط الصنف بالفئة
* `[NEW]` `app/Models/Category.php` - موديل الفئة والعلاقات
* `[NEW]` `app/Actions/Categories/CreateCategoryAction.php` - إجراء إنشاء الفئة
* `[NEW]` `app/Actions/Categories/UpdateCategoryAction.php` - إجراء تعديل الفئة
* `[NEW]` `app/Actions/Categories/DeleteCategoryAction.php` - إجراء حذف الفئة
* `[NEW]` `app/Http/Controllers/Api/CategoryApiController.php` - كنترولر API الفئات
* `[NEW]` `resources/js/views/Items/CategoriesView.vue` - شاشة إدارة الفئات
* `[NEW]` `tests/Feature/Api/CategoryApiTest.php` - اختبارات الفئات
* `[MODIFIED]` `resources/js/views/POS/PosView.vue` - شريط الفئات الأفقي + شاشة الدفع الجديدة بالكامل
* `[MODIFIED]` `resources/js/Components/Items/ItemFormModal.vue` - ربط حقل الفئة بـ BaseSelect
* `[MODIFIED]` `resources/js/Layouts/SpaLayout.vue` - إضافة رابط الفئات في القائمة الجانبية والدرج
* `[MODIFIED]` `resources/js/router/index.js` - تسجيل مسار /categories
* `[MODIFIED]` `routes/api.php` - تسجيل مسارات API الفئات
* `[MODIFIED]` `app/Actions/POS/GetPOSBootstrapDataAction.php` - تضمين بيانات الفئات الغنية
* `[MODIFIED]` `lang/ar/pos.php` & `lang/en/pos.php` - ملفات الترجمة للغتين

## 2. القرارات التقنية والمحاسبية:
* الفصل التام بين "نوع الفاتورة والسداد" (كاش / آجل / جزئي) و "وسيلة التحصيل الفعلية" (كاش / إنستاباي / محفظة).
* معالجة مصاريف الشحن والخدمات بتوجيه محاسبي سليم:
  - `customer_account`: تضاف لصافي الفاتورة المطلوب من العميل.
  - `treasury_*`: تنشئ سند صرف / مصروف مباشر على الخزينة/المحل دون التأثير على حساب العميل.
* توفير حاسبة فكة وباقي العميل الحية وأزرار السداد السريع والخصم السريع.

## 3. التحقق والاختبار:
* [x] اجتياز كافة اختبارات النظام (256 اختباراً بنجاح 100%).
* [x] بناء حزمة Vite كاملة بـ 0 أخطاء.
* [x] خلو الكود من أي نصوص ثابتة والتوافق مع الثيم واللغتين العربية والإنجليزية.