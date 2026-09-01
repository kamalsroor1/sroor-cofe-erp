# سجل تعديل: حزمة التعديلات والتحسينات الشاملة (سرور كوفي، الطباعة، الحذف، سداد الموردين، والمصروفات)

* **التاريخ والوقت:** 2026-08-10 15:35
* **الدور المفعل:** Full Stack AI Squad
* **الهدف من التعديل:** تنفيذ التعديلات السبعة المطلوبة: تعديل الترويسة لسرور كوفي، حذف الضريبي والسجل ونقدي كاش، تغميق خطوط الطباعة، إتاحة الحذف النهائي للفواتير، تصحيح مضاعفة وزن الأصناف، تفعيل سندات صرف وسداد مديونيات الموردين، وإضافة موديول المصروفات والنثريات.

---

## 1. الملفات التي تم إنشاؤها أو تعديلها (Modified Files)
* `[MODIFIED]` `resources/views/layouts/print-a4.blade.php` - تغيير الاسم إلى سرور كوفي وحذف الضريبي والسجل ونقدي كاش وتغميق خطوط الطباعة.
* `[MODIFIED]` `resources/views/layouts/print-thermal.blade.php` - تحديث الترويسة والخطوط الداكنة العريضة.
* `[MODIFIED]` `app/Livewire/ItemIndex.php` - حل مشكلة مضاعفة الوزن والبدء برصيد 0.000 قبل الإيداع.
* `[MODIFIED]` `app/Services/InvoiceService.php` - إضافة دالة `deleteInvoice` لحذف الفاتورة نهائياً مع استرجاع المخزون.
* `[MODIFIED]` `app/Livewire/InvoiceIndex.php` & `resources/views/livewire/invoice-index.blade.php` - إضافة زر وإجراء الحذف النهائي.
* `[MODIFIED]` `app/Livewire/InvoiceShow.php` & `resources/views/livewire/invoice-show.blade.php` - إضافة خيار الحذف في شاشة التفاصيل.
* `[NEW]` `app/Services/SupplierBalanceService.php` - خدمة حساب رصيد المورد بدقة.
* `[MODIFIED]` `app/Services/PurchaseService.php` & `app/Services/PaymentService.php` - ربط حركة المشتريات والسندات بخدمة رصيد المورد.
* `[MODIFIED]` `app/Livewire/SupplierIndex.php` & `resources/views/livewire/supplier-index.blade.php` - إضافة نافذة وزر سداد دفعات الموردين.
* `[MODIFIED]` `app/Livewire/SupplierStatement.php` & `resources/views/livewire/supplier-statement.blade.php` - إضافة سداد دفعات الموردين في كشف الحساب.
* `[NEW]` `database/migrations/2026_08_10_160011_create_expenses_table.php` - جدول المصروفات والنثريات.
* `[NEW]` `app/Models/Expense.php` - موديل المصروفات.
* `[NEW]` `app/Livewire/ExpenseIndex.php` & `resources/views/livewire/expense-index.blade.php` - شاشة موديول المصروفات والنثريات.
* `[MODIFIED]` `routes/web.php` & `resources/views/components/layouts/app.blade.php` - إضافة المسارات وروابط القائمة الجانبية.
* `[MODIFIED]` `app/Livewire/ReportsIndex.php` & `resources/views/livewire/reports-index.blade.php` - احتساب المصروفات وخصمها من صافي الأرباح.
* `[NEW]` `tests/Feature/SystemImprovementsTest.php` - اختبارات جودة وتأكيدات لكافة الميزات الجديدة.

---

## 2. التحقق والاختبار (Verification & Testing)
* [x] تم تشغيل 41 اختباراً مؤتمتاً واجتياز 126 تأكيداً بنسبة 100%.
* [x] تم نشر التعديلات على السيرفر الحي وترحيل قاعدة البيانات.
