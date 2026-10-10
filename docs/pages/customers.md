# 👥 وثيقة المكون والصفحة: دليل وإدارة العملاء والزبائن (`CustomersView`)

## 1. النظرة العامة والتحليل التشغيلي:
* **اسم الصفحة:** دليل وإدارة العملاء وحسابات الذمم (Customers Management)
* **المسار (Route):** `/customers`
* **اسم المسار (Route Name):** `customers.index`
* **الصلاحية المطلوبة (Permission):** `customers.manage`
* **الملف الرئيسي:** `resources/js/views/Customers/CustomersView.vue` (~104 سطرًا).
* **الغرض والتحليل التشغيلي:**
  * المستودع المركزي لبيانات العملاء التجاريين والأفراد والشركات.
  * إدارة التسهيلات الائتمانية وتحديد سقف وحد الائتمان الأقصى (`Credit Limit`) لكل عميل لمنع تجاوز المديونيات.
  * مؤشرات KPI مالية فورية تلخص إجمالي عدد العملاء، العملاء المدينين، إجمالي المديونيات المستحقة، والمتحصلات الشهرية.
  * نافذة تحصيل الدفعات وسندات القبض المباشرة (`Collect Payment Modal`) لقيد السداد في حساب العميل وتوريده للخزينة.
  * زر انتقال مباشر لكشف حساب وأستاذ العميل التفصيلي (`Customer Statement`).
  * تصفية متقدمة حسب حالة المديونية (جميع العملاء، عليهم مستحقات، رصيد صفري، أرصدة دائنة).

---

## 2. هيكلية وشجرة المكونات (Component Tree):
```text
CustomersView.vue (~104 lines)
├── CustomersHeader.vue               <-- رأس الصفحة مع زر إضافة عميل جديد، وتصدير البيانات
├── CustomersMetricsGrid.vue          <-- بطاقات المؤشرات: إجمالي العملاء، المدينين، إجمالي الديون المستحقة
├── CustomersFilterBar.vue            <-- شريط الفلاتر: البحث بالاسم/الهاتف، حالة الرصيد، والفرع
├── CustomersTable.vue                <-- جدول العملاء مع الهواتف، الأرصدة، الحدود الائتمانية، وقائمة الإجراءات
├── CustomerFormModal.vue             <-- نافذة إضافة وتعديل بيانات العميل (الاسم، الهاتف، العنوان، السجل، حد الائتمان)
└── CustomerPaymentModal.vue          <-- نافذة تحصيل وقيد دفعة سداد نقدية أو بنكية في حساب العميل
```

---

## 3. العناصر المشتركة ومخازن الحالة:
* **المكونات المشتركة:** `PageHeader.vue`, `BaseButton.vue`, `DataTable.vue`, `StatusBadge.vue`, `AppModal.vue`.
* **المخازن المستخدمة:** `useAuthStore` (فحص صلاحية `customers.manage` و `customers.statement`)، `useAppConfigStore`.
* **الـ Composables:** `useFormatters.js` لتنسيق المبالغ المالية (`formatMoney`) وأرقام الهواتف.

---

## 4. الاعتماديات والـ APIs:
* `GET /api/v1/customers`: جلب قائمة العملاء مع الفلاتر وترقيم الصفحات:
  * **الكنترولر:** `App\Http\Controllers\Api\CustomerController@index`
  * **Resource:** `App\Http\Resources\CustomerResource`
* `POST /api/v1/customers`: إضافة عميل جديد (`customers.manage`):
  * **Form Request:** `App\Http\Requests\StoreCustomerRequest`
  * **Action:** `App\Actions\Customers\StoreCustomerAction`
* `GET /api/v1/customers/{id}`: جلب بيانات العميل التفصيلية.
* `PUT /api/v1/customers/{id}`: تعديل بيانات العميل وحدوده الائتمانية:
  * **Form Request:** `App\Http\Requests\UpdateCustomerRequest`
  * **Action:** `App\Actions\Customers\UpdateCustomerAction`
* `DELETE /api/v1/customers/{id}`: نقل العميل لسلة المحذوفات (`customers.manage`).
* `PATCH /api/v1/customers/{id}/toggle-active`: تفعيل/تعطيل العميل.
* `POST /api/v1/customers/{id}/collect-payment`: تسجيل سند قبض وتحصيل دفعة سداد:
  * **Form Request:** `App\Http\Requests\CollectCustomerPaymentRequest`
  * **Action:** `App\Actions\Customers\CollectCustomerPaymentAction`

---

## 5. مصفوفة الصلاحيات ونطاق الفروع:
* **الصلاحيات (`PermissionsSeeder.php`):**
  * `customers.manage`: إدارة وتعديل وتحصيل دفعات العملاء (متاحة للمدير، الكاشير، والمحاسب).
  * `customers.statement`: استعراض وطباعة كشف الحساب.
* **نطاق الفروع:** ترسل طلبات التحصيل ترويسة `X-Store-Id` لإيداع النقدية في خزينة الفرع النشط؛ ويتم التدقيق عبر `ClientStoreGuard::concrete($request)`. أي مخالفة تُرجع **HTTP 403** مع كود `store_access_denied`.

---

## 6. القواعد المالية الصارمة والمحاسبية:
1. **تحديث الرصيد التراكمي الذري:** تسجيل الفواتير الآجلة وسندات القبض ينفذ داخل `DB::transaction()` مع استخدام `lockForUpdate()` على سجل العميل لمنع تضارب الأرصدة.
2. **الدقة المالية `DECIMAL(12,3)` و `bcmath`:** كافة حسابات مديونيات العملاء والحدود الائتمانية تُحسب بدقة 3 خانات عشرية متطابقة مع كشف الحساب وبتقريب متماثل للنصف للأعلى (`half-up rounding at 3 dp`).
