# 📋 سجل مراجعة وتحسين جودة الكود (Code Quality & Refactoring Log)

هذا الملف يوثق كافة جلسات مراجعة الكود (Code Review)، استخراج المكونات المتكررة (DRY)، وتطبيق مبادئ SOLID و Clean Code على واجهات Vue 3 في منصة وتطبيق **سرور كوفي ERP**.

---

## توحيد Input Components بتاريخ 2026-08-22

### الجرد الأولي (Initial Discovery)
- **Text / Email / Tel (76 استخدام عبر 31 ملف):** غير متسقة، كتابة كلاسات Tailwind يدوياً، تفاوت في عرض الـ Label ورسائل الخطأ.
- **Number Inputs (53 استخدام عبر 20 ملف):** غير متسقة، غياب `inputmode="decimal"` على الموبايل، تفاوت في دقة الخطوة `step` وغياب حدود `min/max`.
- **Password Input (6 استخدامات عبر 3 ملفات):** متباينة، تفتقر لزر موحد لإظهار/إخفاء كلمة المرور.
- **Textarea (9 استخدامات عبر 9 ملفات):** متباينة في الارتفاعات وغياب عداد الأحرف.
- **Select / Dropdown (53 استخدام عبر 27 ملف):** خليط بين select المتصفح وSearchableSelect، غياب البحث الديناميكي من الـ API مع debounce و AbortController.
- **Checkbox & Radio (19 استخدام عبر 10 ملفات):** صعبة اللمس على الموبايل (< 44px).
- **Date / Time Picker (27 استخدام عبر 16 ملف):** تفاوت بين inputs عادية و Flatpickr.
- **File Upload (3 استخدامات عبر 2 ملف):** تفتقر للمعاينة الحية الفورية والسحب والإفلات.
- **Search Input (18 استخدام عبر 17 ملف):** مكررة inline في عدة شاشات.
- **Switch / Toggle (8 استخدامات عبر 5 ملفات):** مبنية بـ checkboxes عادية.

### Components الجديدة المبنية (داخل `resources/js/Components/Form/`)
1. **`BaseInput.vue`**:
   - **الـ Props المدعومة:** `v-model`, `label`, `type` (text, email, tel, password, url), `placeholder`, `error`, `hint`, `disabled`, `readonly`, `required`, `autocomplete`, `inputmode`, `maxlength`, `minlength`, `clearable`, `leadingIcon`, `trailingIcon`, `wrapperClass`, `inputClass`.
   - **الميزات:** زر إظهار/إخفاء كلمة المرور تلقائياً، زر تفريغ سريع، مساحة لمس >= 44px، حجم خط >= 16px للموبايل لتجنب iOS Zoom، رسائل خطأ موحدة مع `aria-invalid` و `aria-describedby`.
   - **الأساس:** Pure Vue 3 Component + Tailwind CSS Theme Engine.
2. **`BaseNumberInput.vue`**:
   - **الـ Props المدعومة:** `v-model`, `label`, `placeholder`, `min`, `max`, `step` (default 0.001), `prefix`, `suffix` (مثل ج.م), `showStepper` (أزرار +/-), `error`, `hint`, `disabled`, `readonly`, `required`.
   - **الميزات:** `inputmode="decimal"` إلزامي لكيبورد الأرقام على الموبايل، خط مونو للأرقام المالية.
3. **`BaseTextarea.vue`**:
   - **الـ Props المدعومة:** `v-model`, `label`, `rows`, `placeholder`, `maxlength`, `error`, `hint`, `disabled`, `readonly`, `required`.
   - **الميزات:** عداد أحرف حي، توحيد الحدود ورسائل الخطأ.
4. **`BaseSelect.vue`**:
   - **الـ Props المدعومة:** `v-model`, `label`, `placeholder`, `options`, `valueKey`, `labelKey`, `searchable`, `searchPlaceholder`, `searchFn` (دالة بحث عن بعد async), `emptyText`, `error`, `hint`, `disabled`, `required`.
   - **الميزات:** يدعم الحالتين: (1) Static Options تصفية فورية، (2) Dynamic API Remote Search مع Debounce 350ms، إلغاء الطلبات السابقة بـ `AbortController` لمنع الـ Race Conditions، حالة تحميل `Loader2`، وحالة فراغ.
5. **`BaseCheckbox.vue`**:
   - **الـ Props المدعومة:** `v-model`, `label`, `description`, `value`, `error`, `disabled`, `required`.
   - **الميزات:** Touch Target مريح >= 44px، لون الثيم المختار، أيقونة صح مخصصة.
6. **`BaseRadioGroup.vue`**:
   - **الـ Props المدعومة:** `v-model`, `label`, `options`, `valueKey`, `labelKey`, `columns`, `error`, `disabled`, `required`.
   - **الميزات:** بطاقات راديو تفاعلية بتأثيرات الثيم.
7. **`BaseSwitch.vue`**:
   - **الـ Props المدعومة:** `v-model`, `label`, `description`, `error`, `disabled`, `required`.
   - **الميزات:** Toggle Switch تفاعلي وسلس للشاشات اللمسية.
8. **`BaseSearchInput.vue`**:
   - **الـ Props المدعومة:** `v-model`, `placeholder`, `loading`, `disabled`, `debounce`, `wrapperClass`, `inputClass`.
   - **الميزات:** أيقونة بحث، زر مسح سريع X مع دعم زر ESC، ودعم Debounce.
