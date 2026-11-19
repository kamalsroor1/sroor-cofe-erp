# سجل تعديل: إنجاز مهام AGENT-2 ذات الأولوية P1 (SELL-03 و UI-04 وتوحيد 403)

* **التاريخ والوقت:** 2026-11-13
* **الدور المفعل:** Backend Architect + Frontend UI + QA Testing + i18n Guardian
* **المهام المنجزة من بورد Trello:**
  1. `[AGENT-2] [P1] SELL-03`: رسالة تترحيب جاهزة بعد قبول طلب التسجيل (رابط المحل + بيانات الدخول + مركز المساعدة ورقم الدعم) بزر نسخ ورابط واتساب فوري.
  2. `[AGENT-2] [P1 · شكل] UI-04`: توحيد الخط العربي (`--font-tajawal` كـ alias لـ `--font-sans`)، نوافذ SweetAlert2 والـ Toast متوافقة مع الوضعين الفاتح والداكن وزر تأكيد بلون الثيم `--color-primary`، كلاس الأرقام الموحد `.num` في `MetricCard` و`DataTable`، استبدال `toLocaleString` اليدوي بـ `formatters.js`، وإزالة الـ emoji من الطباعة الحرارية والأدوار وفلاتر العملاء.
  3. `[AGENT-2] [P1 · صياغة] TXT-01`: تبسيط العربي وتوحيد المصطلحات (فرع، عميل، صنف، مشتريات، وردية، خزينة، مرتجع)، أسماء القوائم والأزرار، صياغة فصحى مبسطة في subscription و import، رسائل خطأ واضحة تتضمن السبب والخطوة التالية، تنظيف الـ emojis والأسهم الحرفية، وتوحيد رسائل 403 عبر الـ API.
  4. `[AGENT-2] [P1] MOB-02`: رفع `targetSdkVersion` إلى 35 (Android 15) في `variables.gradle` للامتثال لمتطلبات Google Play.
  5. `[AGENT-2] [P1] MOB-07`: سد الثغرات الأمنية لتطبيق Capacitor: تعطيل `cleartext`، تعطيل `allowBackup` لمنع تسريب التوكنات، وحصر نطاق FileProvider على المجلدات المخصصة للتطبيق فقط.
  6. `[AGENT-2] [P1] FE-11`: إظهار مبدل الفروع وشارة الوردية تحت 1024px عبر دمجهما في شريط الهيدر للأجهزة المتوسطة وفي رأس قائمة الموبايل (Mobile Drawer).

---

## 1. الملفات التي تم إنشاؤها أو تعديلها (Modified Files)

### Backend
* `[MODIFIED]` `app/Actions/Signup/ApproveSignupRequestAction.php`: إرجاع كائن `ApprovedSignupResult` محملاً بـ `loginUrl`، `helpUrl`، `trialDays`، و`trialEndsAt` مع حسابهما تلقائياً للـ tenant.
* `[MODIFIED]` `app/DTOs/Signup/ApprovedSignupResult.php`: إضافة الحقول غير الحساسة الخاصة بسياق الترحيب.
* `[MODIFIED]` `app/Http/Controllers/Api/Central/SignupRequestController.php`: إعادة `welcome_context` مع استجابة الاعتماد للكونسول متضمنة رقم دعم المنصة ورابط الدخول والدعم.
* `[MODIFIED]` `app/Http/Controllers/Api/UserController.php`: إزالة الـ emoji المضمن في أسماء الأدوار واستخدام مفاتيح الترجمة المعتمدة `__('users.roles.'.$r->name)`.
* `[MODIFIED]` `bootstrap/app.php`: إضافة معالج `AccessDeniedHttpException` لضمان أن كل رفض صلاحية من Policy أو FormRequest يعود بـ 403 مترجم (`__('auth.unauthorized')`) باللغة المطلوبة عبر `X-Locale`.
* `[MODIFIED]` `lang/ar/signup.php` & `lang/en/signup.php`: إضافة نصوص ومفاتيح رسالة الترحيب للعميل.
* `[MODIFIED]` `lang/ar/contacts.php` & `lang/en/contacts.php`: إزالة الـ emoji من تسميات فلاتر العملاء والموردين.
* `[MODIFIED]` `lang/ar/roles.php` & `lang/en/roles.php`: إزالة الـ emoji من تسميات حفظ الصلاحيات وشارة الصلاحيات الكاملة.
* `[MODIFIED]` `android/variables.gradle`: رفع `targetSdkVersion` إلى 35 (Android 15) للامتثال لمتطلبات Google Play (`MOB-02`).
* `[MODIFIED]` `capacitor.config.json` & `android/app/src/main/assets/capacitor.config.json`: تعطيل حركة المرور غير المشفرة `cleartext: false` (`MOB-07`).
* `[MODIFIED]` `android/app/src/main/AndroidManifest.xml`: ضبط `android:allowBackup="false"` لمنع سحب التوكنات وبيانات الـ WebView (`MOB-07`).
* `[MODIFIED]` `android/app/src/main/res/xml/file_paths.xml`: إزالة مسار الـ root الخارجي المفتوح وحصر المسارات على المجلدات المخصصة للتطبيق (`MOB-07`).
* `[MODIFIED]` كافة ملفات الترجمة تحت `lang/ar/` و `lang/en/` (بما فيها `nav.php`, `dashboard.php`, `treasury.php`, `pos.php`, `inventory.php`, `purchases.php`, `invoices.php`, `users.php`, `auth.php`, `common.php`, `settings.php`, `permissions.php`, `import.php`, `subscription.php`): توحيد المصطلحات وتبسيط الصياغة وإزالة الـ emoji والأسهم الحرفية (`TXT-01`).
* `[MODIFIED]` `resources/views/layouts/print-thermal.blade.php`: استبدال إيموجي الطباعة والصورة بأيقونات SVG واضحة ونظيفة.

