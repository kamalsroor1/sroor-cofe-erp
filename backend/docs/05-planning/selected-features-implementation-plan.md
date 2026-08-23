# خطة تنفيذ الحزمة التطويرية المختارة (Implementation Plan)

توثق هذه الخطة المعمارية والبرمجية كافة التفاصيل الفنية لبناء الميزات الخمس المختارة بدقة تامة، مع الالتزام الصارم بالقواعد المعمارية للمشروع (`DECIMAL(12,3)`، معالجات `bcmath`، تنفيذ العمليات المالية داخل `DB::transaction()`، واستخدام `lockForUpdate()`).

---

## 🎯 الميزات المشمولة في الخطة (Target Features)

1. **تحليل حركة البضاعة وربحية الأصناف (ABC Analysis):** تصنيف الأصناف (ذهب A، متوسط B، راكد C) لحساب الأكثر ربحاً والراكدة لتصفيتها.
2. **لوحة مؤشرات الأداء التفاعلية (Interactive Dashboard):** رسوم بيانية للمبيعات، متوسط سلة الشراء (Basket Size)، وساعات الذروة.
3. **التعريب واللهجة التجارية المصرية العامة (System Localization):** نقل كافة الرسائل والواجهات لملفات `lang/ar/` بمصطلحات تجارية مصرية عامة.
4. **مراكز التكلفة وقائمة أرباح وخسائر الفروع وعربات التوزيع (Cost Centers & P&L per Branch/Van):** صافي ربحية كل فرع وعربة توزيع بعد خصم مصروفاته.
5. **مساعد المشتريات الذكي والتنبؤ بالنواقص (Smart Reorder Assistant):** تتبع سرعة نفاد الأصناف واقتراح كميات الشراء وتوليد أوامر الشراء.

---

## 1. تحليل حركة البضاعة والأصناف الأكثر ربحية والراكدة (ABC Analysis)

### 1.1 الهدف والمعادلات الرياضية
* **تصنيف ABC Analysis:**
  * **الفئة A (الأصناف الذهبية):** تمثل 80% من إجمالي الأرباح أو الإيرادات (عادة 20% من الأصناف).
  * **الفئة B (الأصناف المتوسطة):** تمثل 15% من إجمالي الأرباح.
  * **الفئة C (الأصناف الراكدة / بطيئة الحركة):** تمثل 5% من الأرباح أو التي لم تُباع خلال فترة $N$ يوم لتصفيتها.
* **المعادلات:**
  $$\text{Gross Profit} = \text{Revenue} - (\text{Quantity} \times \text{Weighted Avg Cost})$$
  $$\text{Velocity} = \frac{\text{Quantity Sold}}{\text{Days in Period}}$$

### 1.2 الملفات المقترح إنشاؤها وتعديلها
* `[NEW]` `app/Services/InventoryAnalyticsService.php`: خدمة متخصصة لحسابات الـ ABC، وتحديد الأصناف سريعة وبطيئة الحركة ومعدل الدوران.
* `[NEW]` `app/Livewire/Reports/AbcAnalysisReport.php`: مكون Livewire تفاعلي مخصص لعرض التصنيف مع فلاتر (الفروع، الفئات، الفترة الزمنية).
* `[NEW]` `resources/views/livewire/reports/abc-analysis-report.blade.php`: واجهة عرض تحليل ABC (جدول تفاعلي، كروت تلخيصية، ومؤشرات بصرية ملونة).
* `[MODIFY]` `app/Livewire/ReportsIndex.php` & `resources/views/livewire/reports-index.blade.php`: إضافة تبويب مخصص لتحليل ABC وسرعة حركة الأصناف.

---

## 2. لوحة مؤشرات الأداء التفاعلية (Interactive Executive Dashboard)

### 2.1 المكونات البصرية والتحليلية
* **الرسم البياني للمبيعات اليومية والشهرية (Daily & Monthly Trend):** مقارنة المبيعات الحالية مع الفترات السابقة.
* **متوسط قيمة سلة الشراء (Average Order Value / Basket Size):**
  $$\text{Average Basket Size} = \frac{\text{Total Net Sales}}{\text{Total Invoices Count}}$$
