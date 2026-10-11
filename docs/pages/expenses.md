# 💸 وثيقة المكون والصفحة: إدارة المصروفات العامة والتكاليف التشغيلية (`ExpensesView.vue`)

> **المسار (Route):** `/expenses`  
> **الملف الرئيسي:** `resources/js/views/Expenses/ExpensesView.vue` (Thin Orchestrator: ~75 سطر)  
> **تاريخ المراجعة الشاملة:** 2026-08-24  
> **الحالة:** ✅ مكتملة وموثقة 100% عبر المحاور الأربعة المتزامنة.

---

## 1. التحليل التشغيلي والمعماري (Operational & Architectural Analysis)

### 🎯 الغرض من الصفحة:
تُمثل شاشة **إدارة المصروفات العامة والتكاليف التشغيلية (Expenses & Operational Cost Centers)** الأداة المركزية لضبط التدفقات النقدية الخارجة وقيد المصاريف:
1. **تسجيل المصروفات والخدمات الإضافية:** تسجيل كافة المصروفات (إيجارات، مرافق، رواتب، وقود وصيانة، بوفيه وضيافة، شحن، ومصاريف تشغيلية عامة).
2. **ربط المصروفات بمراكز التكلفة:** توجيه كل مصروف لمركز التكلفة الخاص به (`cost_center`) لتمكين التحليل المالي وحساب صافي الأرباح في قائمة الدخل بدقة.
3. **متابعة السيولة والخزينة:** بطاقات KPI إحصائية حية لحساب إجمالي مصروفات الشهر الحالي (`total_month`)، المصروفات النقدية المسحوبة من درج الكاشير (`total_cash`)، وإجمالي المصروفات المحددة بالفلتر (`total_filtered`).
4. **التصفية المتعددة:** تصفية فورية حسب مراكز التكلفة، البحث بالبيان، النطاق الزمني (`from_date`, `to_date`)، والأقراص السريعة للتصنيفات (`quick_categories`).
5. **تعدد قنوات الصرف:** دعم قنوات الصرف المختلفة (نقداً من الدرج، إنستاباي، محفظة إلكترونية، فيزا، تحويل بنكي، وشيك).
6. **إضافة وتعديل وحذف المصروفات:** نوافذ مودال موحدة وسريعة عبر `AppModal.vue` و `BaseButton.vue` مع تسجيل سندات الصرف في الخزينة والوردية النشطة.

---

## 2. هيكلية وشجرة المكونات (Component Tree & Architecture)

تم تفكيك الصفحة من ملف ضخم بـ 628 سطرًا إلى **Thin Orchestrator** ومكونات أحادية المسؤولية:

```text
resources/js/
├── views/Expenses/
│   └── ExpensesView.vue                         <-- Thin Orchestrator (~75 lines)
├── Components/Expenses/
│   ├── ExpensesMetricsGrid.vue                  <-- بطاقات المؤشرات المالية لمصروفات الشهر والنقدي والفترة
│   ├── ExpensesFilterBar.vue                    <-- شريط البحث ومراكز التكلفة والتواريخ وأقراص التصنيفات السريعة
│   ├── ExpensesTable.vue                        <-- جدول المصروفات المزدوج (Desktop Table + Mobile Cards)
│   └── ExpenseFormModal.vue                     <-- نافذة إضافة وتعديل المصروف
└── Composables/
    └── useExpenses.js                           <-- كبسولة المنطق الحسابي والاتصال بالـ API وإدارة المودال
```

---

## 3. عناصر النماذج والواجهات المشتركة المستخدمة

* `PageHeader.vue`: ترويسة الصفحة وزر إضافة مصروف جديد.
* `BaseSearchInput.vue`: شريط البحث النصي المتجاوب.
* `BaseSelect.vue`: قوائم اختيار مراكز التكلفة.
* `BaseInput.vue`: حقول إدخال التواريخ والنصوص.
* `BaseButton.vue`: أزرار الإجراءات والحفظ مع مؤشرات التحميل.
* `StatCardSkeleton.vue`: هياكل تحميل بطاقات المؤشرات الوميضية.
* `TableSkeleton.vue`: هيكل التحميل الوميضي للجدول.
* `EmptyState.vue`: حالة عدم وجود مصروفات مطابقة.
* `AppModal.vue`: الحاوية الموحدة لنافذة إضافة/تعديل المصروف.

---

## 4. الاعتماديات والـ APIs المرتبطة

| العملية | الـ Endpoint | الطلب (Request Payload) | الاستجابة |
| :--- | :--- | :--- | :--- |
| **جلب قائمة المصروفات** | `GET /api/v1/expenses` | `search`, `cost_center`, `category`, `from`, `to`, `page`, `per_page` | بيانات المصروفات + ملخص `summary` + مراكز التكلفة + `meta` |
| **إضافة مصروف جديد** | `POST /api/v1/expenses` | `title`, `category`, `cost_center`, `amount`, `expense_date`, `payment_method`, `notes` | رسالة نجاح وكائن المصروف وسند الصرف |
| **تعديل بيانات مصروف** | `PUT /api/v1/expenses/{id}` | `title`, `category`, `cost_center`, `amount`, `expense_date`, `payment_method`, `notes` | رسالة نجاح وتحديث البيانات |
| **حذف مصروف** | `DELETE /api/v1/expenses/{id}` | - | نقل المصروف لسلة المحذوفات وتحديث الخزينة |

---

## 5. فحص التجاوب وتجربة اللمس والوضعين (Responsive & Touch Ergonomics)

* **📱 هواتف (360px - 430px):**
  * تراص بطاقات المصروفات بوضوح، إظهار العنوان ومركز التكلفة والتصنيف وقناة الدفع والمبلغ باللون الوردي، مع أزرار لمس مريحة للإبهام للتعديل والحذف بارتفاع $\ge 44	ext{px}$.
* **💻 تابلت وديسكتوب (768px - 1280px+):**
  * جدول بيانات مالي عالي الكثافة مع تمييز لوني واضح للمبالغ وقنوات الصرف.
* **🌓 الوضع الداكن والفاتح:** تباين كامل للبطاقات والصفوف وحقول الإدخال.

---

## 6. قاموس الترجمة (100% Zero Hardcoded Localization)

كافة النصوص تستند إلى ملفات الترجمة المركزية في `lang/ar/expenses.php` و `lang/en/expenses.php`:
* `expenses.title`: المصاريف والخدمات الإضافية / Expenses & Additional Services
* `expenses.total_month_expenses`: إجمالي مصروفات هذا الشهر / Total Month Expenses
* `expenses.cash_expenses`: مصروفات كاش من الدرج / Cash Expenses from Drawer
* `expenses.add_expense`: إضافة مصروف / Add Expense
* `expenses.all_cost_centers`: كافة مراكز التكلفة / All Cost Centers

---

## 7. سجل الاختبارات والتحقق (Test Results)

* ✅ **Playwright E2E Test:** `e2e/flows/expenses-full-page-audit.spec.js` -> نجاح 7/7 اختبارات عبر كافة مقاسات الشاشات الـ 5 بدون أي خطأ Console.
* ✅ **Build Verification:** `npm run build` -> تم البناء بنجاح 100% في 4.58 ثانية.
