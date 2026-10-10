# 📊 وثيقة المكون والصفحة: التقارير الشاملة والإحصائيات والتحليلات (`ReportsView`)

## 1. النظرة العامة والتحليل التشغيلي:
* **اسم الصفحة:** التقارير المالية والتحليلات الشاملة (Financial Reports & BI)
* **المسار (Route):** `/reports`
* **اسم المسار (Route Name):** `reports.index`
* **الصلاحية المطلوبة (Permission):** `reports.view`
* **الملف الرئيسي:** `resources/js/views/Reports/ReportsView.vue` (~118 سطرًا).
* **الغرض والتحليل التشغيلي:**
  * المركز التحليلي والمحاسبي العميق لتقييم أداء المؤسسة واتخاذ القرارات الاستراتيجية.
  * قائمة أرباح وخسائر متكاملة (P&L):
    $$\text{Net Profit} = \text{Revenue} - \text{COGS (تكلفة البضاعة المباعة)} - \text{Operational Expenses}$$
  * تبويبات متخصصة تغطي:
    * **تحليلات المبيعات:** توزيع الإيرادات، طرق الدفع، مبيعات الساعات.
    * **تحليلات المشتريات:** الموردين الأكثر تعاملاً، تكاليف الشراء، والتغيرات السعرية.
    * **تحليلات المخزون:** قيمة الجرد بسعر التكلفة والبيع، ومعدل دوران المخزون، وتحليل ABC.
    * **تحليلات المصروفات:** توزيع المصروفات التشغيلية والنثرية حسب البنود.
    * **كارت الصنف والربحية:** ربحية كل منتج وهامش الربح المحقق.
  * محدد زمني ذكي: اليوم، أمس، هذا الأسبوع، هذا الشهر، الربع الحالي، أو نطاق زمني مخصص.
  * تصدير التقارير لـ Excel و CSV وطباعة رسمية A4.

---

## 2. هيكلية وشجرة المكونات (Component Tree):
```text
ReportsView.vue (~118 lines)
├── ReportsHeader.vue                 <-- رأس الصفحة مع أزرار الطباعة وتصدير التقارير الشاملة
├── ReportsFilterBar.vue              <-- شريط الفلاتر: الفترات الزمنية المجهزة، الفرع، والعملة
├── ReportsSummaryCards.vue           <-- بطاقات الأرباح: الإيرادات، تكلفة البضاعة COGS، مجمل الربح، المصروفات، صافي الربح
└── ReportsTabs.vue                   <-- تبويبات التحليلات المتخصصة:
    ├── ReportsSalesTab.vue           <-- تحليلات المبيعات وطرق الدفع
    ├── ReportsPurchasesTab.vue       <-- تحليلات المشتريات والموردين
    ├── ReportsInventoryTab.vue       <-- جرد وتقييم المخزون وتحليل ABC
    ├── ReportsProfitLossTab.vue      <-- قائمة الأرباح والخسائر الرسمية التفصيلية
    └── ReportsExpensesTab.vue        <-- توزيع المصروفات والعهد
```

---

## 3. العناصر المشتركة ومخازن الحالة:
* **المكونات المشتركة:** `PageHeader.vue`, `BaseButton.vue`, `MetricCard.vue`, `DataTable.vue`.
* **المخازن المستخدمة:** `useAuthStore` (فحص صلاحية `reports.view`)، `useAppConfigStore`.
* **الـ Composables:** `useFormatters.js` لتنسيق المبالغ المالية (`formatMoney`) والنسب المئوية.

---

## 4. الاعتماديات والـ APIs:
* `GET /api/v1/reports/summary`: ملخص المؤشرات العامة والأرباح:
  * **الكنترولر:** `App\Http\Controllers\Api\ReportController@summary`
* `GET /api/v1/reports/comprehensive`: التقرير المالي المجمع الشامل:
  * **Form Request:** `App\Http\Requests\FilterReportRequest`
  * **Action:** `App\Actions\Reports\GetComprehensiveFinancialReportAction`
* `GET /api/v1/reports/items`: تقرير مبيعات وربحية الأصناف.
* `GET /api/v1/reports/stores`: مقارنة أداء ومبيعات الفروع.
* `GET /api/v1/reports/customers`: كبار العملاء وحجم مشترياتهم.
* `GET /api/v1/reports/expenses`: تفصيل المصروفات حسب التصنيف.
* `GET /api/v1/reports/inventory`: تقرير تقييم المخزون وركود الأصناف.
* `GET /api/v1/reports/treasury`: حركة التدفقات النقدية والمقبوضات.
* `GET /api/v1/reports/top-items`: الأصناف الأكثر مبيعاً والأعلى ربحية.
* `GET /api/v1/reports/items/{id}/card`: كارت الأداء المالي لصنف محدد.

---

## 5. نطاق الفروع وعزل البيانات (Store Scoping):
* تعتمد كافة مسارات التقارير على ترويسة `X-Store-Id` ومعامل `store_id`.
* يتم التحقق الأمني عبر `ClientStoreGuard::verified($request)`.
* أي محاولة للاستعلام عن تقارير فرع غير مصرح للمستخدم به تنتج استجابة **403 Forbidden** بكود `store_access_denied`.

---

## 6. القواعد المالية الصارمة والمحاسبية:
1. **الدقة المالية `DECIMAL(12,3)` و `bcmath`:** كافة معادلات الربحية، وتكلفة البضاعة المباعة COGS، والضرائب تُعالج بالكامل بدوال `bcmath` مع تطبيق التقريب المتماثل للنصف للأعلى (`half-up rounding at 3 dp`).
2. **ساعة المستأجر ويوم العمل:** التواريخ والتقارير اليومية تُربط بساعة المستأجر وتوقيت القطع `business_day_cutoff` عبر `TenantClock`.
