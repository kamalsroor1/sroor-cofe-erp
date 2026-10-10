# 🚚 توثيق وتحليل صفحة التحويلات المخزنية بين الفروع والمخازن (Stock Transfers)

## 1. النظرة العامة والتحليل التشغيلي:
* **اسم الصفحة:** سجل التحويلات المخزنية بين الفروع (Stock Transfers Log)
* **المسار (Route):** `/stock-transfers`
* **اسم المسار (Route Name):** `stock_transfers.index`
* **الصلاحية المطلوبة (Permission):** `transfers.view`
* **الملف الرئيسي:** `resources/js/views/StockTransfers/StockTransfersView.vue` (~168 سطرًا).
* **الغرض والتحليل التشغيلي:**
  * التوثيق الشامل لكافة حركات نقل البضائع والمواد الخام بين المستودع الرئيسي والفروع الفرعية أو بين عربات التوزيع.
  * متابعة أذون الصرف والاستلام وحالة النقل (معتمد ومكتمل، أو ملغى).
  * بطاقات إحصائية تلخص إجمالي عمليات التحويل، إجمالي الكميات المنقولة، والتحويلات الحديثة.
  * فلترة متقدمة حسب المخزن المحول منه (`from_store`) والمخزن المحول إليه (`to_store`) والنطاق الزمني.
  * استعراض تفاصيل إذن التحويل والبنود والكميات المنقولة من خلال نافذة منبثقة مع إمكانية إلغاء الإذن في الحالات الطارئة وعكس المخزون فورياً.

---

## 2. هيكلية وشجرة المكونات (Component Tree):
```text
StockTransfersView.vue (~168 lines)
├── StockTransfersMetricsGrid.vue     <-- بطاقات المؤشرات: إجمالي التحويلات، الكميات المنقولة، التحويلات النشطة
├── StockTransfersSearchFilterBar.vue <-- شريط الفلاتر: البحث، المخزن المصدر، المخزن الهدف، والنطاق الزمني
├── StockTransfersTable.vue           <-- جدول أذون التحويل مع أرقام المستندات، الفروع، الحالات، وأزرار الإجراءات
└── StockTransferDetailsModal.vue     <-- نافذة استعراض بنود إذن التحويل وخيار إلغاء المستند
```

---

## 3. العناصر المشتركة ومخازن الحالة:
* **المكونات المشتركة:** `PageHeader.vue`, `BaseButton.vue`, `DataTable.vue`, `StatusBadge.vue`, `AppModal.vue`.
* **المخازن المستخدمة:** `useAuthStore`, `useAppConfigStore`.
* **الـ Composables:** `useFormatters.js` لتنسيق الكميات (`formatQty`) والتواريخ.

---

## 4. الاعتماديات والـ APIs:
* `GET /api/v1/transfers`: جلب سجلات التحويلات المخزنية مع الفلاتر وترقيم الصفحات:
  * **الكنترولر:** `App\Http\Controllers\Api\StockTransferController@index`
  * **Resource:** `App\Http\Resources\StockTransferResource`
* `GET /api/v1/transfers/{id}`: جلب تفاصيل إذن التحويل والبنود المنقولة.
* `POST /api/v1/transfers/{id}/cancel`: إلغاء إذن التحويل وعكس الكميات للمخزن المصدر (`CancelStockTransferRequest`).

---

## 5. مصفوفة الصلاحيات ونطاق الفروع (Store Scoping):
* **الصلاحيات:** `transfers.view` للعرض، و `transfers.create` / `stores.manage` للإنشاء والإلغاء.
* **نطاق الفروع:** يتم التحقق الصارم عبر `ClientStoreGuard::concrete($request)`؛ وإذا كان المستخدم غير مصرح له بالفرع المصدر أو الهدف، يُرجع النظام **HTTP 403** مع كود `store_access_denied`.

---

## 6. القواعد المخزنية الصارمة:
1. **القيد المزدوج الذري (`Double-Entry Stock Movement`):** عند التحويل، يتم خصم الكمية من المخزن المصدر وإضافتها للمخزن الهدف في نفس اللحظة داخل `DB::transaction()` وباستخدام `lockForUpdate()`.
2. **الدقة العددية `DECIMAL(12,3)` و `bcmath`:** كافة كميات التحويل والأوزان تُعالج بدقة 3 خانات عشرية وبتقريب متماثل للنصف للأعلى (`half-up rounding at 3 dp`).
3. **عكس التحويل عند الإلغاء:** عند إلغاء إذن تحويل، تُخصم الكميات من المخزن الهدف وتُعاد للمخزن المصدر فورياً.