* **تحليل ساعات الذروة (Peak Sales Hours Heatmap):** رسم بياني يوضح ساعات اليوم الأكثر نشاطاً في المبيعات لجدولة العمالة والورديات.
* **توزيع طرق الدفع (Cash vs Digital Liquidity Split):** نسبة النقدية مقابل إنستاباي والمحافظ والفيزا.

### 2.2 الملفات المقترح إنشاؤها وتعديلها
* `[NEW]` `app/Services/DashboardAnalyticsService.php`: تجميع إحصائيات المبيعات، سلة الشراء، وتحليل أوقات الذروة مع الـ Caching لتحقيق سرعة فائقة.
* `[MODIFY]` `app/Livewire/Dashboard.php` & `resources/views/livewire/dashboard.blade.php`: دعم مكتبة الرسوم البيانية الخفيفة المدمجة مع Alpine.js وتصميم كروت المؤشرات التفاعلية.

---

## 3. التعريب الشامل وصياغة المصطلحات التجارية المصرية (Localization)

### 3.1 الهدف ومبادئ التعريب
* استبدال كافة الكلمات الثابتة المباشرة في الـ Blade والـ Livewire بملفات ترجمة `lang/ar/` عبر دوال `__('messages.xxx')`.
* صياغة مصطلحات تجارية مصرية واضحة يفهمها أي كاشير ومحاسب بدون تعقيد:
  * "درج الكاش / الفكة الافتتاحية"
  * "سند قبض / استلام نقدية"
  * "سند صرف / نثريات"
  * "حساب العميل / مديونية سابقة"
  * "تحويل بين الخزن / سحب وإيداع"
  * "جرد المخزن / عجز وزيادة"
* فصل الترجمات إلى ملفات منطقية:
  * `lang/ar/pos.php`: خاص بنقاط البيع والفواتير والطباعة.
  * `lang/ar/inventory.php`: خاص بالأصناف والمخازن والتحويلات.
  * `lang/ar/treasury.php`: خاص بالخزن واليومية والتحويلات النقدية.
  * `lang/ar/crm.php`: خاص بالعملاء والموردين وكشوف الحسابات.
  * `lang/ar/reports.php`: خاص بالتقارير والإحصائيات.

### 3.2 الملفات المقترح إنشاؤها وتعديلها
* `[NEW]` `lang/ar/pos.php`, `lang/ar/inventory.php`, `lang/ar/treasury.php`, `lang/ar/crm.php`, `lang/ar/reports.php`.
* `[MODIFY]` كافة شاشات الـ Blade ومكونات Livewire لاستخدام مفاتيح الترجمة الموحدة.

---

## 4. مراكز التكلفة وقائمة أرباح وخسائر الفروع وعربات التوزيع (P&L per Branch/Van)

### 4.1 الهيكل البرمجي وقواعد البيانات
* **ربط المصروفات بمراكز تكلفة أو فروع/عربات:**
  * إضافة حقل `cost_center` إلى جدول `expenses` (مثل: إيجارات، كهرباء ومرافق، رواتب، وقود وزيوت سيارات التوزيع، صيانة، شحن، إكراميات).
* **قائمة الدخل والأرباح والخسائر للفرع (Branch P&L):**
  $$\text{Gross Profit}_{\text{store}} = \text{Store Revenue} - \text{Store COGS}$$
  $$\text{Net Operating Profit}_{\text{store}} = \text{Gross Profit}_{\text{store}} - \text{Store Direct Expenses}$$

