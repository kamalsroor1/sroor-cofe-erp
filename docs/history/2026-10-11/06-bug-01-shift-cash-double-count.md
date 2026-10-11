# سجل تعديل: BUG-01 — الكاش المتوقع في الوردية بيتحسب مرتين
* **التاريخ والوقت:** 2026-10-11 11:38 (ضمن commit `7a8cee19`، W2 الدفعة 5)
* **الدور المفعل:** غير موثّق (الإصلاح جزء من commit الدفعة 5).
* **الهدف:** الكاش المتوقع في الدرج (الوردية)، واليومية (شاشة + طباعة A4)، والداشبورد، وملخص الخزينة يحسبوا كل بيع نقدي **مرة واحدة**، ومدفوعات **الفرع نفسه بس**.

## الاكتشاف
* اكتشفته specs الـ E2E بتاعة Antigravity في PR #9 (`pos-cash-sale-business-flow.spec.js`، `shift-close-business-flow.spec.js`)، واتعلّم هناك بـ `test.fail()` باسم BUG-01. PR #9 لسه draft ومش مدموج.

## السبب الجذري
* `ShiftService::calculateShiftTotals()` كان بيجمع:
  * `invoices.paid_amount` للفواتير `cash` و`partial` في الوردية، **و**
  * كل سندات القبض النقدية (`payments` بـ `customer_id`) — ومنها سندات `PAY-INV-*` اللي الفاتورة نفسها بتكتبها لكل سطر دفع وقت البيع.
* النتيجة: كل بيع نقدي اتحسب **مرتين**. وكمان سطور الدفع غير النقدية في البيع المقسّم كانت بتتحسب كاش (لأن `paid_amount` بيشملها)، والتحصيلات اللاحقة على نفس الفاتورة كانت بتتعد تاني.
* **جدول `payments` ماكانش فيه `store_id`:** السندات غير المرتبطة بفاتورة أو مشتريات (تحصيل مديونية، دفعة لمورد على الحساب) كانت بتتحسب في درج **كل** الفروع، ومدفوعات الموردين النقدية ماكانتش متقيدة بالفرع.
* `GetDailyJournalAction` كان فيه نفس النمط (`paid_amount` + كل سندات المستأجر، وبحساب `float`).

## 1. الملفات المعدلة
* `[NEW]` `backend/app/Services/DailyCashFlowService.php` — **مصدر واحد** للكاش اللي دخل/خرج من الدرج في يوم تجاري: سندات الدفع النقدية فقط (`collectedAtSale`، `customerReceipts`، مدفوعات الموردين) + المصروفات النقدية. تقرأ منه اليومية (شاشة + A4) والداشبوردات وملخص الخزينة.
* `[NEW]` `backend/database/migrations/tenant/2026_10_11_100000_add_store_id_to_payments_table.php` — `payments.store_id` nullable + index، **بدون backfill**.
* `[MODIFIED]` `backend/app/Services/ShiftService.php` — `calculateShiftTotals()` بقى من السندات النقدية بس، في نافذة `[opened_at, closed_at ?? now]`، ومقيّد بفرع الوردية؛ أضاف `total_customer_receipts`؛ الجمع بـ `Decimal::normalize` (بدون `float`).
* `[MODIFIED]` `backend/app/Services/PaymentService.php` — كل سند جديد بيسجّل `store_id`: الصريح، وإلا الفرع النشط للطلب، وإلا فرع الفاتورة/المشتريات.
* `[MODIFIED]` `backend/app/Models/Payment.php` — scopes: `forStore` (السند القديم بدون `store_id` بيتنسب لفرع فاتورته/مشترياته، وإلا للفرع الافتراضي لمستخدمه، وإلا للفرع الرئيسي)، `excludingCancelledInvoices`، `collectedAtSale`، `customerReceipts`.
* `[MODIFIED]` `backend/app/Actions/Shifts/{GetDailyJournalAction,GetDailyJournalPrintDataAction}.php`، `backend/app/Actions/Dashboard/{GetDashboardOverviewAction,GetTenantDashboardAnalyticsAction}.php`، `backend/app/Actions/Treasury/GetTreasurySummaryAction.php`، `backend/resources/views/layouts/print-daily-journal-a4.blade.php`.
* `[NEW]` `backend/tests/Feature/ShiftExpectedCashTest.php` (9 tests)، `backend/tests/Feature/Stores/StoreIsolationCashFlowTest.php`.

## 2. القرارات التقنية
* **مصدر واحد = السندات:** كل مبلغ كاش وصل الدرج هو سند واحد بالظبط. البديل المرفوض: الاعتماد على `invoices.paid_amount` (لا يفرّق بين طرق الدفع ولا يعرف وقت التحصيل).
* **`store_id` nullable بدون backfill:** الصفوف القديمة تتنسب وقت القراءة عبر `forStore` بدل تعديل بيانات تاريخية.
* **أثر مالي:** أرقام الكاش المعروضة (المتوقع في الوردية المفتوحة، اليومية، الداشبورد، الخزينة) هتقل مقارنة بقبل الإصلاح بقيمة الحساب المكرر — ده التصحيح، مش عجز جديد.
* **الورديات المقفولة قبل الإصلاح:** القيم المخزنة `expected_cash_balance` و`cash_difference` (ومعاهم `total_payments_collected`) كانت **أعلى من الحقيقة** في أي وردية فيها بيع نقدي، فالفرق المسجّل ظهر كعجز وهمي. و`total_cash_sales` القديم كان بيشمل سطور الدفع غير النقدية في البيع المقسّم. **لم يُعد كتابتها** — مفيش migration ولا أمر بيعدّل ورديات مقفولة. أي تصحيح تاريخي (إعادة حساب أو تقرير مقارنة) **يحتاج موافقة صريحة من الـ CTO** وbackup قبله.

## 3. التحقق والاختبار
* `ShiftExpectedCashTest` يغطي: كل بيع مرة واحدة وفرع الوردية بس؛ إغلاق الوردية يخزن القيمة المصححة؛ تحصيل على فاتورة partial في نفس الوردية لا يتكرر؛ الجزء النقدي بس من البيع المقسّم؛ استبعاد كاش قبل فتح الوردية أو على فاتورة ملغاة؛ دفعة مورد من فرع آخر؛ سند بدون فرع صريح يتسجل على الفرع النشط؛ سند قديم بدون فرع يتنسب لفرع مستخدمه؛ اليومية تحسب كل بيع مرة.
* الـ tests دي جزء من الـ suite اللي عدّت على CI في `0f55e048`: **2831 passed، 1 skipped** (انظر [05](05-w2-batch-5-archive-purge-store-scoping-app-updates.md)). نتيجة "فشل قبل الإصلاح" لهذه الـ tests **غير موثّقة**.
* specs الـ E2E في PR #9 لسه عليها `test.fail()`؛ لم يُعد تشغيلها بعد الإصلاح.

## 4. ملاحظات / ديون تقنية
* بعد دمج القاعدة في PR #9: شيل `test.fail()` الخاص بـ BUG-01 والتأكد إن الـ specs بتعدّي.
* قرار المرتجع (`refund_amount` لا يُحفظ، والوردية تحسب `total_amount` كله كاش مُرتجع) من [ملخص 2026-10-09](../2026-10-09/summary.md) §6 لسه **مفتوح**؛ `ShiftService` لسه بيطرح `return_documents.total_amount`.
* `ShiftService::closeShift()` لسه فيه رسالة عربية مكتوبة في الكود و`number_format((float) …)` و«ج.م» ثابتة — مخالفة قديمة لم تُعالج ضمن الإصلاح.
