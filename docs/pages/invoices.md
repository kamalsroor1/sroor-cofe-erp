# 🧾 توثيق وتحليل صفحة فواتير المبيعات (Invoices)

## 1. النظرة العامة والتحليل التشغيلي:
* **اسم الصفحة:** سجل وإدارة فواتير المبيعات (Sales Invoices Management)
* **المسار (Route):** `/invoices`
* **الملف الرئيسي:** `resources/js/views/Invoices/InvoicesView.vue` (منسق نحيف: ~70 سطرًا).
* **الغرض والتحليل التشغيلي:**
  * إدارة وأرشفة كافة الفواتير الصادرة للمبيعات سواء المعتمدة، الآجلة، أو الملغاة.
  * فلترة متقدمة ومتعددة المعايير (رقم الفاتورة، اسم العميل، التاريخ من/إلى عبر تقويم Flatpickr، طريقة الدفع، وحالة السداد).
  * استعراض تفاصيل الفاتورة وبنودها والخصومات والضرائب المطبقة في نافذة منبثقة تفاعلية.
  * طباعة الفاتورة بأكثر من نمط (إيصال كاشير حراري 80mm، أو فاتورة ضريبية رسمية قياس A4).
  * إلغاء الفواتير الحساسة مع إلزامية عكس حركات المخزون والخزينة تلقائياً داخل `DB::transaction()`.
  * تصدير بيانات الفواتير وتحديد متعدد لإلغاء أو طباعة دفعات من الفواتير.

---

## 2. هيكلية وشجرة المكونات (Component Tree):
```text
InvoicesView.vue (Thin Orchestrator ~70 lines)
├── InvoicesSearchFilterBar.vue  <-- شريط البحث السريع وزر فتح الفلاتر الجانبية مع شارة الفلاتر النشطة
├── FilterSidebar.vue            <-- الحاوية الموحدة للفلاتر (Sidebar ديسكتوب / Slide Drawer موبايل)
│   └── InvoicesFilterPanel.vue  <-- عناصر الفلترة: BaseSelect, BaseDatePicker, BaseRadioGroup
├── InvoicesTable.vue            <-- جدول الفواتير العالي الكثافة (ديسكتوب) / كروت اللمس التفاعلية (موبايل)
│   └── ActionMenu.vue           <-- قائمة الإجراءات المنسدلة بتقنية Floating Teleport (معاينة، طباعة 80mm، إلغاء)
├── InvoiceDetailsModal.vue      <-- نافذة معاينة الفاتورة الكاملة مع جدول البنود والملخص المالي
│   ├── InvoiceLineItemsTable.vue
│   └── InvoiceFinancialSummary.vue
└── InvoiceCancelModal.vue       <-- نافذة تأكيد الإلغاء وعكس أثر المخزون مع سبب الإلغاء
```

---

## 3. عناصر النماذج والواجهات المشتركة المستخدمة:
* `BaseSearchInput.vue`: البحث الفوري برقم الفاتورة أو العميل.
* `BaseSelect.vue`: قوائم الفلترة لطرق الدفع والفروع والعملاء.
* `BaseDatePicker.vue`: منتقي التواريخ المتجاوب للبحث بفترة زمنية محددة.
* `BaseButton.vue`: أزرار الإجراءات، التصدير، والطباعة.
* `ActionMenu.vue`: قائمة الإجراءات المنسدلة العائمة (Floating Teleport) المانعة للقص.
* `StatusBadge.vue` / `EmptyState.vue` / `AppModal.vue`.

---

## 4. الاعتماديات والـ APIs:
* **Endpoints:**
  * `GET /api/v1/invoices` (مع خط أنابيب الفلاتر Pipeline Filter).
  * `GET /api/v1/invoices/:id` (جلب تفاصيل الفاتورة وبنودها).
  * `POST /api/v1/invoices/:id/cancel` (إلغاء الفاتورة وعكس المخزون).
  * `GET /api/v1/invoices/:id/print` (بيانات الطباعة).
* **Actions:** `App\Actions\Invoices\CreateInvoiceAction`, `CancelInvoiceAction`.

---

## 5. فحص التجاوب واللمس والوضعين الداكن والفاتح:
* **الهواتف (360px - 430px):** تتحول الفواتير لكروت مستقلة بمساحات لمس عريضة وزر الإجراءات يفتح كـ Bottom Action Sheet من أسفل الشاشة.
* **التابلت والديسكتوب (768px - 1280px+):** جدول بيانات عالي الكثافة مع قائمة إجراءات منسدلة عائمة تتكيف تلقائياً مع حواف الشاشة.

---

## 6. سجل الاختبارات والتحقق:
* ✅ **Playwright E2E:** نجاح 6/6 اختبارات عبر كافة المقاسات الخمسة في `invoices-full-page-audit.spec.js`.
* ✅ **Feature API Test:** نجاح كافة اختبارات الفلترة والإلغاء في `InvoiceApiTest.php`.
