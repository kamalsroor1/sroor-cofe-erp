# سجل تعديل: توحيد تباين الوضع الفاتح (Light Mode Contrast Overhaul) ونظام الثيمات البصرية الكامل

* **التاريخ والوقت:** 2026-08-20 17:21
* **الدور المفعل:** Frontend / UI Agent & QA Testing Agent
* **الهدف:** حل مشكلة اختفاء وتباعد الألوان والنصوص البيضاء في الوضع الفاتح عبر كافة شاشات وإجراءات المنظومة (POS، الفواتير، الأصناف، العملاء، الموردين، المشتريات، اليومية، المصروفات، الفروع، التقارير، وتوليف القهوة)، والتأكد من دعم الثيمات اللونية والتبديل السلس للوضعين الداكن والفاتح.

---

## 1. الملفات المعدلة:
* `[MODIFIED]` `backend/resources/js/Pages/POS/Index.vue` - إعادة كتابة ترويسة نقطة البيع وشريط البحث، بطاقات الأصناف والكاتيجوري، ولوحة السلة والدفع لتعمل بسلاسة بين الوضعين الفاتح والداكن.
* `[MODIFIED]` `backend/resources/js/Components/POS/POSItemCard.vue` - ضبط تباين درجات الخطوط وأزرار الأوزان السريعة وشارات المخزون للوضع الفاتح.
* `[MODIFIED]` `backend/resources/js/Components/POS/POSCartItem.vue` - تحسين وضوح صفوف السلة وحقول الأسعار والكميات القابلة للتعديل وشارات السعر السابق.
* `[MODIFIED]` `backend/resources/js/Pages/Invoices/Index.vue` - ضبط بطاقات المؤشرات المالية الأربعة، جدول الفواتير، والأزرار السريعة للوضعين.
* `[MODIFIED]` `backend/resources/js/Pages/Items/Index.vue` - توحيد ألوان بطاقات الأصناف، الجداول، وشريط الفلاتر السريع.
* `[MODIFIED]` `backend/resources/js/Pages/Customers/Index.vue` - ضبط مصفوفة مديونيات العملاء وكشوف الحساب وسندات القبض بدقة تباين عالية.
* `[MODIFIED]` `backend/resources/js/Pages/Suppliers/Index.vue` - تحسين تباين جدول الموردين وسندات الصرف ومؤشرات الأرصدة.
* `[MODIFIED]` `backend/resources/js/Pages/Purchases/Index.vue` - ضبط جدول فواتير المشتريات وحالات الاستلام والإجمالي.
* `[MODIFIED]` `backend/resources/js/Pages/DailyJournal/Index.vue` - ضبط تباين مصفوفة حركة الدرج والخزينة وجداول المصروفات والمبيعات اليومية.
* `[MODIFIED]` `backend/resources/js/Pages/Expenses/Index.vue` - تحسين بطاقات مؤشرات المصروفات الشهرية والنقدية وجدول قيود الصرف.
* `[MODIFIED]` `backend/resources/js/Pages/Reports/Index.vue` - تحسين كامل الألسن السبعة للتقارير الشاملة (الأرباح، مصفوفة ABC، مقارنة الفروع، تدفقات الخزينة).
* `[MODIFIED]` `backend/resources/js/Pages/Stores/Index.vue` - تحسين بطاقات الفروع والمخازن وتوزيع طاقم العمل.
* `[MODIFIED]` `backend/resources/js/Pages/CoffeeBlender/Index.vue` - ضبط شاشة معمل توليف القهوة والنسب وحساب التكاليف والأوزان.
* `[MODIFIED]` `backend/resources/js/Pages/Users/Index.vue` - ضبط جدول إدارة المستخدمين والصلاحيات وحالات الحسابات.

---

## 2. القرارات التقنية:
* اعتماد معيار الـ Dual Theme الموحد:
  - الحاويات الرئيسية والبطاقات: `bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 shadow-xs`
  - حقول الإدخال والبحث: `bg-slate-50 dark:bg-slate-950/80 border border-slate-200 dark:border-slate-800 text-slate-900 dark:text-white`
  - العناوين الرئيسية: `text-slate-900 dark:text-white`
  - النصوص الثانوية والشارات: `text-slate-500 dark:text-slate-400`
  - الأزرار الفرعية: `bg-slate-100 hover:bg-slate-200 dark:bg-slate-800 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-200`
* خلو كامل الواجهات من تضارب نصوص بيضاء فوق خلفيات بيضاء في الوضع الفاتح.

---

## 3. التحقق والاختبار:
* [x] نجاح بناء الأصول البرمجية `npm run build` بنسبة 100% ودون أي أخطاء.
* [x] التوافق مع اللغة العربية RTL وشاشات اللمس والوضعين الفاتح والداكن.
* [x] الالتزام بعدم وجود نصوص ثابتة والاعتماد الكامل على مفاتيح الترجمة.
