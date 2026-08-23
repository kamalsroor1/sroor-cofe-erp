# سجل تدقيق ومراجعة: إدارة الفروع والمخازن (StoresView.vue)

* **التاريخ والوقت:** 2026-08-23 23:58
* **الدور المفعل:** Frontend UI & QA Architect Agent
* **الهدف:** تنفيذ المحاور الأربعة الشاملة ونظام التوثيق الثلاثي على صفحة إدارة الفروع والمخازن ونقاط التوزيع (`/stores`).

---

## 1. الملفات الجديدة والمعدلة:
* `[NEW]` `backend/resources/js/Components/Stores/StoresMetricsGrid.vue`: شبكة 4 بطاقات KPI مع `StatCardSkeleton`.
* `[NEW]` `backend/resources/js/Components/Stores/StoresSearchFilterBar.vue`: شريط بحث وفلاتر النوع والحالة.
* `[NEW]` `backend/resources/js/Components/Stores/StoresGrid.vue`: شبكة بطاقات الفروع المتجاوبة مع `CardSkeleton` وقوائم `ActionMenu`.
* `[NEW]` `backend/resources/js/Components/Stores/StoreFormModal.vue`: نافذة إنشاء وتعديل الفرع بعناصر `BaseInput` و `BaseSelect`.
* `[NEW]` `backend/resources/js/Components/Stores/StoreStaffModal.vue`: نافذة تعيين وتفويض الموظفين على الفرع.
* `[MODIFIED]` `backend/resources/js/views/Stores/StoresView.vue`: تحويل الصفحة لمنسق نحيف (< 70 سطر).
* `[NEW]` `e2e/flows/stores-full-page-audit.spec.js`: فحص Playwright متكامل عبر المقاسات الـ 5.
* `[NEW]` `docs/pages/stores.md`: توثيق المستوى 1 لصفحة الفروع.
* `[MODIFIED]` `docs/modules/inventory.md`: ربط صفحة الفروع بموديول المخزون.
* `[MODIFIED]` `docs/system-architecture-master.md`: تحديث حالة الصفحة إلى مكتمل (100%).
* `[MODIFIED]` `docs/full-page-review-log.md`: تسجيل مراجعة الصفحة السادسة.

---

## 2. القرارات والتحسينات المعمارية:
1. **هياكل التحميل بالوميض (Facebook-Style Shimmer Skeletons):** استبدال السبينر الصغير بـ `StatCardSkeleton` لكروت المؤشرات و `CardSkeleton` لشبكة البطاقات لتبدأ فورياً بحالة `isLoading = ref(true)`.
2. **قائمة إجراءات الصفوف والبطاقات الموحدة (`ActionMenu.vue`):** تنظيم أزرار التعديل وتعيين الموظفين وتغيير الحالة والحذف داخل Dropdown عائم مريح للإبهام.
3. **حظر النصوص البديلة (Zero Fallback Strings):** الاعتماد بنسبة 100% على دوال الترجمة الرسمية `$t()` وقاموس `defaultArabicTranslations`.

---

## 3. التحقق والاختبار:
* [x] خلو الكود من الأخطاء وبناء Vite سليم تماماً في 4.6 ثانية (`npm run build`).
* [x] نجاح 7/7 اختبارات في Playwright عبر المقاسات الـ 5 وتفاعل النوافذ المنبثقة.
* [x] التوافق مع شاشات اللمس واللغة العربية RTL والوضعين الفاتح والداكن.
* [x] توثيق الصفحة عبر المستويات الثلاثة وسجلات المراجعة.
