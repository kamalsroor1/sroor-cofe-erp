# سجل مراجعة وتدقيق: رادار إعادة الطلب الذكي ومساعد المشتريات (`SmartReorderView.vue`)
* **التاريخ والوقت:** 2026-08-24 01:00
* **الدور المفعل:** Full Stack AI Squad (Frontend UI + Backend Architect + QA Testing + Docs PM)
* **الهدف:** تنفيذ بروتوكول المراجعة الشاملة للصفحة رقم 8 (Smart Reorder) عبر المحاور الأربعة المتزامنة.

## 1. الملفات المعدلة والمضافة:
* `[NEW]` `resources/js/Components/SmartReorder/SmartReorderMetricsGrid.vue` - بطاقات مؤشرات مستويات الخطر والتكلفة مع Skeletons.
* `[NEW]` `resources/js/Components/SmartReorder/SmartReorderFilterBar.vue` - شريط البحث وفلاتر فترات التحليل ومستويات الخطر.
* `[NEW]` `resources/js/Components/SmartReorder/SmartReorderTable.vue` - الجدول المزدوج وبطاقات الموبايل اللمسية.
* `[NEW]` `resources/js/Composables/useSmartReorder.js` - كبسولة المنطق الحسابي والاتصال بالـ API.
* `[MODIFIED]` `resources/js/views/Purchases/SmartReorderView.vue` - تحويل الصفحة لمنسق نحيف Thin Orchestrator (~65 سطر).
* `[MODIFIED]` `resources/js/router/index.js` - إضافة Alias للمسار السريع `/smart-reorder`.
* `[NEW]` `e2e/flows/smart-reorder-full-page-audit.spec.js` - اختبارات Playwright E2E الشاملة عبر 5 مقاسات شاشات.
* `[NEW]` `docs/pages/smart-reorder.md` - وثيقة الصفحة المستقلة.
* `[MODIFIED]` `docs/full-page-review-log.md` - تحديث سجل المراجعة المجمع.

## 2. القرارات التقنية:
* تفكيك الـ View إلى مكونات أحادية المسؤولية واستخراج المنطق في Composable `useSmartReorder.js`.
* استخدام عناصر مكتبة النماذج المشتركة `BaseSearchInput`, `BaseSelect`, `BaseButton`, `StatCardSkeleton`, `TableSkeleton`, `EmptyState`.
* توفير نمط عرض مزدوج يضمن تجربة مستخدم مريحة للإبهام على الهواتف وجدول عالي الكثافة على الشاشات الكبيرة.

## 3. التحقق والاختبار:
* [x] خلو الكود من الأخطاء والبناء سليم (`npm run build` بنجاح في 4.29 ثانية).
* [x] نجاح كافة اختبارات الـ Playwright E2E عبر المقاسات الـ 5 (7/7).
* [x] خلو الكود 100% من أي نصوص ثابتة وهندلة ملفات الترجمة للغتين (ar & en).
* [x] تطبيق نمط المنسق النحيف Thin Orchestrator (< 65 سطر).
* [x] التوافق مع شاشات اللمس واللغة العربية RTL والوضعين الفاتح والداكن.
