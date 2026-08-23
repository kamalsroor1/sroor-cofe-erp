# سجل تعديل: مراجعة وترقية استجابة الموبايل وكروت اللمس للشاشات الثانوية (Mobile Audit Phase 2)
* **التاريخ والوقت:** 2026-08-21 02:24
* **الدور المفعل:** Frontend UI & QA Testing Agent
* **الهدف:** توحيد استجابة شاشات كشوف الحسابات والمخازن والتحويلات والمرتجعات وإعادة الطلب الذكي وتفاصيل الفواتير لمعيار اللمس بالأصبع (Touch Ergonomics)، وتحويل الجداول لكروت تفاعلية ذكية (md:hidden) مع دعم كامل للوضعين الفاتح والداكن.

## 1. الملفات المعدلة:
* [MODIFIED] mobile-review-log.md - توثيق المشاكل والحلول وقائمة الملفات المراجعة بالكامل.
* [MODIFIED] backend/resources/js/Pages/CoffeeBlender/Index.vue - ترقية شرائح الأوزان، السلايدر، حقول الإدخال، وأزرار الاعتماد للأصبع.
* [MODIFIED] backend/resources/js/Pages/Customers/Statement.vue - تحويل كشف الحساب لشبكة Bento وكروت موبايل وترقية حقول سند القبض.
* [MODIFIED] backend/resources/js/Pages/Suppliers/Statement.vue - تحويل كشف الحساب لشبكة Bento وكروت موبايل وترقية حقول سند الصرف.
* [MODIFIED] backend/resources/js/Pages/Invoices/Show.vue - ترقية أزرار الإجراءات العلوية، تحويل جدول الأصناف لكروت موبايل، وتنسيق نافذة الإلغاء.
* [MODIFIED] backend/resources/js/Pages/Invoices/Edit.vue - إضافة كروت تعديل الأصناف باللمس، وتكبير حقول البيانات والخصم.
* [MODIFIED] backend/resources/js/Pages/Items/Movements.vue - تحويل كارت حركة الصنف لكروت موبايل وشبكة Bento 2x2.
* [MODIFIED] backend/resources/js/Pages/Stores/Stocks.vue - شريط تمرير أفق للمخازن، شبكة Bento، وكروت حالة المخزون.
* [MODIFIED] backend/resources/js/Pages/StockTransfers/Create.vue - كروت اختيار الأصناف والكميات المحولة وأزرار الإجراءات.
* [MODIFIED] backend/resources/js/Pages/Purchases/SmartReorder.vue - كروت الاختيار الذكي لمقترحات التوريد ومؤشرات الاستهلاك اليومي.
* [MODIFIED] backend/resources/js/Pages/Returns/Create.vue - كروت أصناف المرتجع، أزرار النوع، وحقول المبالغ المستردة.

## 2. القرارات التقنية:
* اعتماد الحد الأدنى لمساحة اللمس بالأصبع: h-11 (44px) للحقول والأزرار الفرعية، و h-12 (48px) لأزرار الاعتماد والتأكيد الرئيسية.
* إلغاء التمرير الأفقي للجداول المعقدة واستبدالها بكروت عمودية ذكية (md:hidden) تظهر على الشاشات الأصغر من md (768px).
* تطبيق شبكة Bento Grid المتجاوبة على الموبايل (grid-cols-2) لإظهار كافة المؤشرات الحيوية دون الحاجة للتمرير العمودي المتكرر.
* الحفاظ التام على منطق الـ API والـ Business Logic دون المساس بأي عملية حسابية أو مالية.

## 3. التحقق والاختبار:
* [x] خلو الكود من الأخطاء (بناء Vite تم بنجاح في 5.57 ثانية).
* [x] مزامنة أصول Capacitor Android بنجاح (npx cap sync android).
* [x] خلو الكود من أي نصوص ثابتة والتوافق مع مفاتيح الترجمة.
* [x] التوافق التام مع شاشات اللمس والهواتف الذكية من ~360px واللغة العربية RTL والوضعين الفاتح والداكن.