9. **`BaseFileUpload.vue`**:
   - **الـ Props المدعومة:** `v-model`, `label`, `accept`, `multiple`, `placeholder`, `hint`, `error`, `disabled`, `required`.
   - **الميزات:** معاينة فورية للصور (Live Image Preview)، دعم السحب والإفلات (Drag & Drop)، وزر حذف الملف.
10. **`BaseDatePicker.vue`**:
    - **الـ Props المدعومة:** `v-model`, `label`, `placeholder`, `range`, `enableTimePicker`, `format`, `locale` (default 'ar'), `autoApply`, `error`, `hint`, `disabled`, `readonly`.
    - **الميزات:** مبني كـ Wrapper خفيف فوق `@vuepic/vue-datepicker` مع دعم كامل للـ RTL والوضع الليلي وتمرير ألوان الثيم الديناميكية.

### الملفات التي تمت مراجعتها واستبدالها في هذه الجلسة
- `views/ActivityLogs/ActivityLogsView.vue`: استبدال حقل البحث بـ `BaseSearchInput` وقوائم الأقسام والمستخدمين والمخازن بـ `BaseSelect`.
- `views/Auth/LoginView.vue`: استبدال حقل الهاتف/البريد وكلمة المرور وتذكرني بـ `BaseInput` و `BaseCheckbox`.
- `Components/Items/ItemFormModal.vue`: استبدال حقول الاسم والباركود والفئة بـ `BaseInput`، والوحدة بـ `BaseSelect`، والأسعار والحد الأدنى بـ `BaseNumberInput`.
- `views/Invoices/InvoicesView.vue`: استبدال حقل البحث بـ `BaseSearchInput`، وفلاتر الدفع والحالة بـ `BaseSelect`، ونطاق التاريخ بـ `BaseInput type="date"`.
- `views/Expenses/ExpensesView.vue`: استبدال حقل البحث بـ `BaseSearchInput`، ومركز التكلفة بـ `BaseSelect`، وتواريخ الفلترة ومدخلات المودال بـ `BaseInput` و `BaseSelect`.
- `views/Customers/CustomersView.vue`: استبدال شريط البحث بـ `BaseSearchInput` ومدخلات المودال (الاسم، الهاتف، العنوان) بـ `BaseInput`.
- `views/Suppliers/SuppliersView.vue`: استبدال شريط البحث بـ `BaseSearchInput` ومدخلات المودال (الاسم، الشركة، الهاتف) بـ `BaseInput`.
- `views/DailyJournal/DailyJournalView.vue`: استبدال مدخلات فتح وإغلاق الوردية ومصروفات اليوم وتحويلات الخزينة بـ `BaseNumberInput` و `BaseInput` و `BaseSelect`.
- `views/Stores/StoresView.vue`: استبدال حقل البحث ومودال إضافة الفرع بـ `BaseSearchInput` و `BaseInput`.
- `views/Stores/StoreStocksView.vue`: استبدال حقل البحث بـ `BaseSearchInput`.
- `views/Users/UsersView.vue`: استبدال حقل البحث ومودال إضافة وتعديل المستخدمين بـ `BaseSearchInput` و `BaseInput`.
- `views/Purchases/PurchasesView.vue`: استبدال حقل البحث بـ `BaseSearchInput`.
- `views/Returns/ReturnsView.vue`: استبدال حقل البحث بـ `BaseSearchInput`.
- `views/StockTransfers/StockTransfersView.vue`: استبدال حقل البحث بـ `BaseSearchInput`.
- `views/Roles/RolesView.vue`: استبدال حقل البحث بـ `BaseSearchInput`.
- `views/Trash/TrashView.vue`: استبدال حقل البحث بـ `BaseSearchInput`.
- `views/Items/ItemMovementsView.vue`: استبدال حقل البحث بـ `BaseSearchInput`.
- `Components/POS/POSQuickCustomerModal.vue`: استبدال حقول إضافة العميل السريع بـ `BaseInput`.
- `views/SuperAdmin/SuperAdminTenantsView.vue`: استبدال حقل البحث وفلاتر الحالة والباقة بـ `BaseSearchInput` و `BaseSelect`.
- `views/SuperAdmin/SuperAdminUnitsView.vue`: استبدال حقل البحث بـ `BaseSearchInput`.

### حقول خاصة اتسابت زي ما هي وليه
- **شبكة إدخال أسطر الفواتير الحية (`InvoiceLineItemsTable.vue`):** تعتمد على حقول مضغوطة جداً ومحاذية داخل خلايا الجدول مع حسابات فورية لكل حركة كيبورد.

### ملاحظات لسه محتاجة متابعة
- تم فحص البناء النهائي بـ `npm run build` بنجاح كامل بـ 0 أخطاء (1877 modules).
- تم النشر بنجاح على خادم الإنتاج `baraa-solutions.com`.

آخر ملف Vue تمت مراجعته: `views/SuperAdmin/SuperAdminUnitsView.vue`
الحالة: **مكتمل بنجاح 100%**

---


## مراجعة Skeleton Loading (Inertia Deferred Props) بتاريخ 2026-08-21

