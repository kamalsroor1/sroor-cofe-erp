# 🔄 وثيقة المكون والصفحة: سجل المرتجعات وإشعارات الخصم والإرجاع (`ReturnsView.vue`)

> **المسار (Route):** `/returns`  
> **الملف الرئيسي:** `resources/js/views/Returns/ReturnsView.vue` (Thin Orchestrator: ~70 سطر)  
> **تاريخ المراجعة الشاملة:** 2026-08-24  
> **الحالة:** ✅ مكتملة وموثقة 100% عبر المحاور الأربعة المتزامنة.

---

## 1. التحليل التشغيلي والمعماري (Operational & Architectural Analysis)

### 🎯 الغرض من الصفحة:
تُمثل شاشة **سجل المرتجعات وإشعارات التسوية المخزنية والمالية (`/returns`)** مركز إدارة العمليات العكسية للمبيعات والمشتريات:
1. **إدارة مرتجعات المبيعات والمشتريات (Sales & Purchase Returns Ledger):** استعراض حركات الإرجاع للعملاء والموردين مع شارة مميزة لنوع الإرجاع وتأثيره على الأرصدة.
2. **مؤشرات المرتجعات المالية (Financial Metrics):** إجمالي قيمة المرتجعات، عدد مرتجعات المبيعات، عدد مرتجعات المشتريات، وإجمالي مستندات الإرجاع.
3. **الفلاتر والبحث المتقدم:** بحث فوري، تصفية حسب نوع الإرجاع (مبيعات / مشتريات)، وتصفية بنطاق التاريخ من/إلى.
4. **معاينة الأصناف المرجعة (Details Modal):** نافذة `AppModal` تعرض قائمة الأصناف والكميات وأسعار البيع والتكلفة وإجمالي القيمة والسبب.
5. **الحذف / الأرشفة الآمنة:** إمكانية حذف مستند الإرجاع مع إشعار تأكيد SweetAlert.

---

## 2. هيكلية وشجرة المكونات (Component Tree & Architecture)

تم تفكيك الصفحة من ملف بـ 439 سطرًا إلى **Thin Orchestrator** ومكونات أحادية المسؤولية:

```text
resources/js/
├── views/Returns/
│   └── ReturnsView.vue                        <-- Thin Orchestrator (~70 lines)
├── Components/Returns/
│   ├── ReturnsMetricsGrid.vue                 <-- بطاقات المؤشرات الأربعة للمرتجعات
│   ├── ReturnsFilterBar.vue                   <-- شريط الفلاتر (بحث، نوع، تاريخ من/إلى)
│   ├── ReturnsTable.vue                       <-- جدول المرتجعات وبطاقات الهواتف مع الأزرار
│   └── ReturnDetailsModal.vue                 <-- نافذة معاينة تفاصيل المستند والأصناف
└── Composables/
    └── useReturns.js                          <-- كبسولة المنطق والاتصال بالـ API والفلترة
```

---

## 3. عناصر النماذج والواجهات المشتركة المستخدمة

* `PageHeader.vue`: ترويسة الصفحة وزر إنشاء مرتجع جديد.
* `BaseSearchInput.vue`: حقل البحث الفوري مع debounce.
* `BaseSelect.vue`: قائمة تصفية نوع الإرجاع.
* `StatCardSkeleton.vue`: هياكل تحميل وميضية لبطاقات المؤشرات.
* `TableSkeleton.vue`: هيكل التحميل الوميضي للجداول.
* `EmptyState.vue`: حالة عدم وجود مرتجعات مطابقة للفلاتر.
* `AppModal.vue`: الحاوية الموحدة لنافذة التفاصيل.

---

## 4. الاعتماديات والـ APIs المرتبطة

| العملية | الـ Endpoint | الطلب (Request Payload) | الاستجابة |
| :--- | :--- | :--- | :--- |
| **جلب سجل المرتجعات والمؤشرات** | `GET /api/v1/returns` | `search`, `type`, `from_date`, `to_date`, `page` | قائمة المرتجعات + الإحصائيات + الترقيم |
| **جلب تفاصيل مرتجع محدد** | `GET /api/v1/returns/{id}` | - | تفاصيل المستند مع جدول الأصناف والكميات |
| **أرشفة / حذف مرتجع** | `DELETE /api/v1/returns/{id}` | - | حذف المستند وإعادة ضبط الأرصدة |

---

## 5. فحص التجاوب وتجربة اللمس والوضعين (Responsive & Touch Ergonomics)

* **📱 هواتف (360px - 430px):**
  * بطاقات مؤشرات متراصة، شريط فلاتر مرن، بطاقات لمسية متراصة لكل مستند إرجاع، وأزرار إجراءات بارتفاع $\ge 40	ext{px}$.
* **💻 تابلت وديسكتوب (768px - 1280px+):**
  * جدول محاسبي متكامل بشارات ملونة وقيم مالية واضحة بالـ Mono Font.
* **🌓 الوضع الداكن والفاتح:** تباين كامل للبطاقات والصفوف وحقول الإدخال.

---

## 6. قاموس الترجمة (100% Zero Hardcoded Localization)

كافة النصوص تستند إلى ملفات الترجمة المركزية في `lang/ar/returns.php` و `lang/en/returns.php`:
* `returns.title`: سجل المرتجعات وإشعارات الخصم والإرجاع / Returns & Credit Notes Ledger
* `returns.new_return`: تسجيل مرتجع جديد / New Return
* `returns.total_returns_value`: إجمالي قيمة المرتجعات / Total Returns Value
* `returns.sales_returns_title`: مرتجعات مبيعات (من عملاء) / Sales Returns (from Customers)
* `returns.purchase_returns_title`: مرتجعات مشتريات (لموردين) / Purchase Returns (to Suppliers)

---

## 7. سجل الاختبارات والتحقق (Test Results)

* ✅ **Playwright E2E Test:** `e2e/flows/returns-full-page-audit.spec.js` -> نجاح 7/7 اختبارات عبر كافة مقاسات الشاشات الـ 5 بدون أي خطأ Console.
* ✅ **Build Verification:** `npm run build` -> تم البناء بنجاح 100% في 3.08 ثانية.
