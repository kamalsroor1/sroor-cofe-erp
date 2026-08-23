# 📋 سجل مراجعة تعميم المنتج وإعدادات اسم المشروع (Generalization & White-Label Review Log)

> **تاريخ بدء المراجعة الشاملة:** 2026-08-22
> **الهدف:** 
> 1. تحويل النظام من نظام مخصص لمحلات البن إلى **نظام ERP عام وشامل** لكافة الأنشطة التجارية والمحلات (تجزئة، جملة، مطاعم/كافيهات، محلات صيانة، أجهزة، ملابس، عطارة...).
> 2. جعل **اسم المشروع والمنصة قابلاً للتغيير ديناميكياً من لوحة Super Admin** وتطبيقه مركزياً على كافة واجهات النظام.

---

## 📊 1. نتائج مرحلة الجرد الشامل (Discovery Breakdown)

* **عدد النصوص العربية المرتبطة بالبن:** 399 موضع
* **عدد النصوص الإنجليزية المرتبطة بالبن:** 169 موضع
* **عدد مواضع اسم المشروع الثابت ("سرور" / "Sroor"):** 107 موضع

---

## 🔴 2. النصوص الظاهرة للمستخدم (User-Facing Strings)

| الملف | المفتاح / السطر | النص الحالي (المخصص للبن) | النص العام المقترح |
|---|---|---|---|
| `lang/ar/dashboard.php` | `app_badge_sub` | `☕ نظام سرور لإدارة محامص ومبيعات البن` | `منظومة ERP السحابية المتكاملة لإدارة المبيعات والمخزون` |
| `lang/en/dashboard.php` | `app_badge_sub` | `☕ Sroor Coffee ERP & Roastery System` | `Cloud ERP Platform for Sales, Inventory & Branches` |
| `lang/en/dashboard.php` | `welcome_banner_title` | `Welcome to Sroor Coffee ERP & Invoicing System` | `Welcome to Cloud ERP & Invoicing System` |
| `lang/ar/dashboard.php` | `coffee_blend_btn` | `توليفة بن` | `تركيب وتجميع الأصناف` |
| `lang/en/dashboard.php` | `coffee_blend_btn` | `Coffee Blend` | `Product Assembly / Blend` |
| `lang/ar/nav.php` | `coffee_blender` | `خلّاط ومطحنة البن` | `معمل تركيب وتجميع الأصناف` |
| `lang/en/nav.php` | `coffee_blender` | `Coffee Blender` | `Product Assembly & Blender` |
| `lang/ar/nav.php` | `cloud_erp_subtitle` | `توزيع خامات مطاحن البن والمبيعات` | `إدارة المبيعات والمخزون ونقاط البيع` |
| `lang/en/nav.php` | `cloud_erp_subtitle` | `Coffee Roastery & Wholesale ERP` | `Sales, Inventory & Multi-Branch ERP` |
| `lang/ar/inventory.php` | `category_placeholder` | `مثال: بن خام، بن محوج، معلبات، مستلزمات...` | `مثال: إلكترونيات، ملابس، مواد غذائية، قطع غيار...` |
| `lang/en/inventory.php` | `category_placeholder` | `e.g. Raw Coffee, Roasted, Syrups, Packaging...` | `e.g. Electronics, Apparel, Groceries, Spare Parts...` |
| `lang/ar/inventory.php` | `assembly_blends` | `معمل توليفات البن والتصنيع الذكي` | `معمل تجميع وتركيب المنتجات المركبة` |
| `lang/en/inventory.php` | `assembly_blends` | `Coffee Blender & Smart Formulation Lab` | `Custom Product Assembly & Formulation Lab` |
| `lang/ar/inventory.php` | `assembly_blends_sub` | `تجميع نسب البن الأخضر والمحمص لإنتاج خلطة مخصصة وخصم الخامات تلقائياً` | `تجميع مكونات ونسب الخامات لإنتاج منتج مركب وخصم المواد الخام تلقائياً` |
| `lang/en/inventory.php` | `assembly_blends_sub` | `Combine green and roasted beans proportions...` | `Combine raw materials and components to produce custom composite products...` |
| `lang/ar/inventory.php` | `raw_beans_components` | `مكونات البن الخام الداخلة في التوليفة` | `المكونات والمواد الأولية الداخلة في التركيب` |
| `lang/en/inventory.php` | `raw_beans_components` | `Raw Coffee Ingredients in Blend` | `Raw Materials & Assembly Components` |
| `lang/ar/inventory.php` | `ingredient_item` | `صنف البن الخام` | `صنف المادة الخام / المكون` |
| `lang/en/inventory.php` | `ingredient_item` | `Raw Bean Item` | `Raw Material / Component Item` |
| `lang/ar/inventory.php` | `roast_loss_percentage` | `نسبة الفاقد / الهالك في التحميص (%)` | `نسبة الهالك / الفاقد في التصنيع والتجهيز (%)` |
| `lang/en/inventory.php` | `roast_loss_percentage` | `Roasting Weight Loss / Waste (%)` | `Manufacturing Loss / Waste (%)` |
| `lang/ar/inventory.php` | `blender_title` | `أداة وحاسبة توليفات البن الاحترافية` | `أداة وحاسبة تركيب وتجميع المنتجات` |
| `lang/en/inventory.php` | `blender_title` | `Professional Coffee Blender & Formulation Tool` | `Product Formulation & Assembly Calculator` |
| `lang/ar/inventory.php` | `blender_subtitle` | `توليف حبوب البن بالنسب المئوية والجرامات، حساب التكلفة، وإصدار الفاتورة للعميل مباشرة` | `تجميع وتركيب المنتجات بالنسب والكميات، واحتساب التكلفة وإصدار الفاتورة مباشرة` |
| `lang/en/inventory.php` | `blender_subtitle` | `Blend coffee beans by percentage and grams...` | `Assemble composite items by ratios and quantities, calculate real-time cost, and invoice directly` |
| `lang/ar/inventory.php` | `blend_name_placeholder` | `مثال: توليفة سرور الخاصة، خلطة فرنسي بالبندق...` | `مثال: طقم تجميعي، وجبة مجهزة، خلطة مخصصة...` |
| `lang/en/inventory.php` | `blend_name_placeholder` | `e.g. Sroor Special Blend, Hazelnut French Roast...` | `e.g. Custom Assembly Kit, Prepared Package, Custom Blend...` |
| `lang/ar/inventory.php` | `select_blend_item_prompt` | `اختر صنف بن لإضافته للتوليفة...` | `اختر صنف مادة أولية لإضافته للتركيبة...` |
| `lang/en/inventory.php` | `select_blend_item_prompt` | `Select coffee bean item to add to recipe...` | `Select raw component item to add to recipe...` |
| `lang/ar/inventory.php` | `category_hint_placeholder` | `مثال: بن حبوب، بهارات، مشروبات` | `مثال: أجهزة، أدوات، مستلزمات، بضائع عامة` |
| `lang/en/inventory.php` | `category_hint_placeholder` | `e.g. Coffee beans, spices, drinks` | `e.g. Hardware, Accessories, Raw Materials` |
| `lang/ar/inventory.php` | `adjust_reason_placeholder` | `مثال: جرد دوري، عجز في الوزن، هالك تحميص...` | `مثال: جرد دوري، تسوية عجز، هالك تشغيل وتالف...` |
| `lang/en/inventory.php` | `adjust_reason_placeholder` | `e.g. Periodic count, weight variance, roast loss...` | `e.g. Periodic inventory count, variance adjustment, operational damage...` |
| `lang/ar/reports.php` | `total_cogs_desc` | `تكلفة شراء خامات البن والأصناف` | `تكلفة شراء البضائع المباعة والمواد الخام` |
| `lang/en/reports.php` | `total_cogs_desc` | `Cost of purchased coffee beans & items` | `Cost of purchased goods & raw materials` |
| `lang/ar/reports.php` | `items_profitability_title` | `مبيعات وربحية الأصناف وحبوب البن` | `مبيعات وربحية الأصناف والمنتجات` |
| `lang/en/reports.php` | `items_profitability_title` | `Coffee & Item Sales Profitability` | `Item & Product Sales Profitability` |
| `lang/ar/purchases.php` | `subtitle` | `توريد البضائع وخامات البن، واحتساب التكلفة...` | `توريد البضائع والمواد الخام، واحتساب التكلفة...` |
| `lang/en/purchases.php` | `subtitle` | `Receive items and raw coffee, calculate landed costs...` | `Receive inventory and raw materials, calculate landed costs...` |
| `lang/ar/settings.php` | `sec_branding_subtitle` | `اسم المحمصة، الهاتف والعنوان` | `اسم المنشأة / الشركة، الهاتف والعنوان` |
| `lang/ar/settings.php` | `branding_section_title` | `الهوية التجارية وبيانات المحمصة` | `الهوية التجارية وبيانات المنشأة والنشاط` |
| `lang/ar/settings.php` | `company_subtitle_placeholder` | `مثال: لتوريدات خامات مطاحن البن الفاخر` | `مثال: للتجارة والتوزيع وإدارة المبيعات` |
| `lang/en/settings.php` | `company_subtitle_placeholder` | `e.g. Premium Coffee Roastery Supplies` | `e.g. Trading, Distribution & Retail Management` |
| `lang/ar/super.php` | `tenants_page_subtitle` | `إنشاء وتفعيل وإيقاف اشتراكات المؤسسات والمحامص على المنصة` | `إنشاء وتفعيل وإيقاف اشتراكات المؤسسات والشركات على المنصة` |
| `lang/en/super.php` | `tenants_page_subtitle` | `...and roasteries on the platform` | `...and commercial enterprises on the platform` |
| `lang/ar/super.php` | `org_name_placeholder` | `مثال: مطاحن ومحمصة الشرق` | `مثال: شركة النصر للتجارة والتوزيع` |
| `lang/en/super.php` | `org_name_placeholder` | `e.g. Al-Sharq Roastery & Mills` | `e.g. Al-Nasr Trading & Distribution` |