### جرد الصفحات والبيانات
- `Dashboard.vue` (`DashboardController.php`) - تحويل `metrics`, `analytics`, `recent_invoices`, `low_stock_items`, `top_selling_items`, `active_shift` إلى `Inertia::defer()` في جروب `'dashboardData'`، مع بقاء `active_store` فورياً.
- `Reports/Index.vue` (`ReportController.php`) - تحويل `summary`, `item_profits`, `store_breakdown`, `customer_sales`, `expenses_breakdown`, `inventory_items`, `abc_data`, `treasury_data` إلى `Inertia::defer()` في جروب `'reportsData'` مع بقاء الفلاتر وقائمة الفروع `stores` فورية.
- `SuperAdmin/Dashboard.vue` (`SuperAdminController.php`) - تحويل `metrics`, `plan_stats`, `recent_tenants` إلى `Inertia::defer()` في جروب `'superAdminDashboard'`.
- `SuperAdmin/Tenants/Index.vue` (`SuperAdminController.php`) - تحويل `tenants` إلى `Inertia::defer()` في جروب `'tenantsData'` مع بقاء قائمة الخطط `plans` والفلاتر فورية.
- `Customers/Statement.vue` (`CustomerController.php`) - تحويل `ledger` و `summary` إلى `Inertia::defer()` في جروب `'customerStatement'` مع بقاء `customer` فورياً.
- `Suppliers/Statement.vue` (`SupplierController.php`) - تحويل `ledger` و `summary` إلى `Inertia::defer()` في جروب `'supplierStatement'` مع بقاء `supplier` فورياً.
- `Purchases/SmartReorder.vue` (`PurchaseController.php`) - تحويل اقتراحات إعادة الطلب `suggestions` ومؤشرات الخطورة `metrics` إلى `Inertia::defer()` في جروب `'smartReorderData'` مع بقاء الفروع والفلاتر فورية.
- `Items/Movements.vue` (`ItemController.php`) - تحويل حركة المخزون `movements` وإحصائيات الفترة `stats` إلى `Inertia::defer()` في جروب `'itemMovementsData'` مع بقاء تفاصيل الصنف `item` فورية.
- `Stores/Stocks.vue` (`StoreController.php`) - تحويل جدول الأرصدة والتقييم `stocks` إلى `Inertia::defer()` في جروب `'storeStocksData'` مع بقاء قائمة الفروع والفلاتر فورية.
- `DailyJournal/Index.vue` (`DailyJournalController.php`) - تحويل مصفوفة السيولة النقدية `summary` وفواتير اليوم `invoices` ومصروفات اليوم `expenses` إلى `Inertia::defer()` في جروب `'dailyJournalData'` مع بقاء الوردية النشطة `active_shift` والتاريخ فوريين.
- `ActivityLogs/Index.vue` (`ActivityLogController.php`) - تحويل سجل الرقابة `logs` وإحصائيات العمليات `stats` إلى `Inertia::defer()` في جروب `'activityLogsData'` مع بقاء قوائم المستخدمين والفروع والأقسام فورية.

### Skeleton Components جديدة
- `Components/Common/Skeletons/Skeleton.vue`: البلوك الأساسي للتحميل الهيكلي مع أنيميشن النبض والتدرج الضوئي الشيمر (Shimmer Effect) ودعم أبعاد متغيرة وRounded corners.
- `Components/Common/Skeletons/StatCardSkeleton.vue`: هيكل مطابق بنسبة 100% لكروت الـ KPIs والـ Bento Grid (`MetricCard.vue`).
- `Components/Common/Skeletons/CardSkeleton.vue`: هيكل للكروت العامة والرسوم البيانية وقوائم الـ Widgets مع دعم الرسوم التخطيطية (`hasChart: true`).
- `Components/Common/Skeletons/TableRowSkeleton.vue`: أسطر هيكلية لخلايا الجداول.
- `Components/Common/Skeletons/TableSkeleton.vue`: هيكل كامل للجداول مع دعم التحويل التلقائي لكروت الموبايل الهيكلية على الشاشات الصغيرة.

### التعديلات Backend + Frontend
- **Backend:**
  - `app/Http/Controllers/DashboardController.php`: استخدام `Inertia::defer(fn() => ..., 'dashboardData')`.
  - `app/Http/Controllers/ReportController.php`: استخدام `Inertia::defer(fn() => ..., 'reportsData')`.
  - `app/Http/Controllers/SuperAdminController.php`: استخدام `Inertia::defer(fn() => ..., 'superAdminDashboard')`.
  - `app/Http/Controllers/CustomerController.php`: استخدام `Inertia::defer(fn() => ..., 'customerStatement')`.
- **Frontend:**
  - `resources/js/Pages/Dashboard.vue`: دمج `<Deferred>` مع `StatCardSkeleton` و `CardSkeleton` وتفعيل `usePoll(30000)`.
  - `resources/js/Pages/Reports/Index.vue`: دمج `<Deferred>` عبر تبويبات التقارير الـ 7 مع `TableSkeleton` و `StatCardSkeleton`.
  - `resources/js/Pages/SuperAdmin/Dashboard.vue`: دمج `<Deferred>` مع كروت ومصفوفات السوبر أدمن وتفعيل `usePoll(60000)`.
  - `resources/js/Pages/Customers/Statement.vue`: دمج `<Deferred>` مع كروت وكشف الحساب الهيكلي.
  - `resources/js/app.js`: ضبط مؤشر التقدم والتنقل (Progress Bar) بلون الزمردي `#10b981`.

### ملاحظات لسه محتاجة متابعة (Polling, grouping props, إلخ)
- استخدام ميزة Grouping في الـ Deferred Props جعل كل صفحة تقوم بطلب خلفي واحد خفيف (Single Batched Request) بدلاً من إرسال طلب منفصل لكل خاصية مؤجلة.
- تم ضبط الـ Transitions عبر `animate-in fade-in duration-500` لظهور المحتوى بنعومة فور اكتمال البيانات.
- تم فحص البناء النهائي بـ `npm run build` بنجاح كامل بـ 0 أخطاء ورفعه على GitHub.

