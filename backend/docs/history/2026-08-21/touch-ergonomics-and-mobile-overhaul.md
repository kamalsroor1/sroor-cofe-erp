# سجل تعديل: تحسين تفاعلية شاشات الموبايل وتكبير أزرار اللمس (Mobile Touch Ergonomics & Bento Cards)
* **التاريخ والوقت:** 2026-08-21 02:10
* **الدور المفعل:** Frontend UI & Mobile Specialist
* **الهدف:** تطبيق معايير Touch Target (الحد الأدنى 44px) وتصميم بطاقات الموبايل المتناسقة وتسهيل الضغط بالإصبع في جميع الشاشات.

## 1. الملفات المعدلة:
* `[MODIFIED]` `backend/resources/js/Pages/POS/Index.vue` - إضافة التبديل بين المنتجات والسلة على شاشات الموبايل، شريط سلة عائم، تكبير لوحة الأرقام وأزرار الدفع `h-11` و `h-12`.
* `[MODIFIED]` `backend/resources/js/Components/POS/POSItemCard.vue` - تكبير أزرار الأوزان السريعة للبن والسائب إلى `h-8` مع حواف ولمسات تفاعلية.
* `[MODIFIED]` `backend/resources/js/Components/POS/POSCartItem.vue` - تكبير أزرار الزيادة والنقصان `+` و `-` إلى `w-8 h-8` وتكبير حقول الكمية والحذف.
* `[MODIFIED]` `backend/resources/js/Pages/Invoices/Index.vue` - تكبير أزرار الإجراءات في كروت الموبايل إلى `w-9.5 h-9.5` وأزرار الفلاتر.
* `[MODIFIED]` `backend/resources/js/Pages/Items/Index.vue` - تحويل ملخص المخزون لشبكة Bento 2x2 وتكبير أزرار الكروت `w-10 h-10`.
* `[MODIFIED]` `backend/resources/js/Pages/Customers/Index.vue` - إضافة عرض كروت الموبايل بدلاً من الجداول وتكبير زر سند القبض `h-10`.
* `[MODIFIED]` `backend/resources/js/Pages/Expenses/Index.vue` - تحويل المؤشرات لـ Bento 2x2 وإضافة كروت الموبايل مع أزرار لمس مريحة.
* `[MODIFIED]` `backend/resources/js/Pages/DailyJournal/Index.vue` - تحويل مصفوفة النقدية لـ 2x2 Bento وتكبير أزرار فتح/إغلاق الوردية والمصاريف `h-11`.
* `[MODIFIED]` `backend/resources/js/Layouts/AppLayout.vue` - تكبير روابط القائمة الجانبية في الموبايل لـ `min-h-[44px]` وزر الإغلاق.

## 2. القرارات التقنية:
* اعتماد معيار Touch Area من Apple & Google (حجم الأزرار التفاعلية لا يقل عن 40-44px مع `active:scale-90..95`).
* اعتماد نمط شبكة Bento (2 أعمدة على الموبايل) للمؤشرات الإحصائية لمنع التمرير الطويل.
* توفير شريط سلة عائم (Floating Cart Bar) في شاشة الكاشير السريع.

## 3. التحقق والاختبار:
* [x] خلو الكود من الأخطاء وبناء Vite بنجاح 100%
* [x] مزامنة Capacitor Android بنجاح
* [x] دعم الوضعين الفاتح والداكن واللغة العربية RTL
