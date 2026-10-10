# 📖 وثيقة المكون والصفحة: دفتر اليومية وحركات الخزينة والورديات (`DailyJournalView`)

## 1. النظرة العامة والتحليل التشغيلي:
* **اسم الصفحة:** دفتر اليومية وحركات الخزينة والورديات (Daily Journal & Cash Shifts)
* **المسار (Route):** `/daily-journal` (مع اسم مستعار `/shifts`)
* **اسم المسار (Route Name):** `daily_journal.index`
* **الصلاحية المطلوبة (Permission):** `daily_journal.view`
* **الملف الرئيسي:** `resources/js/views/DailyJournal/DailyJournalView.vue` (~143 سطرًا).
* **الغرض والتحليل التشغيلي:**
  * المركز المالي اليومي لمتابعة الإيرادات والمصروفات وحركات الخزينة لحظة بلحظة ("يوم بيوم").
  * إدارة دورة حياة ورديات الكاشير: افتتاح الوردية، الرصيد الافتتاحي، قفل الوردية، ومطابقة النقدية الفعلية مع المتوقعة.
  * استخراج تقرير الإقفال المالي النهائي للوردية (Z-Report) مع تفصيل المبيعات النقدية والإلكترونية والآجلة.
  * تسجيل المصروفات النثرية والتشغيلية المباشرة من الخزينة اليومية.
  * طباعة تقرير اليومية الضريبي والمالي الشامل A4 لمراجع الحسابات والإدارة.

---

## 2. هيكلية وشجرة المكونات (Component Tree):
```text
DailyJournalView.vue (~143 lines)
├── DailyJournalShiftBanner.vue       <-- شريط حالة الوردية النشطة للمستخدم (اسم الكاشير، توقيت الافتتاح، الرصيد الافتتاحي)
├── DailyJournalMetricsGrid.vue       <-- بطاقات المؤشرات المالية: إجمالي المقبوضات، المصروفات، سداد الموردين، الرصيد المتوقع
├── DailyJournalBreakdownCards.vue    <-- تفصيل حركات التدفقات النقدية (Inflows vs Outflows) وقائمة ورديات اليوم
├── DailyJournalAuditTrail.vue        <-- سجل العمليات والأحداث المالية لليوم المختار
├── DailyJournalOpenShiftModal.vue    <-- نافذة افتتاح وردية كاشير جديدة وتحديد العهدة الافتتاحية
├── DailyJournalCloseShiftModal.vue   <-- نافذة قفل الوردية، إدخال الجرد الفعلي، وعرض العجز أو الزيادة
├── DailyJournalExpenseModal.vue      <-- نافذة تسجيل مصروف نثري فوري مخصوم من الخزينة
└── DailyJournalZReportModal.vue      <-- نافذة معاينة وطباعة تقرير الـ Z-Report التفصيلي
```

---

## 3. العناصر المشتركة ومخازن الحالة:
* **المكونات المشتركة:** `PageHeader.vue`, `BaseButton.vue`, `MetricCard.vue`, `AppModal.vue`.
* **المخازن المستخدمة:** `useAuthStore` (التحقق من صلاحية قفل وافتتاح الوردية `daily_journal.close_shift`).
* **الـ Composables:** `useFormatters.js` لتنسيق المبالغ المالية (`formatMoney`) وتنسيق الفروقات النقدية.

---

## 4. الاعتماديات والـ APIs:
* `GET /api/v1/daily-journal`: جلب بيانات اليومية للفرع والتاريخ المحددين:
  * **الكنترولر:** `App\Http\Controllers\Api\DailyJournalController@index`
  * **Form Request:** `GetDailyJournalRequest`
* `GET /api/v1/shifts/current`: جلب بيانات الوردية المفتوحة حالياً للكاشير.
* `POST /api/v1/shifts/open`: افتتاح وردية كاشير جديدة (`daily_journal.close_shift`):
  * **Form Request:** `OpenShiftRequest`
  * **Action:** `App\Actions\Shifts\OpenShiftAction`
* `POST /api/v1/shifts/close`: قفل الوردية الحالية وتسجيل الجرد الفعلي (`daily_journal.close_shift`):
  * **Form Request:** `CloseShiftRequest`
  * **Action:** `App\Actions\Shifts\CloseShiftAction`
* `GET /api/v1/shifts/{id}/z-report`: استخراج تقرير Z-Report الرسمي للوردية.
* `POST /api/v1/expenses`: تسجيل مصروف نثري جديد (`StoreExpenseRequest`).
* مسار الطباعة المباشر: `GET /daily-journal/print` (عرض Blade مخصص مقاس A4).

---

## 5. نطاق الفروع وعزل البيانات (Store Scoping):
* تتطلب كافة استعلامات اليومية والورديات ترويسة `X-Store-Id` لتحديد الخزينة والفرع.
* يتم التدقيق الصارم عبر `ClientStoreGuard::concrete($request)`.
* أي محاولة للاستعلام عن خزينة فرع غير مصرح للمستخدم به تنتج استجابة **403 Forbidden** بكود `store_access_denied`.

---

## 6. القواعد المالية الصارمة ويوم العمل (Financial Integrity):
1. **توقيت قطع يوم العمل (`Business-Day Cutoff`):**
   * يعتمد دفتر اليومية على ساعة المستأجر وتوقيت القطع `TenantSettings::businessDayCutoff()` (الافتراضي `00:00` أو وقت مخصص كـ `03:00`).
   * الوردية المفتوحة الساعة 01:30 فجراً قبل وقت القطع 03:00 تُنسب برمجياً ومحاسبياً إلى يوم العمل السابق وتُقيد في دفاتره.
2. **الدقة المالية `DECIMAL(12,3)` و `bcmath`:**
   * حسابات رصيد الخزينة المتوقع:
     $$\text{Expected Cash} = \text{Opening Balance} + \text{Cash Sales} + \text{Customer Payments} - \text{Expenses} - \text{Supplier Payments}$$
   * تنفذ كافة العمليات الحسابية بدقة 3 خانات عشرية باستخدام `bcmath` لضمان عدم وجود أدنى فارق هللات.