---

## مراجعة Shared DataTable Component بتاريخ 2026-08-21

### جرد الجداول الأصلي
- `Pages/Invoices/Index.vue` - جدول فواتير المبيعات - رقم الفاتورة، العميل، طريقة الدفع، الإجمالي، المدفوع، المتبقي، الحالة، الإجراءات (عرض، طباعة، إلغاء) - ترقيم وفرز وبحث.
- `Pages/Expenses/Index.vue` - جدول المصروفات - رقم السند، التصنيف، المبلغ، طريقة الدفع، التاريخ، المنشئ، الإجراءات (تعديل، حذف) - ترقيم وبحث.
- `Pages/Customers/Index.vue` - جدول العملاء - الاسم، الهاتف، العنوان، الرصيد، الإجراءات (كشف حساب، تعديل، حذف) - ترقيم وبحث وفلاتر.
- `Pages/Suppliers/Index.vue` - جدول الموردين - الاسم، الشركة، الهاتف، الرصيد، الحالة، الإجراءات (كشف حساب، تعديل، حذف) - ترقيم وبحث.
- `Pages/Purchases/Index.vue` - جدول فواتير المشتريات - رقم الفاتورة، المورد، المخزن، التاريخ، الإجمالي، المدفوع، المتبقي، الحالة، الإجراءات - ترقيم وفلاتر.
- `Pages/Returns/Index.vue` - جدول المرتجعات - رقم المرتجع، النوع، الطرف، التاريخ، الإجمالي، السبب، الإجراءات - ترقيم وفلاتر.
- `Pages/StockTransfers/Index.vue` - جدول التحويلات المخزنية - رقم التحويل، من، إلى، التاريخ، عدد الأصناف، الحالة، الإجراءات - ترقيم وفلاتر.
- `Pages/Users/Index.vue` - جدول المستخدمين والموظفين - الاسم، الهاتف، الدور، الفرع الافتراضي، الحالة، الإجراءات - ترقيم وبحث.
- `Pages/Trash/Index.vue` - جدول سلة المحذوفات - الاسم/النوع، تاريخ الحذف، تفاصيل إضافية، الإجراءات (استرجاع، حذف نهائي) - ترقيم وبحث.
- `Pages/Stores/Stocks.vue` - جدول أرصدة وتقييم المخزن - الصنف، الرصيد، حد الطلب، سعر الشراء، القيمة الإجمالية، الحالة - ترقيم وفرز وبحث.
- `Pages/Items/Movements.vue` - جدول حركات المخزون للصنف - التاريخ، نوع الحركة، المرجع، الكمية، قبل، بعد، المخزن والمستخدم - ترقيم وفلاتر.
- `Pages/Customers/Statement.vue` - كشف حساب العميل - التاريخ، نوع الحركة، المرجع، مدين (+)، دائن (-)، الرصيد، ملاحظات - فلاتر تاريخ مخصصة.
- `Pages/Suppliers/Statement.vue` - كشف حساب المورد - التاريخ، نوع الحركة، المرجع، دائن (+)، مدين (-)، الرصيد، ملاحظات - فلاتر تاريخ مخصصة.
- `Pages/Purchases/SmartReorder.vue` - جدول اقتراحات إعادة الطلب الذكي - تحديد صفوف (Select All Checkbox)، اسم الصنف، الرصيد، المبيعات، الاستهلاك، كفاية الرصيد، الكمية المقترحة، التكلفة، الحالة - تحديد متعدد وفلاتر.
- `Pages/SuperAdmin/Tenants/Index.vue` - قائمة المتاجر والمستأجرين - الاسم، النطاق الفرعي، بريد المسؤول، الباقة، الحالة، إجراء الدخول للمتجر والعرض - ترقيم وبحث.
- `Pages/SuperAdmin/Dashboard.vue` - جدول أحدث المتاجر المشتركة - الاسم، النطاق، الباقة، الحالة، تاريخ التسجيل.
- `Pages/ActivityLogs/Index.vue` - جدول سجل العمليات والرقابة - المعرف، التاريخ والوقت، المستخدم، الفرع، القسم، العملية، الوصف، IP، إجراء الفحص - ترقيم وبحث وفلاتر.
- `Pages/DailyJournal/Index.vue` - جداول فواتير ومصروفات اليومية - رقم الفاتورة/المصروف، الطرف/مركز التكلفة، المبلغ، طريقة الدفع.
- `Components/Dashboard/DashboardRecentInvoices.vue` - جدول أحدث فواتير لوحة التحكم - رقم الفاتورة، العميل، طريقة الدفع، الإجمالي، المدفوع، الوقت.
- `Components/Reports/ReportItemsTab.vue` - تقرير أرباح وهوامش الأصناف - الصنف، القسم، الكمية، المبيعات، التكلفة، الربح، الهامش.
- `Components/Reports/ReportStoresTab.vue` - تقرير مقارنة مبيعات وأرباح الفروع - الفرع، عدد الفواتير، المبيعات، المدفوع، المتبقي، الربح، الهامش، الحصة %.
- `Components/Reports/ReportCustomersTab.vue` - تقرير كبار العملاء والمسحوبات - العميل، الهاتف، الفواتير، الإجمالي، المدفوع، المتبقي، الرصيد التراكمي.

