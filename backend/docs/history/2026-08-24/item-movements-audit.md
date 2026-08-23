# سجل مراجعة وتدقيق: كارت حركة الصنف وسجل العمليات (`ItemMovementsView.vue`)
* **التاريخ والوقت:** 2026-08-24 01:30
* **الدور المفعل:** Full Stack AI Squad (Frontend UI + Backend Architect + QA Testing + Docs PM)
* **الهدف:** تنفيذ بروتوكول المراجعة الشاملة للصفحة رقم 11 (Item Movements & Bin Card) عبر المحاور الأربعة المتزامنة.

## 1. الملفات المعدلة والمضافة:
* `[NEW]` `resources/js/Components/ItemMovements/ItemMovementsSummaryCards.vue` - بطاقات إجمالي الوارد والمنصرف وصافي الحركة والرصيد.
* `[NEW]` `resources/js/Components/ItemMovements/ItemMovementsFilterBar.vue` - شريط فلترة التواريخ والأقراص السريعة.
* `[NEW]` `resources/js/Components/ItemMovements/ItemMovementsTable.vue` - جدول وتراص بطاقات حركات الصنف والتدقيق.
* `[NEW]` `resources/js/Composables/useItemMovements.js` - كبسولة المنطق الحسابي والاتصال بالـ API.
* `[MODIFIED]` `resources/js/views/Items/ItemMovementsView.vue` - تحويل الصفحة لمنسق نحيف Thin Orchestrator (~60 سطر).
* `[NEW]` `e2e/flows/item-movements-full-page-audit.spec.js` - اختبارات Playwright E2E الشاملة عبر 5 مقاسات شاشات.
* `[NEW]` `docs/pages/item-movements.md` - وثيقة الصفحة المستقلة.
* `[MODIFIED]` `docs/full-page-review-log.md` - تحديث سجل المراجعة المجمع.

## 2. القرارات التقنية:
* تفكيك الـ View إلى مكونات أحادية المسؤولية واستخراج المنطق في Composable `useItemMovements.js`.
* استخدام عناصر مكتبة النماذج المشتركة `BaseButton`, `StatCardSkeleton`, `TableSkeleton`, `EmptyState`.
* توفير نمط عرض مزدوج يضمن تجربة مستخدم مريحة للإبهام على الهواتف وجدول عالي الكثافة على الشاشات الكبيرة مع دعم كامل لطباعة التقرير.

## 3. التحقق والاختبار:
* [x] خلو الكود من الأخطاء والبناء سليم (`npm run build` بنجاح في 4.52 ثانية).
* [x] نجاح كافة اختبارات الـ Playwright E2E عبر المقاسات الـ 5 (7/7).
* [x] خلو الكود 100% من أي نصوص ثابتة وهندلة ملفات الترجمة للغتين (ar & en).
* [x] تطبيق نمط المنسق النحيف Thin Orchestrator (< 60 سطر).
* [x] التوافق مع شاشات اللمس واللغة العربية RTL والوضعين الفاتح والداكن.
