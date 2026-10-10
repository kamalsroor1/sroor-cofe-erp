# 📦 توثيق وتحليل صفحة دليل الأصناف والمخزون الحي (Items)

## 1. النظرة العامة والتحليل التشغيلي:
* **اسم الصفحة:** دليل الأصناف والمخزون (Items & Live Catalog)
* **المسار (Route):** `/items`
* **اسم المسار (Route Name):** `items.index`
* **الصلاحية المطلوبة (Permission):** `items.view`
* **الملف الرئيسي:** `resources/js/views/Items/ItemsView.vue` (~310 سطرًا).
* **الغرض والتحليل التشغيلي:**
  * المستودع المركزي لتعريف المنتجات والمواد الخام، وضبط أسعار البيع القطاعي والجملة، وإدارة تكاليف الشراء.
  * مراقبة المخزون اللحظي عبر الفروع ونقاط البيع، واستعراض رادار النواقص والأصناف التي بلغت حد الطلب الأدنى.
  * محرك متقدم للبحث بالاسم، الباركود، الكود الدولي SKU، وتصفية الأصناف حسب التصنيفات وحالة المخزون.
  * بطاقات KPI تعرض إجمالي عدد الأصناف، الأصناف المفعلة، تنبيهات النواقص، والقيمة التقديرية للمخزون.
  * إدارة التسويات الجردية المباشرة (`Stock Adjustments`) لمعالجة العجز والتلف والزيادة الجردية.
  * إخفاء أسعار التكلفة وهوامش الربح تلقائياً للمستخدمين الذين لا يمتلكون صلاحية `items.view_cost`.

---

## 2. هيكلية وشجرة المكونات (Component Tree):
```text
ItemsView.vue (~310 lines)
├── ItemsHeader.vue                   <-- رأس الصفحة مع زر إضافة صنف جديد، وتصدير الأصناف
├── ItemsMetricsGrid.vue              <-- بطاقات المؤشرات: إجمالي الأصناف، الأصناف النشطة، النواقص، إجمالي التقييم
├── ItemsFilterBar.vue                <-- شريط الفلاتر: البحث، التصنيف، حالة المخزون (متوفر، نواقص، نفد)، والفرع
├── ItemsTable.vue                    <-- جدول الأصناف مع الباركود، الأسعار، الأرصدة، وزر العمليات السريعة
├── ItemFormModal.vue                 <-- نافذة إضافة وتعديل الصنف (الاسم، الباركود، الفئة، التكلفة، البيع، حد الطلب)
└── ItemAdjustStockModal.vue          <-- نافذة التسوية الجردية (نوع التسوية: عجز/زيادة/تلف، الكمية، والسبب الموثق)
```

---

## 3. العناصر المشتركة ومخازن الحالة:
* **المكونات المشتركة:** `PageHeader.vue`, `BaseButton.vue`, `DataTable.vue`, `AppModal.vue`, `StatusBadge.vue`.
* **المخازن المستخدمة:** `useAuthStore` (فحص صلاحيات `items.create`, `items.edit`, `items.delete`, `items.view_cost`, `inventory.adjust`).
* **الـ Composables:** `useFormatters.js` لتنسيق المبالغ المالية (`formatMoney`) والكميات بدقة 3 خانات عشرية.

---

## 4. الاعتماديات والـ APIs:
* `GET /api/v1/items`: جلب قائمة الأصناف مع الفلاتر وترقيم الصفحات:
  * **الكنترولر:** `App\Http\Controllers\Api\ItemController@index`
  * **Resource:** `App\Http\Resources\ItemResource`
* `POST /api/v1/items`: إضافة صنف جديد (`items.create`):
  * **Form Request:** `App\Http\Requests\StoreItemRequest`
  * **Action:** `App\Actions\Items\StoreItemAction`
* `PUT /api/v1/items/{id}`: تعديل بيانات وأسعار الصنف (`items.edit`):
  * **Form Request:** `App\Http\Requests\UpdateItemRequest`
  * **Action:** `App\Actions\Items\UpdateItemAction`
* `DELETE /api/v1/items/{id}`: نقل الصنف لسلة المحذوفات (`items.delete`).
* `PATCH /api/v1/items/{id}/toggle-active`: تفعيل/تعطيل الصنف السريع (`items.edit`).
* `POST /api/v1/items/{id}/adjust-stock`: تسوية رصيد المخزون (`inventory.adjust`):
  * **Form Request:** `App\Http\Requests\AdjustStockRequest`
  * **Action:** `App\Actions\Items\AdjustStockAction`
* `GET /api/v1/items/low-stock`: قائمة الأصناف التي وصلت للحد الأدنى من المخزون.

---

## 5. مصفوفة الصلاحيات وتوزيع الأدوار:
* **الصلاحيات الرسمية (`PermissionsSeeder.php`):**
  * `items.view`, `items.create`, `items.edit`, `items.delete`, `items.view_cost`, `inventory.adjust`.
* **توزيع الأدوار:**
  * `admin`: يمتلك كافة صلاحيات الأصناف والتسويات.
  * `storekeeper`: يمتلك `items.view` فقط (صلاحيات `items.create` و `items.edit` مسحوبة ومحصورة بالإدارة لمنع التلاعب بالأسعار والتكاليف).
  * `accountant`: يمتلك `items.view` و `items.view_cost`.
  * `cashier`: يمتلك `items.view` دون أسعار التكلفة.

---

## 6. نطاق الفروع وعزل البيانات (Store Scoping):
* يتم تمرير ترويسة `X-Store-Id` مع كل استدعاء لجلب رصيد الصنف الخاص بالفرع النشط.
* يتولى `ClientStoreGuard::verified($request)` مطابقة الفرع؛ ومحاولة الاستعلام عن أرصدة فروع غير مصرح بها تُرجع **HTTP 403** مع كود `store_access_denied`.

---

## 7. القواعد المالية الصارمة والمخزون:
1. **القفل السطري أثناء التسوية (`lockForUpdate()`):** أي تعديل على رصيد المخزون في `adjustStock` يتم داخل `DB::transaction()` مع قفل سطري مباشر للسجل.
2. **الدقة المالية `DECIMAL(12,3)` و `bcmath`:** كافة الكميات والأسعار والتكاليف تُخزن وتُعالج بدقة 3 خانات عشرية وبتقريب متماثل للنصف للأعلى (`half-up rounding at 3 dp`).
3. **التوثيق الإلزامي للتسويات الجردية:** تسجيل نوع الحركة، المستخدم المنفذ، والسبب في جدول سجل حركات المخزون `stock_movements`.
