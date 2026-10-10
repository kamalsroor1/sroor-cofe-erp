# 🚚 وثيقة المكون والصفحة: إنشاء إذن تحويل مخزني ونقل بضاعة (`CreateStockTransferView`)

## 1. النظرة العامة والتحليل التشغيلي:
* **اسم الصفحة:** إنشاء إذن تحويل مخزني (Create Stock Transfer Document)
* **المسار (Route):** `/stock-transfers/create`
* **اسم المسار (Route Name):** `stock_transfers.create`
* **الصلاحية المطلوبة (Permission):** `stores.manage` في مسار الواجهة و `transfers.create` في الباك إند
* **الملف الرئيسي:** `resources/js/views/StockTransfers/CreateStockTransferView.vue` (~69 سطرًا).
* **الغرض والتحليل التشغيلي:**
  * شاشة المعالجة التنفيذية لإصدار أذون نقل وصرف البضائع والمنتجات بين المستودعات والفروع وسيارات التوزيع.
  * اختيار الفرع المصدر (`From Store`) والفرع المستلم (`To Store`) مع منع اختيار نفس الفرع كمصدر ووجهة.
  * جدول تفاعلي لإضافة الأصناف المراد نقلها، مع فحص فوري للرصيد الحي المتاح بالفرع المصدر قبل تأكيد النقل.
  * منع التحويل إذا كانت الكمية المطلوبة تتجاوز الرصيد الفعلي المتوفر بالفرع المصدر لتفادي المخزون السالب.
  * إدخال ملاحظات النقل وبيانات السائق أو المندوب المسلم.
  * تنفيذ القيد المزدوج الفوري وتحديث كروت حركة الأصناف بمجرد الحفظ والاعتماد.

---

## 2. هيكلية وشجرة المكونات (Component Tree):
```text
CreateStockTransferView.vue (~69 lines)
├── CreateStockTransferHeaderCard.vue <-- كارد بيانات إذن التحويل: رقم المستند، التاريخ، الفرع المصدر، الفرع المستلم، والملاحظات
├── CreateStockTransferItemsCard.vue  <-- جدول اختيار الأصناف، عرض الرصيد المتاح، حقل الكمية المحولة، وزر حذف البند
└── StockTransferDetailsModal.vue     <-- نافذة معاينة وطباعة ملخص الإذن بعد الاعتماد
```

---

## 3. العناصر المشتركة ومخازن الحالة:
* **المكونات المشتركة:** `PageHeader.vue`, `BaseButton.vue`, `BaseInput.vue`, `BaseSelect.vue`.
* **المخازن المستخدمة:** `useAuthStore` (قائمة الفروع المصرح بها وفحص الصلاحيات)، `useAppConfigStore`.
* **الـ Composables:** `useFormatters.js` لتنسيق الكميات بدقة 3 خانات عشرية (`formatQty`).

---

## 4. الاعتماديات والـ APIs:
* `POST /api/v1/transfers`: حفظ واعتماد إذن التحويل المخزني:
  * **الكنترولر:** `App\Http\Controllers\Api\StockTransferController@store`
  * **Form Request:** `App\Http\Requests\StoreStockTransferRequest`
  * **Action:** `App\Actions\Transfers\StoreStockTransferAction`
  * **DTO:** `App\DTOs\Transfers\StockTransferDTO`
  * **Resource:** `App\Http\Resources\StockTransferResource`
* `GET /api/v1/stores`: جلب قائمة الفروع النشطة للاختيار منها.
* `GET /api/v1/items`: جلب قائمة الأصناف وأرصدتها المتوفرة بالفرع المصدر.

---

## 5. نطاق الفروع وعزل البيانات (Store Scoping):
* يتحقق الطلب عبر `StoreStockTransferRequest` وحارس `ClientStoreGuard` من أن المستخدم يمتلك تصريحاً سارياً على الفرع المصدر والفرع الوجهة.
* إذا حاول المستخدم التحويل من أو إلى فرع لا يمتلك صلاحية عليه، يتم رفض الطلب فوراً برمز **HTTP 403** مع كود `store_access_denied`.

---

## 6. القواعد المخزنية والمالية الصارمة:
1. **القفل السطري المتزامن (`lockForUpdate()`):** يتم قفل أرصدة الصنف في كلا المستودعين (المصدر والمستقبل) داخل `DB::transaction()` لمنع حدوث Race Condition أثناء حركة النقل.
2. **منع الأرصدة السالبة:** يفحص الباك إند برمجياً أن $qty \le balance_{source}$ قبل خصم أي كمية.
3. **الدقة العددية `DECIMAL(12,3)` و `bcmath`:** كافة كميات التحويل تُعالج بدقة 3 خانات عشرية متطابقة في قاعدة البيانات.
