# 📑 وثيقة المكون والصفحة: كشف حساب وأستاذ العميل (`CustomerStatementView.vue`)

> **المسار (Route):** `/customers/:id/statement`  
> **الملف الرئيسي:** `resources/js/views/Customers/CustomerStatementView.vue` (Thin Orchestrator: ~50 سطر)  
> **تاريخ المراجعة الشاملة:** 2026-08-24  
> **الحالة:** ✅ مكتملة وموثقة 100% عبر المحاور الأربعة المتزامنة.

---

## 1. التحليل التشغيلي والمعماري (Operational & Architectural Analysis)

### 🎯 الغرض من الصفحة:
تُمثل شاشة **كشف حساب وأستاذ العميل (Customer Ledger & Account Statement)** الأداة المحاسبية والمالية المتخصصة لتتبع ومراجعة تعاملات ومسحوبات وسدادات العميل:
1. **استعراض الحركات المالية التفصيلية:** عرض كافة العمليات المسجلة على حساب العميل (فواتير مبيعات آجلة، سندات قبض وتحصيل، مرتجعات مبيعات، وأرصدة افتتاحية).
2. **المؤشرات المالية للفترة المحددة:** بطاقات تلخيصية تبين إجمالي المسحوبات (مدين / فواتير آجل)، إجمالي السدادات (دائن / سندات قبض)، والرصيد الختامي المستحق على العميل (`closing_balance`).
3. **الفلترة الزمنية والأزرار السريعة:** تصفية مخصصة للتواريخ (`from_date`, `to_date`) مع أزرار سريعة للأطر الزمنية (اليوم، هذا الشهر، هذا العام، كافة الحركات).
4. **تتبع الرصيد التراكمي:** احتساب الرصيد المتبقي بعد كل حركة لحظياً (`balance_after`) مع التمييز اللوني للحسابات المدينة والدائنة والمسواة.
5. **دعم الطباعة والتصدير:** إمكانية طباعة كشف الحساب بضغطة زر وتنسيق مهيأ للطباعة مع إخفاء الأزرار وأدوات الفلترة غير الطباعية (`no-print`).

---

## 2. هيكلية وشجرة المكونات (Component Tree & Architecture)

تم تفكيك الصفحة من ملف ضخم إلى **Thin Orchestrator** ومكونات أحادية المسؤولية:

```text
resources/js/
├── views/Customers/
│   └── CustomerStatementView.vue                <-- Thin Orchestrator (~50 lines)
├── Components/Customers/
│   ├── CustomerStatementHeader.vue              <-- ترويسة الصفحة وزر العودة وزر الطباعة
│   ├── CustomerStatementSummaryCards.vue        <-- بطاقات المؤشرات المالية لإجمالي المسحوبات والمسدد والرصيد
│   ├── CustomerStatementFilterBar.vue           <-- شريط فلترة التواريخ والأزرار السريعة
│   └── CustomerStatementTable.vue               <-- جدول الأستاذ المالي المزدوج (Desktop Table + Mobile Cards)
└── Composables/
    └── useCustomerStatement.js                  <-- كبسولة المنطق الحسابي والاتصال بالـ API والطباعة
```

---

## 3. عناصر النماذج والواجهات المشتركة المستخدمة

* `BaseButton.vue`: زر الطباعة وزر تطبيق الفلترة.
* `StatCardSkeleton.vue`: هياكل تحميل بطاقات المؤشرات الوميضية.
* `TableSkeleton.vue`: هيكل التحميل الوميضي لجدول الأستاذ.
* `EmptyState.vue`: حالة عدم وجود حركات مسجلة خلال الفترة.

---

## 4. الاعتماديات والـ APIs المرتبطة

| العملية | الـ Endpoint | الطلب (Request Payload) | الاستجابة |
| :--- | :--- | :--- | :--- |
| **جلب كشف حساب العميل** | `GET /api/v1/customers/{id}/statement` | `from_date`, `to_date` | بيانات العميل + جدول الحركات `ledger` + ملخص الفترة `summary` |

---

## 5. فحص التجاوب وتجربة اللمس والوضعين (Responsive & Touch Ergonomics)

* **📱 هواتف (360px - 430px):**
  * تراص بطاقات الحركات المالية بوضوح، إظهار نوع الحركة ورقم الفاتورة/السند، المبالغ المدينة والدائنة، والرصيد التراكمي بعد الحركة، مع أزرار لمس مريحة للإبهام بارتفاع $\ge 44	ext{px}$.
* **💻 تابلت وديسكتوب (768px - 1280px+):**
  * جدول أستاذ محاسبي عالي الكثافة مع تمييز لوني واضح للمبالغ المدينة والدائنة.
* **🌓 الوضع الداكن والفاتح:** تباين كامل للبطاقات والصفوف وحقول الإدخال.

---

## 6. قاموس الترجمة (100% Zero Hardcoded Localization)

كافة النصوص تستند إلى ملفات الترجمة المركزية في `lang/ar/contacts.php` و `lang/en/contacts.php`:
* `contacts.ledger_title`: كشف حساب تفصيلي / Detailed Account Statement
* `contacts.ledger_subtitle`: سجل كافة الحركات المالية من فواتير وسندات قبض وسداد ومرتجعات / Complete ledger history
* `contacts.period_debit`: إجمالي المسحوبات (مدين) / Period Debit
* `contacts.period_credit`: إجمالي المدفوعات (دائن) / Period Credit
* `contacts.closing_balance`: الرصيد الختامي المستحق / Closing Balance

---

## 7. سجل الاختبارات والتحقق (Test Results)

* ✅ **Playwright E2E Test:** `e2e/flows/customer-statement-full-page-audit.spec.js` -> نجاح 7/7 اختبارات عبر كافة مقاسات الشاشات الـ 5 بدون أي خطأ Console.
* ✅ **Build Verification:** `npm run build` -> تم البناء بنجاح 100% في 4.50 ثانية.
