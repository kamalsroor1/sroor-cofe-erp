# سجل مراجعة وتدقيق: دليل وإدارة العملاء والزبائن (`CustomersView.vue`)
* **التاريخ والوقت:** 2026-08-24 02:25
* **الدور المفعل:** Full Stack AI Squad (Frontend UI + Backend Architect + QA Testing + Docs PM)
* **الهدف:** تنفيذ بروتوكول المراجعة الشاملة للصفحة رقم 16 (Customers Directory) عبر المحاور الأربعة المتزامنة.

## 1. الملفات المعدلة والمضافة:
* `[NEW]` `resources/js/Components/Customers/CustomersMetricsGrid.vue` - بطاقات المؤشرات المالية لمديونيات العملاء والمدينين وإجمالي العملاء.
* `[NEW]` `resources/js/Components/Customers/CustomersFilterBar.vue` - شريط البحث النصي وأقراص تصفية حالة المديونية.
* `[NEW]` `resources/js/Components/Customers/CustomersTable.vue` - جدول البيانات المزدوج وبطاقات الهواتف المتراصة.
* `[NEW]` `resources/js/Components/Customers/CustomerFormModal.vue` - نافذة إضافة وتعديل بيانات العميل.
* `[NEW]` `resources/js/Components/Customers/CustomerPaymentModal.vue` - نافذة تسجيل وتحصيل دفعة وسند قبض من العميل.
* `[NEW]` `resources/js/Composables/useCustomers.js` - كبسولة المنطق الحسابي والاتصال بالـ API وإدارة المودالات.
* `[MODIFIED]` `resources/js/views/Customers/CustomersView.vue` - تحويل الصفحة لمنسق نحيف Thin Orchestrator (~70 سطر).
* `[NEW]` `e2e/flows/customers-full-page-audit.spec.js` - اختبارات Playwright E2E الشاملة عبر 5 مقاسات شاشات.
* `[NEW]` `docs/pages/customers.md` - وثيقة الصفحة المستقلة.
* `[MODIFIED]` `docs/full-page-review-log.md` - تحديث سجل المراجعة المجمع.

## 2. القرارات التقنية:
* تفكيك الـ View من 751 سطراً إلى 5 مكونات فرعية متخصصة واستخراج المنطق في Composable `useCustomers.js`.
* استخدام عناصر مكتبة النماذج والمكونات المشتركة `BaseSearchInput`, `BaseButton`, `StatCardSkeleton`, `TableSkeleton`, `EmptyState`, `AppModal`.
* توفير نمط عرض مزدوج يضمن تجربة مستخدم لمسية متكاملة وسلسة على الهواتف وجدول عالي الكثافة على الشاشات الكبيرة مع نوافذ سريعة لتحصيل الدفعات وسندات القبض.

## 3. التحقق والاختبار:
* [x] خلو الكود من الأخطاء والبناء سليم (`npm run build` بنجاح في 4.15 ثانية).
* [x] نجاح كافة اختبارات الـ Playwright E2E عبر المقاسات الـ 5 (7/7).
* [x] خلو الكود 100% من أي نصوص ثابتة وهندلة ملفات الترجمة للغتين (ar & en).
* [x] تطبيق نمط المنسق النحيف Thin Orchestrator (< 70 سطر).
* [x] التوافق مع شاشات اللمس واللغة العربية RTL والوضعين الفاتح والداكن.