### 4.2 الملفات المقترح إنشاؤها وتعديلها
* `[NEW]` `database/migrations/2026_08_19_020000_add_cost_center_to_expenses_table.php`: إضافة تصنيف مركز التكلفة.
* `[NEW]` `app/Services/ProfitLossService.php`: خدمة احتساب الدخل الإجمالي، تكلفة البضاعة المباعة، المصروفات التشغيلية، وصافي ربح كل فرع/سيارة.
* `[NEW]` `app/Livewire/Reports/BranchProfitLossReport.php`: واجهة تقرير الأرباح والخسائر المقارنة للفروع وعربات التوزيع.
* `[NEW]` `resources/views/livewire/reports/branch-profit-loss-report.blade.php`: شاشة عرض قائمة الدخل مع إمكانية التصدير والطباعة A4.
* `[MODIFY]` `app/Livewire/ExpenseIndex.php` & `resources/views/livewire/expense-index.blade.php`: إتاحة اختيار مركز التكلفة والفرع/السيارة عند تسجيل المصروف.

---

## 5. مساعد المشتريات الذكي والتنبؤ بنفاد المخزون (Smart Reorder Assistant)

### 5.1 المنطق والحسابات التنبؤية
* **معدل الاستهلاك اليومي (Daily Run Rate / Consumption):**
  $$\text{Daily Consumption} = \frac{\text{Sales Quantity over last } N \text{ days}}{N}$$
* **الأيام المتبقية لنفاد الصنف (Days Until Stockout):**
  $$\text{Days of Stock} = \frac{\text{Current Stock}}{\text{Daily Consumption}}$$
* **نقطة إعادة الطلب والكمية المقترحة (Reorder Point & Suggested Qty):**
  $$\text{Suggested Order Qty} = (\text{Target Days of Cover} \times \text{Daily Consumption}) - \text{Current Stock}$$
* **توليد أمر الشراء (Draft Purchase Order):**
  * زر في الشاشة يجمع الأصناف المقترحة للمورد المختار ويفتح شاشة `PurchaseCreate` معبأة تلقائياً بالأصناف والكميات المحسوبة.

### 5.2 الملفات المقترح إنشاؤها وتعديلها
* `[NEW]` `app/Services/ReorderAssistantService.php`: فحص أرصدة المخازن وحساب معدلات الاستهلاك اليومي والأيام المتبقية.
* `[NEW]` `app/Livewire/Purchases/SmartReorderIndex.php`: شاشة مساعد المشتريات الذكية مع مؤشرات تحذيرية ملونة (أحمر: سينفد خلال 3 أيام، أصفر: سينفد خلال أسبوع، أخضر: رصيد آمن).
* `[NEW]` `resources/views/livewire/purchases/smart-reorder-index.blade.php`: واجهة مساعد المشتريات مع زر تحويل بنقرة واحدة لأمر شراء.

---

## 🧪 خطة التحقق والاختبار (Verification Plan)

### الاختبارات الآلية (Automated Tests):
1. **اختبارات تحليل ABC:** `tests/Feature/AbcAnalysisFeatureTest.php` للتحقق من دقة تصنيف الأصناف ونسب الأرباح.
2. **اختبارات لوحة المؤشرات:** `tests/Feature/DashboardAnalyticsFeatureTest.php` للتحقق من حساب متوسط سلة الشراء وساعات الذروة.
3. **اختبارات أرباح وخسائر الفروع (P&L):** `tests/Feature/BranchProfitLossFeatureTest.php` لمطابقة صافي أرباح كل فرع ومصروفاته.
4. **اختبارات مساعد المشتريات:** `tests/Feature/SmartReorderFeatureTest.php` للتحقق من حساب الأيام المتبقية وتوليد أوامر الشراء.
5. **تشغيل فحص شامل لكافة الاختبارات السابقة (135+ اختبار) للتأكد من عدم وجود أي تعارض.**

### التحقق اليدوي (Manual Verification):
1. فتح لوحة المؤشرات والتحقق من تفاعلية الرسوم البيانية في الوضع الليلي والنهاري واتجاه RTL.
2. تجربة تقرير P&L ومقارنة فرع رئيسي مع عربة توزيع.
3. تجربة مساعد المشتريات وتوليد فاتورة شراء تجريبية من المقترحات الذكية.
4. فحص جودة ودقة النصوص والمصطلحات المصرية الموحدة عبر كافة القوائم.
