# مراجعة UX/UI شاملة للشاشات — 2026-10-09

> **النوع:** مراجعة قراءة فقط (لم يتغيّر أي كود في التطبيق). **المهمة:** UX-1 (الجزء المتبقي: UX audit لباقي الشاشات) من [`phase-1-plan.md`](../../05-planning/phase-1-plan.md) §4.18.
> **المراجِع:** `frontend-vue` (دور UX reviewer). **المرجع الحاكم:** [`product-overview.md`](../../01-overview/product-overview.md) وقرارات الـ CTO (Q8 التعريب والأرقام، Q13 Mix Builder وإزالة لغة القهوة، Q-R1 والـ branding من مكان واحد).
> **المخرجات:** هذا الملف + اللقطات في [`screens/`](screens/). الـ quick wins هنا **مقترحة** وتحتاج اعتماد الـ CTO قبل UX-2.

---

## 1. المنهجية وحدود المراجعة

| البند | التفاصيل |
|---|---|
| البيئة | تشغيل محلي فقط: `php -S 127.0.0.1:8010..8012` + `vite` (dev). قاعدة **central مؤقتة** في مجلد temp وقاعدة tenant مؤقتة `uxr_tenant_demo.sqlite`، تم حذفهما بعد المراجعة. لا اتصال بأي سيرفر. |
| البيانات | `DatabaseSeeder` (الخطط + super-admin + tenant `demo`) ثم `RichDemoDataSeeder` داخل الـ tenant: 13 صنف، 4 فواتير (فاتورة واحدة في الفرع الرئيسي)، 7 عملاء، 4 موردين، 5 فروع، مصروفان، **صفر مشتريات** (لاختبار حالة الفراغ). |
| الأداة | Playwright (Chromium) بسكربت مؤقت خارج الريبو. لكل صفحة: تحميل، انتظار اختفاء الـ splash والـ skeleton، ثم لقطة viewport. (لقطات «الصفحة الكاملة» لم تنتج شيئًا لأن الـ layout يمرّر المحتوى داخل `<main>` لا الـ document.) |
| المقاسات | Desktop **1366×768**، Tablet **820×1180**، Mobile **390×844** — فاتح وداكن (عربي RTL). إضافةً: Desktop فاتح + Mobile داكن بالإنجليزية كفحص سريع لـ LTR. |
| حالات إضافية | **تحميل**: تأخير طلبات البيانات 9 ثوانٍ ولقطة بعد 4 ثوانٍ. **خطأ**: إرجاع HTTP 500 لطلبات البيانات. لعشر صفحات رئيسية. |
| فحوص آلية لكل لقطة | عدد أهداف اللمس الأصغر من 40px، overflow أفقي، مفاتيح ترجمة خام ظاهرة، كلمات «قهوة/بن/محمصة/مطحنة/خلطة»، أسماء براند ثابتة، أخطاء console، وطلبات API فاشلة. النتائج في [الملحق أ](#ملحق-أ--نتائج-الفحص-الآلي). |
| فحص ساكن | سكربت يقارن كل `$t('file.key')` في `resources/js` بملفات `lang/ar` و`lang/en` ← **44 مفتاحًا ناقصًا** ([الملحق ب](#ملحق-ب--مفاتيح-ترجمة-مستخدمة-وغير-موجودة-44)). |

**ما لم يُغطَّ (يجب أن يُذكر بوضوح):**
- اللقطات **أول شاشة فقط** (viewport)؛ المحتوى أسفلها غير مصوَّر.
- لم تُختبر **تدفقات تفاعلية** (إضافة صنف للسلة، الدفع، فتح/إغلاق وردية، المودالات). اللقطات لحالة الصفحة بعد التحميل فقط.
- لم تُختبر غلافات **Electron / Capacitor** ولا الطباعة الحرارية الفعلية؛ لقطة `invoice-print` هي معاينة المتصفح.
- التشغيل كان بـ Vite dev وعلى جهاز محمّل (لاين أخرى تعمل بالتوازي)، لذلك **أزمنة التحميل غير ممثلة للإنتاج**؛ بعض اللقطات الأولى أظهرت الـ skeleton لأن الـ API تأخر > 7 ثوانٍ (أُعيد تصوير `invoice-show` بانتظار أطول).
- الإنجليزية مؤجلة لـ Phase 3 (قرار Q8)، فلقطات EN للتوثيق فقط وليست مشاكل بأولوية الآن، **إلا** النصوص الثابتة لأنها تخالف قاعدة «صفر نص ثابت».
- لقطات الـ super-admin أُخذت بعد workaround في القاعدة المؤقتة (انظر §6 — فجوة backend حقيقية).

**تسمية اللقطات:** `screens/<page>__<viewport>-<theme>[-en].png`، وحالات التحميل/الخطأ: `screens/<page>__loading-<viewport>-<theme>.png` و`screens/<page>__error-desktop-light.png`. الصور مضغوطة (palette PNG، 256 لون).

**مقياس الخطورة:** **حرج** = يكسر مهمة أساسية أو يعرض بيانات مالية خاطئة · **عالٍ** = يعطّل الاستخدام على جهاز مستهدف أو يخالف قرار CTO · **متوسط** = يضر الوضوح/الاتساق · **منخفض** = تجميلي.

---

## 2. الملخص التنفيذي

**ما يعمل جيدًا (يُحافظ عليه):**
- هوية بصرية متسقة على مستوى الـ shell: بطاقات KPI، رؤوس صفحات بأيقونة + عنوان + وصف، الأزرار الأساسية بلون الـ tenant (`--color-primary`).
- **الوضع الداكن** ناضج في أغلب الشاشات (تباين جيد، صفوف المديونية ملوّنة بشكل خافت مقروء). استثناء واحد واضح (تقارير — صافي الربح).
- **Skeleton shimmer** موجود في الشاشات الثقيلة (Dashboard، تفاصيل الفاتورة، super-admin dashboard) ويعكس التخطيط الحقيقي.
- **حالة الفراغ** جيدة في المشتريات وإصدارات التطبيق (أيقونة + عنوان + وصف).
- **الموبايل:** شريط تنقل سفلي ثابت + زر POS عائم، والجداول تتحول لبطاقات في بعض الشاشات (المستخدمون، المستأجرون، بنود فاتورة الشراء).
- **POS على الديسكتوب:** الـ sidebar يتحول لوضع mini تلقائيًا، اختصارات F2/F9 ظاهرة، تبديل قطاعي/جملة، أكثر مبيعًا، وتبويبات فواتير متعددة.
- لا أخطاء JavaScript ولا طلبات API فاشلة في أي لقطة من الـ 216. (يوجد **تحذيرات Vue** في 4 شاشات — G16.)

**أكبر المشاكل (تفاصيلها في §5):**
1. **أرقام مالية خاطئة على شاشتي العملاء والموردين**: بطاقات المديونية = 0 دائمًا وفلاتر «عليهم مديونية» لا تعمل — عدم تطابق أسماء حقول بين الواجهة والـ API.
2. **Tablet (820px) مكسور تقريبًا في كل الشاشات**: الـ sidebar المفتوح يأخذ ~40% من العرض، فتُقص أعمدة الإجراءات/الحالة وأزرار الرأس، وفي POS تتراكب لوحة الأقسام فوق منطقة الدفع.
3. **مفاتيح ترجمة خام ظاهرة للمستخدم** (`purchases.final_net_total`، `invoices.total_net`، `common.details`، `reports.total_sales`) + خطأ placeholder في ترحيب الـ Dashboard («مرحباً بك في app:») + 44 مفتاحًا ناقصًا في الكود.
4. **لغة وأيقونات القهوة** ما زالت في كل مكان (شعار ☕ في الـ sidebar والـ POS والـ splash، «خامات البن» في الصلاحيات، «صانع الخلطات وتوليفات البن»، شاشة الخلطات بدرجة التحميص والطحن) — يخالف تموضع المنتج كـ SaaS عام وقرار Q13.
5. **براند وأكواد عملاء حقيقية مكتوبة في الواجهة**: chip بكود مستأجر حقيقي وplaceholder بكود عميل آخر في شاشة كود المؤسسة، و«منصة مخزني / Makhzani» في ملفات اللغة — يخالف متطلب الـ branding من مكان واحد ويكشف أكواد عملاء.

---

## 3. ملاحظات عامة عابرة للشاشات (Cross-cutting)

| # | المشكلة | الفئة | الخطورة | أين | الإصلاح المقترح |
|---|---|---|---|---|---|
| G1 | على 820px الـ sidebar يظل مفتوحًا (~320px) والمحتوى يُقص بدون scroll أفقي ظاهر: أعمدة «إجراءات/الحالة/الرصيد» تختفي، أزرار الرأس تخرج من الحاوية | Layout / Tablet | عالٍ | كل الشاشات (أوضحها: [users](screens/users__tablet-light.png)، [customers](screens/customers__tablet-light.png)، [sa-tenants](screens/sa-tenants__tablet-light.png)، [dashboard](screens/dashboard__tablet-light.png)) | `SpaLayout`/`SuperAdminLayout`: الـ sidebar mini افتراضيًا تحت 1280px (نفس منطق POS)، و`DataTable` يلف الجدول في `overflow-x-auto` مع ظل حافة، أو يحوّل للبطاقات تحت `lg` كما في الموبايل |
| G2 | مفاتيح ترجمة خام ظاهرة + 44 مفتاحًا مستخدمًا وغير موجود في `lang/ar` و`lang/en` | i18n | عالٍ | [purchase-create](screens/purchase-create__desktop-light.png)، [invoices](screens/invoices__desktop-light.png)، [sa-tenants](screens/sa-tenants__desktop-light.png)، [sa-tenant-show](screens/sa-tenant-show__desktop-light.png) + [الملحق ب](#ملحق-ب--مفاتيح-ترجمة-مستخدمة-وغير-موجودة-44) | `i18n-guardian` يضيف المفاتيح الـ 44 (ar+en) ثم `lang:export`؛ وإضافة فحص CI بسيط (نفس سكربت الملحق ب) يمنع الرجوع |
| G3 | نص عربي ثابت في الكود: `SpaLayout.vue` (~63 سطرًا: قائمة المستخدم، الوضع الليلي/النهاري، الإشعارات، اسم الفرع الافتراضي)، `useNavigation.js` (العناوين `title` بجانب `titleKey`)، `DashboardAppMenuHub.vue` (فلاتر الـ Hub)، والـ layouts تثبّت `dir="rtl"` | i18n / RTL | متوسط (EN في Phase 3، لكن القاعدة صفر نص ثابت) | [dashboard EN](screens/dashboard__desktop-light-en.png): العنوان إنجليزي والقائمة عربية والاتجاه RTL | استخدام `titleKey` فقط، نقل نصوص الـ layout لـ `nav.*`/`common.*`، و`dir`/`lang` على `<html>` من `appConfig.locale` |
| G4 | **لغة وأيقونات القهوة**: أيقونة `Coffee` كشعار التطبيق (sidebar، شريط POS، رأس super-admin «الذهاب لتطبيق الكاشير»، `DesktopTitlebar`)، ☕ في `SystemBootSplash`، أيقونة كوب لصفحة الأصناف ولتبويب «ربحية الأصناف»، «الأصناف والمخزون **وخامات البن**» (من الـ backend)، «صانع الخلطات **وتوليفات البن**»، شاشة الخلطات (درجة التحضير/التحميص، ناعم جدًا)، تصنيفات مصروفات سريعة «صيانة مطاحن» | Wording / Positioning | عالٍ (Q13 + SaaS عام) | [roles](screens/roles__desktop-light.png)، [coffee-blender](screens/coffee-blender__desktop-light.png)، [items](screens/items__desktop-light.png)، [expenses](screens/expenses__desktop-light.png) | شعار = لوجو الـ tenant/المنصة أو أيقونة محايدة (`Store`/`LayoutGrid`)؛ إعادة تسمية الشاشة لـ «الخلطات والتركيبات (Mix Builder)» وحذف التحميص/الطحن (نسبة هالك اختيارية بدلها)؛ النصوص من `lang` لا من الـ Actions |
| G5 | براند ثابت وأكواد عملاء حقيقيين: chip ثابت بكود مستأجر حقيقي في `WorkspaceStepInput.vue:66` وplaceholder بكودَي عميلين حقيقيين في `auth.workspace_code_placeholder` (`lang/ar/auth.php`)؛ «منصة مخزني / Makhzani SaaS Super Admin» في `lang/*/super.php`؛ اللون الافتراضي amber (هوية القهوة القديمة) قبل الدخول؛ «Retail ERP» في ذيل الإيصال | Branding / Privacy | عالٍ | [connect](screens/connect__desktop-light.png)، [sa-dashboard](screens/sa-dashboard__desktop-light.png)، [invoice-print](screens/invoice-print__desktop-light.png) | حذف الـ chip (أو قائمة «آخر مؤسسات على هذا الجهاز» من localStorage فقط)، placeholder محايد «مثال: my-shop»، اسم المنصة من إعدادات super-admin (`platform_name`) لا من ملف اللغة |
| G6 | **الأرقام والتواريخ غير متسقة**: الساعة في الرأس وتوقيت الفاتورة بأرقام هندية (`١٠:٤٤ ص`) بينما بقية الشاشة غربية؛ «AM 10:38» إنجليزية في اليومية؛ حقول `type="date"` الأصلية تعرض `mm/dd/yyyy` و`10/08/2026` (صيغة أمريكية)؛ الإيصال يعرض التاريخ معكوسًا `08-10-2026` بسبب الـ bidi | Formatting | متوسط (Q8: أرقام غربية) | [invoice-show](screens/invoice-show__desktop-dark.png)، [daily-journal](screens/daily-journal__desktop-light.png)، [expenses](screens/expenses__desktop-light.png)، [invoice-print](screens/invoice-print__desktop-light.png) | `useFormatters` بـ `ar-EG-u-nu-latn` لكل وقت/تاريخ (بدل `toLocaleTimeString('ar-EG')` في `useInvoiceShow.js`)، استبدال `type="date"` بـ `BaseDatePicker` (`ExpensesFilterBar`، `DailyJournalView`، `CreateStockTransferHeaderCard`)، وتغليف التواريخ في الإيصال بـ `dir="ltr"`/`<bdi>` |
| G7 | **بطاقات KPI على الموبايل** كل واحدة بعرض الشاشة وارتفاع ~130px ← 3–4 بطاقات تملأ الشاشة والقائمة الفعلية تبدأ بعد ~900px | Mobile | متوسط | [items](screens/items__mobile-light.png)، [invoices](screens/invoices__mobile-light.png)، [purchases](screens/purchases__mobile-light.png)، [stores](screens/stores__mobile-light.png) | `MetricCard` بوضع compact وشبكة عمودين تحت `sm` (كما في [daily-journal](screens/daily-journal__mobile-light.png) وهي الأفضل حاليًا) أو شريط أفقي قابل للسحب |
| G8 | **أهداف لمس صغيرة**: شرائح الخصم في POS (~30px)، زر «+» على بطاقة الصنف (~24px)، روابط «تحديد الكل / إلغاء الكل» في الصلاحيات، أيقونات إجراءات الجداول (32px)، أزرار الرأس (الإشعارات/الثيم ~36px) | Touch | متوسط | [pos](screens/pos__desktop-light.png)، [roles](screens/roles__desktop-light.png)، [الملحق أ](#ملحق-أ--نتائج-الفحص-الآلي) | حد أدنى `min-h-11 min-w-11` (44px) للأزرار الأيقونية على `pointer: coarse`، وجعل بطاقة الصنف كلها قابلة للنقر |
| G9 | **شارات الحالة تنكسر/تُقص**: «نشط وفعال» على سطرين ومقصوصة، «نقدي (كاش)» مقصوصة في عمود طريقة السداد | Consistency | منخفض (quick) | [customers](screens/customers__desktop-light.png)، [stores](screens/stores__desktop-light.png)، [invoices](screens/invoices__desktop-light.png)، [expenses](screens/expenses__desktop-light.png) | `StatusBadge` بـ `whitespace-nowrap` + نص أقصر («نشط»/«نقدي») |
| G10 | **CTA الوردية مكرر 3–4 مرات بمصطلحات مختلفة**: «فتح يومية» (chip الرأس)، «الوردية مغلقة · فتح –» (أسفل الـ sidebar)، «فتح وردية الآن» (بانر)، «فتح يومية / وردية جديدة» (زر الصفحة) | Consistency / Wording | متوسط | [daily-journal](screens/daily-journal__desktop-light.png) | مصطلح واحد («وردية») وزر أساسي واحد في الصفحة + مؤشر حالة صغير في الرأس فقط |
| G11 | **إيموجي بدل أيقونات lucide** في شارات وأزرار: 👑📦🛒💼 (الأدوار)، 💵 (نقدًا)، 🏬 (اسم الفرع في POS)، ⏳ (تجريبي)، 🚀 (نشر APK)، 🔍 (تفاصيل) | Consistency | منخفض | [roles](screens/roles__desktop-light.png)، [pos](screens/pos__desktop-light.png)، [sa-tenants](screens/sa-tenants__desktop-light.png) | lucide فقط (قاعدة الواجهة)؛ الإيموجي تختلف حسب نظام التشغيل (Windows/Android) |
| G12 | **مصطلحات إنجليزية/تقنية داخل الواجهة العربية**: «(Revenue)»، «(COGS)»، «(Gross Profit)»، «WAC»، «Slug: pro»، «تحديث الميجريشن»، «OTA Updater»، «(App Hub)» | Wording | منخفض | [reports](screens/reports__desktop-light.png)، [purchase-create](screens/purchase-create__desktop-light.png)، [sa-plans](screens/sa-plans__desktop-light.png)، [sa-tenant-show](screens/sa-tenant-show__desktop-light.png) | نقلها لـ tooltip أو حذفها؛ «Slug» ← «الرمز المختصر»، «تحديث الميجريشن» ← «تحديث قاعدة البيانات» |
| G13 | **ألوان دلالية غير متسقة**: قيم سالبة بالأخضر (صافي النقدية `-4,850` في اليومية)، أسعار الأصناف في POS بألوان عشوائية (برتقالي/وردي/بنفسجي) بلا معنى | Visual semantics | منخفض–متوسط | [daily-journal](screens/daily-journal__desktop-light.png)، [pos](screens/pos__desktop-light.png) | لون حسب الإشارة (موجب أخضر/سالب أحمر) عبر `useMoney`، ولون سعر موحد (`--color-primary`) |
| G14 | **أيقونات التصنيفات عشوائية**: «بن خام» = هاتف، «بن محمص» = سماعة، «توليفات» = فيشة كهرباء، «مستلزمات» = بطارية — `DynamicIcon` يطابق بقاموس emoji/كلمات من seeder الإكسسوارات | Consistency | منخفض | [categories](screens/categories__desktop-light.png)، [pos](screens/pos__desktop-light.png) | أيقونة افتراضية محايدة (`Folder`/`Tag`) + اختيار صريح من قائمة lucide في نموذج التصنيف |
| G15 | **حالة الخطأ**: عند إرجاع 500 لطلبات البيانات تعرض الصفحات أصفارًا + حالة الفراغ («لا توجد أصناف مطابقة للبحث»، «لا توجد أصناف في هذا القسم») كأن البيانات فارغة فعلًا — لا رسالة دائمة ولا زر «إعادة المحاولة» (لم يكن هناك toast ظاهر وقت اللقطة بعد 6 ثوانٍ). استثناء: تفاصيل الفاتورة تعرض رسالة الخادم كما هي (ظهرت «Server Error» بالإنجليزية) + «رجوع» بلا إعادة محاولة ([لقطة](screens/invoice-show__error-desktop-light.png)) | Error state | متوسط (عالٍ في POS) | [items](screens/items__error-desktop-light.png)، [pos](screens/pos__error-desktop-light.png)، [customers](screens/customers__error-desktop-light.png)، وباقي لقطات `__error-desktop-light` | `ErrorState` مشترك (أيقونة + رسالة + `BaseButton` إعادة المحاولة) يُعرض بدل المحتوى عند `error` في كل composable |
| G16 | **تحذيرات Vue في الـ console** (القاعدة: صفر تحذيرات): `Extraneous non-props attributes (submitting / is-submitting / form, is-updating-status) … renders fragment or teleport root` عند فتح الموردين والمصروفات والفروع وتفاصيل المستأجر | Code quality | منخفض (quick) | suppliers, expenses, stores, sa-tenant-show ([الملحق أ](#ملحق-أ--نتائج-الفحص-الآلي)) | تعريف الـ prop (`submitting`/`isSubmitting`/`form`/`isUpdatingStatus`) في `defineProps` لمودالات تلك الشاشات أو حذف تمريره |
| G17 | **شاشة الإقلاع (`SystemBootSplash`)**: داكنة دائمًا حتى لو اختار المستخدم الفاتح (وميض داكن→فاتح)، كوب قهوة ☕ كأيقونة، والإصدار «v1.0» بينما التطبيق «v1.0.135» | Branding / Theme | متوسط | [invoices loading](screens/invoices__loading-desktop-light.png) | تطبيق الثيم المخزّن (نفس سكربت anti-flicker في `app.blade.php`)، لوجو الـ tenant/أيقونة محايدة، الإصدار من `appConfig` |

---

## 4. مراجعة كل صفحة

> لكل صفحة: ما يعمل، المشاكل (الفئة · الخطورة · الإصلاح)، ثم روابط اللقطات.

### 4.1 الدخول — كود المؤسسة (`/connect`) وتسجيل الدخول (`/login`)
**اللقطات:** [Desktop فاتح](screens/connect__desktop-light.png) · [Desktop داكن](screens/connect__desktop-dark.png) · [Tablet فاتح](screens/connect__tablet-light.png) · [Tablet داكن](screens/connect__tablet-dark.png) · [Mobile فاتح](screens/connect__mobile-light.png) · [Mobile داكن](screens/connect__mobile-dark.png) · [Desktop EN](screens/connect__desktop-light-en.png) · [Mobile EN داكن](screens/connect__mobile-dark-en.png)

> الـ placeholder والـ chip في لقطات `connect` **مموّهان عمدًا** لأنهما يحتويان أكواد عملاء حقيقيين.

**اللقطات:** [Desktop فاتح](screens/login__desktop-light.png) · [Desktop داكن](screens/login__desktop-dark.png) · [Tablet فاتح](screens/login__tablet-light.png) · [Tablet داكن](screens/login__tablet-dark.png) · [Mobile فاتح](screens/login__mobile-light.png) · [Mobile داكن](screens/login__mobile-dark.png) · [Desktop EN](screens/login__desktop-light-en.png) · [Mobile EN داكن](screens/login__mobile-dark-en.png)

**يعمل:** بطاقة مركزية واضحة، حقول كبيرة (≥44px)، اسم المؤسسة ووصفها ظاهران، زر إظهار كلمة المرور، «تذكرني»، تبديل الثيم قبل الدخول، الداكن والموبايل سليمان.

| المشكلة | الفئة | الخطورة | الإصلاح |
|---|---|---|---|
| chip ثابت بكود مستأجر حقيقي + placeholder بكود عميل آخر — يكشف أكواد عملاء لكل زائر (الأكواد غير مذكورة هنا عمدًا) | Branding / Privacy | عالٍ | حذف الـ chip وplaceholder محايد (G5) |
| الـ placeholder مختلط الاتجاه فيظهر مشوّهًا (ترتيب الكلمات والأرقام مقلوب) | RTL / bidi | متوسط | نص عربي فقط أو `dir="auto"` على الحقل |
| «تغيير المؤسسة» مكرر مرتين (chip أعلى البطاقة + زر أسفلها) | Consistency | منخفض | الإبقاء على واحد (الزر السفلي) |
| لون الدخول amber بينما لون الـ tenant أخضر بعد الدخول (اللون يُطبّق بعد `system/context` فقط) | Branding | منخفض | حفظ لون الـ tenant مع بيانات `resolve` في localStorage وتطبيقه على `/login` |
| «Workspace Code /» إنجليزي داخل التسمية العربية | Wording | منخفض | `auth.workspace_code_label` عربي فقط |
| رسالة الخطأ تظهر 3 مرات (بانر + تحت كل حقل) — ظهر ذلك مع خطأ 500 في دخول super-admin ([لقطة](screens/sa-login__error-sql-desktop-dark.png)) | Error state | منخفض | خطأ عام = بانر واحد؛ أخطاء الحقول من 422 فقط |
| بالإنجليزية تبقى كل النصوص عربية تقريبًا والاتجاه RTL | i18n | منخفض (Phase 3) | G3 |

### 4.2 لوحة التحكم (`/`)
**اللقطات:** [Desktop فاتح](screens/dashboard__desktop-light.png) · [Desktop داكن](screens/dashboard__desktop-dark.png) · [Tablet فاتح](screens/dashboard__tablet-light.png) · [Tablet داكن](screens/dashboard__tablet-dark.png) · [Mobile فاتح](screens/dashboard__mobile-light.png) · [Mobile داكن](screens/dashboard__mobile-dark.png) · [Desktop EN](screens/dashboard__desktop-light-en.png) · [Mobile EN داكن](screens/dashboard__mobile-dark-en.png) · [تحميل Desktop](screens/dashboard__loading-desktop-light.png) · [تحميل Mobile](screens/dashboard__loading-mobile-dark.png) · [خطأ API](screens/dashboard__error-desktop-light.png)

**يعمل:** Hub بلاطات ملونة للوصول السريع مع اختصارات F2/F4، فلترة بالأقسام، بحث، skeleton للـ KPIs، الموبايل بشريط سفلي.

| المشكلة | الفئة | الخطورة | الإصلاح |
|---|---|---|---|
| «مرحباً بك في **app:** مؤسسة…» — `dashboard.welcome` فيه `:app` ولا يُمرَّر | i18n bug | عالٍ (أول شاشة يراها العميل) | `$t('dashboard.welcome', { app: companyName })` في `DashboardWelcomeBanner.vue:13` |
| البلاطات بتدرجات ألوان قوس قزح (أحمر/برتقالي/أزرق/بنفسجي…) لا علاقة لها بلون الـ tenant ولا بمعنى | Visual consistency | متوسط | خلفية محايدة + أيقونة بلون `--color-primary`، أو لون واحد لكل مجموعة |
| على Tablet العنوان ينكسر على 6 أسطر وزر «فاتورة توريد» مقصوص خارج الحاوية | Tablet | عالٍ | G1 + `text-2xl` على `md` |
| شريط فلاتر الـ Hub مقصوص بلا مؤشر تمرير («التعريفات وا…») | Layout | منخفض | ظل حافة/أسهم أو التفاف |
| بلاطة «خلطة وتجميع» بأيقونة كوب | Wording | متوسط | G4 |
| جرس الإشعارات يعرض 3 بينما بطاقة النواقص في الأصناف = 0 | Data consistency | منخفض | توحيد مصدر «النواقص» |

### 4.3 نقطة البيع (`/pos`)
**اللقطات:** [Desktop فاتح](screens/pos__desktop-light.png) · [Desktop داكن](screens/pos__desktop-dark.png) · [Tablet فاتح](screens/pos__tablet-light.png) · [Tablet داكن](screens/pos__tablet-dark.png) · [Mobile فاتح](screens/pos__mobile-light.png) · [Mobile داكن](screens/pos__mobile-dark.png) · [Desktop EN](screens/pos__desktop-light-en.png) · [Mobile EN داكن](screens/pos__mobile-dark-en.png) · [تحميل Desktop](screens/pos__loading-desktop-light.png) · [تحميل Mobile](screens/pos__loading-mobile-dark.png) · [خطأ API](screens/pos__error-desktop-light.png)

**يعمل:** الـ sidebar mini تلقائيًا، تخطيط ثلاثي (أقسام · أصناف · فاتورة) مناسب لـ 1366، بحث بارز مع F2، تبويبات فواتير، تبديل قطاعي/جملة، خصم سريع، حالة «الفاتورة فارغة» واضحة، زر الحفظ معطّل والسلة فارغة، الداكن ممتاز.

| المشكلة | الفئة | الخطورة | الإصلاح |
|---|---|---|---|
| **Tablet:** لوحة الأقسام تتراكب فوق منطقة الدفع، رأس الصفحة مقصوص (العميل/قطاعي-جملة خارج الشاشة)، زر الحفظ خارج الـ viewport | Tablet | عالٍ (أولوية tablet ثالثة لكنه مكسور) | تحت `lg`: أقسام كشريط أفقي فوق الأصناف، والسلة كـ drawer/شريط سفلي ثابت بالإجمالي وزر الدفع |
| **Mobile:** بطاقة صنف واحدة بعرض الشاشة (~5 أصناف فقط مرئية)، لا شريط سلة ثابت بالإجمالي، نص «لا توجد وردية مفتوحة» مقصوص | Mobile | عالٍ (أولوية mobile ثانية) | شبكة عمودين، شريط سفلي ثابت «السلة (n) · الإجمالي · دفع»، تحذير الوردية كبانر كامل |
| أسماء الأصناف مقصوصة بعد سطرين والكود مقصوص من البداية («…BAG-1K#») على 1366 | Layout | متوسط | 3 أعمدة بدل 4 على 1366 أو `line-clamp-3` + كود في tooltip |
| شارة المخزون «350 •» بلا وحدة ولا تسمية | Clarity | منخفض | «350 كجم» أو أيقونة مخزون |
| «لا توجد وردية مفتوحة» نص أحمر صغير في الرأس فقط، والبيع متاح شكليًا | Clarity / Flow | متوسط | بانر واضح مع زر «فتح وردية» (السلوك نفسه قرار backend/POSB) |
| تبويبات «كاش فوري / آجل ذمم / دفع جزئي» — قرار CTO: عقد واحد والآجل tender داخل `payments[]` وحذف «جزئي» من الواجهة | Product decision | متوسط (Phase 2) | ضمن إعادة تصميم POS (Phase 2) |
| **عند فشل تحميل الأصناف** (500/انقطاع) تعرض الشاشة «لا توجد أصناف في هذا القسم» و«الأكثر مبيعًا 0» — الكاشير يظن أن المخزن فارغ ([لقطة](screens/pos__error-desktop-light.png)) | Error state | عالٍ (الإنترنت ينقطع كثيرًا — Q11) | بانر «تعذّر تحميل الأصناف» + «إعادة المحاولة»، ومع بانر الأوفلاين المعتمد (Q11) يُمنع البيع بوضوح |
| شرائح الخصم صغيرة (26×21px) وزر «+» على البطاقة ~24px وزر إغلاق تبويب الفاتورة 16px | Touch | متوسط | G8 |
| chip «عرض المنيو — قريباً» معطل في الشريط الأساسي | Clutter | منخفض | إخفاؤه حتى يتوفر |
| المبلغ المدفوع يعرض `0.000` بثلاث خانات عشرية للنقد | Formatting | منخفض | المبالغ بخانتين عبر `useMoney`، الكميات فقط بثلاث |
| ألوان الأسعار عشوائية لكل بطاقة | Consistency | منخفض | G13 |

### 4.4 الأصناف (`/items`) والتصنيفات (`/categories`)
**اللقطات:** [Desktop فاتح](screens/items__desktop-light.png) · [Desktop داكن](screens/items__desktop-dark.png) · [Tablet فاتح](screens/items__tablet-light.png) · [Tablet داكن](screens/items__tablet-dark.png) · [Mobile فاتح](screens/items__mobile-light.png) · [Mobile داكن](screens/items__mobile-dark.png) · [Desktop EN](screens/items__desktop-light-en.png) · [Mobile EN داكن](screens/items__mobile-dark-en.png) · [تحميل Desktop](screens/items__loading-desktop-light.png) · [تحميل Mobile](screens/items__loading-mobile-dark.png) · [خطأ API](screens/items__error-desktop-light.png)

**اللقطات:** [Desktop فاتح](screens/categories__desktop-light.png) · [Desktop داكن](screens/categories__desktop-dark.png) · [Tablet فاتح](screens/categories__tablet-light.png) · [Tablet داكن](screens/categories__tablet-dark.png) · [Mobile فاتح](screens/categories__mobile-light.png) · [Mobile داكن](screens/categories__mobile-dark.png) · [Desktop EN](screens/categories__desktop-light-en.png) · [Mobile EN داكن](screens/categories__mobile-dark-en.png)

**يعمل:** KPIs مفيدة (قيمة المخزون، النواقص، العدد)، بحث + فلتر تصنيف + شرائح حالة، الجدول يعرض التكلفة والقطاعي والجملة والرصيد بالوحدة.

| المشكلة | الفئة | الخطورة | الإصلاح |
|---|---|---|---|
| شرائح الحالة مقصوصة من اليسار («المتوفر في…») بلا مؤشر تمرير | Layout | منخفض | التفاف أو ظل حافة |
| شارة التصنيف مقصوصة («مستلزمات وتعبئة») وعمود الكود ينكسر على 3 أسطر | Layout | منخفض | `whitespace-nowrap` + عرض أدنى للعمود |
| Tablet: أعمدة الأسعار/الرصيد/الإجراءات خارج الشاشة | Tablet | عالٍ | G1 |
| أيقونة الصفحة كوب قهوة | Wording | متوسط | G4 |
| التصنيفات: أيقونات لا علاقة لها بالاسم (هاتف/سماعة/فيشة/بطارية) | Consistency | منخفض | G14 |
| التصنيفات: كل الإجراءات خلف «⋯» فقط | Discoverability | منخفض | زر «تعديل» ظاهر + القائمة للباقي |
| الموبايل: الجدول يبدأ بعد 3 بطاقات KPI طويلة | Mobile | متوسط | G7 |

### 4.5 فواتير المبيعات (`/invoices`)
**اللقطات:** [Desktop فاتح](screens/invoices__desktop-light.png) · [Desktop داكن](screens/invoices__desktop-dark.png) · [Tablet فاتح](screens/invoices__tablet-light.png) · [Tablet داكن](screens/invoices__tablet-dark.png) · [Mobile فاتح](screens/invoices__mobile-light.png) · [Mobile داكن](screens/invoices__mobile-dark.png) · [Desktop EN](screens/invoices__desktop-light-en.png) · [Mobile EN داكن](screens/invoices__mobile-dark-en.png) · [تحميل Desktop](screens/invoices__loading-desktop-light.png) · [تحميل Mobile](screens/invoices__loading-mobile-dark.png) · [خطأ API](screens/invoices__error-desktop-light.png)

**يعمل:** KPIs + فلاتر زمنية سريعة (اليوم/أمس/7 أيام/الشهر) + بحث، تحديد متعدد، حالة «معتمدة» واضحة، اختصار للـ POS.

| المشكلة | الفئة | الخطورة | الإصلاح |
|---|---|---|---|
| رأس عمود خام `invoices.total_net` | i18n | عالٍ | G2 |
| شارة «نقدي (كاش)» مقصوصة، و«6,350 ج.م» تنكسر على سطرين في المدفوع | Layout | منخفض | G9 + `whitespace-nowrap` للمبالغ |
| زرا «تصفية الفواتير» و«خيارات وإجراءات» بجانب شرائح فلترة ظاهرة = طريقتان للفلترة | Consistency | منخفض | دمج الفلاتر في drawer واحد مع عداد |
| الموبايل: 4 بطاقات KPI قبل أول فاتورة | Mobile | متوسط | G7 |

### 4.6 تفاصيل الفاتورة (`/invoices/:id`) والطباعة (`/invoices/:id/print`)
**اللقطات:** [Desktop فاتح](screens/invoice-show__desktop-light.png) · [Desktop داكن](screens/invoice-show__desktop-dark.png) · [Tablet فاتح](screens/invoice-show__tablet-light.png) · [Tablet داكن](screens/invoice-show__tablet-dark.png) · [Mobile فاتح](screens/invoice-show__mobile-light.png) · [Mobile داكن](screens/invoice-show__mobile-dark.png) · [Desktop EN](screens/invoice-show__desktop-light-en.png) · [Mobile EN داكن](screens/invoice-show__mobile-dark-en.png) · [تحميل Desktop](screens/invoice-show__loading-desktop-light.png) · [تحميل Mobile](screens/invoice-show__loading-mobile-dark.png) · [خطأ API](screens/invoice-show__error-desktop-light.png)

**اللقطات:** [Desktop فاتح](screens/invoice-print__desktop-light.png) · [Desktop داكن](screens/invoice-print__desktop-dark.png) · [Tablet فاتح](screens/invoice-print__tablet-light.png) · [Tablet داكن](screens/invoice-print__tablet-dark.png) · [Mobile فاتح](screens/invoice-print__mobile-light.png) · [Mobile داكن](screens/invoice-print__mobile-dark.png) · [Desktop EN](screens/invoice-print__desktop-light-en.png) · [Mobile EN داكن](screens/invoice-print__mobile-dark-en.png)

**يعمل:** Skeleton ممتاز يطابق التخطيط، أوضاع (تفاعلي / حراري 80mm / A4)، أزرار طباعة وواتساب ونسخ، ملخص مالي بالبطاقات، بيانات العميل مع رابط كشف الحساب. الإيصال الحراري مقروء ومنظم.

| المشكلة | الفئة | الخطورة | الإصلاح |
|---|---|---|---|
| زر «إلغاء الفاتورة وعكس المخزون» أحمر كبير بجانب «رجوع» — سهل الضغط بالخطأ | Safety | متوسط | نقله لقائمة «المزيد» أو فصله بمسافة، مع التأكيد الحالي (سياسة الإلغاء Q7: سبب إلزامي وصلاحية `invoices.cancel`) |
| تبويب «فاتورة ضريبية» مقصوص، وعناوين البطاقات مقصوصة («الصافي المـ…»، «الشحن والتـ…») | Layout | منخفض | 3 بطاقات في الصف على `lg` أو تسميات أقصر |
| الوقت بأرقام هندية `١٠:٣٨ ص` | Formatting | متوسط | G6 |
| الموبايل: رقم الفاتورة في العنوان ينكسر ويُعكس ترتيبه («INV-MAIN-01- 20261008-DF9F») ومحوّل الأوضاع مقصوص | RTL / bidi | منخفض | رقم الفاتورة داخل `<bdi dir="ltr">` + `break-all`، والمحوّل بتمرير أفقي |
| الإيصال: حقل «الوقت» فارغ، التاريخ معكوس `08-10-2026`، إيموجي في سطر الشكر، «Retail ERP» ثابت في الذيل | Print / Branding | متوسط | تمرير الوقت، `<bdi>` للتاريخ، lucide/بدون إيموجي، ذيل من إعدادات الطباعة للـ tenant |
| «الكاشير» يعرض اسم المؤسسة (لأن مستخدم الأدمن اسمه اسم المؤسسة عند الـ provisioning) | Data / Provisioning | منخفض | backend: اسم المستخدم الأول «مدير النظام» لا اسم الشركة |

### 4.7 المشتريات (`/purchases`) وتسجيل فاتورة شراء (`/purchases/create`)
**اللقطات:** [Desktop فاتح](screens/purchases__desktop-light.png) · [Desktop داكن](screens/purchases__desktop-dark.png) · [Tablet فاتح](screens/purchases__tablet-light.png) · [Tablet داكن](screens/purchases__tablet-dark.png) · [Mobile فاتح](screens/purchases__mobile-light.png) · [Mobile داكن](screens/purchases__mobile-dark.png) · [Desktop EN](screens/purchases__desktop-light-en.png) · [Mobile EN داكن](screens/purchases__mobile-dark-en.png)

**اللقطات:** [Desktop فاتح](screens/purchase-create__desktop-light.png) · [Desktop داكن](screens/purchase-create__desktop-dark.png) · [Tablet فاتح](screens/purchase-create__tablet-light.png) · [Tablet داكن](screens/purchase-create__tablet-dark.png) · [Mobile فاتح](screens/purchase-create__mobile-light.png) · [Mobile داكن](screens/purchase-create__mobile-dark.png) · [Desktop EN](screens/purchase-create__desktop-light-en.png) · [Mobile EN داكن](screens/purchase-create__mobile-dark-en.png)

**يعمل:** حالة فراغ ممتازة في قائمة المشتريات، رادار إعادة الطلب ظاهر، نموذج الإنشاء مقسم لبطاقات (المورد · البنود · الملخص)، البنود تتحول لبطاقات على الموبايل.

| المشكلة | الفئة | الخطورة | الإصلاح |
|---|---|---|---|
| `purchases.final_net_total` خام، وعلى Tablet يتراكب فوق المبلغ («0purchases.final_net_total») | i18n / Layout | عالٍ | G2 |
| اختيار الصنف `<select>` أصلي غير قابل للبحث، وعلى Tablet ينكمش لمربع صغير غير قابل للاستخدام | Form UX | عالٍ | `BaseSelect` (searchable موجود) مع بحث بالباركود |
| زر «+ إضافة صنف» بأيقونة فوق النص (نمط مختلف عن باقي الأزرار) | Consistency | منخفض | `BaseButton` قياسي |
| «WAC» و«التكلفة المحملة» بدون شرح | Wording | منخفض | tooltip |
| الموبايل: زر POS العائم في الشريط السفلي فوق نموذج إدخال طويل | Mobile | منخفض | إخفاء الـ FAB في صفحات الإنشاء/التعديل |

### 4.8 العملاء (`/customers`) وكشف الحساب (`/customers/:id/statement`) والموردون (`/suppliers`)
**اللقطات:** [Desktop فاتح](screens/customers__desktop-light.png) · [Desktop داكن](screens/customers__desktop-dark.png) · [Tablet فاتح](screens/customers__tablet-light.png) · [Tablet داكن](screens/customers__tablet-dark.png) · [Mobile فاتح](screens/customers__mobile-light.png) · [Mobile داكن](screens/customers__mobile-dark.png) · [Desktop EN](screens/customers__desktop-light-en.png) · [Mobile EN داكن](screens/customers__mobile-dark-en.png) · [تحميل Desktop](screens/customers__loading-desktop-light.png) · [تحميل Mobile](screens/customers__loading-mobile-dark.png) · [خطأ API](screens/customers__error-desktop-light.png)

**اللقطات:** [Desktop فاتح](screens/customer-statement__desktop-light.png) · [Desktop داكن](screens/customer-statement__desktop-dark.png) · [Tablet فاتح](screens/customer-statement__tablet-light.png) · [Tablet داكن](screens/customer-statement__tablet-dark.png) · [Mobile فاتح](screens/customer-statement__mobile-light.png) · [Mobile داكن](screens/customer-statement__mobile-dark.png) · [Desktop EN](screens/customer-statement__desktop-light-en.png) · [Mobile EN داكن](screens/customer-statement__mobile-dark-en.png)

**اللقطات:** [Desktop فاتح](screens/suppliers__desktop-light.png) · [Desktop داكن](screens/suppliers__desktop-dark.png) · [Tablet فاتح](screens/suppliers__tablet-light.png) · [Tablet داكن](screens/suppliers__tablet-dark.png) · [Mobile فاتح](screens/suppliers__mobile-light.png) · [Mobile داكن](screens/suppliers__mobile-dark.png) · [Desktop EN](screens/suppliers__desktop-light-en.png) · [Mobile EN داكن](screens/suppliers__mobile-dark-en.png)

**يعمل:** صفوف المدينين مظللة بلون خفيف (فاتح وداكن)، زر «تحصيل» مباشر من الصف، كشف الحساب بفلاتر زمنية وطباعة، الموبايل في كشف الحساب مقروء.

| المشكلة | الفئة | الخطورة | الإصلاح |
|---|---|---|---|
| **بطاقات «إجمالي المديونيات / عدد المدينين / إجمالي العملاء» = 0 دائمًا** رغم وجود مديونيات 6,200 و8,500 في الجدول: `useCustomers.js:65` و`useSuppliers.js:65` تقرأ `response.data.metrics` بينما الـ API يرجع `summary` | Data bug | **حرج** | قراءة `response.data.summary` (FE فقط، سطر واحد لكل ملف) |
| **فلاتر «عليهم مديونية / المسواة / الدائنون» لا تعمل**: الواجهة ترسل `balance_type` والـ API يقرأ `debt_status` | Data bug | **حرج** | إرسال `debt_status` بالقيم `debtor/zero/creditor` (التحقق من القيم الحالية في الـ composable) |
| كشف الحساب: عمود «الرصيد» الجاري ينتهي بـ `-8,650` بينما بطاقة «الرصيد الختامي» = 32,500 — لا يوجد سطر رصيد افتتاحي، فالرقمان متناقضان ظاهريًا | Finance clarity | عالٍ | صف أول «رصيد افتتاحي / رصيد ما قبل الفترة» وحساب الجاري منه (يحتاج الحقل من الـ API — §6) |
| تسميات مكررة «(مدين) (مسحوبات)»، «(دائن) (سدادات / تحصيلات)» | Wording | منخفض | «مدين» و«دائن» مع tooltip |
| التاريخ ينكسر على سطرين في كشف الحساب | Layout | منخفض | `whitespace-nowrap` |
| شارة «نشط وفعال» مقصوصة | Layout | منخفض | G9 |
| Tablet: عمود الرصيد والإجراءات خارج الشاشة والبحث ينكمش لأيقونة | Tablet | عالٍ | G1 |

### 4.9 اليومية والورديات والخزينة (`/daily-journal`) والمصروفات (`/expenses`)
> لا توجد شاشة مستقلة باسم «الخزينة» أو «الورديات»؛ كلاهما داخل اليومية (`/daily-journal`).

**اللقطات:** [Desktop فاتح](screens/daily-journal__desktop-light.png) · [Desktop داكن](screens/daily-journal__desktop-dark.png) · [Tablet فاتح](screens/daily-journal__tablet-light.png) · [Tablet داكن](screens/daily-journal__tablet-dark.png) · [Mobile فاتح](screens/daily-journal__mobile-light.png) · [Mobile داكن](screens/daily-journal__mobile-dark.png) · [Desktop EN](screens/daily-journal__desktop-light-en.png) · [Mobile EN داكن](screens/daily-journal__mobile-dark-en.png) · [تحميل Desktop](screens/daily-journal__loading-desktop-light.png) · [تحميل Mobile](screens/daily-journal__loading-mobile-dark.png) · [خطأ API](screens/daily-journal__error-desktop-light.png)

**اللقطات:** [Desktop فاتح](screens/expenses__desktop-light.png) · [Desktop داكن](screens/expenses__desktop-dark.png) · [Tablet فاتح](screens/expenses__tablet-light.png) · [Tablet داكن](screens/expenses__tablet-dark.png) · [Mobile فاتح](screens/expenses__mobile-light.png) · [Mobile داكن](screens/expenses__mobile-dark.png) · [Desktop EN](screens/expenses__desktop-light-en.png) · [Mobile EN داكن](screens/expenses__mobile-dark-en.png)

**يعمل:** بانر واضح عند عدم وجود وردية، KPIs الوارد/المنصرف/الصافي/المتوقع بالدرج، تبويب فواتير اليوم ومصروفاته، والموبايل بعمودين (أفضل تخطيط KPI موبايل في التطبيق). المصروفات: تصنيفات سريعة وفلاتر.

| المشكلة | الفئة | الخطورة | الإصلاح |
|---|---|---|---|
| CTA الوردية مكرر بمصطلحات مختلفة (يومية/وردية) | Consistency | متوسط | G10 |
| «صافي النقدية -4,850» بالأخضر، وعند الصفر تظهر «‎-0» و«‎+0» | Semantics / Formatting | متوسط | G13 + عدم إظهار إشارة للصفر |
| عند فشل الـ API تظهر أصفار + بانر «لا توجد وردية مفتوحة» — رسالة خاطئة لأن الوردية ربما مفتوحة ([لقطة](screens/daily-journal__error-desktop-light.png)) | Error state | متوسط | G15: حالة خطأ بدل الاستنتاج من بيانات فارغة |
| «AM 10:38» إنجليزية، والتاريخ `type="date"` بصيغة `10/08/2026` | Formatting | متوسط | G6 |
| المصروفات: `mm/dd/yyyy` في الفلاتر | Formatting | متوسط | `BaseDatePicker` |
| تصنيفات المصروفات السريعة خاصة بمحمصة («صيانة مطاحن ومعدات») ومقصوصة من اليسار | Wording / Layout | منخفض | تصنيفات من إعدادات الـ tenant |
| زر «فتح يومية / وردية جديدة» بأيقونة فوق النص | Consistency | منخفض | `BaseButton` قياسي |

### 4.10 التقارير (`/reports`)
**اللقطات:** [Desktop فاتح](screens/reports__desktop-light.png) · [Desktop داكن](screens/reports__desktop-dark.png) · [Tablet فاتح](screens/reports__tablet-light.png) · [Tablet داكن](screens/reports__tablet-dark.png) · [Mobile فاتح](screens/reports__mobile-light.png) · [Mobile داكن](screens/reports__mobile-dark.png) · [Desktop EN](screens/reports__desktop-light-en.png) · [Mobile EN داكن](screens/reports__mobile-dark-en.png) · [تحميل Desktop](screens/reports__loading-desktop-light.png) · [تحميل Mobile](screens/reports__loading-mobile-dark.png) · [خطأ API](screens/reports__error-desktop-light.png)

**يعمل:** فلاتر زمنية سريعة + نطاق تاريخ + فرع، تبويبات تقارير متعددة، بطاقات واضحة لإيراد/تكلفة/ربح، طباعة A4.

| المشكلة | الفئة | الخطورة | الإصلاح |
|---|---|---|---|
| **الداكن:** بطاقة «صافي الربح الحقيقي» عليها شريط أبيض يخفي النص — `ReportsSalesTab.vue:70` فيها `via-teal-50` بدون `dark:via-*` | Dark mode bug | عالٍ (quick) | إضافة `dark:via-teal-950/50` |
| شريط التبويبات مقصوص («الخزينة والتحصـ») بلا مؤشر | Layout | منخفض | ظل حافة/التفاف |
| تبويب «ربحية الأصناف» بأيقونة كوب | Wording | منخفض | G4 |
| مصطلحات إنجليزية بين أقواس | Wording | منخفض | G12 |

### 4.11 الإعدادات (`/settings`)
**اللقطات:** [Desktop فاتح](screens/settings__desktop-light.png) · [Desktop داكن](screens/settings__desktop-dark.png) · [Tablet فاتح](screens/settings__tablet-light.png) · [Tablet داكن](screens/settings__tablet-dark.png) · [Mobile فاتح](screens/settings__mobile-light.png) · [Mobile داكن](screens/settings__mobile-dark.png) · [Desktop EN](screens/settings__desktop-light-en.png) · [Mobile EN داكن](screens/settings__mobile-dark-en.png) · [تحميل Desktop](screens/settings__loading-desktop-light.png) · [تحميل Mobile](screens/settings__loading-mobile-dark.png) · [خطأ API](screens/settings__error-desktop-light.png)

**يعمل:** تقسيم واضح (الهوية، المظهر والألوان، الفواتير والطباعة، تلجرام، الوحدات) مع وصف لكل قسم، وزر حفظ ثابت.

| المشكلة | الفئة | الخطورة | الإصلاح |
|---|---|---|---|
| **عند فشل تحميل الإعدادات يظهر النموذج فارغًا (placeholders فقط) وزر «حفظ التعديلات» مفعّل** — ضغطة حفظ قد تمسح اسم المؤسسة والهاتف ([لقطة](screens/settings__error-desktop-light.png)) | Error state / Data safety | عالٍ | تعطيل الحفظ وإخفاء النموذج حتى ينجح التحميل + `ErrorState` بإعادة المحاولة (G15) |
| بطاقة «إدارة آمنة 100%» نص تسويقي بلا وظيفة | Clutter | منخفض | حذفها |
| زر «حفظ التعديلات» بأيقونة فوق النص (نمط مختلف) | Consistency | منخفض | `BaseButton` |
| لا يوجد قسم ظاهر لرفع لوجو الـ tenant: المكوّن `Components/Settings/BrandingTab.vue` (لوجو فاتح/داكن + إظهار اللوجو في الطباعة) موجود لكنه **غير مستخدم في أي شاشة** — متطلب branding لكل محل | Branding | متوسط | ربطه كقسم «الشعار والهوية» في الإعدادات بعد التحقق من الـ endpoint (أو انتظار medialibrary — Phase 1) |

### 4.12 المستخدمون (`/users`) والأدوار (`/roles`) والفروع (`/stores`)
**اللقطات:** [Desktop فاتح](screens/users__desktop-light.png) · [Desktop داكن](screens/users__desktop-dark.png) · [Tablet فاتح](screens/users__tablet-light.png) · [Tablet داكن](screens/users__tablet-dark.png) · [Mobile فاتح](screens/users__mobile-light.png) · [Mobile داكن](screens/users__mobile-dark.png) · [Desktop EN](screens/users__desktop-light-en.png) · [Mobile EN داكن](screens/users__mobile-dark-en.png)

**اللقطات:** [Desktop فاتح](screens/roles__desktop-light.png) · [Desktop داكن](screens/roles__desktop-dark.png) · [Tablet فاتح](screens/roles__tablet-light.png) · [Tablet داكن](screens/roles__tablet-dark.png) · [Mobile فاتح](screens/roles__mobile-light.png) · [Mobile داكن](screens/roles__mobile-dark.png) · [Desktop EN](screens/roles__desktop-light-en.png) · [Mobile EN داكن](screens/roles__mobile-dark-en.png)

**اللقطات:** [Desktop فاتح](screens/stores__desktop-light.png) · [Desktop داكن](screens/stores__desktop-dark.png) · [Tablet فاتح](screens/stores__tablet-light.png) · [Tablet داكن](screens/stores__tablet-dark.png) · [Mobile فاتح](screens/stores__mobile-light.png) · [Mobile داكن](screens/stores__mobile-dark.png) · [Desktop EN](screens/stores__desktop-light-en.png) · [Mobile EN داكن](screens/stores__mobile-dark-en.png)

**يعمل:** المستخدمون: جدول واضح + بطاقات على الموبايل. الأدوار: مصفوفة بمجموعات وتحديد الكل/إلغاء الكل. الفروع: بطاقات لكل فرع بالكود والعنوان والإحصاءات.

| المشكلة | الفئة | الخطورة | الإصلاح |
|---|---|---|---|
| الأدوار: «الأصناف والمخزون **وخامات البن**» — نص عربي ثابت في `GetRolesMatrixAction.php:43` و`GetPermissionsTreeAction.php:36` | Wording / i18n | عالٍ | backend: مفاتيح `lang` + حذف «البن» (§6) |
| الأدوار: أربعة أدوار ثابتة كبطاقات، ولا زر «دور جديد» ظاهر | Feature discoverability | منخفض | إن كانت الأدوار المخصصة مدعومة: زر إضافة؛ وإلا توضيح |
| الأدوار: روابط «تحديد الكل/إلغاء الكل» صغيرة (58 هدف < 40px على الديسكتوب) | Touch | متوسط | G8 |
| الأدوار: إيموجي 👑📦🛒💼 | Consistency | منخفض | G11 |
| الفروع: بطاقة KPI «الفرع الرئيسي» تعرض اسم الفرع بخط ضخم يملأ البطاقة (4 أسطر على Tablet) | Layout | منخفض | `text-lg line-clamp-2` |
| الفروع: صف الفلاتر يخرج من الحاوية على Tablet | Tablet | متوسط | G1 |
| الفروع: «إجمالي أصناف المخزون 65» بينما الأصناف 13 (مجموع الفروع) — التسمية مضللة | Clarity | منخفض | «أرصدة أصناف عبر الفروع» |

### 4.13 الخلطات (`/coffee-blender`)
**اللقطات:** [Desktop فاتح](screens/coffee-blender__desktop-light.png) · [Desktop داكن](screens/coffee-blender__desktop-dark.png) · [Tablet فاتح](screens/coffee-blender__tablet-light.png) · [Tablet داكن](screens/coffee-blender__tablet-dark.png) · [Mobile فاتح](screens/coffee-blender__mobile-light.png) · [Mobile داكن](screens/coffee-blender__mobile-dark.png) · [Desktop EN](screens/coffee-blender__desktop-light-en.png) · [Mobile EN داكن](screens/coffee-blender__mobile-dark-en.png)

**يعمل:** ملخص تكلفة وسعر مقترح وهامش ربح، أحجام سريعة، شرائح نسب لكل مكوّن.

| المشكلة | الفئة | الخطورة | الإصلاح |
|---|---|---|---|
| الشاشة كلها مصممة للقهوة: المسار `/coffee-blender`، «درجة التحضير/المعالجة: تحضير وسط»، «مستوى التجهيز: ناعم جدًا»، أحجام «ثمن/ربع/نصف كيلو» فقط | Positioning | عالٍ (Q13) | «الخلطات والتركيبات (Mix Builder)» عامة: وحدة من الصنف، حقل «نسبة هالك» اختياري، المسار `/mixes` مع redirect |
| شرائح النسب `input[type=range]` صغيرة للمس | Touch | متوسط | زيادة ارتفاع المقبض أو أزرار ± |
| يجب أن تكون خلف `FeatureGate` لـ `mixes.manage` (add-on) | Gating | متوسط | التحقق عند تنفيذ الـ add-on |

### 4.14 لوحة الـ Super-admin (`/super-admin/*`)
**اللقطات:** [Desktop فاتح](screens/sa-dashboard__desktop-light.png) · [Desktop داكن](screens/sa-dashboard__desktop-dark.png) · [Tablet فاتح](screens/sa-dashboard__tablet-light.png) · [Tablet داكن](screens/sa-dashboard__tablet-dark.png) · [Mobile فاتح](screens/sa-dashboard__mobile-light.png) · [Mobile داكن](screens/sa-dashboard__mobile-dark.png) · [Desktop EN](screens/sa-dashboard__desktop-light-en.png) · [Mobile EN داكن](screens/sa-dashboard__mobile-dark-en.png)

**اللقطات:** [Desktop فاتح](screens/sa-tenants__desktop-light.png) · [Desktop داكن](screens/sa-tenants__desktop-dark.png) · [Tablet فاتح](screens/sa-tenants__tablet-light.png) · [Tablet داكن](screens/sa-tenants__tablet-dark.png) · [Mobile فاتح](screens/sa-tenants__mobile-light.png) · [Mobile داكن](screens/sa-tenants__mobile-dark.png) · [Desktop EN](screens/sa-tenants__desktop-light-en.png) · [Mobile EN داكن](screens/sa-tenants__mobile-dark-en.png) · [تحميل Desktop](screens/sa-tenants__loading-desktop-light.png) · [تحميل Mobile](screens/sa-tenants__loading-mobile-dark.png) · [خطأ API](screens/sa-tenants__error-desktop-light.png)

**اللقطات:** [Desktop فاتح](screens/sa-tenant-show__desktop-light.png) · [Desktop داكن](screens/sa-tenant-show__desktop-dark.png) · [Tablet فاتح](screens/sa-tenant-show__tablet-light.png) · [Tablet داكن](screens/sa-tenant-show__tablet-dark.png) · [Mobile فاتح](screens/sa-tenant-show__mobile-light.png) · [Mobile داكن](screens/sa-tenant-show__mobile-dark.png) · [Desktop EN](screens/sa-tenant-show__desktop-light-en.png) · [Mobile EN داكن](screens/sa-tenant-show__mobile-dark-en.png)

**اللقطات:** [Desktop فاتح](screens/sa-plans__desktop-light.png) · [Desktop داكن](screens/sa-plans__desktop-dark.png) · [Tablet فاتح](screens/sa-plans__tablet-light.png) · [Tablet داكن](screens/sa-plans__tablet-dark.png) · [Mobile فاتح](screens/sa-plans__mobile-light.png) · [Mobile داكن](screens/sa-plans__mobile-dark.png) · [Desktop EN](screens/sa-plans__desktop-light-en.png) · [Mobile EN داكن](screens/sa-plans__mobile-dark-en.png)

**اللقطات:** [Desktop فاتح](screens/sa-app-versions__desktop-light.png) · [Desktop داكن](screens/sa-app-versions__desktop-dark.png) · [Tablet فاتح](screens/sa-app-versions__tablet-light.png) · [Tablet داكن](screens/sa-app-versions__tablet-dark.png) · [Mobile فاتح](screens/sa-app-versions__mobile-light.png) · [Mobile داكن](screens/sa-app-versions__mobile-dark.png) · [Desktop EN](screens/sa-app-versions__desktop-light-en.png) · [Mobile EN داكن](screens/sa-app-versions__mobile-dark-en.png)

**يعمل:** layout مستقل بلون بنفسجي يميّزه عن تطبيق المحل، skeleton في اللوحة، توزيع الباقات، قائمة المستأجرين مع الحالة، تفاصيل المستأجر بالإحصاءات والوحدات والمميزات، حالة فراغ في الإصدارات، قسم «الهوية واسم المنصة» في اللوحة (مكان واحد للـ branding — يطابق المتطلب).

| المشكلة | الفئة | الخطورة | الإصلاح |
|---|---|---|---|
| مفاتيح خام: `common.details` (زر التفاصيل)، `reports.total_sales` | i18n | عالٍ | G2 |
| عمود «الباقة المشتركة» في جدول المستأجرين فارغ (pill بلا نص) رغم أن الباقة Enterprise | Data / Display | متوسط | التحقق من حقل الباقة في الـ resource/الجدول |
| «منصة مخزني» ثابت في ملف اللغة بدل `platform_name` | Branding | عالٍ | G5 |
| أسعار الباقات المعروضة 299/599/999 لا تطابق المعتمد (449/899/1,799) — بيانات الـ seeder | Data | متوسط | ENTI track (backend) |
| زر «حذف» المستأجر ظاهر وأحمر في الجدول والتفاصيل، بينما القرار W0: الحذف معطل (403) ثم «أرشفة» | Safety / Product | عالٍ | إخفاؤه أو تحويله لـ «أرشفة» حسب W2 |
| «دخول كأدمن» بدون أي إشارة لسبب/مدة (Impersonation المعتمد يتطلب سببًا ومدة ~30 دقيقة وبانر) | Product | متوسط | عند تنفيذ Q-B1 |
| المميزات في تفاصيل المستأجر كلها «معطل» مع أن الباقة Enterprise تشملها — غير واضح أن هذه overrides وليست الحالة الفعلية | Clarity | متوسط | ثلاث حالات: «من الباقة / مفعّل يدويًا / معطّل يدويًا» |
| MRR يعرض `0.00` بينما باقي المبالغ بلا كسور | Formatting | منخفض | `useMoney` |
| «Slug»، «تحديث الميجريشن»، «OTA Updater» | Wording | منخفض | G12 |
| Tablet/Mobile: عمود الإجراءات مقصوص، وزر التفاصيل خارج البطاقة على الموبايل | Responsive | منخفض (لوحة ويب للـ desktop حسب Q-B5) | G1 |
| الإصدار النشط «v1.0.0» بينما المنصة «v1.0.135» | Clarity | منخفض | توضيح «آخر APK منشور» |

---

## 5. أهم 20 إصلاح UX مرتبة بالأولوية

> **الترتيب:** الخطورة × عدد المستخدمين المتأثرين ÷ الجهد. «الجهد» تقديري: S < نصف يوم، M ≤ يومين، L > يومين. أغلب الـ S تصلح كـ quick wins لـ UX-2 بعد اعتماد الـ CTO.

| # | الإصلاح | الخطورة | الجهد | الشاشات | الملفات المرجّحة |
|---|---|---|---|---|---|
| 1 | قراءة `summary` بدل `metrics` وإرسال `debt_status` بدل `balance_type` في العملاء والموردين | حرج | S | customers, suppliers | `Composables/useCustomers.js`، `Composables/useSuppliers.js` |
| 2 | إضافة الـ 44 مفتاح ترجمة الناقصة (ar+en) + فحص CI يمنع مفاتيح ناقصة | عالٍ | S–M | purchases, invoices, super-admin, reports… | `lang/{ar,en}/*.php` (`i18n-guardian`) |
| 3 | إصلاح ترحيب الـ Dashboard «app:» | عالٍ | S | dashboard | `Components/Dashboard/DashboardWelcomeBanner.vue` |
| 4 | إصلاح بطاقة صافي الربح في الداكن (`dark:via-*`) | عالٍ | S | reports | `Components/Reports/ReportsSalesTab.vue` |
| 5 | حذف chip الكود الثابت وplaceholder أكواد العملاء | عالٍ | S | connect | `Components/Auth/WorkspaceStepInput.vue`، `lang/*/auth.php` |
| 6 | Tablet: sidebar mini افتراضيًا تحت 1280px + `DataTable` بـ scroll أفقي ظاهر أو بطاقات تحت `lg` | عالٍ | M | كل الشاشات | `Layouts/SpaLayout.vue`، `Layouts/SuperAdminLayout.vue`، `Components/Common/DataTable.vue` |
| 7 | اختيار الصنف في فاتورة الشراء بـ `BaseSelect` searchable | عالٍ | S–M | purchase-create | `Components/Purchases/CreatePurchaseItemsCard.vue` |
| 8 | إزالة شعار/أيقونات القهوة من الـ shell (sidebar، POS rail، titlebar، splash، super-admin) واستخدام لوجو الـ tenant/أيقونة محايدة | عالٍ | S | كل الشاشات | `SpaLayout.vue`، `SuperAdminLayout.vue`، `DesktopTitlebar.vue`، `SystemBootSplash.vue` |
| 9 | إعادة صياغة نصوص القهوة: «خامات البن» (backend)، «صانع الخلطات وتوليفات البن»، أيقونات الأصناف/التقارير، تصنيفات المصروفات | عالٍ | S–M | roles, nav, items, reports, expenses | `useNavigation.js`، `lang/*`، Actions الصلاحيات (backend) |
| 10 | POS mobile: شبكة عمودين + شريط سلة سفلي ثابت (الإجمالي + دفع) + تحذير وردية كامل | عالٍ | M | pos | `Components/POS/*`، `views/POS/PosView.vue` (ضمن حدود المهمة) |
| 11 | POS tablet: الأقسام كشريط أفقي والسلة drawer — يمنع التراكب فوق الدفع | عالٍ | M | pos | `Components/POS/*` |
| 12 | إخفاء/تحويل زر حذف المستأجر لـ «أرشفة» حسب قرار W0/W2 | عالٍ | S | sa-tenants, sa-tenant-show | `Components/SuperAdmin/TenantsTable.vue`، `views/SuperAdmin/SuperAdminTenantShowView.vue` |
| 13 | `ErrorState` مشترك بزر «إعادة المحاولة» عند فشل الـ API بدل أصفار/فراغ صامت — **أولًا** الإعدادات (نموذج فارغ قابل للحفظ) وPOS (يبدو المخزن فارغًا) واليومية | عالٍ (settings, POS) / متوسط | M | كل الشاشات | `Components/Common/` (جديد) + composables، `Composables/useSettings.js` |
| 14 | أرقام غربية لكل وقت/تاريخ + `BaseDatePicker` بدل `type="date"` + `<bdi>` للتواريخ في الإيصال | متوسط | S–M | header, invoice-show, daily-journal, expenses, print | `useFormatters`، `useInvoiceShow.js`، `ExpensesFilterBar.vue`، `DailyJournalView.vue`، `InvoicePrintView.vue` |
| 15 | KPI compact بعمودين على الموبايل (نموذج اليومية) | متوسط | S | items, invoices, purchases, customers, stores | `Components/Common/MetricCard.vue` |
| 16 | أهداف لمس ≥ 44px (خصم POS، «+» البطاقة، تحديد الكل، أيقونات الجداول، أزرار الرأس) | متوسط | S–M | pos, roles, tables | `BaseButton`، مكونات POS/Roles |
| 17 | توحيد CTA ومصطلح الوردية (زر واحد + مؤشر حالة) | متوسط | S | daily-journal, header, sidebar | `SpaLayout.vue`، `Components/DailyJournal/*` |
| 18 | كشف الحساب: صف رصيد افتتاحي وجاري متسق مع الرصيد الختامي | عالٍ | M (يحتاج حقل API) | customer/supplier statement | `Components/Customers/*` + backend |
| 19 | `StatusBadge` بلا التفاف + مبالغ بلا التفاف + نصوص أقصر | منخفض | S | customers, stores, invoices, expenses | `Components/Common/StatusBadge.vue` |
| 20 | ألوان دلالية: سالب أحمر/موجب أخضر، لون سعر موحد في POS، بلاطات Dashboard بلون الـ tenant | منخفض–متوسط | S–M | daily-journal, pos, dashboard | `useMoney`، `DashboardAppMenuHub.vue`، بطاقات POS |

**بعد الـ 20 (backlog):** نقل نصوص `SpaLayout`/`useNavigation` لملفات اللغة + `dir` من الـ locale (G3)، استبدال الإيموجي بـ lucide (G11)، أيقونات التصنيفات (G14)، تبسيط المصطلحات الإنجليزية (G12)، شاشة Mix Builder عامة (§4.13)، حالة «من الباقة/يدوي» للمميزات في super-admin.

---

## 6. فجوات backend مطلوبة من `backend-architect`

| # | الفجوة | الأثر | المطلوب |
|---|---|---|---|
| B1 | **دخول super-admin على قاعدة central نظيفة يرجع 500**: مسار الدخول يستعلم المتجر النشط للمستخدم (المرجّح `User::stores()`/`Store::getMainStore()`) ثم يعدّ النواقص من `items`، أي يستعلم جداول tenant على الاتصال central (`no such table: stores` ثم `no such table: items`). ظهر هذا على قاعدة مُنشأة بـ `migrate --seed` فقط. أكملتُ المراجعة بإنشاء جداول tenant فارغة في القاعدة المؤقتة كـ workaround. [لقطة الخطأ](screens/sa-login__error-sql-desktop-dark.png) | يمنع دخول المنصة على VPS إنتاج جديد | تخطّي حل المتجر/إحصاءات المخزون للمستخدم المركزي (أو مسار SuperAdminLogin مستقل — IDEN-1.x) + اختبار Feature على قاعدة central بلا جداول tenant |
| B2 | رسالة الخطأ أعلاه تعرض SQL ومسار الملف في الواجهة (مع `APP_DEBUG=true` محليًا) | تسريب معلومات إن ظهر في staging | التأكد أن `ApiLogin` يرجع رسالة عامة دائمًا (`__('auth.failed')`) |
| B3 | عناوين مجموعات الصلاحيات نص عربي ثابت فيه «خامات البن» (`GetRolesMatrixAction.php:43`، `GetPermissionsTreeAction.php:36`) | مخالفة i18n + لغة قهوة | مفاتيح `lang/{ar,en}/permissions.php` |
| B4 | كشف حساب العميل/المورد: لا يوجد «رصيد افتتاحي للفترة» في الاستجابة لعرض رصيد جارٍ متسق | تناقض ظاهري بين الجدول والبطاقة | حقل `opening_balance` (string decimal) في استجابة الكشف |
| B5 | `CustomerController@index` (`total_debt`) و`SupplierController@index` (`total_payable`) يحسبان المجموع بـ `(float)` | مخالفة قاعدة bcmath | إرجاع string decimal (الواجهة تعرض فقط) |
| B6 | المستخدم الأول عند الـ provisioning يأخذ اسم المؤسسة فيظهر «الكاشير: <اسم الشركة>» في الإيصالات | وضوح الإيصال | اسم افتراضي «مدير النظام» أو اسم صاحب الحساب من النموذج |
| B7 | أسعار وحدود الباقات في `PlansAndFeaturesSeeder` (299/599/999) ≠ المعتمد (449/899/1,799 وحدود §4) | عرض أسعار خاطئة في super-admin | ضمن مهام ENTI |

**ملاحظة توثيق (لـ `docs-historian`):** [`ui-design-system.md`](../ui-design-system.md) ما زال يصف Primary أزرق (`bg-blue-600`) ومكتبة Flowbite، بينما الواقع لون الـ tenant عبر `var(--color-primary)` (افتراضي أخضر/amber) — يحتاج تحديثًا مع اعتماد quick wins.

---

## 7. قرارات مطلوبة من الـ CTO

1. **اعتماد قائمة الـ quick wins** لتنفيذها في UX-2 — حسب الخطة لا كود قبل الاعتماد. المقترح كدفعة أولى (جهد S): البنود 1، 3، 4، 5، 8، 12، 15، 19 من §5، ثم 2 و7 (S–M).
2. **Tablet:** هل نصلح الـ responsive للـ tablet في Phase 1 (البند 6 و11) أم نكتفي بالـ sidebar mini والجداول القابلة للتمرير وننقل POS tablet لإعادة التصميم في Phase 2؟ (الترتيب المعتمد للأجهزة: desktop ← mobile ← tablet.)
3. **شعار التطبيق قبل وجود لوجو للـ tenant:** أيقونة محايدة (اقتراح: `Store`) أم الحرف الأول من اسم المحل؟
4. **تسمية الوردية:** «وردية» فقط في كل الواجهة (والـ «يومية» للتقرير اليومي)؟
5. **Dashboard Hub:** الإبقاء على البلاطات الملونة أم توحيدها بلون الـ tenant؟

---

## ملحق أ — نتائج الفحص الآلي

> «أهداف لمس < 40px» = عناصر تفاعلية مرئية عرضها أو ارتفاعها أقل من 40px في الـ viewport وحتى 3 أضعاف ارتفاعه (يشمل الروابط النصية داخل الجداول، فهو مؤشر لا حكم). القيم من الوضع الفاتح العربي. «overflow أفقي» = `scrollWidth − innerWidth` على الموبايل (0 لا يعني عدم القص: الـ layout يستخدم `overflow-hidden` فيُخفي المحتوى بدل التمرير — راجع G1).

| الصفحة | أهداف لمس < 40px (desktop / tablet / mobile) | overflow أفقي (mobile) | مفاتيح ترجمة خام | كلمات قهوة | براند ثابت | أخطاء console | طلبات API فاشلة |
|---|---|---|---|---|---|---|---|
| login | 5 / 5 / 5 | 0 | — | — | — | 0 | — |
| connect | 1 / 1 / 1 | 0 | — | — | — | 0 | — |
| dashboard | 19 / 16 / 11 | 0 | — | البن، خلطة، مطحنة | — | 0 | — |
| pos | 32 / 32 / 29 | 0 | — | البن، مطحنة | — | 0 | — |
| items | 39 / 37 / 8 | 0 | — | البن، القهوة، قهوة، كافيه، مطحنة | — | 0 | — |
| categories | 14 / 12 / 9 | 0 | — | البن، مطحنة | — | 0 | — |
| invoices | 14 / 11 / 5 | 0 | `invoices.total_net` | مطحنة | — | 0 | — |
| invoice-show | 14 / 12 / 9 | 0 | — | مطحنة | — | 0 | — |
| invoice-print | 2 / 2 / 2 | 0 | — | — | Retail ERP | 0 | — |
| purchases | 11 / 7 / 6 | 0 | — | مطحنة | — | 0 | — |
| purchase-create | 11 / 10 / 6 | 0 | `purchases.final_net_total` | البن، مطحنة | — | 0 | — |
| customers | 41 / 39 / 36 | 0 | — | قهوة، كافيه، محمصة، مطحنة | — | 0 | — |
| customer-statement | 12 / 10 / 7 | 0 | — | مطحنة | — | 0 | — |
| suppliers | 24 / 22 / 23 | 0 | — | البن، القهوة، مطحنة | — | 16 (Vue warn) | — |
| daily-journal | 12 / 9 / 6 | 0 | — | مطحنة | — | 0 | — |
| expenses | 18 / 16 / 13 | 0 | — | البن، مطحنة | — | 8 (Vue warn) | — |
| reports | 24 / 21 / 19 | 0 | — | مطحنة | — | 0 | — |
| settings | 9 / 7 / 4 | 0 | — | مطحنة | — | 0 | — |
| users | 13 / 10 / 8 | 0 | — | مطحنة | — | 0 | — |
| roles | 58 / 55 / 47 | 0 | — | البن، مطحنة | — | 0 | — |
| stores | 23 / 17 / 15 | 0 | — | البن، مطحنة | — | 16 (Vue warn) | — |
| coffee-blender | 16 / 7 / 11 | 0 | — | البن، مطحنة | — | 0 | — |
| sa-dashboard | 8 / 6 / 4 | 0 | — | — | baraa, مخزني | 0 | — |
| sa-tenants | 13 / 10 / 7 | 0 | `common.details` | — | baraa, مخزني | 0 | — |
| sa-tenant-show | 57 / 57 / 37 | 0 | `reports.total_sales` | البن | baraa, مخزني | 8 (Vue warn) | — |
| sa-plans | 7 / 5 / 3 | 0 | — | — | مخزني | 0 | — |
| sa-app-versions | 8 / 6 / 4 | 0 | — | — | مخزني | 0 | — |

**قراءة الجدول:**
- عمود «كلمات قهوة» يخلط بين **بيانات الـ seeder** (أسماء فروع/عملاء/أصناف مثل «مطحنة وفرع تجزئة» الظاهرة في محدد الفرع بكل صفحة) و**نصوص الواجهة**. المصادر الخاصة بالواجهة فقط مذكورة في G4. ملاحظة جانبية: `RichDemoDataSeeder` نفسه قهوة بالكامل، فلو استُخدم لعروض البيع يحتاج نسخة عامة (بقالة/ملابس/إكسسوارات).
- «baraa» = دومين المنصة في روابط المستأجر (متوقع حاليًا)، و«مخزني» في شاشات super-admin = اسم المنصة الثابت (G5). في شاشات المحل كلمة «مخزني» صفة (تحويل مخزني) فاستُبعدت.
- في `sa-tenant-show` تظهر مفاتيح الصلاحيات (`pos.access`، `invoices.create`…) كسطر فرعي تحت اسم الميزة **عمدًا** — استُبعدت من عمود المفاتيح الخام لكنها مصطلحات تقنية (G12).
- أخطاء console كلها تحذيرات Vue من نوع واحد (G16)، مكررة لكل مقاس/ثيم.

## ملحق ب — مفاتيح ترجمة مستخدمة وغير موجودة (44)

> ناتج فحص ساكن لكل `$t('file.key')` / `t('file.key')` في `resources/js` مقابل `lang/ar` و`lang/en`. كل المفاتيح التالية ناقصة في اللغتين.

| المفتاح | مستخدم في |
|---|---|
| `common.details` | `Components/Returns/ReturnsTable.vue`, `Components/SuperAdmin/TenantsTable.vue` |
| `common.filter_search` | `Components/Common/FilterDrawer.vue` |
| `common.save_changes` | `Components/Categories/CategoryFormModal.vue`, `Components/Customers/CustomerFormModal.vue`, `Components/Stores/StoreFormModal.vue`, `Components/Suppliers/SupplierFormModal.vue` |
| `common.saving` | `Components/SuperAdmin/TenantUnitsCard.vue`, `views/SuperAdmin/SuperAdminUnitsView.vue` |
| `common.server_error` | `views/Items/CategoriesView.vue` |
| `contacts.customer_initial_balance_hint` | `Components/Customers/CustomerFormModal.vue` |
| `contacts.initial_balance_hint` | `Components/Suppliers/SupplierFormModal.vue` |
| `contacts.pay_to` | `Components/Suppliers/SupplierPaymentModal.vue` |
| `contacts.payment_notes_placeholder` | `Components/Customers/CustomerPaymentModal.vue`, `Components/Suppliers/SupplierPaymentModal.vue` |
| `contacts.save_customer` | `Components/Customers/CustomerFormModal.vue` |
| `contacts.save_supplier` | `Components/Suppliers/SupplierFormModal.vue` |
| `dashboard.company_title` | `Components/Common/DesktopTitlebar.vue`, `Components/Navigation/DesktopSidebar.vue` |
| `inventory.cancel_transfer` | `Components/StockTransfers/StockTransfersTable.vue` |
| `inventory.min_selling_price` | `Components/Items/ItemFormModal.vue` |
| `inventory.quantity` | `Components/Items/ItemStockAdjustModal.vue` |
| `inventory.view_transfer_details` | `Components/StockTransfers/StockTransfersTable.vue` |
| `invoices.payment_ewallet` | `Components/Invoices/InvoicesTable.vue` |
| `invoices.total_net` | `Components/Invoices/InvoicesTable.vue` |
| `purchases.final_net_total` | `Components/Purchases/CreatePurchaseSummaryCard.vue` |
| `purchases.remaining_debt` | `Components/Purchases/CreatePurchaseSummaryCard.vue` |
| `reports.low_stock_only` | `Components/Reports/ReportsFilterBar.vue` |
| `reports.no_data_desc` | `Components/Reports/ReportsCustomersTab.vue`, `Components/Reports/ReportsExpensesTab.vue`, `Components/Reports/ReportsInventoryTab.vue`, `Components/Reports/ReportsItemsTab.vue`, `Components/Reports/ReportsStoresTab.vue` |
| `reports.no_data_title` | `Components/Reports/ReportsCustomersTab.vue`, `Components/Reports/ReportsExpensesTab.vue`, `Components/Reports/ReportsInventoryTab.vue`, `Components/Reports/ReportsItemsTab.vue`, `Components/Reports/ReportsStoresTab.vue` |
| `reports.out_of_stock_only` | `Components/Reports/ReportsFilterBar.vue` |
| `reports.total_sales` | `Components/SuperAdmin/TenantStatsGrid.vue` |
| `super.account_status_and_plan_modal_title` | `Components/SuperAdmin/TenantStatusModal.vue` |
| `super.creating_org_status` | `Components/SuperAdmin/CreateTenantModal.vue` |
| `super.custom_db_warning` | `Components/SuperAdmin/CreateTenantModal.vue` |
| `super.db_host_label` | `Components/SuperAdmin/CreateTenantModal.vue` |
| `super.db_name_label` | `Components/SuperAdmin/CreateTenantModal.vue` |
| `super.db_pass_label` | `Components/SuperAdmin/CreateTenantModal.vue` |
| `super.db_user_label` | `Components/SuperAdmin/CreateTenantModal.vue` |
| `super.extend_days_placeholder` | `Components/SuperAdmin/EditTenantStatusModal.vue`, `Components/SuperAdmin/TenantStatusModal.vue` |
| `super.extend_subscription_label` | `Components/SuperAdmin/EditTenantStatusModal.vue`, `Components/SuperAdmin/TenantStatusModal.vue` |
| `super.hide_custom_db_settings` | `Components/SuperAdmin/CreateTenantModal.vue` |
| `super.initial_password_label` | `Components/SuperAdmin/CreateTenantModal.vue` |
| `super.initial_password_placeholder` | `Components/SuperAdmin/CreateTenantModal.vue` |
| `super.manage_tenant_status_modal_title` | `Components/SuperAdmin/EditTenantStatusModal.vue` |
| `super.show_custom_db_settings` | `Components/SuperAdmin/CreateTenantModal.vue` |
| `super.status_cancelled` | `Components/SuperAdmin/EditTenantStatusModal.vue`, `Components/SuperAdmin/TenantStatusModal.vue` |
| `super.status_pending` | `Components/SuperAdmin/EditTenantStatusModal.vue`, `Components/SuperAdmin/TenantStatusModal.vue` |
| `treasury.drawer_balanced` | `Components/DailyJournal/CloseShiftModal.vue` |
| `treasury.drawer_surplus` | `Components/DailyJournal/CloseShiftModal.vue` |
| `treasury.record_expense_btn` | `Components/DailyJournal/QuickExpenseModal.vue` |
