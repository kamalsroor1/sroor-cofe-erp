# سجل مراجعة وتدقيق: حاسبة خلطات وتكاليف البن (`CoffeeBlenderView.vue`)
* **التاريخ والوقت:** 2026-08-24 00:50
* **الدور المفعل:** Full Stack AI Squad (Frontend UI + Backend Architect + QA Testing + Docs PM)
* **الهدف:** تنفيذ بروتوكول المراجعة الشاملة للصفحة رقم 7 (Coffee Blender) عبر المحاور الأربعة المتزامنة.

## 1. الملفات المعدلة والمضافة:
* `[NEW]` `resources/js/Components/CoffeeBlender/CoffeeBlenderSpecsCard.vue` - بطاقة مواصفات الخلطة والوزن المستهدف.
* `[NEW]` `resources/js/Components/CoffeeBlender/CoffeeBlenderFormulationCard.vue` - بطاقة نسب الخامات وسلايدر المكونات.
* `[NEW]` `resources/js/Components/CoffeeBlender/CoffeeBlenderCostSummary.vue` - لوحة التلخيص المالي واختيار العميل.
* `[NEW]` `resources/js/Composables/useCoffeeBlender.js` - كبسولة المنطق الحسابي والاتصال بالـ API.
* `[MODIFIED]` `resources/js/views/CoffeeBlender/CoffeeBlenderView.vue` - تحويل الصفحة لمنسق نحيف Thin Orchestrator (~65 سطر).
* `[MODIFIED]` `app/Http/Requests/CreateBlenderInvoiceRequest.php` - دعم التعيين التلقائي للعميل النقدي العام في خلطات البن.
* `[NEW]` `e2e/flows/coffee-blender-full-page-audit.spec.js` - اختبارات Playwright E2E الشاملة عبر 5 مقاسات شاشات.
* `[NEW]` `docs/pages/coffee-blender.md` - وثيقة الصفحة المستقلة.
* `[MODIFIED]` `docs/full-page-review-log.md` - تحديث سجل المراجعة المجمع.

## 2. القرارات التقنية:
* تفكيك الـ View إلى مكونات أحادية المسؤولية واستخراج المنطق في Composable `useCoffeeBlender.js`.
* استخدام عناصر مكتبة النماذج المشتركة `BaseInput`, `BaseNumberInput`, `BaseSelect`, `BaseButton`.
* توفير التعيين التلقائي للعميل النقدي العام في `prepareForValidation` لتجنب أخطاء التحقق عند إصدار فواتير الخلطات السريعة.

## 3. التحقق والاختبار:
* [x] خلو الكود من الأخطاء والبناء سليم (`npm run build` بنجاح في 4.47 ثانية).
* [x] نجاح كافة اختبارات الـ API Feature Tests (2/2).
* [x] نجاح كافة اختبارات الـ Playwright E2E عبر المقاسات الـ 5 (7/7).
* [x] خلو الكود 100% من أي نصوص ثابتة وهندلة ملفات الترجمة للغتين (ar & en).
* [x] تطبيق نمط المنسق النحيف Thin Orchestrator (< 65 سطر).
* [x] التوافق مع شاشات اللمس واللغة العربية RTL والوضعين الفاتح والداكن.
