# سجل تعديل: توحيد ألوان الأزرار الـ Primary مع الثيم وإضافة كليندر ومنتقي الألوان المخصص
* **التاريخ والوقت:** 2026-08-20 18:07
* **الدور المفعل:** Frontend UI & Backend Architect Agent
* **الهدف:** توحيد كافة أزرار الإجراءات الرئيسية (`.btn-primary-theme`) بما في ذلك زر كاشير POS السريع F2 في الهيدر، وإنشاء فاتورة جديدة، وإضافة كليندر ومنتقي ألوان مخصص تفاعلي (Color Picker & Wheel + Hex Code + Extended Swatches) لإتاحة اختيار أي لون براند مخصص وتطبيقه لحظياً على كامل أرجاء النظام.

## 1. الملفات المعدلة:
* `[MODIFIED]` `backend/resources/js/Layouts/AppLayout.vue` - تحويل زر كاشير POS السريع F2 من الأخضر الثابت إلى `.btn-primary-theme` ليتوافق مع لون الثيم المختار.
* `[MODIFIED]` `backend/resources/js/Composables/useTheme.js` - ترقية المحرك اللوني لدعم كود الـ HEX المخصص، حساب درجات الـ Hover، التباين التلقائي للنصوص (`--color-primary-text`)، وحقن متغيرات CSS مباشرة في الـ DOM.
* `[MODIFIED]` `backend/resources/css/app.css` - ربط `.btn-primary-theme` و `.tab-theme-active` بالتباين اللوني الديناميكي.
* `[MODIFIED]` `backend/resources/js/Pages/Settings/Index.vue` - إضافة منتقي ألوان مخصص (Interactive Native Color Picker & Wheel)، حقل كود HEX، و12 درجة لون إضافية جاهزة (Swatches) مع معاينة حية فورية.
* `[MODIFIED]` `backend/app/Http/Controllers/SettingController.php` - تحديث التحقق من الحقل `system_theme_color` لدعم أكواد HEX.
* `[MODIFIED]` `backend/lang/ar/settings.php` & `backend/lang/en/settings.php` - إضافة مفاتيح الترجمة لمنتقي الألوان والباليتات.
* `[MODIFIED]` `backend/resources/js/Pages/Invoices/Index.vue` & `Edit.vue` & `Purchases/Create.vue` & `SmartReorder.vue` & `Suppliers/Statement.vue` & `Invoices/Show.vue` - استبدال التدرجات الثابتة بـ `.btn-primary-theme`.

## 2. التحقق والاختبار:
* [x] بناء الأصول بنجاح تام (`npm run build` - Code 0).
* [x] خلو الكود من أي نصوص ثابتة والتوافق مع اللغتين العربية والإنجليزية.
* [x] رفع التعديلات للمستودعين `origin` و `erp-hub`.
