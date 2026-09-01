# 📑 وثيقة المكون والصفحة: كشف حساب وأستاذ المورد (`SupplierStatementView.vue`)

> **المسار (Route):** `/suppliers/:id/statement`  
> **الملف الرئيسي:** `resources/js/views/Suppliers/SupplierStatementView.vue` (Thin Orchestrator: ~50 سطر)  
> **تاريخ المراجعة الشاملة:** 2026-08-24  
> **الحالة:** ✅ مكتملة وموثقة 100% عبر المحاور الأربعة المتزامنة.

---

## 1. التحليل التشغيلي والمعماري (Operational & Architectural Analysis)

### 🎯 الغرض من الصفحة:
تُمثل شاشة **كشف حساب وأستاذ المورد (Supplier Ledger & Account Statement)** الأداة المحاسبية التفصيلية لمتابعة حركة التعاملات مع الموردين:
1. **استعراض الحركات المالية التفصيلية:** عرض كافة العمليات المسجلة على حساب المورد (فواتير مشتريات وتوريد، سندات صرف وسداد، مرتجعات، وأرصدة افتتاحية).
2. **المؤشرات المالية للفترة المحددة:** بطاقات تلخيصية تبين إجمالي المشتريات (دائن / مسحوبات)، إجمالي المسدد (مدين / سندات صرف)، والرصيد الختامي المستحق للمورد (`closing_balance`).
3. **الفلترة الزمنية والأزرار السريعة:** تصفية مخصصة للتواريخ (`from_date`, `to_date`) مع أزرار سريعة للأطر الزمنية (اليوم، هذا الشهر، هذا العام، كافة الحركات).
4. **تتبع الرصيد التراكمي:** احتساب الرصيد المتبقي بعد كل حركة لحظياً (`balance_after`).
5. **دعم الطباعة والتصدير:** إمكانية طباعة كشف الحساب بضغطة زر وتنسيق مهيأ للطباعة مع إخفاء الأزرار وأدوات الفلترة غير الطباعية (`no-print`).

---

## 2. هيكلية وشجرة المكونات (Component Tree & Architecture)

تم تفكيك الصفحة من ملف ضخم إلى **Thin Orchestrator** ومكونات أحادية المسؤولية:

```text
resources/js/
├── views/Suppliers/
│   └── SupplierStatementView.vue                <-- Thin Orchestrator (~50 lines)
├── Components/Suppliers/
│   ├── SupplierStatementHeader.vue              <-- ترويسة الصفحة وزر العودة وزر الطباعة
│   ├── SupplierStatementSummaryCards.vue        <-- بطاقات المؤشرات المالية لإجمالي المشتريات والمسدد والرصيد
│   ├── SupplierStatementFilterBar.vue           <-- شريط فلترة التواريخ والأزرار السريعة
│   └── SupplierStatementTable.vue               <-- جدول الأستاذ المالي المزدوج (Desktop Table + Mobile Cards)
└── Composables/
    └── useSupplierStatement.js                  <-- كبسولة المنطق الحسابي والاتصال بالـ API والطباعة
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
| **جلب كشف حساب المورد** | `GET /api/v1/suppliers/{id}/statement` | `from_date`, `to_date` | بيانات المورد + جدول الحركات `ledger` + ملخص الفترة `summary` |

---

## 5. فحص التجاوب وتجربة اللمس والوضعين (Responsive & Touch Ergonomics)

* **📱 هواتف (360px - 430px):**
  * تراص بطاقات الحركات المالية بوضوح، إظهار نوع الحركة ورقم الفاتورة/السند، المبالغ الدائنة والمدينة، والرصيد التراكمي بعد الحركة، مع أزرار لمس مريحة للإبهام بارتفاع $\ge 44	ext{px}$.
* **💻 تابلت وديسكتوب (768px - 1280px+):**
  * جدول أستاذ محاسبي عالي الكثافة مع تمييز لوني واضح للمبالغ الدائنة والمدينة.
* **🌓 الوضع الداكن والفاتح:** تباين كامل للبطاقات والصفوف وحقول الإدخال.

---

## 6. قاموس الترجمة (100% Zero Hardcoded Localization)

كافة النصوص تستند إلى ملفات الترجمة المركزية في `lang/ar/contacts.php` و `lang/en/contacts.php`:
* `contacts.supplier_ledger_title`: كشف حساب المورد / Supplier Account Statement
* `contacts.supplier_ledger_subtitle`: سجل التوريدات والمشتريات وسندات الصرف والدفعات / Complete Ledger History
* `contacts.total_purchases_label`: إجمالي المشتريات / Total Purchases
* `contacts.total_payments_label`: إجمالي المدفوعات / Total Payments
* `contacts.closing_balance`: الرصيد الختامي المستحق / Closing Balance

---

## 7. سجل الاختبارات والتحقق (Test Results)

* ✅ **Playwright E2E Test:** `e2e/flows/supplier-statement-full-page-audit.spec.js` -> نجاح 7/7 اختبارات عبر كافة مقاسات الشاشات الـ 5 بدون أي خطأ Console.
* ✅ **Build Verification:** `npm run build` -> تم البناء بنجاح 100% في 4.08 ثانية.
