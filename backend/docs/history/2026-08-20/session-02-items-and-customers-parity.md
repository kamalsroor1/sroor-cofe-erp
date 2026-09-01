# سجل تعديل: مطابقة صفحات حركة الأصناف، دليل العملاء، وكشف الحساب بنسبة 100%

* **التاريخ والوقت:** 2026-08-20 02:25
* **الدور المفعل:** Full-stack Architect & QA Specialist
* **الهدف من التعديل:** تحقيق المطابقة التامة والشاملة (100% Feature Parity) بين شاشات Livewire القديمة وشاشات Inertia.js + Vue 3 الجديدة لصفحات: كشف وتتبع حركة الصنف، دليل العملاء وسندات التحصيل السريع، وكشف حساب العميل التراكمي.

---

## 1. الملفات التي تم إنشاؤها أو تعديلها (Modified Files)
* `[MODIFIED]` `backend/app/Http/Controllers/ItemController.php` - إضافة حساب إحصائيات الفترة التجميعية (`total_in`, `total_out`, `net_movement`, `current_scope_stock`) وتمريرها لواجهة Vue.
* `[MODIFIED]` `backend/resources/js/Pages/Items/Movements.vue` - إضافة بطاقات المؤشرات الـ 4، أزرار الفلاتر الزمنية السريعة (اليوم، الأسبوع، الشهر، السنة، الكل)، زر طباعة تقرير الحركات A4، وتنسيق كافة أنواع الحركات المخزنية.
* `[MODIFIED]` `backend/app/Http/Controllers/CustomerController.php` - ربط التحصيل بـ `PaymentService::recordCustomerPayment` و `CustomerBalanceService::updateBalance`، استخدام `CustomerBalanceService::getCustomerLedger` لكشف الحساب، وإضافة ميزة تفعيل/تعطيل حساب العميل `toggleActive`.
* `[MODIFIED]` `backend/resources/js/Pages/Customers/Index.vue` - إضافة زر التحصيل السريع، زر تفعيل/تعطيل العميل المباشر، حماية الحذف مع أسباب المنع، وتصفية حسب حالة المديونية (مدينين / خالصين / دائنين).
* `[MODIFIED]` `backend/resources/js/Pages/Customers/Statement.vue` - إضافة بطاقات الملخص المالي الـ 3 (المسحوبات / التحصيلات / صافي المديونية)، أزرار الفترات الزمنية السريعة، وأعمدة كشف الحساب المتتالية مع الرصيد بعد الحركة.
* `[MODIFIED]` `backend/routes/web.php` & `backend/routes/tenant.php` - تسجيل راوت تفعيل/تعطيل العميل `POST /customers/{id}/toggle-active`.
* `[NEW]` `backend/tests/Feature/CustomerAndItemMovementInertiaTest.php` - اختبارات تحقق شاملة للعمليات المالية والتحصيل وحساب الأرصدة وحركات المخزون.

---

## 2. القرارات المعمارية والمنطق البرمجي (Key Decisions)
* **الدقة المالية التامة باستخدام `CustomerBalanceService`:** استخدام دالة `getCustomerLedger` الرسمية التي تجمع فواتير المبيعات وسندات القبض ومرتجعات المبيعات بشكل زمني متسلسل مع حساب الرصيد التراكمي `balance_after` عبر دوال `bcmath`.
* **التحصيل السريع الموثوق:** تسجيل سندات القبض عبر `PaymentService` داخل `DB::transaction()` مع التوليد التلقائي لرقم السند وتحديث رصيد العميل وتسجيل العملية في سجل التدقيق الأمني.
* **إحصائيات فترة الصنف المتوافقة:** احتساب الوارد والمنصرف وصافي الحركة وفق الفلاتر الزمنية والمخزن المختار مع الحفاظ على الرصيد الحالي للصنف.

---

## 3. الاختبارات والتحقق (Verification & Testing)
* [x] تم تشغيل `npm run build` بنجاح وتجميع حزم Vite بدون أي أخطاء.
* [x] تم تشغيل واجتياز اختبارات `CustomerAndItemMovementInertiaTest` بنسبة 100% (6/6 نجاح).
* [x] تم اجتياز اختبارات `CustomerIndexTest` بنسبة 100%.
* [x] خلو الكود من أي نصوص ثابتة والتوافق مع الوضعين الفاتح والداكن و RTL.