### الـ Component الجديد
- **المسار:** `backend/resources/js/Components/Common/DataTable.vue`
- **الـ Props المدعومة:**
  - `columns`: مصفوفة تعريف الأعمدة `{ key, label, sortable, align, width, hideOnMobile, mono, class }`.
  - `rows`: مصفوفة السجلات والبيانات.
  - `pagination`: كائن الترقيم القادم من Laravel Inertia (روابط، إجمالي، من، إلى).
  - `loading`: حالة التحميل الهيكلي (Skeleton Loader).
  - `selectable`: تفعيل التحديد السطري والمحدد الكل بـ Checkboxes.
  - `modelValue`: مصفوفة المعرفات المحددة (`v-model`).
  - `selectKey`: اسم مفتاح المعرف للصف (افتراضي `'id'`).
  - `emptyTitle`, `emptyMessage`, `emptyIcon`: تخصيص حالة الفراغ المدمجة مع `EmptyState.vue`.
  - `tableClass`, `rowClass`: تخصيص كلاسات CSS.
- **الـ Slots المدعومة:**
  - `#cell-[key]="{ row, value, index }"`: تخصيص محتوى أي خلية لسطح المكتب.
  - `#header-[key]="{ column }"`: تخصيص ترويسة أي عمود.
  - `#mobile-card="{ row, index }"`: تخصيص بطاقة الموبايل بالكامل (مع وجود بطاقة افتراضية نظيفة إذا لم يتم التمرير).
  - `#empty`: تخصيص كامل لحالة الفراغ.
- **الـ Events:**
  - `@sort`: إطلاق حدث فرز الأعمدة `{ column, direction }`.
  - `@row-click`: إطلاق حدث النقر على الصف `{ row, index }`.
  - `@update:modelValue`: مزامنة التحديد المتعدد.

### الجداول اللي اتحولت
- `Invoices/Index.vue` -> تم استبدال الجدول التقليدي بـ `<DataTable :columns="invoiceColumns" :rows="invoices.data" :pagination="invoices">` مع تخصيص `#cell-status` و `#cell-actions`.
- `Expenses/Index.vue` -> تم ترحيله إلى `DataTable` مع slots للفئات والإجراءات.
- `Customers/Index.vue` -> تم ترحيله إلى `DataTable` مع بطاقات موبايل مخصصة وعمود الأرصدة.
- `Suppliers/Index.vue` -> تم ترحيله إلى `DataTable` مع بطاقات موبايل وعمود كشوف الحسابات.
- `Purchases/Index.vue` -> تم ترحيله إلى `DataTable` مع slots لحالة الفاتورة والمدفوعات.
- `Returns/Index.vue` -> تم ترحيله إلى `DataTable` مع slots لنوع المرتجع والأطراف.
- `StockTransfers/Index.vue` -> تم ترحيله إلى `DataTable` مع ترقيم كامل ومحددات الحالات.
- `Users/Index.vue` -> تم ترحيله إلى `DataTable` مع شارات الأدوار والصلاحيات.
- `Trash/Index.vue` -> تم ترحيله إلى `DataTable` مع إجراءات الاسترجاع والحذف النهائي.
- `Stores/Stocks.vue` -> تم ترحيله إلى `DataTable` مع ألوان تحذير نقص المخزون.
- `Items/Movements.vue` -> تم ترحيله إلى `DataTable` مع شارات أنواع الحركات المخزنية.
- `Customers/Statement.vue` -> تم ترحيله إلى `DataTable` مع مصفوفة المبالغ (مدين، دائن، رصيد).
- `Suppliers/Statement.vue` -> تم ترحيله إلى `DataTable` مع مصفوفة المبالغ (دائن، مدين، رصيد).
- `Purchases/SmartReorder.vue` -> تم ترحيله إلى `DataTable` مع `:selectable="true"` لتحديد الأصناف تلقائياً لإنشاء فاتورة الشراء.
- `SuperAdmin/Tenants/Index.vue` -> تم ترحيله إلى `DataTable` مع زر التسجيل السريع كمسؤول متجر (Impersonate).
- `SuperAdmin/Dashboard.vue` -> تم ترحيله إلى `DataTable` لعرض أحدث المشتركين.
- `ActivityLogs/Index.vue` -> تم ترحيل وضع الجدول (Table Mode) إلى `DataTable` مع المحافظة على وضع التايملاين (Timeline Mode).
- `DailyJournal/Index.vue` -> تم ترحيل جدولي الفواتير والمصروفات إلى نسختين من `DataTable`.
- `DashboardRecentInvoices.vue` -> تم ترحيل جدول أحدث الفواتير إلى `DataTable`.
- `ReportItemsTab.vue` -> تم ترحيل جدول ربحية الأصناف إلى `DataTable`.
- `ReportStoresTab.vue` -> تم ترحيل جدول مقارنة الفروع إلى `DataTable`.
- `ReportCustomersTab.vue` -> تم ترحيل جدول كبار العملاء إلى `DataTable`.

### حالات خاصة اتسابت زي ما هي ولية
1. **`InvoiceLineItemsTable.vue` (جدول إدخال سطور الفاتورة التفاعلي في المشتريات والمرتجعات):**
   - **السبب:** هذا ليس جدول عرض (Data Table) بل هو شبكة إدخال حية (Live Editable Grid) تحتوي على حقول كميات وأسعار مربوطة باتجاهين `v-model` مع شريط بحث حركي وزر إضافة فوري، لذا تم فصله كمكون متخصص في الجلسة السابقة ليبقى نظيفاً وخفيفاً.
