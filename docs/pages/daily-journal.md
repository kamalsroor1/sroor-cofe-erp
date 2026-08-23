# 📖 وثيقة المكون والصفحة: دفتر اليومية وحركات الخزينة والورديات (`DailyJournalView.vue`)

> **المسار (Route):** `/daily-journal`  
> **الملف الرئيسي:** `resources/js/views/DailyJournal/DailyJournalView.vue` (Thin Orchestrator: ~75 سطر)  
> **تاريخ المراجعة الشاملة:** 2026-08-24  
> **الحالة:** ✅ مكتملة وموثقة 100% عبر المحاور الأربعة المتزامنة.

---

## 1. التحليل التشغيلي والمعماري (Operational & Architectural Analysis)

### 🎯 الغرض من الصفحة:
تُمثل شاشة **دفتر اليومية وحركات الخزينة والورديات (Daily Journal & Cash Shifts Ledger)** مركز التحكم المالي اليومي للتدفقات النقدية ومطابقة رصيد الدرج:
1. **دفتر اليومية الموحد:** تتبع ومطابقة كافة المقبوضات (فواتير مبيعات، تحصيلات عملاء) والمصروفات (مصروفات تشغيلية، سداد موردين) لتاريخ محدد.
2. **إدارة ورديات الكاشير (Shift Control & Z-Report):** فتح الوردية وتحديد رصيد البداية الافتتاحي (Float/Opening Cash)، وإغلاق الوردية وحساب العجز أو الزيادة (Drawer Shortage/Overage) فورياً ومقارنة النقدية الفعلية مع المتوقعة.
3. **بطاقات المؤشرات والسيولة الحية:** إجمالي المقبوضات النقدية (+Total Inflow)، إجمالي المدفوعات والمصروفات (-Total Outflow)، صافي التدفق اليومي (Net Cash Flow)، والنقدية المتوقعة في الدرج (Expected Cash in Drawer).
4. **تبويبات المقبوضات والمصروفات التفاعلية:** تصفح فواتير المبيعات الصادرة لليوم ومصروفات وسندات الصرف بنقرة واحدة مع شارات طرق الدفع والحالة.
5. **تسجيل المصروف السريع من الدرج (Quick Journal Expense Modal):** قيد فوري لأي مصروف نثري أو نولون مباشر وتحديث حسابات الوردية والدرج.

---

## 2. هيكلية وشجرة المكونات (Component Tree & Architecture)

تم تفكيك الصفحة من ملف ضخم بـ 724 سطرًا إلى **Thin Orchestrator** ومكونات أحادية المسؤولية:

```text
resources/js/
├── views/DailyJournal/
│   └── DailyJournalView.vue                     <-- Thin Orchestrator (~75 lines)
├── Components/DailyJournal/
│   ├── DailyJournalShiftBanner.vue              <-- شريط الوردية النشطة وطباعة Z-Report أو تنبيه الفتح
│   ├── DailyJournalMetricsGrid.vue              <-- بطاقات التدفق النقدي (الوارد، المنصرف، الصافي، رصيد الدرج)
│   ├── DailyJournalTabs.vue                     <-- تبويبات وجداول الفواتير والمصروفات (Desktop + Mobile Cards)
│   ├── OpenShiftModal.vue                       <-- نافذة فتح الوردية وإدخال العهدة الافتتاحية
│   ├── CloseShiftModal.vue                      <-- نافذة إغلاق الوردية وحساب العجز/الزيادة Z-Report
│   └── QuickExpenseModal.vue                    <-- نافذة تسجيل مصروف سريع من اليومية
└── Composables/
    └── useDailyJournal.js                       <-- كبسولة المنطق والاتصال بالـ API وإدارة الورديات
```

---

## 3. عناصر النماذج والواجهات المشتركة المستخدمة

* `PageHeader.vue`: ترويسة الصفحة، فلتر التاريخ، وزر فتح/إغلاق الوردية.
* `BaseButton.vue`: أزرار الإجراءات وفتح/إغلاق الوردية مع مؤشرات التحميل.
* `BaseNumberInput.vue`: حقول إدخال النقدية الفعلية للعد والجرد.
* `StatCardSkeleton.vue`: هياكل تحميل بطاقات المؤشرات الوميضية.
* `TableSkeleton.vue`: هيكل التحميل الوميضي للجداول.
* `EmptyState.vue`: حالة عدم وجود فواتير أو مصروفات لتاريخ محدد.
* `AppModal.vue`: الحاوية الموحدة لنوافذ الورديات والمصروفات.

---

## 4. الاعتماديات والـ APIs المرتبطة

| العملية | الـ Endpoint | الطلب (Request Payload) | الاستجابة |
| :--- | :--- | :--- | :--- |
| **جلب بيانات اليومية والوردية** | `GET /api/v1/daily-journal` | `date` | الوردية النشطة + ملخص السيولة + فواتير ومصروفات اليوم |
| **فتح وردية جديدة** | `POST /api/v1/shifts/open` | `opening_cash_balance`, `notes` | تسجيل بداية الوردية وربط الكاشير والدرج |
| **إغلاق الوردية واعتماد Z-Report** | `POST /api/v1/shifts/close` | `shift_id`, `actual_cash_balance`, `notes` | تقفيل الوردية وحساب الفارق واعتماد Z-Report |
| **قيد مصروف سريع** | `POST /api/v1/expenses` | `title`, `amount`, `cost_center`, `payment_method`, `category`, `expense_date` | تسجيل سند الصرف وخصم المبلغ من الدرج |
| **طباعة تقرير Z-Report** | `GET /api/v1/shifts/{id}/z-report` | - | بيانات التقرير المالي الشامل للوردية |

---

## 5. فحص التجاوب وتجربة اللمس والوضعين (Responsive & Touch Ergonomics)

* **📱 هواتف (360px - 430px):**
  * تراص بطاقات الوردية والسيولة، بطاقات لمسية متراصة لحركات الفواتير والمصروفات، مع أزرار لمس مريحة للإبهام لفتح وإغلاق الوردية بارتفاع $\ge 44	ext{px}$.
* **💻 تابلت وديسكتوب (768px - 1280px+):**
  * جداول بيانات مالية محاسبية عالية الكثافة مع تمييز لوني واضح للقيم الموجبة والسالبة.
* **🌓 الوضع الداكن والفاتح:** تباين كامل للبطاقات والصفوف وحقول الإدخال.

---

## 6. قاموس الترجمة (100% Zero Hardcoded Localization)

كافة النصوص تستند إلى ملفات الترجمة المركزية في `lang/ar/treasury.php` و `lang/en/treasury.php`:
* `treasury.journal_title`: يومية المبيعات وحركة الخزينة / Sales & Treasury Daily Journal
* `treasury.open_shift`: فتح وردية / Open Shift
* `treasury.close_shift`: تقفيل الوردية (Z-Report) / Close Shift
* `treasury.expected_drawer_balance`: الرصيد المتوقع بالدرج / Expected Drawer Cash
* `treasury.exact_match_no_diff`: مطابقة تماماً بدون عجز أو زيادة ✓ / Exact Match (No Difference)

---

## 7. سجل الاختبارات والتحقق (Test Results)

* ✅ **Playwright E2E Test:** `e2e/flows/daily-journal-full-page-audit.spec.js` -> نجاح 7/7 اختبارات عبر كافة مقاسات الشاشات الـ 5 بدون أي خطأ Console.
* ✅ **Build Verification:** `npm run build` -> تم البناء بنجاح 100% في 4.58 ثانية.
