# سجل تعديل: هوية المنتج «إبراء» (Ibraa) + لوجو «الدايرة المقفولة»
* **التاريخ والوقت:** 2026-11-10 02:30
* **الدور المفعل:** frontend-vue + backend (الجلسة الرئيسية) + code-reviewer
* **الهدف:** تطبيق اسم المنتج الجديد «إبراء» / Ibraa ولوجو «الدايرة المقفولة» بالأنيميشن على الويب والأندرويد والديسكتوب (كارت Trello: https://trello.com/c/ogH3fejl).

## 1. الملفات المعدلة
* `[NEW]` `backend/resources/js/Components/Brand/BrandMark.vue` — العلامة SVG، أنيميشن مرة واحدة ≈2.3 ثانية، تحترم `prefers-reduced-motion`، ألوان من توكنز `--brand-*` الثابتة.
* `[NEW]` `backend/resources/js/Components/Brand/BrandLogo.vue` — العلامة + الاسم بخط Reem Kufi (أفقي/رأسي، ERP/POS، الجملة).
* `[NEW]` `backend/lang/{ar,en}/brand.php` — الاسم، وصف اللوجو، الجملة، سطر «Ibraa by Sroor».
* `[NEW]` `backend/public/brand/*.svg`، `backend/public/icons/*`، `backend/public/apple-touch-icon.png`، `desktop/src/assets/*` — أصول الهوية.
* `[MODIFIED]` `backend/public/{logo,logo-light,logo-dark}.png`، `favicon.ico`، `manifest.json`، `index.html` — اللوجو والاسم الجديد.
* `[MODIFIED]` `backend/resources/views/app.blade.php`، `resources/css/app.css` — الخطوط (IBM Plex Sans Arabic + Reem Kufi)، شاشة التحميل قبل Vue بالأنيميشن، preset `ibraa` افتراضي، `theme-color`.
* `[MODIFIED]` شاشات: `SystemBootSplash`، `LoginView`، `WorkspaceConnectingState`، `DesktopSidebar`، `SpaLayout`، `DesktopTitlebar`، `SuperAdminLayout`، `AppDownloadView`، `SystemTab` (كارت «عن التطبيق»)، `ThemeTab`، `InvoicePrintView`؛ و`themeHelper.js`، `useTheme.js`، `useSettings.js`، `stores/appConfig.js` (getter جديد `companyLogo`).
* `[MODIFIED]` `backend/config/branding.php`، `PlatformBranding.php` — افتراضي المنصة «إبراء» / Ibraa / `#0F6E5E` / legal «Ibraa by Sroor».
* `[MODIFIED]` `TenantBranding.php` + `ApiMeAction`، `BuildAuthPayload`، `GetSystemContextAction`، `SettingController` — preset `ibraa` هو الافتراضي (`DEFAULT_THEME_COLOR`)، ولون الفواتير الافتراضي `emerald` (`DEFAULT_INVOICE_COLOR`).
* `[MODIFIED]` `backend/routes/web.php` — الـ manifest الديناميكي: ألوان الهوية + أيقونات عادية وmaskable.
* `[MODIFIED]` `backend/capacitor.config.json`، `android/.../values/{strings,ic_launcher_background}.xml`، أيقونات mipmap وصور splash — اسم التطبيق «إبراء» والأيقونات الجديدة.
* `[MODIFIED]` `desktop/package.json`، `desktop/main.js`، `desktop/src/splash.html` — `productName` «Ibraa POS»، splash بالأنيميشن، الأيقونة.
* `[MODIFIED]` مفاتيح اسم المنتج في `lang/{ar,en}/{common,auth,nav,dashboard,super,settings}.php` (واتشال تكرار `common.app_title`).
* `[MODIFIED]` `backend/.env.example` — `APP_NAME=Ibraa`.
* `[MODIFIED]` `backend/tests/Feature/Branding/BrandingApiTest.php` — الافتراضي `ibraa`، لون الفواتير `emerald`، والـ hex بتاع المحل بيغلب.

## 2. القرارات التقنية
* **قرارات صاحب المشروع:** «Ibraa by Sroor» في فوتر الدخول وكارت «عن التطبيق» بس؛ «صافي» افتراضي للمنصة والمحلات اللي ما اختارتش لون؛ الخطين الاتنين؛ الأنيميشن في شاشة الافتتاح.
* براند المحل (لوجو/لون) لسه له الأولوية في كل مكان؛ اللوجو الجديد بيظهر بس لما المحل ما رفعش لوجو.
* `#0F6E5E` غامق على الخلفية الداكنة، فـ `html.dark[data-theme-color="ibraa"]` بيحوّله `#4CC3A8` (لون الهوية على الغامق) بـ `!important` لأن الكود بيكتب اللون inline على `<html>` و`<body>`.
* **ما اتغيرش عن قصد** (عشان التحديثات والروابط ما تتكسرش): `appId`/`applicationId`، بروتوكول `sroor://`، مفاتيح localStorage `sroor_*`، `appId` بتاع Electron، و`artifactName` (اسم ملف التحديث `Sroor-ERP-POS-Setup-*`).
* Electron بياخد فولدر البيانات من اسم المنتج، فـ`main.js` بيكمّل على الفولدر القديم لو فيه إعدادات الجهاز أو `Local Storage`/`IndexedDB` (الجلسة والطابور الأوفلاين).
* أيقونة Windows اتنقلت لملف متتبع `desktop/src/assets/icon.png` لأن `desktop/build/*` متجاهل في git.

## 3. التحقق والاختبار
* `php artisan test --filter=BrandingApiTest`: 46 نجحوا. فلتر أوسع (Branding|Translation|Lang|Locale|Auth|SystemContext|Settings|PlatformValues): 629 من 630؛ الفاشل `PosPriceAuthorityApiTest` تبع شغل POS لسه ما اتعملوش commit من جلسة تانية، مش من التعديل ده.
* `npm run build` نجح. ESLint وPrettier نضاف على الملفات المعدلة، وPint نضيف.
* `composer analyse`: خطأين بس في `ValidatesItemPriceTiers.php` (ملف POS ما اتعملوش commit من جلسة تانية).
* الديسكتوب `npm test`: 145 نجحوا. تستات Capacitor web assets: 7 نجحوا.
* اتشاف في المتصفح: شاشة التحميل قبل Vue، الـ boot splash، وشاشة الدخول في الداكن والفاتح.
* **ما اتختبرش:** build حقيقي لـ APK أو installer Windows، والسايدبار بعد تسجيل الدخول، والمقاسات الخمسة.

## 4. ملاحظات / ديون تقنية
* لو السيرفر الحي فيه اسم/لوجو متسجلين في `platform_settings` من السوبر أدمن، هيغلبوا الافتراضي الجديد؛ يتغيروا من شاشة إعدادات المنصة.
* شاشات المنصة بتعرض `BrandMark` دايمًا؛ عشان تحترم لوجو يرفعه السوبر أدمن، الـ API محتاج flag زي `is_custom`.
* الخطوط من Google Fonts، فالتطبيق أوفلاين بيرجع لـ Cairo/Tajawal.
* المحلات اللي متسجل عندها `amber` (حتى لو اتسجل تلقائي من فورم الإعدادات) هتفضل على amber، ومفيش migration ليهم.
* مطلوب من مصمم: نسخ نهائية للوجو، أنيميشن Lottie، وصور المتجر.
