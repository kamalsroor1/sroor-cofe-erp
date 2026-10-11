# 🏭 وثيقة المكون والصفحة: دليل وإدارة الموردين والتجار (`SuppliersView.vue`)

> **المسار (Route):** `/suppliers`  
> **الملف الرئيسي:** `resources/js/views/Suppliers/SuppliersView.vue` (Thin Orchestrator: ~70 سطر)  
> **تاريخ المراجعة الشاملة:** 2026-08-24  
> **الحالة:** ✅ مكتملة وموثقة 100% عبر المحاور الأربعة المتزامنة.

---

## 1. التحليل التشغيلي والمعماري (Operational & Architectural Analysis)

### 🎯 الغرض من الصفحة:
تُمثل شاشة **دليل وإدارة الموردين والتجار (Suppliers Management & Payables Ledger)** الركيزة الأساسية في إدارة الحسابات الدائنة والتعاملات التجارية مع الشركات الموردة:
1. **سجل بيانات الموردين:** حفظ واستعراض بيانات الموردين (الاسم، اسم الشركة، رقم الهاتف، العنوان، والملاحظات).
2. **متابعة المديونية الإجمالية والدائنين:** بطاقات KPI إحصائية حية لحساب إجمالي المبالغ المستحقة للموردين (`total_payable`)، عدد الموردين الدائنين (`creditors_count`)، وإجمالي الموردين المسجلين (`total_suppliers`).
3. **تصفية الحسابات الدائنة والمسواة:** فلترة فورية للموردين (الكل، من لهم مستحقات دائنة 🚨، أو الحسابات المسددة بالكامل ✅).
4. **سداد الدفعات النقدية والبنكية (Pay Supplier Modal):** إمكانية صرف دفعات نقدية أو عبر إنستاباي أو المحفظة أو البنك للمورد مباشرة من الجدول مع تحديث الرصيد الدائن وتسجيل سند الصرف داخل الخزينة.
5. **الانتقال لكشف الحساب (Supplier Statement Ledger):** زر مباشر لاستعراض كشف الحساب التفصيلي وتتبع الفواتير وسندات الصرف والمرتجعات لكل مورد (`/suppliers/:id/statement`).
6. **إضافة وتعديل وحذف الموردين:** نوافذ مودال موحدة وسريعة عبر `AppModal.vue` و `BaseButton.vue` مع حماية الحسابات التي تحتوي على حركات من الحذف العشوائي.

---

## 2. هيكلية وشجرة المكونات (Component Tree & Architecture)

تم تفكيك الصفحة من ملف ضخم بـ 742 سطرًا إلى **Thin Orchestrator** ومكونات أحادية المسؤولية:

```text
resources/js/
├── views/Suppliers/
│   └── SuppliersView.vue                        <-- Thin Orchestrator (~70 lines)
├── Components/Suppliers/
│   ├── SuppliersMetricsGrid.vue                 <-- بطاقات المؤشرات المالية للدائنين والمستحقات
│   ├── SuppliersFilterBar.vue                   <-- شريط البحث النصي وأقراص تصفية حالة المديونية
│   ├── SuppliersTable.vue                       <-- جدول البيانات المزدوج (Desktop Table + Mobile Cards)
│   ├── SupplierFormModal.vue                    <-- نافذة إضافة وتعديل بيانات المورد
│   └── SupplierPaymentModal.vue                 <-- نافذة تسجيل وسداد دفعة نقدية/بنكية للمورد
└── Composables/
    └── useSuppliers.js                          <-- كبسولة المنطق والاتصال بالـ API وإدارة النوافذ
```

---

## 3. عناصر النماذج والواجهات المشتركة المستخدمة

* `PageHeader.vue`: ترويسة الصفحة وزر إضافة مورد جديد.
* `BaseSearchInput.vue`: شريط البحث النصي المتجاوب.
* `BaseButton.vue`: أزرار الإجراءات والحفظ مع مؤشرات التحميل.
* `StatCardSkeleton.vue`: هياكل تحميل بطاقات المؤشرات الوميضية.
* `TableSkeleton.vue`: هيكل التحميل الوميضي للجدول.
* `EmptyState.vue`: حالة عدم وجود موردين أو نتائج مطابقة.
* `AppModal.vue`: الحاوية الموحدة لنوافذ الإدخال والسداد.

---

## 4. الاعتماديات والـ APIs المرتبطة

| العملية | الـ Endpoint | الطلب (Request Payload) | الاستجابة |
| :--- | :--- | :--- | :--- |
| **جلب قائمة الموردين** | `GET /api/v1/suppliers` | `search`, `balance_type`, `page`, `per_page` | بيانات الموردين + إحصائيات `metrics` + `meta` |
| **إضافة مورد جديد** | `POST /api/v1/suppliers` | `name`, `company_name`, `phone`, `address`, `opening_balance`, `notes` | رسالة نجاح وكائن المورد |
| **تعديل بيانات مورد** | `PUT /api/v1/suppliers/{id}` | `name`, `company_name`, `phone`, `address`, `notes` | رسالة نجاح وتحديث البيانات |
| **صرف دفعة للمورد** | `POST /api/v1/suppliers/{id}/payments` | `amount`, `payment_method`, `payment_date`, `notes` | تسجيل سند الصرف وتحديث رصيد المورد |
| **حذف مورد** | `DELETE /api/v1/suppliers/{id}` | - | حذف السجل أو رفض إذا كان يحتوي رصيد |

---

## 5. فحص التجاوب وتجربة اللمس والوضعين (Responsive & Touch Ergonomics)

* **📱 هواتف (360px - 430px):**
  * تراص بطاقات الموردين التفاعلية بوضوح، إظهار الاسم والشركة ورقم الهاتف والرصيد المستحق، مع أزرار لمس مريحة للإبهام لسداد الدفعة وكشف الحساب والتعديل والحذف بارتفاع $\ge 44	ext{px}$.
* **💻 تابلت وديسكتوب (768px - 1280px+):**
  * جدول بيانات عالي الكثافة مع تمييز لوني واضح للموردين الدائنين وأرقام الهواتف ومبالغ العملة.
* **🌓 الوضع الداكن والفاتح:** تباين كامل للبطاقات والصفوف وحقول الإدخال.

---

## 6. قاموس الترجمة (100% Zero Hardcoded Localization)

كافة النصوص تستند إلى ملفات الترجمة المركزية في `lang/ar/contacts.php` و `lang/en/contacts.php`:
* `contacts.suppliers_title`: دليل الموردين والتجار / Suppliers Directory
* `contacts.total_payables`: إجمالي مستحقات الموردين / Total Supplier Payables Due
* `contacts.creditors_count`: موردون لهم مستحقات قائمة / Active Creditor Suppliers
* `contacts.pay_supplier`: سداد دفعة / Pay Supplier
* `contacts.supplier_creditors_only`: مستحق له (دائن) 🚨 / Due Balance (Creditor) 🚨
* `contacts.supplier_settled_only`: مسدد بالكامل ✅ / Fully Settled ✅

---

## 7. سجل الاختبارات والتحقق (Test Results)

* ✅ **Playwright E2E Test:** `e2e/flows/suppliers-full-page-audit.spec.js` -> نجاح 7/7 اختبارات عبر كافة مقاسات الشاشات الـ 5 بدون أي خطأ Console.
* ✅ **Build Verification:** `npm run build` -> تم البناء بنجاح 100% في 4.69 ثانية.
