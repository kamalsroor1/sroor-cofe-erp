# سجل تعديل: تطبيق نظام الحذف المرن الشامل (Comprehensive Soft Deletes System)

* **التاريخ والوقت:** 2026-08-11 19:35
* **الدور المفعل:** Backend Architect & Frontend UI Agent
* **الهدف من التعديل:** تحويل كافة عمليات الحذف في النظام بالكامل من الحذف الفيزيائي إلى الحذف المرن (Soft Deletes) لحماية السجلات المالية والمخزنية التاريخية وإتاحة سلة المحذوفات والاستعادة الفورية.

---

## 1. الملفات التي تم إنشاؤها أو تعديلها (Modified Files)
* `[NEW]` `database/migrations/2026_08_11_200000_add_soft_deletes_to_all_tables.php` - هجرة مجمعة وآمنة لإضافة `deleted_at` لجميع الجداول.
* `[NEW]` `tests/Feature/SoftDeletesTest.php` - اختبارات تحقق من الحذف المرن والاستعادة وصحة العلاقات التاريخية.
* `[MODIFIED]` `app/Models/Item.php` - تفعيل `SoftDeletes`.
* `[MODIFIED]` `app/Models/Customer.php` - تفعيل `SoftDeletes`.
* `[MODIFIED]` `app/Models/Supplier.php` - تفعيل `SoftDeletes`.
* `[MODIFIED]` `app/Models/Store.php` - تفعيل `SoftDeletes`.
* `[MODIFIED]` `app/Models/StoreStock.php` - تفعيل `SoftDeletes` مع `withTrashed()`.
* `[MODIFIED]` `app/Models/User.php` - تفعيل `SoftDeletes`.
* `[MODIFIED]` `app/Models/Expense.php` - تفعيل `SoftDeletes` مع `withTrashed()`.
* `[MODIFIED]` `app/Models/Invoice.php` - تفعيل `SoftDeletes` مع `withTrashed()` على العلاقات.
* `[MODIFIED]` `app/Models/InvoiceItem.php` - تفعيل `SoftDeletes` مع `withTrashed()`.
* `[MODIFIED]` `app/Models/Purchase.php` - تفعيل `SoftDeletes` مع `withTrashed()`.
* `[MODIFIED]` `app/Models/PurchaseItem.php` - تفعيل `SoftDeletes` مع `withTrashed()`.
* `[MODIFIED]` `app/Models/Payment.php` - تفعيل `SoftDeletes` مع `withTrashed()`.
* `[MODIFIED]` `app/Models/ReturnDocument.php` - تفعيل `SoftDeletes` مع `withTrashed()`.
* `[MODIFIED]` `app/Models/ReturnItem.php` - تفعيل `SoftDeletes` مع `withTrashed()`.
* `[MODIFIED]` `app/Models/StockTransfer.php` - تفعيل `SoftDeletes` مع `withTrashed()`.
* `[MODIFIED]` `app/Models/StockTransferItem.php` - تفعيل `SoftDeletes` مع `withTrashed()`.
* `[MODIFIED]` `app/Models/StockDeposit.php` - تفعيل `SoftDeletes` مع `withTrashed()`.
* `[MODIFIED]` `app/Models/CashShift.php` - تفعيل `SoftDeletes` مع `withTrashed()`.
* `[MODIFIED]` `app/Models/Setting.php` - تفعيل `SoftDeletes`.
* `[MODIFIED]` `app/Models/StockMovement.php` - ربط العلاقات بـ `withTrashed()`.
* `[MODIFIED]` `app/Livewire/ItemIndex.php` & `item-index.blade.php` - إضافة تبويب سلة المحذوفات وزر الاستعادة والأرشفة.
* `[MODIFIED]` `app/Livewire/CustomerIndex.php` & `customer-index.blade.php` - إضافة سلة المحذوفات وزر الاستعادة.
* `[MODIFIED]` `app/Livewire/SupplierIndex.php` & `supplier-index.blade.php` - إضافة سلة المحذوفات وزر الاستعادة.
* `[MODIFIED]` `app/Livewire/StoreIndex.php` & `store-index.blade.php` - إضافة سلة المحذوفات وزر الاستعادة.

---

## 2. القرارات المعمارية والمنطق البرمجي (Key Decisions)
* تطبيق سمة `SoftDeletes` عبر كافة النماذج والجداول بدون استثناء لمنع فقدان البيانات.
* استخدام `->withTrashed()` على جميع علاقات `belongsTo` التاريخية لضمان عدم حدوث أي انهيار أو إظهار قيم فارغة في الفواتير والتقارير المحاسبية القديمة عند أرشفة صنف أو عميل.
* إضافة تبويب مخصص لسلة المحذوفات مع عداد رقمي وزر استعادة بضغطة زر واحدة `[♻️ استعادة]`.

---

## 3. الاختبارات والتحقق (Verification & Testing)
* [x] تم التحقق من نجاح كافة الاختبارات بنسبة 100% (76 اختبار و 291 Assertion).
* [x] تم اختبار استعادة وحذف الأصناف والعملاء والموردين والفروع.
* [x] تم النشر الآمن على السيرفر الحي `sroor.baraa-solutions.com` مع كاش الإعدادات والقوالب.

---

## 4. الخطوات التالية المقترحة (Next Recommended Steps)
1. بناء وتشغيل سيناريوهات الـ Python E2E Automation.
