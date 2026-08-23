# سجل تعديل: استخراج مكونات الفاتورة المشتركة (InvoiceLineItemsTable + InvoiceFinancialSummary)
* **التاريخ والوقت:** 2026-08-21 03:30
* **الدور المفعل:** Frontend UI Agent
* **الهدف:** حل الملاحظات المفتوحة في `code-review-log.md` بتوحيد جدول عناصر الفواتير وملخصها المالي عبر مكونات مشتركة قابلة لإعادة الاستخدام.

## 1. الملفات المعدلة:
* `[NEW]` `backend/resources/js/Components/Common/InvoiceLineItemsTable.vue` - جدول عناصر الفاتورة/المرتجع الموحد (Desktop Table + Mobile Cards + بحث وإضافة).
* `[NEW]` `backend/resources/js/Components/Common/InvoiceFinancialSummary.vue` - لوحة الملخص المالي الجانبي الموحدة (Subtotal، خصم، Net، مدفوع/مسترد، متبقي، زر إرسال).
* `[MODIFIED]` `backend/resources/js/Components/Common/PageHeader.vue` - إضافة prop اختياري `backHref` لعرض زر رجوع موحد.
* `[MODIFIED]` `backend/resources/js/Pages/Purchases/Create.vue` - إعادة الهيكلة باستخدام المكونات الجديدة (تقليص من 316 إلى 145 سطر).
* `[MODIFIED]` `backend/resources/js/Pages/Returns/Create.vue` - إعادة الهيكلة باستخدام المكونات الجديدة (تقليص من 369 إلى 175 سطر).
* `[MODIFIED]` `code-review-log.md` - توثيق الجلسة الرابعة.

## 2. القرارات المعمارية:
* **DRY:** تكرار جدول الأصناف وكارت الملخص المالي تم حله بمكون واحد لكل منهما.
* **OCP (Open/Closed):** المكونان يعملان بدون تعديل داخلي — كل التخصيص عبر props (priceField، subtotal، paidAmount، refundAmount...).
* **Interface Segregation:** كل prop في `InvoiceFinancialSummary` اختياري — لا يُعرض إلا عند الحاجة.
* الحفاظ التام على سلوك المنطق المالي والتحقق.

## 3. التحقق والاختبار:
* [x] البناء `npm run build` نجح بدون أخطاء.
* [x] مزامنة أندرويد `npx cap sync android` نجحت.
* [x] خلو الكود من النصوص الثابتة.
