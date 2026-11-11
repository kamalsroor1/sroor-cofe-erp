# سجل تعديل: المبالغ والكميات في الـ API كنصوص عشرية بثلاث خانات

* **التاريخ والوقت:** 2026-11-12
* **الدور المفعل:** Backend Architect + Frontend UI + QA Testing
* **الهدف من التعديل:** اختبار الـ E2E لرحلة الاشتراك (`e2e/flows/saas-onboarding-flow.spec.js`) كشف إن ردود API كتير بترجّع الفلوس والكميات كأرقام JSON بـ `(float)` (مثلاً `62.5`) بدل نص عشري بثلاث خانات (`'62.500'`)، وده مخالف للقاعدة الذهبية 1. القيم كانت صحيحة رقميًا، لكن الخطر تقريب الـ float عند العملاء والتقارير.

---

## 1. الملفات التي تم إنشاؤها أو تعديلها (Modified Files)

### Backend
* `[MODIFIED]` كل الـ Resources اللي كانت بتعمل `(float)` على فلوس أو كميات: `ItemResource`، `InvoiceResource`، `InvoiceSummaryResource`، `InvoiceItemResource`، `PurchaseResource`، `PurchaseItemResource`، `ReturnResource`، `ReturnItemResource`، `CustomerResource`، `SupplierResource`، `POSCustomerResource`، `ExpenseResource`، `CashShiftResource`، `StockMovementResource`، `StockTransferItemResource`، `StoreStockResource`، `POSItemResource`، `PosGridItemResource`، `PosLookupItemResource` — بقت `Decimal::normalize((string) …)`.
* `[MODIFIED]` `app/Support/PosItemPrices.php` — `price_wholesale` و`min_selling_price` نصوص.
* `[MODIFIED]` الـ Actions: تقرير الـ Z للوردية، اليومية، لوحة التحكم (overview + analytics)، كشف حساب العميل والمورد، حركة الصنف، bootstrap الـ POS، ملخص المصروفات، اقتراحات إعادة الطلب، `/me`، سياق النظام، وكل تقارير `Reports/*`.
* `[MODIFIED]` ملخصات القوائم في `InvoiceController` و`PurchaseController` و`ReturnController` و`CustomerController` و`SupplierController` و`ItemController` و`PaymentController`، ورد `POSController`، و`SupplierBalanceService`.
* `[MODIFIED]` `app/Actions/Blends/CalculateBlendCostAction.php` — اتعادت كتابته بـ bcmath بدل حسابات float.

### Frontend (`backend/resources/js`)
* `[MODIFIED]` إغلاق الوردية (`CloseShiftModal`، `useDailyJournal`) + `[NEW]` `helpers/shiftCash.js` لفرق الدرج بحساب عشري دقيق.
* `[MODIFIED]` كروت ملخص كشف حساب العميل/المورد، لوحة التحكم (`DashboardAnalyticsRow`)، المرتجعات (3 مكونات)، تفاصيل المشتريات، تبويبات التقارير (أصناف/مخزون)، `useCreatePurchase`، `useCreateReturn`.
* `[MODIFIED]` الأصناف (`ItemsTable` — عمود سعر الجملة بقى يعرض سعر الجملة الحقيقي مش الحد الأدنى)، حركة الصنف، التحويلات، الخلاط.

### Tests / E2E
* `[NEW]` `tests/Feature/Api/MoneyAsDecimalStringApiTest.php` — بيع 0.250 كجم × 250 ثم الصنف والفاتورة وملخص القائمة وتقرير Z بنصوص دقيقة، وفحص "مفيش float" على كشوف الحساب والتقارير والـ POS وملخصات القوائم.
* `[MODIFIED]` 12 ملف اختبار كانت بتثبّت النوع float — اتشدّت لنصوص دقيقة (`assertJsonPath` / `assertSame`)، وزيادة حالة تقريب للخلاط.
* `[NEW]` `tests/js/pos-cart-lines.test.js`، `tests/js/shift-cash.test.js`.
* `[MODIFIED]` `playwright.config.js` — `webServer` بيقرا `SROOR_HTTP_PORT` زي `e2e/utils/local-stack.js`.

---

## 2. القرارات المعمارية والمنطق البرمجي (Key Decisions)
* كل مبلغ/كمية في الـ API = نص بثلاث خانات عبر `App\Support\Money\Decimal::normalize` (half-up)، ومفيش حساب float.
* النسب المئوية (`margin`، `share_pct`، `margin_percentage`، `monthly_margin`، `profit_margin`) نصوص بنفس الـ scale اللي بتتحسب بيه (1 أو 2) لأن الواجهة بتعرضها خام جنب `%`.
* الحقول المخفية بالصلاحية (`cost_price`، `min_selling_price`) لسه `null` مش `'0.000'`.
* الخلاط: الجرامات half-up على خانتين، والهيل `Decimal::mul` (half-up) — فرق 0.001 ممكن في مدخلات بثلاث خانات عن السلوك القديم (اللي كان بيقص).
* الواجهة: `Number()` للعرض بس؛ أي حساب فلوس عبر `helpers/decimal.js`؛ استبدال `||` بـ `??` أو `isPositive()` لأن `'0.000'` truthy.

---

## 3. الاختبارات والتحقق (Verification & Testing)
* `php artisan test` كامل: 3620 اختبار — 3619 نجح، 1 skipped (موجود من قبل)، 0 فشل.
* `composer analyse`: 0 أخطاء، والـ baseline ما اتغيرش. `pint --dirty`: نظيف.
* `npm run test:js`: 245 نجح، 0 فشل. `npm run build`: نجح (ملفات `public/build` الناتجة ما اتعملهاش commit).
* `npx playwright test e2e/flows/saas-onboarding-flow.spec.js --project=desktop` على stack الـ Docker من الـ worktree (`SROOR_HTTP_PORT=8001`، من غير خدمة vite): نجح بالكامل، ومفيش أي app-defect للـ float.
* مراجعة `code-reviewer`: APPROVE WITH NITS؛ اتعالج: scale الـ `share_pct` (خانة واحدة زي ما بيتحسب)، اختبارات دقيقة للخلاط + حالة تقريب، وفحص "مفيش float" على endpoints أكتر.
* ما اتختبرش: tablet/mobile للـ E2E، والفحص اليدوي في المتصفح لكل شاشة اتعدلت (اتغطت بالـ build والـ JS tests والـ E2E بس).

## 4. الخطوات التالية المقترحة (Next Recommended Steps)
1. تقرير Z: `total_expenses` دايمًا `'0.000'` لأن جدول `cash_shifts` مفيهوش العمود ده أصلًا.
2. باقي الـ float: سلاسل الرسوم في `DashboardAnalyticsService`، `abc_data` في `InventoryAnalyticsService`، MRR في `SuperAdminAnalyticsService`، وطباعة التقارير `ReportPrintController` (number_format على float).
3. `ShiftController` بيقرر رسالة العجز/الزيادة بـ `(float)` و`==`.
4. الخلاط في الواجهة بيحسب بـ float ومش بيستخدم `/coffee-blender/calculate`.
5. `POSHeader::getItemPrice` بيعرض `min_selling_price` كسعر جملة، عكس `priceFor` في السلة.
6. فورم الشراء/المرتجع بيحسب الإجماليات الحية بـ float، والتحويل بيبعت الكمية `parseFloat`.
