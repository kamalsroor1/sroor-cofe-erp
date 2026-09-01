# سجل تعديل: توحيد محركات الطباعة وتصحيح تراكب نوافذ الاختيار المنزلقة
* **التاريخ والوقت:** 2026-08-15 17:00
* **الدور المفعل:** NativePHP & Mobile UI/UX Specialist & QA
* **الهدف:** توحيد قوالب الطباعة (حراري 80mm، وفواتير A4، وكشوف حسابات) ومشاركتها بالكامل مع نظام الباك إند، بالإضافة إلى إصلاح ترتيب وتراكب طبقات النوافذ المنزلقة Z-Index لمنع ظهور نوافذ البحث خلف النوافذ المفتوحة.

## 1. الملفات المعدلة والجديدة:
* `[NEW]` `mobile/resources/views/layouts/print-thermal.blade.php` - قالب الإيصال الحراري 80mm المشترك مع الباك إند.
* `[NEW]` `mobile/resources/views/layouts/print-a4.blade.php` - قالب الفاتورة الرسمية A4 / PDF المشترك مع الباك إند.
* `[NEW]` `mobile/resources/views/layouts/print-customer-statement-a4.blade.php` - قالب كشف حساب العميل A4 المشترك.
* `[NEW]` `mobile/resources/js/Components/SupplierPickerSheet.vue` - منتقي الموردين المنزلق الذكي مع البحث الفوري.
* `[MODIFIED]` `mobile/resources/js/Components/CustomerPickerSheet.vue` - ترقية Z-Index إلى `z-[70]`.
* `[MODIFIED]` `mobile/resources/js/Components/WeightPickerModal.vue` - ترقية Z-Index إلى `z-[70]`.
* `[MODIFIED]` `mobile/resources/js/Pages/Payments/Index.vue` - ترقية واجهة السندات لتعمل بمنتقيات السحب الذكية وتصحيح التراكب.
* `[MODIFIED]` `mobile/resources/js/Pages/Invoices/Show.vue` - إضافة أزرار الطباعة الحرارية 80mm و A4 الرسمية.
* `[MODIFIED]` `mobile/resources/js/Pages/Customers/Statement.vue` - إضافة زر طباعة كشف الحساب A4.
* `[OUTPUT]` `mobile/sroor-coffee-erp-v1.0.apk` - تجميع ملف الأندرويد النهائي.

## 2. القرارات التقنية:
* توحيد مظهر وهوية الإيصالات والفواتير لتبدو متطابقة 100% سواء طُبعت من شاشة الموبايل أو لوحة التحكم.
* ضبط هرمية الـ Z-Index للنوافذ المتداخلة (`z-50` للنافذة الأم، `z-[70]` لنوافذ البحث والاختيار).

## 3. التحقق والاختبار:
* [x] خلو الكود وبناء Vite من الأخطاء.
* [x] بناء حزمة الأندرويد وتوقيع APK بنجاح (`BUILD SUCCESSFUL in 59s`).
* [x] اختبار فتح نافذة العميل من داخل سند القبض وظهورها في الطبقة العليا بنجاح.
