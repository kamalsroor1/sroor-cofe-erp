# 🔄 وثيقة المكون والصفحة: إنشاء وتسجيل مستند مرتجع جديد (`CreateReturnView.vue`)

> **المسار (Route):** `/returns/create`  
> **الملف الرئيسي:** `resources/js/views/Returns/CreateReturnView.vue` (Thin Orchestrator: ~65 سطر)  
> **تاريخ المراجعة الشاملة:** 2026-08-24  
> **الحالة:** ✅ مكتملة وموثقة 100% عبر المحاور الأربعة المتزامنة.

---

## 1. التحليل التشغيلي والمعماري (Operational & Architectural Analysis)

### 🎯 الغرض من الصفحة:
تُمثل شاشة **إنشاء وتسجيل مستند مرتجع مبيعات أو مشتريات (`/returns/create`)** واجهة المعالجة العكسية للمبيعات والمشتريات:
1. **تسجيل العمليات العكسية (Reverse Transactions):** اختيار نوع المرتجع (مرتجع مبيعات من عميل أو مرتجع مشتريات إلى مورد) مع ضبط آلي للأسعار (سعر البيع لمرتجع المبيعات وسعر التكلفة لمرتجع المشتريات).
2. **ربط الأطراف والتاريخ والسبب:** اختيار العميل أو المورد مع تحديد تاريخ الإرجاع وسبب الإرجاع.
3. **جدول الأصناف والكميات:** اختيار الصنف وإضافته مع تعديل الكمية وسعر الوحدة واحتساب إجمالي السطر فورياً.
4. **الملخص المالي وصرف النقدية:** عرض إجمالي القيمة وتحديد المبلغ المسترد نقداً من الدرج/الخزينة (Refund Cash) أو قيده كرصيد آجل.

---

## 2. هيكلية وشجرة المكونات (Component Tree & Architecture)

تم تفكيك الصفحة من ملف بـ 404 أسطر إلى **Thin Orchestrator** ومكونات أحادية المسؤولية:

```text
resources/js/
├── views/Returns/
│   └── CreateReturnView.vue                   <-- Thin Orchestrator (~65 lines)
├── Components/Returns/
│   ├── ReturnPartySection.vue                 <-- محول نوع المرتجع والطرف والتاريخ والسبب
│   ├── ReturnItemsTable.vue                   <-- محدد إضافة الأصناف وجدول الكميات والأسعار
│   └── ReturnFinancialSummary.vue             <-- بطاقة الملخص المالي واسترداد النقدية والاعتماد
└── Composables/
    └── useCreateReturn.js                     <-- كبسولة المنطق والاعتماديات والعمليات الحسابية
```

---

## 3. عناصر النماذج والواجهات المشتركة المستخدمة

* `PageHeader.vue`: ترويسة الصفحة وزر العودة لسجل المرتجعات.
* `BaseButton.vue`: زر الحفظ والاعتماد مع مؤشرات التحميل.
* `BaseInput.vue`: حقل سبب الإرجاع والملاحظات.

---

## 4. الاعتماديات والـ APIs المرتبطة

| العملية | الـ Endpoint | الطلب (Request Payload) | الاستجابة |
| :--- | :--- | :--- | :--- |
| **تحميل بيانات النموذج** | `GET /api/v1/customers`, `GET /api/v1/suppliers`, `GET /api/v1/items` | `per_page=100` | قوائم العملاء والموردين والأصناف |
| **حفظ واعتماد مستند المرتجع** | `POST /api/v1/returns` | `return_type`, `customer_id`, `supplier_id`, `return_date`, `refund_amount`, `reason`, `items` | إنشاء المرتجع وتعديل المخزن والحسابات |

---

## 5. فحص التجاوب وتجربة اللمس والوضعين (Responsive & Touch Ergonomics)

* **📱 هواتف (360px - 430px):**
  * ترتيب عمودي للنموذج والملخص المالي، حقول إدخال وأزرار بارتفاع $\ge 40	ext{px}$، ونمط لمسي سهل الاستخدام.
* **💻 تابلت وديسكتوب (768px - 1280px+):**
  * تخطيط 2/3 للنموذج وجدول الأصناف و 1/3 للملخص المالي الثابت.
* **🌓 الوضع الداكن والفاتح:** تباين كامل للبطاقات والصفوف وحقول الإدخال.

---

## 6. قاموس الترجمة (100% Zero Hardcoded Localization)

كافة النصوص تستند إلى ملفات الترجمة المركزية في `lang/ar/returns.php` و `lang/en/returns.php`:
* `returns.create_title`: تسجيل مستند مرتجع وإشعار تسوية مخزنية / Create Return Document
* `returns.back_to_returns`: العودة للمرتجعات / Back to Returns
* `returns.sales_return_option`: ↩️ مرتجع مبيعات (من عميل) / Sales Return (from Customer)
* `returns.purchase_return_option`: ↪️ مرتجع مشتريات (إلى مورد) / Purchase Return (to Supplier)
* `returns.confirm_return_save_btn`: حفظ واعتماد المرتجع / Confirm & Save Return

---

## 7. سجل الاختبارات والتحقق (Test Results)

* ✅ **Playwright E2E Test:** `e2e/flows/create-return-full-page-audit.spec.js` -> نجاح 7/7 اختبارات عبر كافة مقاسات الشاشات الـ 5 بدون أي خطأ Console.
* ✅ **Build Verification:** `npm run build` -> تم البناء بنجاح 100% في 2.94 ثانية.
