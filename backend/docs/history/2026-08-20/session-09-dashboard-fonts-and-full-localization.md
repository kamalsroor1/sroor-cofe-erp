# سجل تعديل: تحسين وتكبير خطوط لوحة التحكم ومطابقة الترجمة الكاملة للغات

* **التاريخ والوقت:** 2026-08-20 11:20
* **الدور المفعل:** Frontend UI/UX & Localization Specialist
* **الهدف من التعديل:**
  1. تكبير وتنسيق خطوط لوحة التحكم الرئيسية (`Dashboard.vue`) لتوفير وضوح بصري فائق، وقراءة مريحة للأرقام والعناوين وجداول الفواتير ورادار النواقص.
  2. تدقيق وإكمال منظومة الترجمة بنسبة 100% (Zero Hardcoded Strings) عبر تفعيل التحميل التلقائي لكافة حزم اللغات (`lang/ar/*.php`, `lang/en/*.php`, `ar.json`, `en.json`) وربطها بالـ Inertia Middleware.

---

## 1. الملفات التي تم إنشاؤها أو تعديلها (Modified & Created Files)
* `[MODIFIED]` `backend/resources/js/Pages/Dashboard.vue` - تكبير الخطوط والعناوين والأرقام المالية وشارات الفواتير، واستبدال كافة النصوص الثابتة بدوال الترجمة `$t(...)`.
* `[MODIFIED]` `backend/app/Http/Middleware/HandleInertiaRequests.php` - تحميل حزم الترجمة ديناميكياً لكافة ملفات اللغات المتاحة مع دعم الـ JSON والـ Fallback التلقائي.
* `[MODIFIED]` `backend/lang/ar/dashboard.php` & `backend/lang/en/dashboard.php` - توسيع مفاتيح لوحة التحكم لتشمل كافة مسميات البطاقات والرادار والمبيعات.
* `[NEW]` `backend/lang/en.json` & `backend/lang/en/*.php` (`common.php`, `inventory.php`, `contacts.php`, `expenses.php`, `purchases.php`, `reports.php`, `treasury.php`, `invoices.php`) - استكمال الترجمة الإنجليزية الكاملة لكافة أقسام وموديولات النظام.
* `[NEW]` `backend/lang/ar/invoices.php` - مفاتيح ترجمة فواتير المبيعات وحالات الدفع.

---

## 2. القرارات المعمارية والتصميمية (Key Decisions)
* **الهرمية البصرية وتكبير الخطوط:**
  * العناوين الرئيسية: ترقيتها من `text-xl` إلى `text-2xl lg:text-3xl font-black`.
  * الأرقام المالية: ترقيتها من `text-2xl` إلى `text-3xl lg:text-4xl font-black font-mono`.
  * عناوين البطاقات والجداول: ترقيتها إلى `text-sm font-bold`.
  * نصوص الجداول ورادار النواقص: ترقيتها إلى `text-sm font-bold`.
* **الترجمة الديناميكية الشاملة:**
  * ربط مصفوفة `translations` في Inertia لتحميل أي ملف يتم إضافته في `lang/ar/` أو `lang/en/` تلقائياً دون الحاجة لتعديل الـ Middleware يدوياً.

---

## 3. الاختبارات والتحقق (Verification & Testing)
* [x] تم تشغيل `npm run build` بنجاح وتجميع حزم Vite بدون أي أخطاء (2.27s).
* [x] تم تشغيل واجتياز كافة اختبارات النظام (34 اختباراً و 138 assertion).
* [x] التحقق من اختفاء النصوص الثابتة والتوافق مع الوضعين الفاتح والداكن و RTL.
