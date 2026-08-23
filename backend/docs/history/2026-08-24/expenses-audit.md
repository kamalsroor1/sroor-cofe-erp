# سجل مراجعة وتدقيق: إدارة المصروفات والتكاليف التشغيلية (`ExpensesView.vue`)
* **التاريخ والوقت:** 2026-08-24 02:47
* **الدور المفعل:** Full Stack AI Squad (Frontend UI + Backend Architect + QA Testing + Docs PM)
* **الهدف:** تنفيذ بروتوكول المراجعة الشاملة للصفحة رقم 18 (Expenses Management) عبر المحاور الأربعة المتزامنة.

## 1. الملفات المعدلة والمضافة:
* `[NEW]` `resources/js/Components/Expenses/ExpensesMetricsGrid.vue` - بطاقات المؤشرات المالية لمصروفات الشهر والنقدي والفترة.
* `[NEW]` `resources/js/Components/Expenses/ExpensesFilterBar.vue` - شريط البحث ومراكز التكلفة والتواريخ وأقراص التصنيفات السريعة.
* `[NEW]` `resources/js/Components/Expenses/ExpensesTable.vue` - جدول المصروفات المزدوج وبطاقات الهواتف المتراصة.
* `[NEW]` `resources/js/Components/Expenses/ExpenseFormModal.vue` - نافذة إضافة وتعديل المصروف ومراكز التكلفة.
* `[NEW]` `resources/js/Composables/useExpenses.js` - كبسولة المنطق الحسابي والاتصال بالـ API وإدارة المودال.
* `[MODIFIED]` `resources/js/views/Expenses/ExpensesView.vue` - تحويل الصفحة لمنسق نحيف Thin Orchestrator (~75 سطر).
* `[NEW]` `e2e/flows/expenses-full-page-audit.spec.js` - اختبارات Playwright E2E الشاملة عبر 5 مقاسات شاشات.
* `[NEW]` `docs/pages/expenses.md` - وثيقة الصفحة المستقلة.
* `[MODIFIED]` `docs/full-page-review-log.md` - تحديث سجل المراجعة المجمع.

## 2. القرارات التقنية:
* تفكيك الـ View من 628 سطراً إلى 4 مكونات فرعية متخصصة واستخراج المنطق في Composable `useExpenses.js`.
* استخدام عناصر مكتبة النماذج والمكونات المشتركة `BaseSearchInput`, `BaseSelect`, `BaseInput`, `BaseButton`, `StatCardSkeleton`, `TableSkeleton`, `EmptyState`, `AppModal`.
* توفير نمط عرض مزدوج يضمن تجربة مستخدم لمسية متكاملة وسلسة على الهواتف وجدول عالي الكثافة على الشاشات الكبيرة مع نوافذ سريعة لقيد وتعديل المصروفات.

## 3. التحقق والاختبار:
* [x] خلو الكود من الأخطاء والبناء سليم (`npm run build` بنجاح في 4.58 ثانية).
* [x] نجاح كافة اختبارات الـ Playwright E2E عبر المقاسات الـ 5 (7/7).
* [x] خلو الكود 100% من أي نصوص ثابتة وهندلة ملفات الترجمة للغتين (ar & en).
* [x] تطبيق نمط المنسق النحيف Thin Orchestrator (< 75 سطر).
* [x] التوافق مع شاشات اللمس واللغة العربية RTL والوضعين الفاتح والداكن.
