# سجل مراجعة وتدقيق: إدارة باقات الاشتراك والأسعار المركزية (`SuperAdminPlansView.vue`)
* **التاريخ والوقت:** 2026-08-24 04:08
* **الدور المفعل:** Full Stack AI Squad (Frontend UI + Backend Architect + QA Testing + Docs PM)
* **الهدف:** تنفيذ بروتوكول المراجعة الشاملة للصفحة رقم 32 (Super Admin Plans & Pricing) عبر المحاور الأربعة المتزامنة.

## 1. الملفات المعدلة والمضافة:
* `[NEW]` `resources/js/Components/SuperAdmin/PlansGrid.vue` - شبكة بطاقات الباقات وهياكل التحميل.
* `[NEW]` `resources/js/Components/SuperAdmin/PlanCard.vue` - بطاقة الباقة الفردية مع الأسعار والحدود.
* `[NEW]` `resources/js/Components/SuperAdmin/EditPlanModal.vue` - نافذة AppModal لتعديل الباقة والأسعار والحدود.
* `[NEW]` `resources/js/Composables/useSuperAdminPlans.js` - كبسولة المنطق وإدارة البيانات والعمليات.
* `[MODIFIED]` `resources/js/views/SuperAdmin/SuperAdminPlansView.vue` - تحويل الصفحة لمنسق نحيف Thin Orchestrator (~65 سطر).
* `[NEW]` `e2e/flows/super-admin-plans-full-page-audit.spec.js` - اختبارات Playwright E2E الشاملة عبر 5 مقاسات شاشات.
* `[NEW]` `docs/pages/super-admin-plans.md` - وثيقة الصفحة المستقلة.
* `[MODIFIED]` `docs/full-page-review-log.md` - تحديث سجل المراجعة المجمع.

## 2. القرارات التقنية:
* تفكيك الـ View من 320 سطراً إلى 3 مكونات فرعية متخصصة واستخراج المنطق في Composable `useSuperAdminPlans.js`.
* استخدام عناصر مكتبة النماذج والمكونات المشتركة `BaseButton`, `BaseInput`, `CardSkeleton`, `AppModal`, `DarkSwal`.
* توفير تجربة مستخدم لمسية متكاملة وسلسة على الهواتف مع بطاقات لمسية متراصة وشبكة ثلاثية الأبعاد على الشاشات الكبيرة.

## 3. التحقق والاختبار:
* [x] خلو الكود من الأخطاء والبناء سليم (`npm run build` بنجاح في 5.18 ثانية).
* [x] نجاح كافة اختبارات الـ Playwright E2E عبر المقاسات الـ 5 (7/7).
* [x] خلو الكود 100% من أي نصوص ثابتة وهندلة ملفات الترجمة للغتين (ar & en).
* [x] تطبيق نمط المنسق النحيف Thin Orchestrator (< 65 سطر).
* [x] التوافق مع شاشات اللمس واللغة العربية RTL والوضعين الفاتح والداكن.
