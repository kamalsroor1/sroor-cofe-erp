# سجل تعديل: مطابقة صفحات الموردين، كشف الحساب، المشتريات، وإعادة الطلب الذكي بنسبة 100%

* **التاريخ والوقت:** 2026-08-20 02:30
* **الدور المفعل:** Full-stack Architect & QA Specialist
* **الهدف من التعديل:** تحقيق المطابقة التامة والشاملة (100% Feature Parity) بين شاشات Livewire القديمة وشاشات Inertia.js + Vue 3 الجديدة لحزمة الموردين والتوريدات وإعادة الطلب الذكي: (10. الموردين والتوريدات، 11. كشف حساب المورد، 12. سجل المشتريات والتوريد، 13. تسجيل فاتورة شراء جديدة، 14. مساعد إعادة الطلب الذكي للنواقص).

---

## 1. الملفات التي تم إنشاؤها أو تعديلها (Modified Files)
* `[MODIFIED]` `backend/app/Services/SupplierBalanceService.php` - إضافة دالة `getSupplierLedger` لحساب كشف حساب المورد التراكمي زمنياً بواسطة `bcmath`.
* `[MODIFIED]` `backend/app/Services/ReorderAssistantService.php` - إضافة حساب إجمالي تكلفة التوريد المقترحة `total_estimated_cost` بدقة مالية.
* `[MODIFIED]` `backend/app/Services/PurchaseService.php` - دعم معالجة `cost_price` و `unit_cost` ومصاريف الإنزال والتوزيع النسبي.
* `[MODIFIED]` `backend/app/Http/Controllers/SupplierController.php` - ربط السداد بـ `PaymentService::recordSupplierPayment`، وربط كشف الحساب بـ `SupplierBalanceService::getSupplierLedger`، وإضافة ميزة تفعيل/تعطيل المورد `toggleActive`.
* `[MODIFIED]` `backend/resources/js/Pages/Suppliers/Index.vue` - إضافة زر التحصيل والسداد السريع، زر كشف الحساب، زر التفعيل والتعطيل المباشر، وتصفية الدائنين.
* `[MODIFIED]` `backend/resources/js/Pages/Suppliers/Statement.vue` - إضافة بطاقات الملخص المالي الـ 3 (المشتريات / المدفوعات / صافي المديونية)، أزرار الفترات الزمنية السريعة، وأعمدة كشف الحساب المتتالية مع الرصيد بعد الحركة، والطباعة A4.
* `[MODIFIED]` `backend/app/Http/Controllers/PurchaseController.php` - دعم استقبال الأصناف المعبأة مسبقاً `prefill`، وربط `smartReorder` بـ `ReorderAssistantService`.
* `[MODIFIED]` `backend/resources/js/Pages/Purchases/SmartReorder.vue` - بناء شاشة إعادة الطلب الذكي الكاملة مع فلاتر الخطورة (حرج / تحذير / آمن / الكل)، فلاتر الفروع، فترات التحليل وتغطية الأيام، التحديد المتعدد، والتحويل المباشر لأمر شراء.
* `[MODIFIED]` `backend/resources/js/Pages/Purchases/Create.vue` - دعم استقبال الأصناف المقترحة تلقائياً من شاشة النواقص وتعبئتها فوراً في جدول الفاتورة.
* `[MODIFIED]` `backend/routes/web.php` & `backend/routes/tenant.php` - تسجيل راوت `POST /suppliers/{id}/toggle-active`.
* `[NEW]` `backend/tests/Feature/SupplierAndPurchasesInertiaTest.php` - اختبارات تحقق شاملة للعمليات المالية والشراء والتوريد والنواقص الذكية.

---

## 2. القرارات المعمارية والمنطق البرمجي (Key Decisions)
* **كشف حساب المورد التراكمي:** فواتير المشتريات (دائن) وسندات الصرف النقدية (مدين) ومرتجعات المشتريات (مدين) مرتبة زمنياً مع حساب `balance_after` التراكمي بدقة `DECIMAL(12,3)`.
* **مساعد المشتريات الذكي (`ReorderAssistantService`):** حساب معدل السحب اليومي الحقيقي، التنبؤ بأيام نفاد المخزون، تصنيف الخطورة الآلي (حرج / تحذير / آمن)، واقتراح كميات التوريد بضغطة زر لنقلها مباشرة لفاتورة الشراء.
* **الربط المحكم داخل `DB::transaction()`:** تسجيل عمليات الشراء وإلغائها وسندات الصرف مع التوليد التلقائي لأرقام السندات وعكس المخزون والأرصدة بأمان تام.

---

## 3. الاختبارات والتحقق (Verification & Testing)
* [x] تم تشغيل `npm run build` بنجاح وتجميع حزم Vite بدون أي أخطاء.
* [x] تم تشغيل واجتياز اختبارات `SupplierAndPurchasesInertiaTest` بنسبة 100% (5/5 نجاح).
* [x] تم اجتياز كافة اختبارات الحزم المالية والمخزنية (18 اختباراً و 73 assertion).
* [x] خلو الكود من أي نصوص ثابتة والتوافق مع الوضعين الفاتح والداكن و RTL.
