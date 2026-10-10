# 🏭 وثيقة المكون والصفحة: دليل وإدارة الموردين والتجار (`SuppliersView`)

## 1. النظرة العامة والتحليل التشغيلي:
* **اسم الصفحة:** دليل وإدارة الموردين والتجار (Suppliers Management)
* **المسار (Route):** `/suppliers`
* **اسم المسار (Route Name):** `suppliers.index`
* **الصلاحية المطلوبة (Permission):** `suppliers.manage`
* **الملف الرئيسي:** `resources/js/views/Suppliers/SuppliersView.vue` (~104 سطرًا).
* **الغرض والتحليل التشغيلي:**
  * المستودع المركزي لبيانات الموردين والشركات وتجار الجملة المعتمدين لتوريد المواد الخام والبن والمستلزمات.
  * متابعة أرصدة المديونيات المستحقة للموردين والمتحصلات ومواعيد السداد لتفادي توقف التوريدات.
  * بطاقات إحصائية تلخص إجمالي الموردين، إجمالي الديون المستحقة للموردين، والمبالغ المسددة خلال الشهر الجاري.
  * نافذة تسجيل سندات الصرف وسداد الدفعات (`Supplier Payment Modal`) لقيد السداد المالي للمورد من الخزينة أو الحساب البنكي.
  * زر وصول مباشر لكشف حساب وأستاذ المورد التفصيلي (`Supplier Statement`).
  * تصفية متقدمة حسب حالة المديونية (جميع الموردين، مستحق لهم مبالغ، أرصدة صفرية).

---

## 2. هيكلية وشجرة المكونات (Component Tree):
```text
SuppliersView.vue (~104 lines)
├── SuppliersHeader.vue               <-- رأس الصفحة مع زر إضافة مورد جديد، وتصدير البيانات
├── SuppliersMetricsGrid.vue          <-- بطاقات المؤشرات: إجمالي الموردين، المستحقات للموردين، المدفوعات الشهرية
├── SuppliersFilterBar.vue            <-- شريط الفلاتر: البحث بالاسم/الشركة/الهاتف، وحالة الرصيد
├── SuppliersTable.vue                <-- جدول الموردين مع بيانات الاتصال، الأرصدة المستحقة، وقائمة الإجراءات
├── SupplierFormModal.vue             <-- نافذة إضافة وتعديل بيانات المورد (الاسم، الشركة، الهاتف، العنوان، السجل التجاري)
└── SupplierPaymentModal.vue          <-- نافذة تسجيل سند صرف وسداد دفعة للمورد من الخزينة
```

---

## 3. العناصر المشتركة ومخازن الحالة:
* **المكونات المشتركة:** `PageHeader.vue`, `BaseButton.vue`, `DataTable.vue`, `StatusBadge.vue`, `AppModal.vue`.
* **المخازن المستخدمة:** `useAuthStore` (فحص صلاحية `suppliers.manage` و `suppliers.statement`)، `useAppConfigStore`.
* **الـ Composables:** `useFormatters.js` لتنسيق المبالغ المالية (`formatMoney`).

---

## 4. الاعتماديات والـ APIs:
* `GET /api/v1/suppliers`: جلب قائمة الموردين مع الفلاتر وترقيم الصفحات:
  * **الكنترولر:** `App\Http\Controllers\Api\SupplierController@index`
  * **Resource:** `App\Http\Resources\SupplierResource`
* `POST /api/v1/suppliers`: إضافة مورد جديد (`suppliers.manage`):
  * **Form Request:** `App\Http\Requests\StoreSupplierRequest`
  * **Action:** `App\Actions\Suppliers\StoreSupplierAction`
* `GET /api/v1/suppliers/{id}`: جلب تفاصيل المورد.
* `PUT /api/v1/suppliers/{id}`: تعديل بيانات المورد:
  * **Form Request:** `App\Http\Requests\UpdateSupplierRequest`
  * **Action:** `App\Actions\Suppliers\UpdateSupplierAction`
* `DELETE /api/v1/suppliers/{id}`: نقل المورد لسلة المحذوفات (`suppliers.manage`).
* `PATCH /api/v1/suppliers/{id}/toggle-active`: تفعيل/تعطيل المورد.
* `POST /api/v1/suppliers/{id}/pay`: تسجيل سند صرف وسداد دفعة للمورد:
  * **Form Request:** `App\Http\Requests\PaySupplierRequest`
  * **Action:** `App\Actions\Suppliers\PaySupplierAction`

---

## 5. مصفوفة الصلاحيات ونطاق الفروع:
* **الصلاحيات (`PermissionsSeeder.php`):**
  * `suppliers.manage`: إدارة الموردين وتسجيل الدفعات (متاحة للمدير، أمين المخزن، والمحاسب).
  * `suppliers.statement`: استعراض وطباعة كشف الحساب.
* **نطاق الفروع:** ترسل طلبات الصرف المالي ترويسة `X-Store-Id` لخصم القيمة من خزينة الفرع النشط؛ ويتم التدقيق عبر `ClientStoreGuard::concrete($request)`. أي انتهاك يُرجع **HTTP 403** بكود `store_access_denied`.

---

## 6. القواعد المالية الصارمة والمحاسبية:
1. **تحديث رصيد المورد التراكمي:** فواتير الشراء وسندات الصرف تسجل داخل `DB::transaction()` مع استخدام `lockForUpdate()` على سجل المورد.
2. **الدقة المالية `DECIMAL(12,3)` و `bcmath`:** كافة حسابات أرصدة الموردين تُحسب بدقة 3 خانات عشرية وبتقريب متماثل للنصف للأعلى (`half-up rounding at 3 dp`).
