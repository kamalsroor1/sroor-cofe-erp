# سجل مراجعة وتدقيق: دليل وإدارة الموردين والتجار (`SuppliersView.vue`)
* **التاريخ والوقت:** 2026-08-24 02:05
* **الدور المفعل:** Full Stack AI Squad (Frontend UI + Backend Architect + QA Testing + Docs PM)
* **الهدف:** تنفيذ بروتوكول المراجعة الشاملة للصفحة رقم 14 (Suppliers Directory) عبر المحاور الأربعة المتزامنة.

## 1. الملفات المعدلة والمضافة:
* `[NEW]` `resources/js/Components/Suppliers/SuppliersMetricsGrid.vue` - بطاقات المؤشرات المالية للمستحقات والدائنين وإجمالي الموردين.
* `[NEW]` `resources/js/Components/Suppliers/SuppliersFilterBar.vue` - شريط البحث النصي وأقراص تصفية حالة المديونية.
* `[NEW]` `resources/js/Components/Suppliers/SuppliersTable.vue` - جدول البيانات المزدوج وبطاقات الهواتف المتراصة.
* `[NEW]` `resources/js/Components/Suppliers/SupplierFormModal.vue` - نافذة إضافة وتعديل بيانات المورد.
* `[NEW]` `resources/js/Components/Suppliers/SupplierPaymentModal.vue` - نافذة تسجيل وسداد دفعة نقدية أو بنكية للمورد.
* `[NEW]` `resources/js/Composables/useSuppliers.js` - كبسولة المنطق الحسابي والاتصال بالـ API وإدارة المودالات.
* `[MODIFIED]` `resources/js/views/Suppliers/SuppliersView.vue` - تحويل الصفحة لمنسق نحيف Thin Orchestrator (~70 سطر).
* `[NEW]` `e2e/flows/suppliers-full-page-audit.spec.js` - اختبارات Playwright E2E الشاملة عبر 5 مقاسات شاشات.
* `[NEW]` `docs/pages/suppliers.md` - وثيقة الصفحة المستقلة.
* `[MODIFIED]` `docs/full-page-review-log.md` - تحديث سجل المراجعة المجمع.

## 2. القرارات التقنية:
* تفكيك الـ View من 742 سطراً إلى 5 مكونات فرعية متخصصة واستخراج المنطق في Composable `useSuppliers.js`.
* استخدام عناصر مكتبة النماذج والمكونات المشتركة `BaseSearchInput`, `BaseButton`, `StatCardSkeleton`, `TableSkeleton`, `EmptyState`, `AppModal`.
* توفير نمط عرض مزدوج يضمن تجربة مستخدم لمسية متكاملة وسلسة على الهواتف وجدول عالي الكثافة على الشاشات الكبيرة مع نوافذ سريعة لسداد الدفعات وتعديل البيانات.

## 3. التحقق والاختبار:
* [x] خلو الكود من الأخطاء والبناء سليم (`npm run build` بنجاح في 4.69 ثانية).
* [x] نجاح كافة اختبارات الـ Playwright E2E عبر المقاسات الـ 5 (7/7).
* [x] خلو الكود 100% من أي نصوص ثابتة وهندلة ملفات الترجمة للغتين (ar & en).
* [x] تطبيق نمط المنسق النحيف Thin Orchestrator (< 70 سطر).
* [x] التوافق مع شاشات اللمس واللغة العربية RTL والوضعين الفاتح والداكن.
