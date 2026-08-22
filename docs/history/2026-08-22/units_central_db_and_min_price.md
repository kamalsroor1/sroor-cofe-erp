# سجل تعديل: ضبط قواعد الوحدات، عزل الداتا بيز السنترال، وإضافة أقل سعر بيع

* **التاريخ والوقت:** 2026-08-22 19:30
* **الدور المفعل:** Backend Architect & Frontend UI
* **الهدف:** عزل الداتا بيز المركزية (Central DB) للسوبر أدمن فقط، منع البيع بكسور للوحدات العددية (مثل قطعة وعلبة)، إضافة حقل أقل سعر بيع (سعر الجملة)، وإدارة وحدات القياس ديناميكياً من الإعدادات.

## 1. الملفات المعدلة والمنشأة:
* `[NEW]` `backend/database/migrations/tenant/2026_08_22_200000_add_min_selling_price_to_items_table.php` - إضافة حقل `min_selling_price` لجدول الأصناف في قواعد بيانات المنشآت.
* `[DELETED]` حذف كافة الميجريشنز الخاصة بالمنشآت من مجلد `database/migrations/` الرئيسي لتظل الداتا بيز المركزية مخصصة للسوبر أدمن والمشتركين والخطط فقط.
* `[MODIFIED]` `backend/app/Models/Item.php` - دعم `min_selling_price` في الـ Fillable والـ Casts.
* `[MODIFIED]` `backend/app/DTOs/Items/ItemDTO.php` & `CreateItemAction.php` & `UpdateItemAction.php` - معالجة أقل سعر بيع.
* `[MODIFIED]` `backend/app/Http/Requests/StoreItemRequest.php` & `UpdateItemRequest.php` - قواعد التحقق لأقل سعر بيع.
* `[MODIFIED]` `backend/app/Http/Resources/ItemResource.php` & `POSItemResource.php` - تمرير التكلفة وأقل سعر بيع وفئة الجملة.
* `[MODIFIED]` `backend/resources/js/views/Items/ItemsView.vue` - إضافة حقل أقل سعر بيع (الجملة) وتحميل وحدات القياس ديناميكياً.
* `[MODIFIED]` `backend/resources/js/views/POS/PosView.vue` - فرض الأعداد الصحيحة الصارمة للوحدات العددية (`قطعة`، `علبة`، `كرتونة`...) وعرض التكلفة وأقل سعر بيع في السلة.
* `[MODIFIED]` `backend/resources/js/views/Settings/SettingsView.vue` - قسم إدارة وتخصيص وحدات القياس المعتمدة للمخزون.
* `[MODIFIED]` `backend/app/Http/Controllers/Api/SettingController.php` - حفظ واسترجاع الوحدات المفعلة `inventory_units`.

## 2. القرارات التقنية:
1. **عزل الداتا بيز المركزية (Central DB Isolation):** تنظيف الداتا بيز المركزية وحذف جداول الأصناف والمبيعات والمخازن منها، حيث تعمل المنظومة بنمط Multi-Tenancy وتُنشأ هذه الجداول فقط داخل قواعد بيانات المنشآت (`database/migrations/tenant/`).
2. **منع الكسور للوحدات الفردية (Discrete Unit Stepping):** الوحدات مثل (قطعة، حبة، علبة، كرتونة، شيكارة، دستة) تقبل فقط أعداداً صحيحة (`step="1"`) ولا يمكن بيع 1.5 قطعة، بينما الوحدات الوزنية والحجمية (كجم، جرام، لتر) تقبل الكسور العشرية بدقة 3 أرقام.
3. **أقل سعر بيع (Minimum Selling Price):** إتاحة تحديد سعر التكلفة، سعر البيع التجزئة، وأقل سعر بيع (سعر الجملة) لكل صنف مع شفافية تامة للكاشير في شاشة الـ POS.

## 3. التحقق والاختبار:
* [x] خلو الكود من أي أخطاء وبناء Vite بنجاح
* [x] تنفيذ الميجريشن على قاعدة بيانات المنشأة `2m`
* [x] تنظيف وتطهير الداتا بيز المركزية من الجداول المكررة
* [x] اختبار إدخال الكميات في كاشير POS ومنع الكسور في الوحدات العددية
* [x] التوافق مع الوضعين الفاتح والداكن والهوية البصرية
