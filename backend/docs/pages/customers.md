# 👥 وثيقة المكون والصفحة: دليل وإدارة العملاء والزبائن (`CustomersView.vue`)

> **المسار (Route):** `/customers`  
> **الملف الرئيسي:** `resources/js/views/Customers/CustomersView.vue` (Thin Orchestrator: ~70 سطر)  
> **تاريخ المراجعة الشاملة:** 2026-08-24  
> **الحالة:** ✅ مكتملة وموثقة 100% عبر المحاور الأربعة المتزامنة.

---

## 1. التحليل التشغيلي والمعماري (Operational & Architectural Analysis)

### 🎯 الغرض من الصفحة:
تُمثل شاشة **دليل وإدارة العملاء والزبائن (Customers Management & Receivables Ledger)** القلب النابض لإدارة حسابات مبيعات الآجل وسندات التحصيل وقاعدة عملاء المؤسسة:
1. **سجل بيانات العملاء:** حفظ واستعراض بيانات العملاء (الاسم، رقم الهاتف، العنوان، الرقم الضريبي، والرصيد الافتتاحي).
2. **متابعة المديونيات والتحصيل:** بطاقات KPI إحصائية حية لحساب إجمالي المديونيات المطلوبة من العملاء (`total_debt`)، عدد العملاء المدينين (`debtors_count`)، وإجمالي العملاء المسجلين (`total_customers`).
3. **تصفية حسابات الآجل والمسواة:** فلترة فورية للعملاء (الكل، عليهم مديونية 🚨، الحسابات المسددة بالكامل ✅، أو العملاء الدائنون 💳).
4. **تحصيل الدفعات وسندات القبض (Collect Payment Modal):** إمكانية تحصيل دفعات نقدية أو إلكترونية (إنستاباي / محفظة / بنك) مباشرة من الجدول وتحديث رصيد العميل وتسجيل سند القبض في الخزينة والوردية النشطة.
5. **الانتقال لكشف الحساب (Customer Statement Ledger):** زر مباشر لاستعراض كشف الحساب التفصيلي وتتبع الفواتير وسندات التحصيل والمرتجعات لكل عميل (`/customers/:id/statement`).
6. **إضافة وتعديل وحذف العملاء:** نوافذ مودال موحدة وسريعة عبر `AppModal.vue` و `BaseButton.vue` مع حماية الحسابات التي تحتوي على حركات من الحذف العشوائي.

---

## 2. هيكلية وشجرة المكونات (Component Tree & Architecture)

تم تفكيك الصفحة من ملف ضخم بـ 751 سطرًا إلى **Thin Orchestrator** ومكونات أحادية المسؤولية:

```text
resources/js/
├── views/Customers/
│   └── CustomersView.vue                        <-- Thin Orchestrator (~70 lines)
├── Components/Customers/
│   ├── CustomersMetricsGrid.vue                 <-- بطاقات المؤشرات المالية لمديونيات العملاء والمدينين
│   ├── CustomersFilterBar.vue                   <-- شريط البحث النصي وأقراص تصفية حالة المديونية
│   ├── CustomersTable.vue                       <-- جدول البيانات المزدوج (Desktop Table + Mobile Cards)
│   ├── CustomerFormModal.vue                    <-- نافذة إضافة وتعديل بيانات العميل
│   └── CustomerPaymentModal.vue                 <-- نافذة تحصيل وتسجيل دفعة وسند قبض من العميل
└── Composables/
    └── useCustomers.js                          <-- كبسولة المنطق والاتصال بالـ API وإدارة النوافذ
```

---

## 3. عناصر النماذج والواجهات المشتركة المستخدمة

* `PageHeader.vue`: ترويسة الصفحة وزر إضافة عميل جديد.
* `BaseSearchInput.vue`: شريط البحث النصي المتجاوب.
* `BaseButton.vue`: أزرار الإجراءات والحفظ مع مؤشرات التحميل.
* `StatCardSkeleton.vue`: هياكل تحميل بطاقات المؤشرات الوميضية.
* `TableSkeleton.vue`: هيكل التحميل الوميضي للجدول.
* `EmptyState.vue`: حالة عدم وجود عملاء أو نتائج مطابقة.
* `AppModal.vue`: الحاوية الموحدة لنوافذ الإدخال والتحصيل.

---

## 4. الاعتماديات والـ APIs المرتبطة

| العملية | الـ Endpoint | الطلب (Request Payload) | الاستجابة |
| :--- | :--- | :--- | :--- |
| **جلب قائمة العملاء** | `GET /api/v1/customers` | `search`, `balance_type`, `page`, `per_page` | بيانات العملاء + إحصائيات `metrics` + `meta` |
| **إضافة عميل جديد** | `POST /api/v1/customers` | `name`, `phone`, `address`, `tax_number`, `opening_balance`, `notes` | رسالة نجاح وكائن العميل |
| **تعديل بيانات عميل** | `PUT /api/v1/customers/{id}` | `name`, `phone`, `address`, `tax_number`, `notes` | رسالة نجاح وتحديث البيانات |
| **تحصيل دفعة من العميل** | `POST /api/v1/customers/{id}/payments` | `amount`, `payment_method`, `payment_date`, `notes` | تسجيل سند القبض وتحديث رصيد العميل |
| **حذف عميل** | `DELETE /api/v1/customers/{id}` | - | حذف السجل أو رفض إذا كان يحتوي رصيد |

---

## 5. فحص التجاوب وتجربة اللمس والوضعين (Responsive & Touch Ergonomics)

* **📱 هواتف (360px - 430px):**
  * تراص بطاقات العملاء التفاعلية بوضوح، إظهار الاسم ورقم الهاتف والعنوان والرصيد المستحق، مع أزرار لمس مريحة للإبهام للتحصيل وكشف الحساب والتعديل والحذف بارتفاع $\ge 44	ext{px}$.
* **💻 تابلت وديسكتوب (768px - 1280px+):**
  * جدول بيانات عالي الكثافة مع تمييز لوني واضح للحسابات المدينة والدائنة وأرقام الهواتف ومبالغ العملة.
* **🌓 الوضع الداكن والفاتح:** تباين كامل للبطاقات والصفوف وحقول الإدخال.

---

## 6. قاموس الترجمة (100% Zero Hardcoded Localization)

كافة النصوص تستند إلى ملفات الترجمة المركزية في `lang/ar/contacts.php` و `lang/en/contacts.php`:
* `contacts.customers_title`: دليل العملاء والزبائن / Customers Directory
* `contacts.total_receivables`: إجمالي مديونيات العملاء المطلوبة / Total Outstanding Customer Debts
* `contacts.debtors_count`: عدد العملاء المدينين / Debtor Customers Count
* `contacts.collect_payment`: تحصيل / Collect
* `contacts.debtors_only`: عليهم مديونية 🚨 / With Outstanding Debt 🚨
* `contacts.settled_only`: الحسابات المسواة (رصيد 0) ✅ / Settled Accounts (0.00) ✅

---

## 7. سجل الاختبارات والتحقق (Test Results)

* ✅ **Playwright E2E Test:** `e2e/flows/customers-full-page-audit.spec.js` -> نجاح 7/7 اختبارات عبر كافة مقاسات الشاشات الـ 5 بدون أي خطأ Console.
* ✅ **Build Verification:** `npm run build` -> تم البناء بنجاح 100% في 4.15 ثانية.
