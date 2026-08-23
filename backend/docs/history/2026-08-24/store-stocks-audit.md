# سجل مراجعة وتدقيق: جرد وأرصدة الفروع والمخازن (`StoreStocksView.vue`)
* **التاريخ والوقت:** 2026-08-24 01:10
* **الدور المفعل:** Full Stack AI Squad (Frontend UI + Backend Architect + QA Testing + Docs PM)
* **الهدف:** تنفيذ بروتوكول المراجعة الشاملة للصفحة رقم 9 (Store Stocks & Inventory Audit) عبر المحاور الأربعة المتزامنة.

## 1. الملفات المعدلة والمضافة:
* `[NEW]` `resources/js/Components/StoreStocks/StoreStocksFilterBar.vue` - شريط اختيار الفرع، البحث، وأقراص تصفية الحالة.
* `[NEW]` `resources/js/Components/StoreStocks/StoreStocksTable.vue` - الجدول المزدوج وبطاقات الموبايل اللمسية والترقيم.
* `[NEW]` `resources/js/Composables/useStoreStocks.js` - كبسولة المنطق الحسابي والاتصال بالـ API.
* `[MODIFIED]` `resources/js/views/Stores/StoreStocksView.vue` - تحويل الصفحة لمنسق نحيف Thin Orchestrator (~50 سطر).
* `[NEW]` `e2e/flows/store-stocks-full-page-audit.spec.js` - اختبارات Playwright E2E الشاملة عبر 5 مقاسات شاشات.
* `[NEW]` `docs/pages/store-stocks.md` - وثيقة الصفحة المستقلة.
* `[MODIFIED]` `docs/full-page-review-log.md` - تحديث سجل المراجعة المجمع.

## 2. القرارات التقنية:
* تفكيك الـ View إلى مكونات أحادية المسؤولية واستخراج المنطق في Composable `useStoreStocks.js`.
* استخدام عناصر مكتبة النماذج المشتركة `BaseSelect`, `BaseSearchInput`, `TableSkeleton`, `EmptyState`.
* توفير نمط عرض مزدوج يضمن تجربة مستخدم مريحة للإبهام على الهواتف وجدول عالي الكثافة على الشاشات الكبيرة.

## 3. التحقق والاختبار:
* [x] خلو الكود من الأخطاء والبناء سليم (`npm run build` بنجاح في 4.18 ثانية).
* [x] نجاح كافة اختبارات الـ Playwright E2E عبر المقاسات الـ 5 (7/7).
* [x] خلو الكود 100% من أي نصوص ثابتة وهندلة ملفات الترجمة للغتين (ar & en).
* [x] تطبيق نمط المنسق النحيف Thin Orchestrator (< 50 سطر).
* [x] التوافق مع شاشات اللمس واللغة العربية RTL والوضعين الفاتح والداكن.
