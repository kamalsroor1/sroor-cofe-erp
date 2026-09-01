# سجل تعديل: تحويل النظام للنمط التجاوبي مع الهواتف (Mobile-First) ودمج تطبيق الموبايل الأصلي عبر Capacitor

* **التاريخ والوقت:** 2026-08-21 01:50
* **الدور المفعل:** Frontend / UI Agent & Backend Architect
* **الهدف:** توفير تجربة استخدام متكاملة للهواتف الذكية مع شريط تنقل سفلي، تحويل الجداول إلى كروت لمسية تفاعلية، وتهيئة تطبيق أندرويد أصلي (Native Android) عبر CapacitorJS.

---

## 1. الملفات المعدلة والمنشأة:

* `[MODIFIED]` `backend/resources/js/Layouts/AppLayout.vue` - إضافة شريط التنقل السفلي الثابت للهواتف (`Fixed Mobile Bottom Navigation`)، نافذة الإشعارات السفلية المنبثقة (`Mobile Notifications Sheet`)، ومزامنة شريط الحالة (Status Bar) مع الهوية والألوان.
* `[MODIFIED]` `backend/resources/js/Pages/Invoices/Index.vue` - تحويل جدول الفواتير إلى كروت لمسية تفاعلية على شاشات الموبايل (`Mobile Cards View`) مع الحفاظ على الجدول الكلاسيكي لسطح المكتب.
* `[MODIFIED]` `backend/resources/js/Pages/Items/Index.vue` - تحويل جدول الأصناف والمخزون إلى كروت ذكية على الموبايل توضح الأرصدة والأسعار وحد الطلب وإجراءات التعديل والحذف وحركات الصنف.
* `[NEW]` `backend/resources/js/Composables/useNativeBridge.js` - كلاس Composable موحد للربط بين Vue 3 وعتاد الهاتف (الاهتزاز التفاعلي Haptics، شريط الحالة StatusBar، كاشف الاتصال بالإنترنت Network، التنبيهات Toast، وطباعة البلوتوث الحرارية).
* `[NEW]` `backend/capacitor.config.json` - إعدادات Capacitor لتطبيق سرور كوفي ERP.
* `[NEW]` `backend/public/index.html` - بوابة تشغيل سريعة مع شاشة تمهيدية (Splash Loader) لتوجيه التطبيق إلى السيرفر السحابي.
* `[NEW]` `backend/android/` - مشروع Android Studio الأصلي مهيأ مع صلاحيات الكاميرا والبلوتوث والإنترنت في `AndroidManifest.xml`.
* `[NEW]` `build-apk.ps1` & `build-apk.bat` - سكربتات أتمتة البناء والمزامنة مع الأندرويد بنقرة واحدة.

---

## 2. القرارات التقنية:

1. **معمارية المصدر الموحد (Single Unified Codebase):**
   * الاعتماد الكامل على مشروع `backend` (Inertia + Vue 3 + Tailwind) للويب والموبايل والتابلت لمنع تكرار الكود، وضمان تحديثات فورية بدون إعادة تثبيت التطبيق.
2. **شريط التنقل السفلي (Bottom Navigation):**
   * تثبيت أزرار (الرئيسية، الفواتير، نقطة البيع السريعة، الإشعارات، المزيد) في أسفل شاشات الهواتف فقط (`lg:hidden`) لسهولة الاستخدام بيد واحدة.
3. **تغليف Capacitor الأصلي (Native Android Wrapper):**
   * تثبيت حزم `@capacitor/core`، `@capacitor/android`، `@capacitor/camera`، `@capacitor/haptics`، `@capacitor/status-bar`، `@capacitor/network`، و `@capacitor/toast`.
   * ضبط الصلاحيات في `AndroidManifest.xml` لقراءة الباركود بالكاميرا والطباعة عبر طابعات البلوتوث المحمولة.

---

## 3. التحقق والاختبار:

* [x] بناء أصول الويب بنجاح تام (`npm run build` - 0 errors).
* [x] مزامنة Capacitor مع مشروع الأندرويد بنجاح تام (`npx cap sync android`).
* [x] اختبار سكربت البناء الآلي `build-apk.ps1` بنجاح كامل.
* [x] التوافق مع شاشات اللمس والموبايل واللغة العربية RTL والوضعين الفاتح والداكن.