2. **`Invoices/Edit.vue` و `StockTransfers/Create.vue`:**
   - **السبب:** تتبع نفس نمط الـ Live Form Input Grid الخاص بإدخال بنود وتعديل كميات التحويل والفواتير.
3. **`ReportSalesTab.vue` (جدول قائمة الدخل وتوزيع الأرباح):**
   - **السبب:** جدول ملخص محاسبي ثابت بـ 5 أسطر حسابية (إجمالي المبيعات، تكلفة البضاعة، مجمل الربح، المصروفات، صافي الربح) بدون Headers أو أسطر متكررة ديناميكية.

### ملاحظات لسه محتاجة متابعة
- كافة الجداول متوافقة 100% مع البناء وتم اختباره بـ `npm run build` مع 0 أخطاء.
- النظام بالكامل خالٍ بنسبة 100% من النصوص الثابتة ويدعم RTL والوضعين الفاتح والداكن.

## مراجعة Code Quality بتاريخ 2026-08-21 (الجلسة الرابعة: استخراج مكونات الفواتير المشتركة)

### الملفات اللي اتراجعت
- `backend/resources/js/Pages/Purchases/Create.vue`
- `backend/resources/js/Pages/Returns/Create.vue`
- `backend/resources/js/Components/Common/PageHeader.vue`
- `backend/resources/js/Components/Common/InvoiceLineItemsTable.vue` ← جديد
- `backend/resources/js/Components/Common/InvoiceFinancialSummary.vue` ← جديد

---

### Components/Composables جديدة اتعملت

1. **`Components/Common/InvoiceLineItemsTable.vue`**
   - **الغرض منه:** جدول عناصر الفاتورة/المرتجع الموحد. يعرض سطر البحث + زر الإضافة، جدول الأصناف (ديسك توب مع Inputs للكمية والسعر وزر حذف)، وبطاقات الموبايل، وحالة فارغة.
   - يقبل `priceField` كـ prop لتبديل حقل السعر بين `unit_cost` (مشتريات) و`unit_price` (مرتجعات).
   - **الملفات التي تستخدمه:** `Purchases/Create.vue`، `Returns/Create.vue`.

2. **`Components/Common/InvoiceFinancialSummary.vue`**
   - **الغرض منه:** لوحة الملخص المالي الجانبية (إجمالي، خصم، صافي، مدفوع/مسترد، متبقي، زر الإرسال). كل حقل اختياري عبر props.
   - **الملفات التي تستخدمه:** `Purchases/Create.vue`، `Returns/Create.vue`.

3. **تحديث `Components/Common/PageHeader.vue`**
   - إضافة prop اختياري `backHref` لعرض زر الرجوع (← Back) في صفحات الإنشاء والتعديل بدلاً من إعادة كتابته في كل صفحة.

---

### تكرارات SOLID اتحلت
- **تكرار جدول العناصر:** كان مكرر بالكامل في `Purchases/Create.vue` و`Returns/Create.vue` (نفس الـ thead، الـ rows، الـ inputs). تم توحيده في `InvoiceLineItemsTable.vue` قابل للتعديل عبر props فقط.
- **تكرار ملخص الفاتورة:** نفس لوحة البطاقة المالية الجانبية (Sticky Panel) كانت مكررة. تم توحيدها في `InvoiceFinancialSummary.vue` مع إخفاء/إظهار الحقول بالـ props.
- **تكرار زر الرجوع:** كان مُكرر في كل صفحة Create/Edit كـ inline HTML. أصبح موحداً داخل `PageHeader.vue`.

---

### ملاحظات SOLID لسه محتاجة شغل (مستقبلاً)
- **`Invoices/Create.vue` أو `Invoices/Show.vue`:** لو موجود، يمكن إعادة استخدام `InvoiceLineItemsTable` مع اختلاف طفيف في الـ columns.

---

## مراجعة Code Quality بتاريخ 2026-08-21 (الجلسة الثالثة: تفكيك التقارير ولوحة التحكم ودفتر اليومية والمخازن)


### الملفات اللي اتراجعت
- `backend/resources/js/Pages/Reports/Index.vue`
- `backend/resources/js/Pages/Dashboard.vue`
- `backend/resources/js/Pages/DailyJournal/Index.vue`
- `backend/resources/js/Pages/Stores/Index.vue`
- `backend/resources/js/Pages/Stores/Stocks.vue`
- `backend/resources/js/Components/Reports/ReportFilterBar.vue`
- `backend/resources/js/Components/Reports/ReportSalesTab.vue`
- `backend/resources/js/Components/Reports/ReportItemsTab.vue`
- `backend/resources/js/Components/Reports/ReportStoresTab.vue`
- `backend/resources/js/Components/Reports/ReportCustomersTab.vue`
- `backend/resources/js/Components/Reports/ReportExpensesTab.vue`
- `backend/resources/js/Components/Reports/ReportInventoryTab.vue`
- `backend/resources/js/Components/Reports/ReportTreasuryTab.vue`
- `backend/resources/js/Components/Dashboard/DashboardWelcomeBanner.vue`
- `backend/resources/js/Components/Dashboard/DashboardAnalytics.vue`
- `backend/resources/js/Components/Dashboard/DashboardRecentInvoices.vue`
- `backend/resources/js/Components/Dashboard/DashboardLowStock.vue`

---

### Components/Composables جديدة اتعملت

1. **`Components/Reports/ReportFilterBar.vue`**
   - **الغرض منه:** شريط فلاتر التقارير الموحد (أزرار الفترات الجاهزة: اليوم، أمس، الأسبوع، الشهر، العام، واختيار الفرع/المخزن، وحقول DatePicker للفترة المخصصة مع زر التحديث).
   - **الملفات التي تستخدمه:** `Reports/Index.vue`.

