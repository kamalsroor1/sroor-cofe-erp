# سجل تعديل: إلغاء المشتريات، طرق الدفع الذكية، التسوية الجردية، كارت حركة الصنف، وطباعة التقارير A4

* **التاريخ والوقت:** 2026-08-18 22:15
* **الدور المفعل:** Backend Architect & Frontend UI & QA Agent
* **الهدف من التعديل:** تنفيذ متطلبات العميل بدقة متناهية: (1) حل مشكلة عكس المخزن عند إلغاء فواتير الشراء وإمكانية استعادتها، (2) إضافة طرق الدفع (كاش، إنستاباي، محفظة إلكترونية) مع إيقاف الفيزا والشيك والتحويل بدون حذف، (3) إضافة تسوية وتصحيح رصيد المخزن، (4) إنشاء كارت حركة الصنف مع فلاتر التاريخ والطباعة A4 والتصدير، (5) طباعة كافة تقارير النظام A4 بتصميم موحد ومنظم.

---

## 1. الملفات التي تم إنشاؤها أو تعديلها (Modified Files)
* `[NEW]` `app/Enums/PaymentMethod.php` - تعريف Enum طرق الدفع مع تمييز الطرق النشطة (كاش، إنستاباي، محفظة) وإيقاف الطرق الأخرى برمجياً دون حذفها.
* `[NEW]` `database/migrations/2026_08_18_220000_update_payment_methods_in_tables.php` - إضافة `payment_method` لجدول `invoices` وتوسيعه في `payments`.
* `[NEW]` `app/Livewire/ItemMovements.php` - مكون كارت حركة الصنف الشامل مع فلاتر الفترات والتجميعات وإجماليات الوارد والمنصرف.
* `[NEW]` `resources/views/livewire/item-movements.blade.php` - واجهة تفاعلية لكارت حركة الصنف.
* `[NEW]` `resources/views/layouts/print-item-movements-a4.blade.php` - قالب طباعة كارت حركة الصنف A4 مع هوية رسمية ومربعات توقيع.
* `[NEW]` `app/Http/Controllers/ReportPrintController.php` - متحكم طباعة التقارير المالية والإدارية الـ 6 A4.
* `[NEW]` `resources/views/layouts/print-report-a4.blade.php` - قالب طباعة التقارير A4 الموحد.
* `[MODIFIED]` `app/Services/PurchaseService.php` - إضافة دوال `cancelPurchase()` مع شرط الرصيد الكافي وعكس المخزن بحركة `purchase_cancel_out` وإلغاء السندات، ودالة `restorePurchase()`.
* `[MODIFIED]` `app/Livewire/PurchaseIndex.php` & `purchase-index.blade.php` - إضافة أزرار الإلغاء والاستعادة وتحديث فلاتر الحالة.
* `[MODIFIED]` `app/Services/StockService.php` - إضافة دالة `adjustStock()` الذرية بالقفل السطري لحساب العجز والزيادة وتسجيل حركات التسوية.
* `[MODIFIED]` `app/Livewire/ItemIndex.php` & `item-index.blade.php` - إضافة نافذة التسوية الجردية السريعة وزر كارت حركة الصنف.
* `[MODIFIED]` `app/Livewire/InvoiceCreate.php` & `invoice-create.blade.php` - إضافة أزرار السداد السريعة اللمسية الثلاثة (💵 كاش، ⚡ إنستاباي، 📲 محفظة).
* `[MODIFIED]` `app/Livewire/InvoiceIndex.php` & `invoice-index.blade.php` - إضافة فلاتر وسيلة الدفع والشارات في الجدول.
* `[MODIFIED]` `app/Livewire/ExpenseIndex.php` & `expense-index.blade.php` - فلاتر ونوافذ طرق الدفع النشطة للمصروفات.
* `[MODIFIED]` `resources/views/livewire/customer-index.blade.php` & `supplier-statement.blade.php` - تحديث خيارات طرق السداد والقبض.
* `[MODIFIED]` `app/Services/ExportService.php` & `ExportController.php` - إضافة تصدير كارت حركة الصنف لـ CSV مع ترميز UTF-8 BOM.
* `[MODIFIED]` `routes/web.php` - إضافة مسارات كارت حركة الصنف والطباعة والتصدير والتقارير.
* `[MODIFIED]` `docs/05-planning/tasks-breakdown.md` - توثيق إنجاز المرحلة العاشرة.

---

## 2. القرارات المعمارية والمنطق البرمجي (Key Decisions)
* **إلغاء واستعادة المشتريات:** استخدام `lockForUpdate()` وفحص `current_stock >= item_quantity` قبل السماح بالإلغاء لمنع تحول المخزون لسالب. عند الإلغاء يتم خصم المخزون بحركة `purchase_cancel_out` وإلغاء سندات الصرف المرتبطة وتعديل رصيد المورد. عند الاستعادة يتم إعادة التوريد بحركة `purchase_restore_in` وإعادة حساب WAC.
* **إيقاف طرق الدفع بدون حذف:** تم الاحتفاظ بحالات `Visa` و `BankTransfer` و `Check` داخل الـ Enum والـ Database لعدم كسر الفواتير التاريخية، مع حصر الطرق المتاحة للاختيار في الواجهات وشاشة الـ POS على الطرق الثلاث المطلوبة: (كاش، إنستاباي، محفظة إلكترونية).
* **التسوية الجردية:** تنفيذ التسوية داخل `DB::transaction()` مع قفل سطري على `Item` و `StoreStock`، واحتساب الفارق بالـ `bcmath` (`bcsub`) وتسجيل الحركة المناسبة (`stock_adjustment_in` للزيادة أو `stock_adjustment_out` للعجز).
* **حماية تكامل البيانات ومنع الحذف غير المصرح به (Deletion Integrity Protection):**
  - منع حذف أي صنف إذا كان له رصيد بالمخزن أو مرتبط بفواتير مبيعات، فواتير شراء، حركات مخزنية، تحويلات، أو مرتجعات.
  - منع حذف أي عميل أو مورد إذا كان له فواتير، مدفوعات، أو مديونية غير مسواة.
  - منع حذف أي فرع إذا كان لديه بضاعة أو فواتير أو تحويلات.
  - إتاحة زر التفعيل والتعطيل (`is_active`) كبديل آمن لإخفاء الصنف من نقطة البيع دون الإضرار بالسجلات المالية والتقارير.
* **معايير التقارير A4:** قوالب نظيفة باللونين الأبيض والأسود/الرمادي متوافقة 100% مع `@media print` وأحجام A4 مع خطوط Cairo/Tajawal وتضمين مربعات التوقيع الثلاثية (أمين المخزن، المحاسب، اعتماد الإدارة).

---

## 3. الاختبارات والتحقق (Verification & Testing)
* [x] تم التحقق من خلو كافة ملفات PHP من أخطاء الـ Syntax (`php -l`).
* [x] تم تشغيل اختبارات فواتير الشراء والعمليات المالية بنجاح 100% (5/5 tests, 17 assertions passed).
* [x] تم التأكد من دعم اللغة العربية RTL والتوافق مع الوضعين الليلي والنهاري.
* [x] تم التأكد من عمل القفل السطري وتطبيق `DECIMAL(12,3)` و `bcmath`.

---

## 4. الخطوات التالية المقترحة (Next Recommended Steps)
1. تشغيل التطبيق في بيئة التشغيل أو الاختبار لمراجعة طباعة الفواتير وكروت الحركة على الطابعات الفعلية.
