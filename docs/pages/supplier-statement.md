# 📑 وثيقة المكون والصفحة: كشف حساب وأستاذ المورد (`SupplierStatementView`)

## 1. النظرة العامة والتحليل التشغيلي:
* **اسم الصفحة:** كشف حساب وأستاذ المورد (Supplier Account Statement)
* **المسار (Route):** `/suppliers/:id/statement`
* **اسم المسار (Route Name):** `suppliers.statement`
* **الصلاحية المطلوبة (Permission):** `suppliers.manage` (أو `suppliers.statement`)
* **الملف الرئيسي:** `resources/js/views/Suppliers/SupplierStatementView.vue` (~54 سطرًا).
* **الغرض والتحليل التشغيلي:**
  * كشف الحساب والأستاذ المساعد التفصيلي المعتمد لمطابقة وتدقيق مستحقات ومشتريات المورد.
  * استعراض الرصيد الافتتاحي ما قبل الفترة، إجمالي فواتير الشراء والتوريد (دائن)، إجمالي سندات الصرف والمدفوعات (مدين)، والمرتجعات، وصولاً إلى صافي الرصيد المستحق النهائي.
  * فلترة متقدمة حسب النطاق الزمني لتوليد كشوف حساب دورية شهرية أو سنوية.
  * جدول زمني تسلسلي يوضح تاريخ كل معاملة، رقم المستند، البيان، المبالغ المدينة، المبالغ الدائنة، والرصيد التراكمي المتحرك.
  * دعم كامل للطباعة الرسمية الفورية A4 المنسقة مع الترويسة، والتصدير لـ PDF.

---

## 2. هيكلية وشجرة المكونات (Component Tree):
```text
SupplierStatementView.vue (~54 lines)
├── SupplierStatementHeader.vue      <-- بيانات المورد الأساسية، الشركة، السجل التجاري، وأزرار الطباعة
├── SupplierStatementSummaryCards.vue<-- بطاقات التلخيص المالي: الرصيد السابق، المشتريات، المدفوعات، المستحق للمورد
├── SupplierStatementFilterBar.vue   <-- شريط فلترة الفترة الزمنية ونوع المعاملات
└── SupplierStatementTable.vue       <-- جدول حركة الأستاذ: التاريخ، المستند، البيان، مدين، دائن، والرصيد التراكمي
```

---

## 3. العناصر المشتركة ومخازن الحالة:
* **المكونات المشتركة:** `PageHeader.vue`, `BaseButton.vue`, `DataTable.vue`.
* **المخازن المستخدمة:** `useAuthStore`, `useAppConfigStore`.
* **الـ Composables:** `useFormatters.js` لتنسيق المبالغ المالية بدقة (`formatMoney`) والتواريخ.

---

## 4. الاعتماديات والـ APIs:
* `GET /api/v1/suppliers/{id}/statement`: جلب كشف الحساب والمعاملات التفصيلية للمورد:
  * **الكنترولر:** `App\Http\Controllers\Api\SupplierController@statement`
  * **الصلاحية المطلوبة:** `suppliers.statement` أو `suppliers.manage`

---

## 5. نطاق الفروع وعزل البيانات (Store Scoping):
* يتم التحقق الأمني من أحقية المستخدم في استعراض بيانات المورد وحساباته التابعة لمؤسسة المستأجر.
* في حال طلب معاملات فرع محدد، يتم التحقق عبر `ClientStoreGuard::verified($request)`؛ ومحاولة الوصول غير المصرح بها تنتج خطأ **HTTP 403** بكود `store_access_denied`.

---

## 6. القواعد المحاسبية الصارمة والدقة المالية:
1. **معادلة الرصيد التراكمي للمورد:**
   $$\text{Running Balance}_i = \text{Running Balance}_{i-1} + \text{Credit}_i - \text{Debit}_i$$
2. **الدقة المالية `DECIMAL(12,3)` و `bcmath`:** كافة بنود الحساب والرصيد التراكمي تُعالج بالكامل عبر دوال `bcmath` بـ 3 خانات عشرية وبتقريب متماثل للنصف للأعلى (`half-up rounding at 3 dp`).