---

## 🟡 3. منطق وهيكلة البيانات (Data Structure & Business Logic)

1. **محرك تجميع وتركيب المنتجات المركبة (`CoffeeBlender` / `Assembly`):**
   * **الوضع الحالي:** كلاسات الـ Action والمتحكمات تحتوي على متغيرات وحقول مخصصة للبن مثل (`roast_type`, `grind_level`, `cardamom_grams`).
   * **المشكلة البرمجية في `BlenderController.php` (سطر 25):** يوجد كود استعلام ثابت: `$q->where('category', 'like', '%بن%')` مما يحصر الخامات في كلمة "بن" فقط.
   * **الخيارات المطروحة للقرار:**
     * **الخيار الأول (الموصى به - Generic Bill of Materials / Assembly Engine):** تحويل المحرك إلى نظام تجميع عام للمنتجات المركبة (BOM)، حيث يمكن لأي نشاط تجميع مكونات خامات أو أصناف بنسب وأوزان، مع إتاحة الحقول كـ `custom_attributes` (JSON) اختيارية.
     * **الخيار الثاني (Modular Feature Flag):** جعل الميزة اختيارية في باقات السوبر أدمن تحت اسم "موديول التصنيع والتركيب" مع تعميم واجهاتها.

---

## 🏢 4. جرد أماكن اسم المشروع الثابتة (Hardcoded App Name Locations)