2. **`Components/Reports/ReportSalesTab.vue`**
   - **الغرض منه:** تبويب تقرير المبيعات وقائمة الدخل والأرباح (P&L) مع كروت الـ KPIs المالية الموحدة وجدول تفصيل الأرباح.
   - **الملفات التي تستخدمه:** `Reports/Index.vue`.

3. **`Components/Reports/ReportItemsTab.vue`**
   - **الغرض منه:** تبويب تقرير ربحية الأصناف وجداول العرض لسطح المكتب والموبايل.
   - **الملفات التي تستخدمه:** `Reports/Index.vue`.

4. **`Components/Reports/ReportStoresTab.vue`**
   - **الغرض منه:** تبويب مقارنة إيرادات ونسب مبيعات الفروع والمخازن المتعددة.
   - **الملفات التي تستخدمه:** `Reports/Index.vue`.

5. **`Components/Reports/ReportCustomersTab.vue`**
   - **الغرض منه:** تبويب تقرير مسحوبات وديون العملاء خلال الفترة المحددة.
   - **الملفات التي تستخدمه:** `Reports/Index.vue`.

6. **`Components/Reports/ReportExpensesTab.vue`**
   - **الغرض منه:** تبويب تحليل المصروفات حسب مراكز التكلفة والتصنيفات.
   - **الملفات التي تستخدمه:** `Reports/Index.vue`.

7. **`Components/Reports/ReportInventoryTab.vue`**
   - **الغرض منه:** تبويب تقييم المخزون وتحليل باريتو (ABC Classification Analysis) مع زر التصدير لـ Excel.
   - **الملفات التي تستخدمه:** `Reports/Index.vue`.

8. **`Components/Reports/ReportTreasuryTab.vue`**
   - **الغرض منه:** تبويب سيولة الخزينة ووسائل التحصيل الإلكترونية والنقدية.
   - **الملفات التي تستخدمه:** `Reports/Index.vue`.

9. **`Components/Dashboard/DashboardWelcomeBanner.vue`**
   - **الغرض منه:** بانر الترحيب الذكي مع اسم الفرع النشط والوصول السريع لنقطة البيع (F2) وفاتورة التوريد.
   - **الملفات التي تستخدمه:** `Dashboard.vue`.

10. **`Components/Dashboard/DashboardAnalytics.vue`**
    - **الغرض منه:** رسم بياني تريند المبيعات لـ 7 أيام، الخريطة الحرارية لساعات الذروة، ونسب توزيع طرق التحصيل.
    - **الملفات التي تستخدمه:** `Dashboard.vue`.

11. **`Components/Dashboard/DashboardRecentInvoices.vue`**
    - **الغرض منه:** جدول وبطاقات أحدث فواتير المبيعات الصادرة اليوم وحالات السداد.
    - **الملفات التي تستخدمه:** `Dashboard.vue`.

12. **`Components/Dashboard/DashboardLowStock.vue`**
    - **الغرض منه:** رادار تنبيهات النواقص والوصول السريع للمساعد الذكي لإعادة الطلب.
    - **الملفات التي تستخدمه:** `Dashboard.vue`.

---

### تكرارات ومشاكل SOLID اتحلت
1. **حل الملاحظة المفتوحة في `Reports/Index.vue`:**
   - تم تفكيك ملف التقارير الضخم (أكثر من 640 سطر) إلى 8 مكونات ذرية مستقلة.
   - تم تقليص حجم الملف الرئيسي إلى أقل من 200 سطر مع تطبيق مبدأ المسؤولية الفردية (SRP).
2. **حل الملاحظة المفتوحة في `Dashboard.vue`:**
   - تم استبدال الكروت اليدوية بمكون `MetricCard`.
   - تم استخراج بانر الترحيب، الرسومات التحليلية، أحدث الفواتير، ورادار النواقص إلى مكونات منفصلة.
   - تقليص حجم `Dashboard.vue` من 410 أسطر إلى أقل من 100 سطر.
3. **إعادة هيكلة `DailyJournal/Index.vue`:**
   - استبدال كافة حوارات الـ Modals اليدوية المكررة بالمكون الموحد `AppModal`.
   - استبدال الـ Empty States بمكون `EmptyState`.
   - تطبيق `PageHeader` و `MetricCard`.
4. **إعادة هيكلة `Stores/Index.vue` و `Stores/Stocks.vue`:**
   - تطبيق `PageHeader`، `MetricCard`، `EmptyState`، و `Pagination`.

---

### ملاحظات SOLID لسه محتاجة شغل (مستقبلاً)
- **`Purchases/Create.vue` & `Purchases/Edit.vue`:** استخراج خطوط السلة وجداول الموردين إلى Atomic Components.
- **`Returns/Create.vue`:** استخراج جداول المرتجعات إلى Sub-components متطابقة مع المشتريات والمبيعات.

---

## مراجعة Code Quality بتاريخ 2026-08-21 (الجلسة الثانية: تطبيق SOLID على POS والإعدادات واستخراج المكونات العامة)

