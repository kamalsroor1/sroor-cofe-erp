# سجل تعديل: مراجعة شاملة للوضع الفاتح والداكن وتطبيق الثيم اللوني المخصص
* **التاريخ والوقت:** 2026-08-20 17:40
* **الدور المفعل:** Frontend UI Agent & Backend Architect Agent
* **الهدف:** تنفيذ فحص ومراجعة دقيقة وشاملة للوضع الداكن (Dark Mode) والوضع الفاتح (Light Mode) عبر شاشات النظام كافة (المخزون، الفروع، التوزيع والتحويلات، المرتجعات، المصروفات، العملاء والموردين، التقارير، إدارة النظام والمستخدمين والأدوار، وخلاط البن)، وربط كافة الأزرار والبطاقات النشطة بنظام الثيم اللوني الديناميكي (--color-primary, .btn-primary-theme, .tab-theme-active, .text-theme-primary) بحيث ينعكس أي لون يختاره المستخدم فوراً على كامل أرجاء النظام بدلاً من اللون البرتقالي الثابت.

## 1. الملفات المعدلة والمحدثة:
* [MODIFIED] ackend/resources/css/app.css - إضافة فئات الثيم العامة .btn-primary-theme و .tab-theme-active و .text-theme-primary و .bg-theme-light و .border-theme-primary وتعديل تدرجات الألوان الديناميكية.
* [MODIFIED] ackend/resources/js/Pages/Customers/Index.vue - تحويل الأزرار والتصنيفات والـ KPIs والبحث والمودال إلى الوضع المزدوج والفئات الديناميكية.
* [MODIFIED] ackend/resources/js/Pages/Suppliers/Index.vue - تحويل أزرار الإضافة والبحث وكروت الأرصدة والمودال إلى فئات الثيم الموحدة.
* [MODIFIED] ackend/resources/js/Pages/Expenses/Index.vue - تحويل زر الإضافة السريع وأقراص التصنيفات والمودال إلى الثيم اللوني والوضع الفاتح/الداكن.
* [MODIFIED] ackend/resources/js/Pages/Stores/Index.vue - تحويل كروت الفروع وزر إضافة فرع جديد ومودال تعيين الموظفين.
* [MODIFIED] ackend/resources/js/Pages/Stores/Stocks.vue - إعادة هيكلة الصفحة بالكامل من الثيم الداكن الثابت إلى الوضعين الفاتح والداكن المتناسقين وشريط الفروع الديناميكي.
* [MODIFIED] ackend/resources/js/Pages/StockTransfers/Index.vue - تحديث جدول التحويلات المخزنية ومودال تفاصيل البنود والـ KPIs للألوان والوضع المزدوج.
* [MODIFIED] ackend/resources/js/Pages/StockTransfers/Create.vue - تحديث حقول نماذج التحويل والمستودعات وجدول اختيار الكميات والزر الأساسي.
* [MODIFIED] ackend/resources/js/Pages/Returns/Index.vue - ترقية شاشة المرتجعات (فلاتر سريعة، جدول، كروت مالية، درج الفلاتر، مودال تفاصيل الإرجاع).
* [MODIFIED] ackend/resources/js/Pages/Returns/Create.vue - ترقية شاشة إنشاء المرتجع (اختيار نوع المرتجع، جدول الأصناف، ملخص الحساب، وزر الحفظ).
* [MODIFIED] ackend/resources/js/Pages/Purchases/Index.vue - ربط زر أمر الشراء والمؤشرات المالية بالثيم الموحد.
* [MODIFIED] ackend/resources/js/Pages/Reports/Index.vue - تحويل شريط الفترات الزمنية والتبويبات الـ 7 والمؤشرات إلى الوضع المزدوج وفئات الثيم.
* [MODIFIED] ackend/resources/js/Pages/Users/Index.vue - تحديث جدول المستخدمين، شارات الأدوار، أزرار التفعيل والتعديل، ومودال الحساب.
* [MODIFIED] ackend/resources/js/Pages/Roles/Index.vue - تحديث مصفوفة الصلاحيات، قائمة الأدوار، شبكة الوحدات، وزر الحفظ التفاعلي.
* [MODIFIED] ackend/resources/js/Pages/Settings/Index.vue - تحويل كافة تبويبات الإعدادات (الهوية والمطبوعات، التليجرام، النسخ الاحتياطي، معلومات النظام) إلى الثيم المزدوج والأزرار الديناميكية.
* [MODIFIED] ackend/resources/js/Pages/CoffeeBlender/Index.vue - تحويل خيارات الأوزان التقديرية، جدول المكونات، وزر إصدار الفاتورة إلى الثيم اللوني.

## 2. القرارات المعمارية والتقنية:
* **نظام التنسيق المركزي (CSS Variables Engine):** اعتماد متغير --color-primary و --color-primary-rgb على مستوى الـ :root و data-theme-color بحيث تتحكم الأنماط في كافة الأزرار وحلقات التركيز (ocus:ring-theme-primary) والتبويبات النشطة تلقائياً.
* **التباين البصري (High-Contrast Dual Mode):** استخدام g-white dark:bg-slate-900 للحاويات، و g-slate-50 dark:bg-slate-950 للمدخلات مع نصوص 	ext-slate-900 dark:text-white لمنع أي تعتيم أو تلاشي في كلا الوضعين.
* **الحفاظ على التوافق مع الهوية العربية والـ RTL وشاشات اللمس.**

## 3. التحقق والاختبار:
* [x] نجاح بناء الحزم بدون أخطاء (
pm run build Code 0).
* [x] خلو الكود من أي نصوص ثابتة والاعتماد 100% على $t() و 	rans().
* [x] فحص التوافقية الكاملة مع شاشات الجوال وشاشات سطح المكتب.
