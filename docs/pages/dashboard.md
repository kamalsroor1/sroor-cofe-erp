# 📊 توثيق وتحليل صفحة لوحة القيادة والمؤشرات (Dashboard)

## 1. النظرة العامة والتحليل التشغيلي:
* **اسم الصفحة:** لوحة القيادة والتحليلات الرئيسية (Tenant ERP Dashboard)
* **المسار (Route):** `/` (مع اسم مستعار `/dashboard`)
* **اسم المسار (Route Name):** `dashboard`
* **الصلاحية المطلوبة (Permission):** مصادقة المستأجر (`requiresAuth: true`)
* **الملف الرئيسي:** `resources/js/views/DashboardView.vue` (~86 سطرًا).
* **الغرض والتحليل التشغيلي:**
  * غرفة المراقبة المركزية والبوصلة اليومية لأصحاب الأعمال ومديري الفروع في "سرور كوفي ERP".
  * شاشة موحدة عالية الأداء تستدعي حزمة مؤشرات موحدة عبر مسار `/api/v1/dashboard/summary` لتقليل زمن الاستجابة.
  * بطاقات KPI فورية تعرض مبيعات اليوم، مبيعات الشهر، صافي الأرباح التقديرية، وعدد الأصناف التي بلغت حد النواقص.
  * مخططات بيانية لتوزيع المبيعات الأسبوعية وتوزيع طرق السداد (نقدي، إلكتروني، آجل).
  * خريطة ساعات الذروة والضغط اليومي (`Peak Hours Heatmap`) لمعرفة أوقات الازدحام.
  * جدول سريع بآخر الفواتير الصادرة، وقائمة تنبيهات النواقص السريعة بالمخزن.

---

## 2. هيكلية وشجرة المكونات (Component Tree):
```text
DashboardView.vue (~86 lines)
├── DashboardWelcomeBanner.vue        <-- الترحيب بالمستخدم، الفرع النشط، وأزرار الاختصارات السريعة (POS، فاتورة، صنف)
├── DashboardKpiGrid.vue              <-- شبكة كروت المؤشرات المالية: مبيعات اليوم، مبيعات الشهر، الأرباح، النواقص
├── DashboardAnalyticsRow.vue         <-- مخطط المبيعات الأسبوعي ومخطط نسب توزيع طرق الدفع
├── DashboardPeakHours.vue            <-- خريطة ساعات الذروة ومعدلات الفواتير بالساعة
├── DashboardRecentInvoices.vue       <-- جدول مصغر بآخر فواتير المبيعات الصادرة
└── DashboardLowStock.vue             <-- قائمة الأصناف الحرجة التي تتطلب إعادة طلب عاجلة
```

---

## 3. العناصر المشتركة ومخازن الحالة:
* **المكونات المشتركة:** `PageHeader.vue`, `MetricCard.vue`, `DashboardSectionCard.vue`, `SimpleBarChart.vue`.
* **المخازن المستخدمة:** `useAuthStore` (الفرع النشط والصلاحيات)، `useAppConfigStore`.
* **الـ Composables:** `useFormatters.js` لتنسيق المبالغ المالية (`formatMoney`) والنسب المئوية.

---

## 4. الاعتماديات والـ APIs:
* `GET /api/v1/dashboard` و `GET /api/v1/dashboard/summary`: جلب مؤشرات لوحة القيادة المجمعة:
  * **الكنترولر:** `App\Http\Controllers\Api\DashboardApiController@index`
  * **Actions:** `App\Actions\Dashboard\GetDashboardApiOverviewAction` و `App\Actions\Dashboard\GetTenantDashboardAnalyticsAction`

---

## 5. نطاق الفروع وعزل البيانات (Store Scoping):
* ترسل لوحة التحكم ترويسة `X-Store-Id` مع كل استدعاء، لعرض إحصائيات الفرع المحدد للمستخدم.
* يتم التدقيق الأمني عبر `ClientStoreGuard::verified($request)`؛ ومحاولة الاستعلام عن فرع غير مصرح به تُرجع **HTTP 403** مع كود `store_access_denied`.
* استعراض بيانات كافة الفروع مجمعاً يتطلب صلاحية `stores.view_all` أو دور `admin`.

---

## 6. القواعد المالية الصارمة ويوم العمل:
1. **توقيت قطع يوم العمل (`Business-Day Cutoff`):**
   * مفهوم "اليوم" في بطاقة "مبيعات اليوم" لا يعتمد على توقيت الخادم، بل يتبع ساعة المستأجر وتوقيت القطع `TenantSettings::businessDayCutoff()` عبر `TenantClock`.
2. **الدقة المالية `DECIMAL(12,3)` و `bcmath`:** كافة مجاميع المبيعات والأرباح التقديرية تُحسب باستخدام `bcmath` مع التقريب المتماثل للنصف للأعلى (`half-up rounding at 3 dp`).