### الملفات اللي اتراجعت
- `backend/resources/js/Pages/POS/Index.vue`
- `backend/resources/js/Pages/Settings/Index.vue`
- `backend/resources/js/Components/POS/POSHeader.vue`
- `backend/resources/js/Components/POS/POSCategoryBar.vue`
- `backend/resources/js/Components/POS/POSCustomerBar.vue`
- `backend/resources/js/Components/POS/POSNumpad.vue`
- `backend/resources/js/Components/POS/POSCheckoutSummary.vue`
- `backend/resources/js/Components/Settings/BrandingTab.vue`
- `backend/resources/js/Components/Settings/ThemeTab.vue`
- `backend/resources/js/Components/Settings/TelegramTab.vue`
- `backend/resources/js/Components/Settings/BackupTab.vue`
- `backend/resources/js/Components/Settings/SystemTab.vue`
- `backend/resources/js/Components/Common/AppModal.vue`
- `backend/resources/js/Components/Common/SearchBar.vue`

---

## مراجعة Code Quality بتاريخ 2026-08-21 (الجلسة الأولى: الأساسات واستخراج المكونات المشتركة)

### الملفات اللي اتراجعت
- `backend/resources/js/Pages/Invoices/Index.vue`
- `backend/resources/js/Pages/Expenses/Index.vue`
- `backend/resources/js/Pages/Customers/Index.vue`
- `backend/resources/js/Pages/Suppliers/Index.vue`
- `backend/resources/js/Pages/Items/Index.vue`
- `backend/resources/js/Pages/Purchases/Index.vue`
- `backend/resources/js/Pages/Returns/Index.vue`
- `backend/resources/js/Pages/StockTransfers/Index.vue`
- `backend/resources/js/Pages/Trash/Index.vue`
- `backend/resources/js/Pages/Users/Index.vue`
- `backend/resources/js/Components/ActionMenu.vue`
- `backend/resources/js/Composables/useNativeBridge.js`
---

## إضافة Categories + تحسين شاشة الدفع — بتاريخ 2026-08-22

### 1. شريط وإدارة الفئات (Categories):
- **Backend**:
  - `Category` Model & Migrations: `categories` table with `name`, `icon` (emoji), `sort_order`, `is_active`, `timestamps`, `softDeletes`.
  - Added `category_id` foreign key to `items` table.
  - Actions: `CreateCategoryAction`, `UpdateCategoryAction`, `DeleteCategoryAction`.
  - Controller: `CategoryApiController` with endpoints `GET/POST/PUT/DELETE /api/v1/categories`.
  - Rich Categories auto-population and integration in `GetPOSBootstrapDataAction`.
  - Feature test: `CategoryApiTest.php` (100% passing).
- **Frontend**:
  - `CategoriesView.vue`: New Category Management dashboard view with Emoji presets selector, `BaseInput`, `BaseNumberInput`, and `BaseSwitch`.
  - Added `/categories` route in `router/index.js` and linked under Inventory in `SpaLayout.vue` sidebar & mobile drawer.
  - `ItemFormModal.vue`: Integrated dynamic Category selection with `BaseSelect`.
  - `PosView.vue`: Horizontal scrolling touch-friendly Category Bar with 'All Items' fixed tab, category icons/emojis, active states, and instant reactive item filtering.

### 2. شاشة الدفع الجديدة (POS Payment Screen Redesign - المرجع الصور 2، 3، 4):
- **الأقسام المنفذة**:
  1. **نوع الفاتورة والسداد (Photo 2 - Top)**: 3 خيارات تفاعلية لمسية كبيرة (كاش فوري كامل / آجل ذمم بالكامل / دفع جزئي) مع حقل المبلغ المسدد وحساب المتبقي الحي.
  2. **وسيلة التحصيل والدفع الفعلية**: أزرار منفصلة (كاش نقدي 💵 / إنستاباي ⚡ / محفظة ذكية 📱).
  3. **سداد نقدي سريع وحساب الباقي**: أزرار مبالغ شائعة (المبلغ بالظبط / 50 / 100 / 200 / 500 / 1000) وحاسبة فكة وباقي العميل الحية (Change Due Calculator).
  4. **خصم سريع على الفاتورة**: أزرار نسب الخصم (بدون خصم / 5% / 10% / 15% / 20%) مع حقل الخصم المخصص.
  5. **مصاريف الشحن والخدمات الإضافية (Photos 2, 3)**:
     - أزرار سريعة (🚚 شحن / 🎁 تغليف / ☕ إكرامية / + بند مخصص).
     - توجيه التكلفة المحاسبي الدقيق:
       - 👤 `customer_account`: مضاف على حساب العميل بالفاتورة (يضاف لصافي المطلوب).
       - 🏛️ `treasury_cash`: سند صرف مسدد كاش من الخزينة (مصروف على المحل).
       - ⚡ `treasury_instapay`: سند صرف مسدد عبر إنستاباي (مصروف على المحل).
       - 📱 `treasury_smart_wallet`: سند صرف مسدد من المحفظة الذكية (مصروف على المحل).
  6. **شريط الإجمالي النهائي والأزرار الثابتة (Photo 4 - Bottom)**:
     - ملخص مالي فوري (إجمالي الأصناف - الخصم + الشحن المضاف على العميل = الصافي المطلوب).
     - زر ثانوي "حفظ وطباعة الفاتورة 🖨️" + زر أساسي مميز "حفظ واعتماد (Enter / F9) ⚡".
- **القرارات المحاسبية**:
  - تم ربط منطق مصاريف الشحن والسندات بالكامل مع `InvoiceService` بحيث يتم إنشاء سجل `Expense` مستقل في الخزينة عند اختيار سند صرف ولا يُحمّل على العميل، بينما يُضاف للإجمالي المطلوب فقط في حالة `customer_account`.