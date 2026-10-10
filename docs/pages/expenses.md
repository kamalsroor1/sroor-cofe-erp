# 💸 وثيقة المكون والصفحة: إدارة المصروفات العامة والتكاليف التشغيلية (`ExpensesView`)

## 1. النظرة العامة والتحليل التشغيلي:
* **اسم الصفحة:** إدارة المصروفات والعهد النثرية (Operational Expenses)
* **المسار (Route):** `/expenses`
* **اسم المسار (Route Name):** `expenses.index`
* **الصلاحية المطلوبة (Permission):** `expenses.manage`
* **الملف الرئيسي:** `resources/js/views/Expenses/ExpensesView.vue` (~98 سطرًا).
* **الغرض والتحليل التشغيلي:**
  * المركز المالي لتوثيق ومراقبة كافة المصروفات التشغيلية والنثرية (مثل: إيجار، فواتير كهرباء ومياه، خامات تشغيل، صيانة، رواتب، ضيافة).
  * ربط كل مصروف بالفرع المسؤول عنه وتحديد طريقة السداد (نقداً من الخزينة، عهدة موظف، تحويل بنكي).
  * بطاقات إحصائية فورية تلخص إجمالي المصروفات للشهر الحالي، مصروفات اليوم، وتوزيع المصروفات حسب التصنيفات.
  * إرفاق صور الفواتير وسندات الصرف الورقية كملفات إثبات لكل عملية.
  * فلترة متقدمة حسب التصنيف، الفرع، النطاق الزمني، واسم الموظف الذي قيد المصروف.

---

## 2. هيكلية وشجرة المكونات (Component Tree):
```text
ExpensesView.vue (~98 lines)
├── ExpensesHeader.vue                <-- رأس الصفحة مع زر تسجيل مصروف جديد، وتصدير البيانات
├── ExpensesMetricsGrid.vue           <-- بطاقات المؤشرات: إجمالي مصروفات الشهر، مصروفات اليوم، العهد النثرية
├── ExpensesFilterBar.vue             <-- شريط الفلاتر: البحث، تصنيف المصروف، الفرع، والنطاق الزمني
├── ExpensesTable.vue                 <-- جدول المصروفات مع البيان، المبلغ، الفرع، الموظف، المرفقات، وقائمة الإجراءات
└── ExpenseFormModal.vue              <-- نافذة إضافة وتعديل المصروف (البند، التصنيف، المبلغ، الفرع، المرفق، الملاحظات)
```

---

## 3. العناصر المشتركة ومخازن الحالة:
* **المكونات المشتركة:** `PageHeader.vue`, `BaseButton.vue`, `DataTable.vue`, `AppModal.vue`, `StatusBadge.vue`.
* **المخازن المستخدمة:** `useAuthStore`, `useAppConfigStore`.
* **الـ Composables:** `useFormatters.js` لتنسيق المبالغ المالية (`formatMoney`) وتواريخ العمليات.

---

## 4. الاعتماديات والـ APIs:
* `GET /api/v1/expenses`: جلب قائمة المصروفات مع الفلاتر وترقيم الصفحات:
  * **الكنترولر:** `App\Http\Controllers\Api\ExpenseController@index`
  * **Resource:** `App\Http\Resources\ExpenseResource`
* `POST /api/v1/expenses`: تسجيل مصروف تشغيلي جديد (`expenses.manage`):
  * **Form Request:** `App\Http\Requests\StoreExpenseRequest`
  * **Action:** `App\Actions\Expenses\StoreExpenseAction`
* `GET /api/v1/expenses/{id}`: جلب تفاصيل المصروف.
* `PUT /api/v1/expenses/{id}`: تعديل بيانات المصروف:
  * **Form Request:** `App\Http\Requests\UpdateExpenseRequest`
  * **Action:** `App\Actions\Expenses\UpdateExpenseAction`
* `DELETE /api/v1/expenses/{id}`: حذف سجل المصروف (`expenses.manage`).

---

## 5. مصفوفة الصلاحيات ونطاق الفروع:
* **الصلاحيات (`PermissionsSeeder.php`):**
  * `expenses.manage`: تسجيل وتعديل ومراقبة المصروفات (متاحة للمدير والمحاسب).
* **نطاق الفروع:** تتطلب طلبات المصروفات ترويسة `X-Store-Id` ومعرف الفرع `store_id`؛ ويتم التحقق عبر `ClientStoreGuard::concrete($request)`. أي محاولة لتسجيل أو استعراض مصروفات فرع غير مصرح للمستخدم به تنتج خطأ **HTTP 403** بكود `store_access_denied`.

---

## 6. القواعد المالية الصارمة ويوم العمل:
1. **خصم فوري من الخزينة اليومية:** المصروفات النقدية تُخصم فوراً من رصيد درج الكاشير في دفتر اليومية `daily-journal` للفرع داخل `DB::transaction()`.
2. **الدقة المالية `DECIMAL(12,3)` و `bcmath`:** كافة مبالغ المصروفات تُعالج بدقة 3 خانات عشرية وبتقريب متماثل للنصف للأعلى (`half-up rounding at 3 dp`).
3. **ارتباط المصروف بيوم العمل:** يتبع المصروف ساعة المستأجر وتوقيت القطع `business_day_cutoff` عبر `TenantClock`.
