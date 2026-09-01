# 🛒 وثيقة المكون والصفحة: إنشاء واعتماد فاتورة شراء جديدة (`CreatePurchaseView.vue`)

> **المسار (Route):** `/purchases/create`  
> **الملف الرئيسي:** `resources/js/views/Purchases/CreatePurchaseView.vue` (Thin Orchestrator: ~55 سطر)  
> **تاريخ المراجعة الشاملة:** 2026-08-24  
> **الحالة:** ✅ مكتملة وموثقة 100% عبر المحاور الأربعة المتزامنة.

---

## 1. التحليل التشغيلي والمعماري (Operational & Architectural Analysis)

### 🎯 الغرض من الصفحة:
تُمثل شاشة **إنشاء واعتماد فاتورة شراء جديدة وتوريد المخزون (Create Purchase Invoice & Stock Receiving)** حجر الأساس في دورة المشتريات والتوريد:
1. **تحديد المورد وبيانات الفاتورة:** اختيار المورد من القائمة المنسدلة، تحديد تاريخ الاستلام، وإدخال رقم الفاتورة الدفترية للمورد (`supplier_invoice_ref`).
2. **جدول بنود الأصناف والتسعير:** إضافة الأصناف المستلمة وتحديد الكميات بدقة `DECIMAL(12,3)` مع الجلب التلقائي لأسعار التكلفة المعتمدة، واحتساب إجمالي كل سطر لحظياً.
3. **التكامل مع رادار الطلب الذكي (Smart Reorder Prefill):** دعم الاستيراد التلقائي للأصناف المقترحة والكميات المطلوبة عند تحويل أوامر الشراء من `/purchases/smart-reorder`.
4. **ملخص الحسابات والمديونية:** احتساب قيمة البضاعة، الخصومات المكتسبة، صافي الفاتورة، المبلغ المسدد، والمتبقي آجل على المنشأة.
5. **التنفيذ الذري والمعاملة الآمنة (Atomic Transaction):** إرسال طلب `POST /api/v1/purchases` لإيداع الأصناف في المخزن الرئيسي/المستهدف، تسجيل أثر الأستاذ المخزني، وتحديث رصيد المورد داخل `DB::transaction()`.

---

## 2. هيكلية وشجرة المكونات (Component Tree & Architecture)

تم تفكيك الصفحة من ملف ضخم إلى **Thin Orchestrator** ومكونات أحادية المسؤولية:

```text
resources/js/
├── views/Purchases/
│   └── CreatePurchaseView.vue                   <-- Thin Orchestrator (~55 lines)
├── Components/Purchases/
│   ├── CreatePurchaseSupplierCard.vue           <-- بطاقة بيانات المورد وتاريخ الفاتورة والمرجع الدفتري
│   ├── CreatePurchaseItemsCard.vue              <-- بطاقة وجدول وتراص بطاقات بنود الأصناف المستلمة
│   └── CreatePurchaseSummaryCard.vue            <-- بطاقة ملخص الحسابات والملاحظات والمدفوع والخصم
└── Composables/
    └── useCreatePurchase.js                     <-- كبسولة المنطق الحسابي والاتصال بالـ APIs
```

---

## 3. عناصر النماذج والواجهات المشتركة المستخدمة

* `PageHeader.vue`: ترويسة الصفحة وزر العودة لسجل المشتريات.
* `BaseSelect.vue`: القوائم المنسدلة لاختيار المورد والأصناف.
* `BaseInput.vue`: حقول التاريخ، المرجع الدفتري، والملاحظات.
* `BaseButton.vue`: زر إضافة سطر الصنف وزر تأكيد واعتماد التوريد.

---

## 4. الاعتماديات والـ APIs المرتبطة

| العملية | الـ Endpoint | الطلب (Request Payload) | الاستجابة |
| :--- | :--- | :--- | :--- |
| **جلب الموردين والأصناف** | `GET /api/v1/suppliers`, `GET /api/v1/items` | `per_page=100` | قائمة الموردين والأصناف وأسعار التكلفة |
| **اعتماد فاتورة الشراء** | `POST /api/v1/purchases` | `supplier_id`, `purchase_date`, `paid_amount`, `discount_amount`, `items: [{item_id, quantity, unit_cost}]` | إشعار نجاح وتحديث المخزون والحسابات |

---

## 5. فحص التجاوب وتجربة اللمس والوضعين (Responsive & Touch Ergonomics)

* **📱 هواتف (360px - 430px):**
  * ترتيب عمودي كامل للنماذج، بطاقات لمسية متراصة للأصناف تشمل اختيار الصنف والكمية وسعر التكلفة مع إجمالي السطر، وزر حذف واضح ومريح للإبهام بارتفاع $\ge 44	ext{px}$.
* **💻 تابلت وديسكتوب (768px - 1280px+):**
  * شبكة ثلاثية لحقول المورد، وجدول بيانات متناسق عالي الكثافة.
* **🌓 الوضع الداكن والفاتح:** تباين كامل للبطاقات والصفوف وحقول الإدخال.

---

## 6. قاموس الترجمة (100% Zero Hardcoded Localization)

كافة النصوص تستند إلى ملفات الترجمة المركزية في `lang/ar/purchases.php` و `lang/en/purchases.php`:
* `purchases.new_purchase`: تسجيل فاتورة شراء / New Purchase Invoice
* `purchases.supplier_po_section`: بيانات المورد وأمر الشراء / Supplier & PO Details
* `purchases.supply_items_section`: بنود وأصناف التوريد / Supply Items & Quantities
* `purchases.confirm_and_supply_btn`: حفظ واعتماد الفاتورة وتوريد المخزون / Save & Confirm Purchase Supply

---

## 7. سجل الاختبارات والتحقق (Test Results)

* ✅ **Playwright E2E Test:** `e2e/flows/create-purchase-full-page-audit.spec.js` -> نجاح 7/7 اختبارات عبر كافة مقاسات الشاشات الـ 5 بدون أي خطأ Console.
* ✅ **Build Verification:** `npm run build` -> تم البناء بنجاح 100% في 4.16 ثانية.
