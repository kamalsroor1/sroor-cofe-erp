# سجل مراجعة وتدقيق: سلة المحذوفات والاسترجاع الآمن (`TrashView.vue`)
* **التاريخ والوقت:** 2026-08-24 03:22
* **الدور المفعل:** Full Stack AI Squad (Frontend UI + Backend Architect + QA Testing + Docs PM)
* **الهدف:** تنفيذ بروتوكول المراجعة الشاملة للصفحة رقم 24 (Trash & Soft Deletes Recovery) عبر المحاور الأربعة المتزامنة.

## 1. الملفات المعدلة والمضافة:
* `[NEW]` `resources/js/Components/Trash/TrashModuleTabs.vue` - شريط التبويبات الـ 6 مع الأيقونات والعدادات.
* `[NEW]` `resources/js/Components/Trash/TrashFilterBar.vue` - شريط البحث الفوري في سلة المحذوفات.
* `[NEW]` `resources/js/Components/Trash/TrashTable.vue` - جدول المحذوفات وبطاقات الهواتف مع أزرار الاسترجاع والحذف.
* `[NEW]` `resources/js/Composables/useTrash.js` - كبسولة المنطق والاتصال بالـ API والاستعادة والحذف.
* `[MODIFIED]` `resources/js/views/Trash/TrashView.vue` - تحويل الصفحة لمنسق نحيف Thin Orchestrator (~65 سطر).
* `[NEW]` `e2e/flows/trash-full-page-audit.spec.js` - اختبارات Playwright E2E الشاملة عبر 5 مقاسات شاشات.
* `[NEW]` `docs/pages/trash.md` - وثيقة الصفحة المستقلة.
* `[MODIFIED]` `docs/full-page-review-log.md` - تحديث سجل المراجعة المجمع.

## 2. القرارات التقنية:
* تفكيك الـ View من 261 سطراً إلى 3 مكونات فرعية متخصصة واستخراج المنطق في Composable `useTrash.js`.
* استخدام عناصر مكتبة النماذج والمكونات المشتركة `BaseButton`, `BaseSearchInput`, `TableSkeleton`, `EmptyState`, `PageHeader`.
* توفير نمط عرض مزدوج يضمن تجربة مستخدم لمسية متكاملة وسلسة على الهواتف مع بطاقات لمسية متراصة وجداول عالية الكثافة على الشاشات الكبيرة مع عدادات حية لكل موديول.

## 3. التحقق والاختبار:
* [x] خلو الكود من الأخطاء والبناء سليم (`npm run build` بنجاح في 4.15 ثانية).
* [x] نجاح كافة اختبارات الـ Playwright E2E عبر المقاسات الـ 5 (7/7).
* [x] خلو الكود 100% من أي نصوص ثابتة وهندلة ملفات الترجمة للغتين (ar & en).
* [x] تطبيق نمط المنسق النحيف Thin Orchestrator (< 65 سطر).
* [x] التوافق مع شاشات اللمس واللغة العربية RTL والوضعين الفاتح والداكن.
