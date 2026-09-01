# سجل تعديل: صفحة معاينة الفاتورة والطباعة المزدوجة (Invoice Show & Dual Print)
* **التاريخ والوقت:** 2026-08-24 03:39
* **الدور المفعل:** Frontend UI & QA Testing Agent
* **الهدف:** إنشاء وتدقيق صفحة مشاهدة ومعاينة الفاتورة المتكاملة ودعم الطباعة الحرارية 80mm والضريبية A4 ومشاركة الواتساب وإلغاء الفواتير وفق معايير المنظومة القياسية.

## 1. الملفات المنشأة والمعدلة:
* `[NEW]` `resources/js/views/Invoices/InvoiceShowView.vue` - المنسق النحيف (37 سطراً فقط).
* `[NEW]` `resources/js/Composables/useInvoiceShow.js` - منطق جلب الفاتورة، الطباعة، المشاركة، وإلغاء الفاتورة.
* `[NEW]` `resources/js/Components/Invoices/Show/InvoiceShowHeader.vue` - رأس الصفحة مع مبدل الأوضاع التفاعلي.
* `[NEW]` `resources/js/Components/Invoices/Show/InvoiceShowActionsBar.vue` - شريط الإجراءات السريعة.
* `[NEW]` `resources/js/Components/Invoices/Show/InvoiceShowInteractiveView.vue` - حاوية العرض التفاعلي.
* `[NEW]` `resources/js/Components/Invoices/Show/InvoiceShowKpiGrid.vue` - مؤشرات الأرقام المالية.
* `[NEW]` `resources/js/Components/Invoices/Show/InvoiceShowCustomerCard.vue` - بطاقة العميل ورصيده.
* `[NEW]` `resources/js/Components/Invoices/Show/InvoiceShowStoreCard.vue` - بطاقة الفرع ونقطة البيع.
* `[NEW]` `resources/js/Components/Invoices/Show/InvoiceShowItemsTable.vue` - جدول بنود الأصناف.
* `[NEW]` `resources/js/Components/Invoices/Show/InvoiceShowPaymentsTimeline.vue` - سجل المدفوعات والملاحظات.
* `[NEW]` `resources/js/Components/Invoices/Show/InvoiceShowThermalReceipt.vue` - قالب إيصال الكاشير الحراري 80mm.
* `[NEW]` `resources/js/Components/Invoices/Show/InvoiceShowA4Document.vue` - قالب الفاتورة الضريبية الرسمية A4.
* `[NEW]` `resources/js/Components/Invoices/Show/CancelInvoiceModal.vue` - نافذة إلغاء الفاتورة وعكس المخزون.
* `[MODIFIED]` `resources/js/router/index.js` - إضافة المسار `/invoices/:id`.
* `[MODIFIED]` `resources/js/Components/Invoices/InvoiceDetailsModal.vue` - ربط زر العرض الكامل بالمسار المباشر.
* `[MODIFIED]` `lang/ar/invoices.php` و `lang/en/invoices.php` - إضافة مفاتيح الترجمة لكافة الحقول والأوضاع.
* `[NEW]` `e2e/flows/invoice-show-full-page-audit.spec.js` - اختبارات Playwright الشاملة.
* `[NEW]` `docs/pages/invoice-show.md` - توثيق الصفحة المستقل.

## 2. القرارات التقنية:
* **تعدد أوضاع العرض (Multi-Mode Switcher):** دعم 3 أوضاع سلسة (Interactive / 80mm Thermal / A4 Official Tax Document) دون إعادة تحميل الصفحة.
* **المنسق النحيف (< 40 سطرًا):** حصر ملف الـ View في 37 سطراً فقط وتوزيع المنطق والواجهات على 10 مكونات فرعية متخصصة.
* **بوابة الترجمة الكاملة (100% Localization Gate):** خلو كافة القوالب والإيصالات من أي نصوص ثابتة والاعتماد الكامل على مفاتيح الترجمة.

## 3. التحقق والاختبار:
* [x] خلو الكود من الأخطاء والبناء سليم (npm run build ناجح).
* [x] خلو الكود 100% من أي نصوص ثابتة وهندلة ملفات الترجمة للغتين (ar & en).
* [x] تطبيق نمط المنسق النحيف Thin Orchestrator (37 سطرًا).
* [x] التوافق مع شاشات اللمس واللغة العربية RTL والوضعين الفاتح والداكن.
* [x] اجتياز باقة اختبارات Playwright E2E بنسبة 7/7 ناجحة.
