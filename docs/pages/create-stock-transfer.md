# 🚚 وثيقة المكون والصفحة: إنشاء إذن تحويل مخزني ونقل بضاعة (`CreateStockTransferView.vue`)

> **المسار (Route):** `/stock-transfers/create`  
> **الملف الرئيسي:** `resources/js/views/StockTransfers/CreateStockTransferView.vue` (Thin Orchestrator: ~55 سطر)  
> **تاريخ المراجعة الشاملة:** 2026-08-24  
> **الحالة:** ✅ مكتملة وموثقة 100% عبر المحاور الأربعة المتزامنة.

---

## 1. التحليل التشغيلي والمعماري (Operational & Architectural Analysis)

### 🎯 الغرض من الصفحة:
تُمثل شاشة **إنشاء إذن تحويل مخزني جديد (Create Stock Transfer)** مركز إدارة تدفق البضائع ونقل الأصناف والمواد الخام بين المستودعات المركزية وفروع البيع:
1. **تحديد المخزن المصدر والمخزن المستلم:** اختيار الفرع المُرسل والمستلم من القوائم المنسدلة مع منع التحويل لنفس المخزن تلقائياً.
2. **تاريخ الإذن والملاحظات التشغيلية:** تسجيل تاريخ التحويل وأي ملاحظات أو أرقام بوالص شحن.
3. **اختيار الأصناف والكميات المحولة:** اختيار الأصناف مع استعراض الرصيد المتاح حالياً لمنع تجاوز الرصيد الفعلي، وضبط الكميات المحولة بدقة `DECIMAL(12,3)`.
4. **التنفيذ الذري والمعاملة الآمنة (Atomic Transaction):** إرسال طلب `POST /api/v1/transfers` ليتم خصم المخزن المصدر وإيداع المخزن المستلم ذرياً داخل `DB::transaction()` مع استخدام `lockForUpdate()` لمنع البيع المزدوج أو التضارب.

---

## 2. هيكلية وشجرة المكونات (Component Tree & Architecture)

تم تفكيك الصفحة من ملف ضخم إلى **Thin Orchestrator** ومكونات أحادية المسؤولية:

```text
resources/js/
├── views/StockTransfers/
│   └── CreateStockTransferView.vue              <-- Thin Orchestrator (~55 lines)
├── Components/StockTransfers/
│   ├── CreateStockTransferHeaderCard.vue        <-- بطاقة الفروع المصدر/الوجهة وتاريخ الإذن والملاحظات
│   └── CreateStockTransferItemsCard.vue         <-- بطاقة إضافة الأصناف وجدول وتراص بطاقات البنود
└── Composables/
    └── useCreateStockTransfer.js                <-- كبسولة المنطق الحسابي والاتصال بالـ APIs
```

---

## 3. عناصر النماذج والواجهات المشتركة المستخدمة

* `PageHeader.vue`: ترويسة الصفحة وزر العودة لسجل التحويلات.
* `BaseSelect.vue`: القوائم المنسدلة لاختيار المخازن والأصناف.
* `BaseInput.vue`: حقول التاريخ والملاحظات.
* `BaseButton.vue`: زر إضافة البند وزر التنفيذ النهائي.

---

## 4. الاعتماديات والـ APIs المرتبطة

| العملية | الـ Endpoint | الطلب (Request Payload) | الاستجابة |
| :--- | :--- | :--- | :--- |
| **جلب الفروع والأصناف** | `GET /api/v1/stores`, `GET /api/v1/items` | — | قائمة المخازن والأصناف وأرصدتها |
| **تنفيذ التحويل المخزني** | `POST /api/v1/transfers` | `from_store_id`, `to_store_id`, `transfer_date`, `items: [{item_id, quantity}]` | إشعار نجاح وتحديث الأرصدة |

---

## 5. فحص التجاوب وتجربة اللمس والوضعين (Responsive & Touch Ergonomics)

* **📱 هواتف (360px - 430px):**
  * ترتيب عمودي كامل للنماذج، حقول إدخال عريضة بارتفاع $\ge 44	ext{px}$، بطاقات لمسية متراصة للأصناف المحولة مع زر حذف مريح للإبهام وحقل كمية واضح.
* **💻 تابلت وديسكتوب (768px - 1280px+):**
  * شبكة ثلاثية لحقول المخازن والتاريخ، وجدول بيانات متناسق للأصناف.
* **🌓 الوضع الداكن والفاتح:** تباين كامل للبطاقات وخلفيات الحقول وتأثيرات التمرير.

---

## 6. قاموس الترجمة (100% Zero Hardcoded Localization)

كافة النصوص تستند إلى ملفات الترجمة المركزية في `lang/ar/inventory.php` و `lang/en/inventory.php`:
* `inventory.new_transfer`: عملية تحويل مخزني جديدة / New Stock Transfer
* `inventory.from_store_label`: من مخزن / فرع / From Store / Branch
* `inventory.to_store_label`: إلى مخزن / فرع / To Store / Branch
* `inventory.transfer_date_label`: تاريخ إذن التحويل / Transfer Date
* `inventory.transferred_items_section`: الأصناف المحولة / Transferred Items
* `inventory.execute_transfer_now_btn`: تنفيذ التحويل المخزني ونقل البضاعة فوراً / Execute Stock Transfer Now

---

## 7. سجل الاختبارات والتحقق (Test Results)

* ✅ **Playwright E2E Test:** `e2e/flows/create-stock-transfer-full-page-audit.spec.js` -> نجاح 7/7 اختبارات عبر كافة مقاسات الشاشات الـ 5 بدون أي خطأ Console.
* ✅ **Build Verification:** `npm run build` -> تم البناء بنجاح 100% في 4.88 ثانية.