### Frontend
* `[NEW]` `resources/js/helpers/alertTheme.js`: استخراج كاشف ومحدد ألوان SweetAlert2 الفاتح/الداكن ولون الثيم `var(--color-primary)` ديناميكياً.
* `[MODIFIED]` `resources/js/helpers/alert.js`: دعم الثيمين الفاتح والداكن بدلاً من اللون الداكن الثابت، وزر التأكيد الرئيسي بألوان الثيم.
* `[MODIFIED]` `resources/css/app.css`:
  - ربط `--font-tajawal` بالخط الأساسي `--font-sans` لحل ازدواجية الخطوط العربية في الواجهة.
  - إضافة كلاس `.num` بالأرقام الجدولية والبطانية `tabular-nums lining-nums`.
  - إضافة outline عام لـ `:focus-visible` بلون الثيم.
* `[NEW]` `resources/js/helpers/signupWelcomeMessage.js`: بناء نص رسالة الترحيب المنسقة للعميل وروابط واتساب مع ترميز آمن.
* `[NEW]` `resources/js/Components/SuperAdmin/SignupRequests/SignupWelcomeMessagePanel.vue`: لوحة عرض الرسالة الجاهزة وزر نسخها وزر فتح واتساب داخل نافذة الاعتماد.
* `[MODIFIED]` `resources/js/Components/SuperAdmin/SignupRequests/ApproveSignupModal.vue`: دمج لوحة الرسالة الجاهزة وعرضها فور نجاح الاعتماد.
* `[MODIFIED]` `resources/js/Composables/useSignupRequestApproval.js`: إدارة حالة سياق الترحيب وفتح محادثة واتساب وتنظيف الباسورد فور إغلاق النافذة.
* `[MODIFIED]` `resources/js/views/SuperAdmin/SuperAdminSignupRequestsView.vue`: ربط معالجات وأحداث الرسالة الجاهزة.
* `[MODIFIED]` `resources/js/Components/Common/MetricCard.vue` & `DataTable.vue`: تطبيق كلاس `.num` على المبالغ والأرقام.
* `[MODIFIED]` `resources/js/Components/Roles/RolesSelectorGrid.vue`: استخدام أيقونات Lucide (Crown, ShoppingCart, Package, Briefcase) بدلاً من الـ emoji.
* `[MODIFIED]` `resources/js/Components/SuperAdmin/SuperAdminPlansDistribution.vue` & `SuperAdminMetricsGrid.vue`: استخدام `formatMoneyAmount` من `helpers/formatters.js` بدلاً من `toLocaleString`.
* `[MODIFIED]` `resources/js/Layouts/SpaLayout.vue`: إظهار مبدل الفروع وشارة الوردية على الشاشات المتوسطة والصغيرة وداخل قائمة الموبايل الجانبية (`FE-11`).
* `[MODIFIED]` `resources/js/helpers/defaultTranslations.ar.json` & `defaultTranslations.en.json`: تحديث قواميس الفرونت إند عبر `php artisan lang:export`.

### Tests
* `[NEW]` `tests/js/sell03-welcome-message.test.js`: اختبار بناء رسالة الترحيب بالعربية والإنجليزية وترميز أسماء المتاجر ذات الرموز الخاصة في روابط واتساب.
* `[NEW]` `tests/js/ui04-theme-and-emojis.test.js`: اختبار تباين ألوان التنبيهات الفاتحة والداكنة، وتنسيق المبالغ، وفحص خلو مكونات الـ Vue غير الـ POS من الـ emoji.
* `[NEW]` `tests/js/fe11-store-shift-switcher.test.js`: اختبار محددات ومكون مبدل الفروع والوردية ودعم variants (chip, drawer) وتفاعل تغيير الفرع (`FE-11`).
* `[NEW]` `tests/Feature/Security/MobileSecurityConfigTest.php`: اختبار إعدادات أمان أندرويد وCapacitor (targetSdkVersion >= 35, cleartext=false, allowBackup=false, مسارات FileProvider المقيدة) (`MOB-02`, `MOB-07`).
* `[NEW]` `tests/Feature/Api/UnauthorizedMessageTest.php`: اختبار إرجاع رسالة 403 مترجمة دائمًا من الـ API باللغتين العربية والإنجليزية.
* `[MODIFIED]` `tests/Feature/Signup/SignupRequestConsoleApiTest.php`: التحقق من وجود `welcome_context` بروابط المتاجر الصحيحة مع كل اعتماد.

---

## 2. نتائج الفحص والاختبارات (Verification & Quality Gates)

* **PHPUnit Tests:**
  - `SignupRequestConsoleApiTest`: 16/16 نجح.
  - `UnauthorizedMessageTest`: 2/2 نجح.
  - `MobileSecurityConfigTest`: 4/4 نجح.
  - `LangKeyParityTest` + `SpaTranslationKeysExistTest`: 105/105 نجح (25,486 assertions).
* **Larastan (PHPStan):** 0 أخطاء على الملفات المعدلة (`level 5`).
* **Laravel Pint:** اجتاز بنجاح (`passed`).
* **JavaScript Tests:** 415/415 نجح (20 suites).
* **ESLint & Prettier:** 0 أخطاء، وتم التنسيق بنجاح عبر `npm run format:dirty`.
* **Production Build (`npm run build`):** نجح بالكامل في 2.83s دون أي أخطاء.