1. **الترويسة وعنوان المتصفح `<title>`:**
   * `backend/resources/views/app.blade.php`: `<title>{{ config('app.name', 'سرور كوفي ERP') }}</title>`
   * `backend/resources/js/Layouts/SpaLayout.vue`: `{{ appConfigStore.companyName || 'سرور كوفي ERP' }}`
2. **شاشة تسجيل الدخول والواجهات العامة:**
   * `backend/resources/js/views/Auth/LoginView.vue`
   * `backend/resources/js/views/SuperAdmin/SuperAdminLayout.vue`
3. **ملفات الإعدادات الافتراضية والـ Seeders:**
   * `backend/database/migrations/2026_08_10_221000_create_settings_table.php`
   * `backend/database/migrations/tenant/2026_08_10_221000_create_settings_table.php`
   * `backend/resources/js/stores/appConfig.js`
4. **ملفات الـ PWA و APK Updates:**
   * `backend/routes/web.php` (PWA manifest JSON name & short_name)
   * `backend/public/manifest.json`
   * `backend/resources/js/Composables/useAppUpdate.js` (APK download filename)
5. **قوالب الطباعة (A4 / Thermal / Reports / Journal):**
   * استخدام `\App\Models\Setting::get('company_name', 'سرور كوفي')` كقيمة افتراضية fallback.

---

## ⚙️ 5. مقترح البنية المركزية لاسم المشروع (Super Admin Dynamic App Name)

* **المستوى الأول (Platform-Level):** إعداد `platform_name` في جدول الإعدادات المركزي للـ Super Admin، يتحكم في اسم المنصة العام، صفحة الدخول، عنوان التبويب، والـ PWA، مع تخزينه في `Cache` فائق السرعة وتمريره للفرونت إند عبر `/api/v1/system/context` و `/api/v1/super-admin/settings`.
* **المستوى الثاني (Tenant-Level):** إعداد `company_name` داخل كل Tenant يحدد اسم شركته وفروعه المنعكسة في الفواتير والطباعة.

---

## 🏆 6. حالة التنفيذ والتحقق النهائي (Execution Status: COMPLETED ✅)

* **تاريخ اكتمال التنفيذ:** 2026-08-22 13:55
* **الحالة:** مكتمل ومفحوص بنسبة 100%.
* **نتائج الاختبارات:**
  * اختبارات الـ API Feature Tests: نجاح 103 / 103 اختبار (634 assertion).
  * حزمة الاختبارات الكاملة: نجاح 254 / 254 اختبار (1,216 assertion).
  * بناء حزم الإنتاج لـ Vite: ناجح 100% بدون أي أخطاء.
* **تقرير التوثيق:** متوفر في [`docs/history/2026-08-22/generalization-and-whitelabel-report.md`](file:///d:/projects/sroor/docs/history/2026-08-22/generalization-and-whitelabel-report.md).
