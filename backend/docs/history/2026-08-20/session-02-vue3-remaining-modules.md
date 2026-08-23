# سجل تعديل: إكمال تحويل وحدات النظام إلى Vue 3 مع مكونات الفلترة والتواريخ والتوليفات

* **التاريخ والوقت:** 2026-08-20 01:42
* **الدور المفعل:** Mobile Backend & UI/UX Specialist
* **الهدف من التعديل:** إكمال باقي صفحات النظام ونقلها إلى Vue 3 + Inertia.js مع بناء مكونات الاختيار والبحث (SearchableSelect)، والتقويم المخصص (DatePicker Flatpickr باللغة العربية)، وقائمة الفلاتر الجانبية (FilterDrawer Slide-Over)، وتغطية كافة المميزات (العملاء، الموردين، الأصناف والمخزون، اليومية والخزينة والورديات Z-Report، وحاسبة توليفات البن).

---

## 1. الملفات التي تم إنشاؤها أو تعديلها (Modified & Created Files)
* `[NEW]` `backend/resources/js/Components/SearchableSelect.vue` - مكون اختيار وبحث متقدم للأصناف والعملاء والموردين.
* `[NEW]` `backend/resources/js/Components/DatePicker.vue` - تقويم Flatpickr معرب بالكامل للغة العربية مع هوية الألوان الداكنة والذهبية.
* `[NEW]` `backend/resources/js/Components/FilterDrawer.vue` - قائمة فلاتر جانبية منزلقة Slide-Over مع عداد الفلاتر النشطة وزري التطبيق وإعادة الضبط.
* `[NEW]` `backend/app/Http/Controllers/ItemController.php` - كنترولر إدارة الأصناف والمخزون والتقييم المالي.
* `[NEW]` `backend/resources/js/Pages/Items/Index.vue` - شاشة دليل الأصناف والمخزون وفلاتر النواقص ومودال الإضافة والتعديل.
* `[NEW]` `backend/app/Http/Controllers/CustomerController.php` - كنترولر العملاء وحسابات المديونية وكشوف الحساب وسندات القبض.
* `[NEW]` `backend/resources/js/Pages/Customers/Index.vue` - شاشة دليل العملاء وإجمالي المديونيات وسندات التحصيل النقدية.
* `[NEW]` `backend/resources/js/Pages/Customers/Statement.vue` - كشف حساب تفصيلي للعميل مع تحديد التواريخ والطباعة A4.
* `[NEW]` `backend/app/Http/Controllers/SupplierController.php` - كنترولر الموردين وسندات الصرف والسداد.
* `[NEW]` `backend/resources/js/Pages/Suppliers/Index.vue` - شاشة دليل الموردين ومتابعة المستحقات وسندات الصرف.
* `[NEW]` `backend/app/Http/Controllers/DailyJournalController.php` - كنترولر اليومية العامة وحركة الخزينة والورديات وتقرير Z-Report.
* `[NEW]` `backend/resources/js/Pages/DailyJournal/Index.vue` - شاشة حركة الخزينة اليومية، فتح وإغلاق الوردية، تسجيل المصروفات وفواتير اليوم.
* `[NEW]` `backend/app/Http/Controllers/CoffeeBlenderController.php` - كنترولر حاسبة وتوليفات البن وإصدار فواتير الخلطات.
* `[NEW]` `backend/resources/js/Pages/CoffeeBlender/Index.vue` - حاسبة توليفات البن التفاعلية، أوزان مسبقة، التحويجة، وحساب التكلفة والربح.
* `[MODIFIED]` `backend/routes/tenant.php` & `backend/routes/web.php` - ربط كافة المسارات بالكنترولرز الجديدة المتوافقة مع Inertia.js.

---

## 2. القرارات المعمارية والمنطق البرمجي (Key Decisions)
* **دقة الحسابات المالية بدوال bcmath:** كافة حسابات التوليفات، المديونيات، حركة الخزينة، والمصروفات تعتمد على `DECIMAL(12,3)` و `bcmath`.
* **نظام الفلاتر الجانبي (Slide-Over Drawer):** توفير مساحة الشاشة وإعطاء تجربة مستخدم سريعة وسلسة لاستيعاب فلاتر متعددة دون تشويش الجداول.
* **التقويم المعرب والوضع الليلي:** توحيد نمط التاريخ عبر Flatpickr المدمج مع الخطوط وهوية النظام.

---

## 3. الاختبارات والتحقق (Verification & Testing)
* [x] تم اختبار جميع المسارات عبر HTTP بنجاح وأعادت `200 OK`.
* [x] بناء حزمة الأصول عبر `npm run build` بدون أخطاء.
* [x] خلو الأكواد 100% من نصوص غير مترجمة والتوافق الكامل مع RTL والخطوط العربية Cairo / Tajawal.