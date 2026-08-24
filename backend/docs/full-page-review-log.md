# 📄 سجل المراجعة الشاملة الموحد للصفحات (Full Page Review Log)
> يوثق هذا الملف المراجعة الشاملة لكل صفحة عبر المحاور الأربعة المتزامنة: (1) التوثيق الكامل، (2) استخراج المكونات المشتركة، (3) التجاوب وتجربة اللمس، (4) الترجمة الكاملة 100%.

---

## 📌 صفحة 1: فواتير المبيعات (`InvoicesView.vue`) — بتاريخ 2026-08-23
* **المسار:** `/invoices`
* **الحالة العامة:** ✅ مكتملة 100% (تقطيع للمكونات الفرعية + نمط المنسق النحيف < 80 سطر + تجاوب ولمس كامل لكافة المقاسات الـ 5 + تعريب كامل بدون نصوص ثابتة + اختبارات E2E و Feature ناجحة).

---

### 1. التوثيق الكامل (Full Documentation)

#### نظرة عامة:
* **اسم الصفحة:** فواتير المبيعات (Sales Invoices)
* **المسار (Route):** `/invoices`
* **الملف الرئيسي:** `resources/js/views/Invoices/InvoicesView.vue` (Thin Orchestrator: ~70 سطر)
* **الغرض منها:** إدارة وعرض فواتير المبيعات المعتمدة والملغاة، تتبع المقبوضات النقدية والإلكترونية والمديونيات الآجلة، البحث المتقدم، الإلغاء الآمن مع عكس أثر المخزون، الطباعة الحرارية 80mm وفواتير A4، ومشاركة الفواتير عبر واتساب وتصدير ملفات Excel.

#### تقسيم الأجزاء (Sections & Components Hierarchy):
1. **رأس الصفحة والإجراءات العامة (`PageHeader.vue`):**
   * زر تبديل فلتر البحث المتقدم (`isFilterSidebarOpen`) مع شارة عداد الفلاتر النشطة (`activeFiltersCount`).
   * قائمة الخيارات والإجراءات المسدلة (تصدير Excel/CSV، طباعة تقرير المبيعات المفلتر، التحديث الحي).
   * زر الوصول السريع لنقطة البيع (`/pos`).
2. **بطاقات المؤشرات المالية (`InvoicesMetricsCards.vue`):**
   * 4 بطاقات KPI (إجمالي المبيعات، المحصل الفعلي، المتبقي الآجل، إجمالي عدد الفواتير) مبنية باستخدام `MetricCard.vue` المشترك.
3. **شريط البحث السريع والمدى الزمني (`InvoicesQuickSearch.vue`):**
   * حقل بحث سريع مع Debounce 300ms وارتفاع لمسي مريح $\ge 44\text{px}$.
   * أقراص فلترة التاريخ السريعة الملموسة (`الكل`, `اليوم`, `أمس`, `آخر 7 أيام`, `هذا الشهر`) مع دعم التمرير الأفقي السلس.
4. **شريط العمليات المجمعة العائم (`InvoicesBulkActionsBar.vue`):**
   * يظهر تلقائياً عند تحديد فاتورة أو أكثر (`selectedInvoiceIds.length > 0`).
   * يتيح: طباعة إيصالات مجمعة، تصدير الفواتير المحددة إلى Excel، وإلغاء الفواتير المحددة مجمعاً مع تأكيد وعكس المخزون.
5. **جدول وبطاقات الفواتير (`InvoicesTable.vue`):**
   * نمط عرض مزدوج (Dual Responsive View):
     * **للشاشات الكبيرة (`hidden md:block`):** جدول متقدم عالي الكثافة مع Checkboxes، اسم العميل، الهاتف، التاريخ، طريقة الدفع، المبالغ (الصافي، المدفوع، المتبقي)، شارة الحالة، وأزرار الإجراءات (معاينة، طباعة 80mm، إلغاء).
     * **للهواتف والشاشات الصغيرة (`block md:hidden`):** بطاقات لمسية تفاعلية واضحة (Cards Stack) بأزرار وإجراءات مريحة $\ge 44\text{px}$.
   * شريط التصفح والترقيم (Pagination).
   * حالة عدم وجود بيانات (`EmptyState.vue`).
6. **شريط الفلترة الجانبي المتقدم (`InvoicesFilterSidebar.vue`):**
   * فلتر الفرع/المخزن، نوع السداد المالي (نقدي، آجل، جزئي)، حالة الفاتورة (معتمدة، ملغاة)، ونطاق التاريخ المخصص (من / إلى).
7. **نافذة تفاصيل الفاتورة ومشاركتها (`InvoiceDetailsModal.vue`):**
   * نافذة منبثقة تفاعلية تعرض تفاصيل العميل والفرع والكاشير، جدول الأصناف والكميات والأسعار، التلخيص المالي، زر طباعة الإيصال الحراري 80mm، وزر المشاركة المباشرة عبر WhatsApp.

#### العناصر التفاعلية:
* Pagination، Checkbox تحديد مجمع لكل الصفحة، فلاتر فورية بدون إعادة تحميل، SweetAlert2 للتأكيدات الآمنة للإلغاء، تصدير CSV فوري بترميز UTF-8 BOM.

#### الاعتماديات والمصادر:
* **API Endpoints:** `GET /api/v1/invoices`, `GET /api/v1/invoices/{id}`, `POST /api/v1/invoices/{id}/cancel`.
* **Composables:** `useFormatters.js` (للمبالغ المالية والكميات)، `useTrans.js` / `trans.js`.
* **المكونات المشتركة:** `PageHeader.vue`, `MetricCard.vue`, `BaseSelect.vue`, `AppModal.vue`, `EmptyState.vue`.

---

### 2. المكونات المشتركة (Shared Components)

* **موجودة بالفعل ومُستخدمة هنا:**
  * `PageHeader.vue`: رأس الصفحة وأزرار الإجراءات.
  * `MetricCard.vue`: بطاقات الـ KPI المالية الأربعة.
  * `BaseSelect.vue`: القوائم المنسدلة للفرع والحالة ونوع السداد.
  * `AppModal.vue`: حاوية النافذة المنبثقة لمعاينة الفاتورة.
  * `EmptyState.vue`: حالة الشاشة عند عدم وجود فواتير.

* **جديدة اتعملت من هذه الصفحة (داخل `resources/js/Components/Invoices/`):**
  * `InvoicesMetricsCards.vue`: بطاقات الإحصاءات الأربعة (`summary`).
  * `InvoicesQuickSearch.vue`: شريط البحث وأقراص التواريخ (`modelValue`, `activePreset`, `presets`).
  * `InvoicesBulkActionsBar.vue`: شريط الإجراءات المجمعة العائم (`selectedCount`).
  * `InvoicesFilterSidebar.vue`: درج وفلتر السايدبار المتقدم.
  * `InvoicesTable.vue`: الجدول المزدوج التجاوبي (Desktop Table + Mobile Cards).
  * `InvoiceDetailsModal.vue`: نافذة معاينة الفاتورة ومشاركتها عبر واتساب.

---

### 3. التجاوب وتجربة اللمس (Responsive & Touch Ergonomics)

* **📱 مشاكل هاتف (360px - 430px):**
  * **المشكلة:** الجدول بـ 11 عموداً كان ينضغط بشكل غير قابل للقراءة وتتداخل النصوص والأزرار.
  * **الحل:** بناء نمط بطاقات الفواتير اللمسية (Mobile Touch Cards Stack) بحيث تظهر كل فاتورة ككارت منفصل ومريح مع إبراز الإجمالي وشارة الحالة وتوفير 3 أزرار لمس واضحة بارتفاع $\ge 44\text{px}$.
  * **شريط أقراص التواريخ:** أصبح قابلاً للتمرير الأفقي السلس باللمس `overflow-x-auto scrollbar-none`.
  * **الفلتر الجانبي:** يعمل بسلاسة كـ Slide-over Panel مريح للهاتف مع زر إغلاق وتطبيق لمسي `min-h-[44px]`.

* **📱 مشاكل تابلت رأسي (600px - 768px):**
  * بطاقات الـ KPI تتوزع في عمودين متوازنين `grid-cols-2` بدلاً من عمود واحد أو 4، مما يستغل الشاشة الرأسية.

* **💻 لابتوب وشاشات كبيرة (1024px+ و 1280px+):**
  * عرض الجدول الكامل فائق الكثافة، الفلتر الجانبي يظهر كعمود مخصص بجانب الجدول بدون حجب البيانات.

* **مساحة اللمس:**
  * كافة الأزرار وعناصر الإدخال والـ Checkboxes تم ضبطها لمعيار $\ge 44\text{px}$.

---

### 4. الترجمة والتعريب (100% Zero Hardcoded Localization)

* **المفاتيح المضافة والمحدثة:**
  * تم استخراج كافة النصوص الثابتة وإضافتها إلى `lang/ar/invoices.php` و `lang/en/invoices.php` و `defaultTranslations.js`.
  * تم تطبيق أسلوب مصري خفيف ومهني متوازن:
    * "مفيش نتائج ظاهرة دلوقتي" / "تصفية الفواتير" / "إعادة ضبط" / "طباعة إيصال كاشير 80mm" / "مشاركة الفاتورة عبر واتساب" / "إلغاء الفاتورة".
  * **حالة التطابق بين اللغتين:** ✅ متطابقة 100%.

---

### 5. الاختبار والتحقق الآلي (Automated Tests Suite)

* ✅ **اختبار الباك إند API (Feature Test):** تشغيل `php artisan test --filter=InvoicesAndPosApiTest` -> نجاح 7/7 اختبارات و 46 تأكيد في 2.8 ثانية.
* ✅ **اختبار الفرونت إند الشامل (Playwright E2E Test):** تشغيل `e2e/flows/invoices-full-page-audit.spec.js` -> نجاح 7/7 اختبارات عبر كافة مقاسات الشاشات الـ 5 مع خلو تام من أخطاء الـ Console.
* ✅ **فحص البناء (Build Verification):** تشغيل `npm run build` -> تم البناء بنجاح وبدون أي خطأ أو تحذير في 4.28 ثانية.

---

---

## 📌 صفحة 2: نقطة البيع والكاشير السريع (`PosView.vue`) — بتاريخ 2026-08-23
* **المسار:** `/pos`
* **الحالة العامة:** ✅ مكتملة 100% (تفكيك 1034 سطر إلى 6 مكونات فرعية + نمط المنسق النحيف Thin Orchestrator + تجاوب لمسي كامل لكافة المقاسات الـ 5 + تعريب كامل بدون نصوص ثابتة + اختبارات E2E و Feature API ناجحة 100%).

---

### 1. التوثيق الكامل (Full Documentation)

#### نظرة عامة:
* **اسم الصفحة:** نقطة البيع والكاشير السريع (POS Cashier)
* **المسار (Route):** `/pos`
* **الملف الرئيسي:** `resources/js/views/POS/PosView.vue` (Thin Orchestrator: ~70 سطر)
* **الغرض منها:** إصدار فواتير البيع الفوري السريع، قارئ الباركود، حساب الخصومات والشحن، السداد النقدي وحساب الباقي للعميل، السداد الإلكتروني، والطباعة الحرارية الفورية لإيصالات الكاشير 80mm.

#### تقسيم الأجزاء (Sections & Components Hierarchy):
1. **شريط الأوامر والبحث والتحكم العلوي (`POSHeader.vue`):**
   * زر العودة للفواتير، هوية النظام، اسم الفرع النشط، وحالة الوردية المفتوحة (رقم الوردية).
   * حقل البحث والباركود العملاق مع قائمة النتائج الحية المنسدلة والتنقل بالأسهم (F2 / Enter / Esc).
   * زر اختيار العميل السريع، مبدل فئة التسعير (قطاعي / جملة)، وزر إفراغ السلة.
2. **جدول بنود الفاتورة الحالية (`POSCartTable.vue`):**
   * عرض بنود السلة، الكميات، أزرار الزيادة والنقصان اللمسية (+ / -) وحقل الإدخال المباشر، السعر الفردي، إجمالي السطر، وزر الحذف.
   * حالة السلة الفارغة التفاعلية.
3. **شريط الأصناف الشائعة والسريعة (`POSQuickPinnedItems.vue`):**
   * قائمة أفقية قابلة للتمرير باللمس لأكثر الأصناف مبيعاً وإضافتها بنقرة واحدة (One-touch add).
4. **لوحة المحاسبة والإنهاء المالي (`POSCheckoutPanel.vue`):**
   * بطاقة التلخيص المالي (المجموع الفرعي، الخصم السريع 5%, 10%, 15%, 20%، المصاريف الإضافية والشحن، الصافي العملاق).
   * نوع الفاتورة والسداد (كاش فوري / آجل ذمم / دفع جزئي).
   * وسيلة الدفع (كاش نقدي / إنستاباي / محفظة ذكية).
   * حاسبة الكاش السريع (المبلغ بالظبط، 50، 100، 200، 500، 1000، 2000) وحساب الباقي للعميل بدقة.
   * أزرار التنفيذ المباشرة مع اختصارات لوحة المفاتيح (F9 / Enter / Ctrl+Enter).
5. **نافذة اختيار وإنشاء العميل السريع (`POSCustomerModal.vue`):**
   * بحث سريع عن العملاء، عرض رصيد العميل الحالي، ونموذج مصغر لإنشاء عميل جديد بلمسة واحدة.
6. **نافذة نجاح الفاتورة والطباعة (`POSSuccessModal.vue`):**
   * تأكيد إصدار الفاتورة برقمها وصافيها، زر طباعة الإيصال الحراري وزر بدء فاتورة جديدة (Enter).

#### العناصر التفاعلية:
* اختصارات الكيبورد (`F2` للبحث، `F9` للحفظ والاعتماد، `Enter` لاختيار الصنف أو بدء فاتورة جديدة، `Esc` لإغلاق القوائم)، سداد كاش فوري وحساب الفكة، فئات التسعير (جملة/قطاعي).

#### الاعتماديات والمصادر:
* **API Endpoints:** `GET /api/v1/items`, `GET /api/v1/customers`, `POST /api/v1/customers`, `GET /api/v1/shifts/current`, `POST /api/v1/invoices`.
* **Composables:** `useFormatters.js` (تنسيق المبالغ المالية).
* **المكونات المشتركة:** `AppModal.vue`.

---

### 2. المكونات المشتركة (Shared Components)
* **موجودة بالفعل ومُستخدمة هنا:**
  * `AppModal.vue`: حاوية النوافذ المنبثقة لاختيار العميل وتأكيد الفاتورة.
* **جديدة اتعملت من هذه الصفحة (داخل `resources/js/Components/POS/`):**
  * `POSHeader.vue`: شريط رأس الكاشير والبحث بالباركود والخيارات.
  * `POSCartTable.vue`: جدول بنود الفاتورة وإدارة الكميات.
  * `POSQuickPinnedItems.vue`: شريط الأصناف الشائعة الملموسة.
  * `POSCheckoutPanel.vue`: لوحة الحساب النهائي والدفع وطرق السداد.
  * `POSCustomerModal.vue`: نافذة اختيار أو إضافة عميل سريع.
  * `POSSuccessModal.vue`: نافذة اكتمال الفاتورة والطباعة السريعة.

---

### 3. التجاوب وتجربة اللمس (Responsive & Touch Ergonomics)
* **📱 مشاكل هاتف (360px - 430px):**
  * التخطيط يتحول عمودياً (Single Column Stack) حيث تظهر السلة بالأعلى تليها الأصناف الشائعة ثم لوحة المحاسبة بالأسفل.
  * تكبير مساحة أزرار الكميات (+ / -) وأزرار الدفع والتأكيد لتكون $\ge 44\text{px}$ ملائمة للمس بالإبهام.
  * شريط الأصناف الشائعة قابل للتمرير الأفقي باللمس.
* **📱 تابلت (600px - 768px):**
  * شريط البحث يتوسط الرأس مع توفير أزرار واضحة للعملاء وفئات التسعير.
* **💻 لابتوب وشاشات كبيرة (1024px+ و 1280px+):**
  * توزيع شبكي بنسبة 65% لجدول الأصناف و 35% للوحة المحاسبة لتجربة كاشير فائقة السرعة بدون أي Scroll رأسي غير ضروري.

---

### 4. الترجمة والتعريب (100% Zero Hardcoded Localization)
* **المفاتيح المضافة والمحدثة:**
  * تم استخراج كافة النصوص وإضافتها في `lang/ar/pos.php` و `lang/en/pos.php` و `defaultTranslations.js`.
  * صياغة مصرية خفيفة ومهنية: ("المبلغ بالظبط", "الباقي للعميل", "نقدي عام", "الفاتورة فارغة حالياً", "حفظ واعتماد الفاتورة").
  * حالة التطابق: ✅ متطابقة 100%.

---

### 5. الاختبار والتحقق الآلي (Automated Tests Suite)
* ✅ **اختبار الباك إند API (Feature Test):** تشغيل `php artisan test --filter=InvoicesAndPosApiTest` -> نجاح 7/7 اختبارات و 46 تأكيد.
* ✅ **اختبار الفرونت إند الشامل (Playwright E2E Test):** تشغيل `e2e/flows/pos-full-page-audit.spec.js` -> نجاح 8/8 اختبارات عبر كافة مقاسات الشاشات الـ 5 وخلو تام من أخطاء الـ Console.
* ✅ **فحص البناء (Build Verification):** تشغيل `npm run build` -> تم البناء بنجاح وبدون أي خطأ في 4.92 ثانية.

---

## 📌 صفحة 3: إدارة الأصناف والمخزون (`ItemsView.vue`) — بتاريخ 2026-08-23
* **المسار:** `/items`
* **الحالة العامة:** ✅ مكتملة 100% (تفكيك 846 سطر إلى 5 مكونات فرعية + نمط المنسق النحيف Thin Orchestrator < 80 سطر + تجاوب لمسي كامل لكافة المقاسات الـ 5 مع نمط بطاقات الموبايل الملموسة + استخدام مكتبة Form و Common بنسبة 100% + تعريب كامل بدون نصوص ثابتة + اختبارات E2E و Feature API ناجحة 100%).

---

### 1. التوثيق الكامل (Full Documentation)

#### نظرة عامة:
* **اسم الصفحة:** إدارة المخزون وبطاقات الأصناف (Items & Inventory Management)
* **المسار (Route):** `/items`
* **الملف الرئيسي:** `resources/js/views/Items/ItemsView.vue` (Thin Orchestrator: ~75 سطر)
* **الغرض منها:** متابعة وإدارة دليل بطاقات الأصناف والمنتجات، متابعة أرصدة المخازن الحية، إدارة أسعار الشراء (التكلفة) والبيع قطاعي وأقل سعر بيع (الجملة)، تنبيهات نواقص المخزون وحد الطلب، التسويات المخزنية الفورية (جرد/هالك/إيداع)، وكارت حركة الصنف.

#### تقسيم الأجزاء (Sections & Components Hierarchy):
1. **رأس الصفحة والإجراءات العامة (`PageHeader.vue`):**
   * عنوان الصفحة والوصف وهوية الأيقونة.
   * زر إضافة صنف جديد (`BaseButton` Gradient).
2. **بطاقات المؤشرات المخزنية (`ItemsMetricsGrid.vue`):**
   * 3 بطاقات KPI مبنية بـ `MetricCard.vue`: (إجمالي قيمة المخزون التقديرية، أصناف قاربت على النفاد، وإجمالي عدد الأصناف المسجلة).
3. **شريط البحث وفلاتر الحالة (`ItemsSearchFilterBar.vue`):**
   * حقل البحث المباشر بـ `BaseSearchInput.vue` (يدعم البحث بالاسم، الكود، والباركود مع زر تفريغ سريع).
   * القائمة المنسدلة للأقسام والتصنيفات بـ `BaseSelect.vue`.
   * أقراص فلاتر حالة الرصيد (الكل، نواقص المخزن 🚨، نفدت الكمية ❌، متوفر في المخزن ✅) بأزرار لمس مريحة.
4. **جدول وبطاقات الأصناف المزدوج (`ItemsTable.vue`):**
   * **للشاشات الكبيرة والتابلت (`hidden md:block`):** جدول متقدم عالي الكثافة (الكود، الاسم والملاحظات، التصنيف، سعر التكلفة، سعر البيع قطاعي، سعر الجملة، الرصيد المتاح وشارة النواقص، شارة الحالة، وأزرار التسوية الجردية، كارت الحركة، التعديل، والحذف).
   * **للهواتف والشاشات الصغيرة (`block md:hidden`):** بطاقات لمسية متراصة (Tactile Cards Stack) بأزرار وإجراءات مريحة $\ge 44\text{px}$.
   * شريط التصفح والترقيم (Pagination).
   * حالة عدم وجود بيانات (`EmptyState.vue`).
5. **نافذة إضافة وتعديل الصنف (`ItemFormModal.vue`):**
   * نافذة منبثقة تفاعلية مبنية بالكامل باستخدام مكونات `Components/Form/` (`BaseInput`, `BaseNumberInput`, `BaseSelect`, `BaseTextarea`, `BaseButton`).
6. **نافذة التسوية المخزنية السريعة (`ItemStockAdjustModal.vue`):**
   * تسوية رصيد الصنف الفورية (تسوية بالزيادة، تسوية بالنقصان، إهلاك وهالك، إيداع مخزني) مع تحديث الرصيد وتسجيل الحركة آلياً.

#### الاعتماديات والمصادر:
* **API Endpoints:** `GET /api/v1/items`, `POST /api/v1/items`, `PUT /api/v1/items/{id}`, `DELETE /api/v1/items/{id}`, `POST /api/v1/items/{id}/adjust-stock`.
* **Composables:** `useFormatters.js` (للمبالغ المالية والكميات).
* **المكونات المشتركة المستخرجة والمستخدمة:** `PageHeader.vue`, `MetricCard.vue`, `BaseButton.vue`, `BaseSearchInput.vue`, `BaseSelect.vue`, `BaseInput.vue`, `BaseNumberInput.vue`, `BaseTextarea.vue`, `AppModal.vue`, `EmptyState.vue`.

---

### 2. المكونات المشتركة (Shared Components)
* **مستخدمة من مكتبة `Form/` و `Common/`:**
  * `PageHeader.vue`: ترويسة الصفحة.
  * `MetricCard.vue`: بطاقات الـ KPI الثلاث.
  * `BaseButton.vue`: كافة أزرار الإجراءات والتنقل والحفظ والإلغاء.
  * `BaseSearchInput.vue`: حقل البحث المباشر مع زر الإفراغ.
  * `BaseSelect.vue`: قوائم الفئات، الوحدات، ونوع حركة التسوية.
  * `BaseInput.vue`: حقول إدخال النصوص (الاسم، الباركود، الملاحظات).
  * `BaseNumberInput.vue`: حقول الأرقام والأسعار والكميات بدقة `DECIMAL(12,3)`.
  * `BaseTextarea.vue`: حقل الملاحظات.
  * `AppModal.vue`: نوافذ الإضافة والتعديل والتسوية.
  * `EmptyState.vue`: شاشة عدم وجود أصناف.
* **المكونات التابعة للصفحة (داخل `resources/js/Components/Items/`):**
  * `ItemsMetricsGrid.vue`: شبكة بطاقات الإحصاءات المخزنية.
  * `ItemsSearchFilterBar.vue`: شريط البحث وتصفية الفئات وحالة الرصيد.
  * `ItemsTable.vue`: الجدول المزدوج التجاوبي (Desktop Table + Mobile Cards).
  * `ItemFormModal.vue`: نافذة إضافة وتعديل بيانات الصنف.
  * `ItemStockAdjustModal.vue`: نافذة التسوية الجردية والمخزنية.

---

### 3. التجاوب وتجربة اللمس (Responsive & Touch Ergonomics)
* **📱 هواتف (360px - 430px):**
  * إلغاء الجدول الأفقي المضغوط واستبداله ببطاقات لمسية مستقلة لكل صنف توضح السعر قطاعي وجملة والرصيد وشارة النواقص.
  * مساحات لمس الأزرار $\ge 44\text{px}$ ملائمة للإبهام.
  * شريط فلاتر الحالة قابل للتمرير الأفقي باللمس.
* **📱 تابلت (600px - 768px):**
  * بطاقات الـ KPI تتوزع بنظام 3 أعمدة مرنة.
* **💻 لابتوب وشاشات كبيرة (1024px+ و 1280px+):**
  * جدول فائق الكثافة ومنظم مع محاذاة الأرقام المالية جهة اليسار والنصوص جهة اليمين.

---

### 4. الترجمة والتعريب (100% Zero Hardcoded Localization)
* **المفاتيح المضافة والمحدثة:**
  * تسجيل كافة النصوص في `lang/ar/inventory.php` و `lang/en/inventory.php` و `defaultTranslations.js`.
  * تعريب كامل بدون أي نصوص ثابتة للغتين العربية والإنجليزية.
  * حالة التطابق: ✅ 100%.

---

### 5. الاختبار والتحقق الآلي (Automated Tests Suite)
* ✅ **اختبار الباك إند API (Feature Test):** تشغيل `php artisan test --filter=ItemsApiTest` -> نجاح 9/9 اختبارات و 36 تأكيد في 2.9 ثانية.
* ✅ **اختبار الفرونت إند الشامل (Playwright E2E Test):** تشغيل `e2e/flows/items-full-page-audit.spec.js` -> نجاح 7/7 اختبارات عبر كافة مقاسات الشاشات الـ 5 بدون أي خطأ Console.
* ✅ **فحص البناء (Build Verification):** تشغيل `npm run build` -> تم البناء بنجاح وبدون أي خطأ في 4.04 ثانية.

---

---

## 📌 صفحة 4: فئات وتصنيفات الأصناف (`CategoriesView.vue`) — بتاريخ 2026-08-23
* **المسار:** `/categories`
* **الحالة العامة:** ✅ مكتملة 100% (تفكيك 318 سطر إلى 3 مكونات فرعية + نمط المنسق النحيف Thin Orchestrator < 60 سطر + تجاوب لمسي كامل لكافة المقاسات الـ 5 مع شبكة بطاقات الفئات التفاعلية + استخدام مكتبة Form و Common بنسبة 100% + تعريب كامل بدون نصوص ثابتة + اختبارات E2E و Feature API ناجحة 100%).

---

### 1. التوثيق الكامل (Full Documentation)

#### نظرة عامة:
* **اسم الصفحة:** إدارة فئات وتصنيفات الأصناف (Categories Management)
* **المسار (Route):** `/categories`
* **الملف الرئيسي:** `resources/js/views/Items/CategoriesView.vue` (Thin Orchestrator: ~55 سطر)
* **الغرض منها:** تنظيم وهيكلة أقسام وتصنيفات المنتجات (مثل: قهوة ساخنة، بن مطحون، حلويات، عصائر ومثلجات)، تحديد الأيقونة أو الإيموجي التعريفي لكل فئة، تحديد ترتيب ظهورها في شريط الفئات السريع بشاشة الـ POS، وتفعيل/تعطيل ظهور الفئة في الكاشير.

#### تقسيم الأجزاء (Sections & Components Hierarchy):
1. **رأس الصفحة والإجراءات العامة (`PageHeader.vue`):**
   * عنوان الصفحة والوصف وهوية الأيقونة `🗂️`.
   * زر إضافة فئة جديدة (`BaseButton` Gradient).
2. **شبكة بطاقات الفئات (`CategoriesGrid.vue`):**
   * شبكة متجاوبة لعرض بطاقات الفئات بتنسيق `grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4`.
   * مؤشر التحميل الدائري.
   * حالة عدم وجود فئات مسجلة (`EmptyState.vue`) مع زر إضافة أول فئة.
3. **بطاقة الفئة التفاعلية (`CategoryCard.vue`):**
   * عرض إيموجي الفئة الكبير مع تأثير تكبير سلس عند الـ Hover.
   * اسم الفئة وشارة عدد الأصناف المرتبطة بها (`items_count`).
   * شارة الحالة (نشط / معطل).
   * رقم ترتيب الظهور `#sort_order`.
   * أزرار التعديل والحذف اللمسية (`BaseButton`).
4. **نافذة إضافة وتعديل الفئة (`CategoryFormModal.vue`):**
   * نافذة منبثقة تفاعلية مبنية بالكامل باستخدام مكونات `Components/Form/` (`BaseInput`, `BaseNumberInput`, `BaseSwitch`, `BaseButton`).
   * لوحة سريعة لاختيار الإيموجي المناسب للفئة (`☕`, `🧃`, `🍰`, `🥪`, `🍪`, `🫘`, `🥤`, `🧊`, `🎁`, `📦`, `🥐`, `🥗`, `🍕`, `🍦`, `🍨`, `🍵`).

#### الاعتماديات والمصادر:
* **API Endpoints:** `GET /api/v1/categories`, `POST /api/v1/categories`, `PUT /api/v1/categories/{id}`, `DELETE /api/v1/categories/{id}`.
* **Composables:** `useTrans.js` / `trans.js`.
* **المكونات المشتركة المستخرجة والمستخدمة:** `PageHeader.vue`, `BaseButton.vue`, `BaseInput.vue`, `BaseNumberInput.vue`, `BaseSwitch.vue`, `AppModal.vue`, `EmptyState.vue`.

---

### 2. المكونات المشتركة (Shared Components)
* **مستخدمة من مكتبة `Form/` و `Common/`:**
  * `PageHeader.vue`: ترويسة الصفحة.
  * `BaseButton.vue`: كافة أزرار الإجراءات والتعديل والحذف والإلغاء والحفظ.
  * `BaseInput.vue`: حقول إدخال اسم الفئة والإيموجي.
  * `BaseNumberInput.vue`: حقل ترتيب الظهور مع أزرار الزيادة والنقصان Stepper.
  * `BaseSwitch.vue`: مبدل حالة التفعيل والظهور في الـ POS.
  * `AppModal.vue`: نافذة إضافة وتعديل الفئة.
  * `EmptyState.vue`: شاشة عدم وجود فئات.
* **المكونات التابعة للصفحة (داخل `resources/js/Components/Categories/`):**
  * `CategoryCard.vue`: بطاقة الفئة التفاعلية.
  * `CategoriesGrid.vue`: شبكة عرض الفئات وحالات التحميل والفراغ.
  * `CategoryFormModal.vue`: نافذة إنشاء وتعديل الفئة مع لوحة الإيموجي.

---

### 3. التجاوب وتجربة اللمس (Responsive & Touch Ergonomics)
* **📱 هواتف (360px - 430px):**
  * بطاقات الفئات تتوزع عمودياً `grid-cols-1` بحجم لمسي مريح مع أزرار تعديل وحذف $\ge 44\text{px}$.
  * لوحة اختيار الإيموجي تتيح النقر بالإبهام على أزرار عريضة ومريحة.
* **📱 تابلت (600px - 768px):**
  * شبكة من عمودين متوازنين `grid-cols-2`.
* **💻 لابتوب وشاشات كبيرة (1024px+ و 1280px+):**
  * شبكة رباعية الأعمدة `grid-cols-4` تستغل كامل عرض الشاشة مع تأثيرات حركية أنيقة.

---

### 4. الترجمة والتعريب (100% Zero Hardcoded Localization)
* **المفاتيح المضافة والمحدثة:**
  * تسجيل كافة النصوص في `lang/ar/inventory.php` و `lang/en/inventory.php` و `defaultTranslations.js`.
  * تعريب كامل بدون أي نصوص ثابتة للغتين العربية والإنجليزية.
  * حالة التطابق: ✅ 100%.

---

### 5. الاختبار والتحقق الآلي (Automated Tests Suite)
* ✅ **اختبار الباك إند API (Feature Test):** تشغيل `php artisan test --filter=CategoryApiTest` -> نجاح 2/2 اختبارات و 15 تأكيد في 1.1 ثانية.
* ✅ **اختبار الفرونت إند الشامل (Playwright E2E Test):** تشغيل `e2e/flows/categories-full-page-audit.spec.js` -> نجاح 7/7 اختبارات عبر كافة مقاسات الشاشات الـ 5 بدون أي خطأ Console.
* ✅ **فحص البناء (Build Verification):** تشغيل `npm run build` -> تم البناء بنجاح وبدون أي خطأ في 4.38 ثانية.

---

---

## 📌 صفحة 5: التحويلات المخزنية بين الفروع والمخازن (`StockTransfersView.vue`) — بتاريخ 2026-08-23
* **المسار:** `/stock-transfers`
* **الحالة العامة:** ✅ مكتملة 100% (تقطيع للمكونات الفرعية + نمط المنسق النحيف ~65 سطر + تجاوب ولمس كامل لكافة المقاسات الـ 5 + قائمة إجراءات Dropdown عائمة + تعريب كامل بدون نصوص ثابتة + دعم فائق للوضعين الداكن والفاتح في عناصر النماذج + اختبارات E2E و Feature ناجحة).

---

### 1. التوثيق الكامل (Full Documentation)

#### نظرة عامة:
* **اسم الصفحة:** التحويلات المخزنية بين الفروع والمخازن (Stock Transfers)
* **المسار (Route):** `/stock-transfers`
* **الملف الرئيسي:** `resources/js/views/StockTransfers/StockTransfersView.vue` (Thin Orchestrator: ~65 سطر).
* **الغرض منها:** إدارة وتتبع حركة نقل البضائع بين المخازن المركزية والفروع وسيارات التوزيع، فلترة السجلات، استعراض تفاصيل الأصنـاف، والإلغاء الآمن مع عكس المخزون.

#### تقسيم الأجزاء (Sections & Components Hierarchy):
1. **رأس الصفحة والإجراءات العامة (`PageHeader.vue`):**
   * زر إنشاء إذن تحويل مخزني جديد (`/stock-transfers/create`).
2. **بطاقات المؤشرات المخزنية (`StockTransfersMetricsGrid.vue`):**
   * 3 بطاقات KPI (إجمالي أذونات التحويل، التحويلات المنفذة، التحويلات الملغاة) مبنية بواسطة `MetricCard.vue`.
3. **شريط البحث والفلترة (`StockTransfersSearchFilterBar.vue`):**
   * حقل بحث برقم الإذن أو الملاحظات عبر `BaseSearchInput.vue`.
   * قوائم اختيار الفرع المصدر والفرع المستلم عبر `BaseSelect.vue`.
   * منتقي التواريخ المتجاوب عبر `BaseDatePicker.vue` (Flatpickr).
4. **جدول وبطاقات التحويلات (`StockTransfersTable.vue`):**
   * نمط عرض مزدوج (Dual Responsive View):
     * **ديسكتوب وتابلت (`hidden md:block`):** جدول بيانات عالي الكثافة مع قائمة إجراءات منسدلة عائمة `ActionMenu.vue`.
     * **موبايل (`block md:hidden`):** بطاقات لمسية توضح مسار التحويل وأزرار بارتفاع $\ge 44\text{px}$.
   * شريط الترقيم والتنقل بين الصفحات (Pagination).
   * حالة عدم وجود بيانات (`EmptyState.vue`).
5. **نافذة تفاصيل إذن التحويل (`StockTransferDetailsModal.vue`):**
   * نافذة تفاعلية عبر `AppModal.vue` تعرض بيانات المسار والتاريخ والمستخدم المسؤول وجدول الأصناف المحولة والملاحظات.

#### الاعتماديات والمصادر:
* **API Endpoints:** `GET /api/v1/transfers`, `GET /api/v1/transfers/{id}`, `POST /api/v1/transfers/{id}/cancel`, `GET /api/v1/stores`.
* **Actions:** `App\Actions\StockTransfers\CreateStockTransferAction`, `CancelStockTransferAction`.
* **Composables:** `useFormatters.js`, `useTrans.js` / `trans.js`.

---

### 2. المكونات المشتركة (Shared Components)
* **المكونات المشتركة المستخدمة من مكتبة `Form/` و `Common/`:**
  * `PageHeader.vue`, `BaseButton.vue`, `BaseSearchInput.vue`, `BaseSelect.vue`, `BaseDatePicker.vue`, `ActionMenu.vue`, `MetricCard.vue`, `AppModal.vue`, `EmptyState.vue`.
* **المكونات التابعة للصفحة (داخل `resources/js/Components/StockTransfers/`):**
  * `StockTransfersMetricsGrid.vue`, `StockTransfersSearchFilterBar.vue`, `StockTransfersTable.vue`, `StockTransferDetailsModal.vue`.

---

### 3. التجاوب وتجربة اللمس (Responsive & Touch Ergonomics)
* **📱 هواتف (360px - 430px):** بطاقات لمسية متكاملة وقائمة إجراءات سفلية مريحة للإبهام.
* **📱 تابلت وديسكتوب (768px - 1280px+):** جدول متناسق وقوائم منسدلة بدون أي قص بالـ Overflow.
* **🌓 الوضع الداكن والفاتح:** معالجة كاملة لتباين حقول الإدخال، وخيارات منتقي التقويم (`Flatpickr monthDropdown`) لتظهر بوضوح تام وخلفيات داكنة محكمة.

---

### 4. الترجمة والتعريب (100% Zero Hardcoded Localization)
* مفاتيح موثقة ومترجمة في `lang/ar/inventory.php`، `lang/en/inventory.php`، و `defaultTranslations.js`.
* نسبة التطابق: ✅ 100%.

---

### 5. الاختبار والتحقق الآلي (Automated Tests Suite)
* ✅ **اختبار الباك إند API (Feature Test):** تشغيل `php artisan test tests/Feature/Api/StockTransfersApiTest.php` -> نجاح 4/4 اختبارات و 25 تأكيد في 1.7 ثانية.
* ✅ **اختبار الفرونت إند الشامل (Playwright E2E Test):** تشغيل `e2e/flows/stock-transfers-full-page-audit.spec.js` -> نجاح 7/7 اختبارات عبر كافة مقاسات الشاشات الـ 5.
* ✅ **اختبار مجمع لكافة الصفحات:** نجاح 25/25 اختبار في 1.7 دقيقة بدون أي خطأ Console.
* ✅ **فحص البناء (Build Verification):** تشغيل `npm run build` -> تم البناء بنجاح وبدون أي خطأ في 3.8 ثانية.

---

* **آخر صفحة تمت مراجعتها بالكامل:** إدارة الفروع والمخازن (`StoresView.vue`)
* **الصفحة التالية المقترحة للجلسة القادمة:** حاسبة خلطات وتكاليف البن (`CoffeeBlenderView.vue`)

---

## 📌 صفحة 6: إدارة الفروع والمخازن (`StoresView.vue`) — بتاريخ 2026-08-23
* **المسار:** `/stores`
* **الحالة العامة:** ✅ مكتملة 100% (تقطيع للمكونات الفرعية + نمط المنسق النحيف < 70 سطر + تجاوب ولمس كامل لكافة المقاسات الـ 5 + تعريب كامل بدون نصوص ثابتة + معيار هياكل الوميض Skeleton + اختبارات E2E ناجحة 7/7).

---

### 1. التوثيق الكامل (Full Documentation)

#### نظرة عامة:
* **اسم الصفحة:** إدارة الفروع والمخازن ونقاط التوزيع (Stores & Branches Management)
* **المسار (Route):** `/stores`
* **الملف الرئيسي:** `resources/js/views/Stores/StoresView.vue` (Thin Orchestrator: ~70 سطر)
* **الغرض منها:** إدارة الهيكل الجغرافي للمنشأة متعددة الفروع والمخازن وعربات التوزيع، تعيين وحماية الفرع الرئيسي، إدارة وتعيين الموظفين والكاشيرات، وتتبع إحصائيات الأداء الحية لكل فرع.

#### تقسيم الأجزاء (Sections & Components Hierarchy):
1. **رأس الصفحة والإجراءات العامة (`PageHeader.vue`):**
   * زر الوصول السريع لجرد وأرصدة الفروع (`/stores/stocks`).
   * زر إضافة فرع/مخزن جديد.
2. **بطاقات المؤشرات العامة (`StoresMetricsGrid.vue`):**
   * 4 بطاقات KPI (إجمالي الفروع، الفروع النشطة، الفرع الرئيسي، وتشكيلة الأصناف الـ SKUs) مع هياكل التحميل التفاعلية `StatCardSkeleton.vue`.
3. **شريط البحث والتصفية (`StoresSearchFilterBar.vue`):**
   * بحث سريع بالاسم والكود والعنوان ورقم الهاتف، فلاتر نوع الفرع وحالة النشاط، وزر إعادة الضبط السريع.
4. **شبكة بطاقات الفروع (`StoresGrid.vue`):**
   * شبكة متجاوبة تدعم `CardSkeleton.vue` أثناء التحميل، شارة الفرع الرئيسي مع Ambient Glow، إحصائيات الـ KPIs الداخلية، وعناصر الإجراءات الموحدة `ActionMenu.vue`.
5. **نافذة إنشاء وتعديل الفرع (`StoreFormModal.vue`):**
   * نافذة تفاعلية عبر `AppModal.vue` مع عناصر `BaseInput` و `BaseSelect`.
6. **نافذة تعيين الموظفين (`StoreStaffModal.vue`):**
   * قائمة اختيار تفاعلية للموظفين والكاشيرات المصرح لهم بإجراء العمليات على الفرع.

#### الاعتماديات والمصادر:
* **API Endpoints:** `GET /api/v1/stores`, `POST /api/v1/stores`, `PUT /api/v1/stores/{id}`, `PATCH /api/v1/stores/{id}/toggle-active`, `POST /api/v1/stores/{id}/assign-users`, `DELETE /api/v1/stores/{id}`.
* **Actions:** `CreateStoreAction`, `UpdateStoreAction`, `ToggleStoreActiveAction`, `AssignStoreUsersAction`, `DeleteStoreAction`.
* **Form Requests:** `StoreStoreRequest`, `UpdateStoreRequest`, `AssignStoreUsersRequest`.
* **DTO:** `StoreDTO`.

---

### 2. المكونات المشتركة (Shared Components)
* **المكونات المشتركة المستخدمة:**
  * `PageHeader.vue`, `BaseButton.vue`, `BaseInput.vue`, `BaseSelect.vue`, `ActionMenu.vue`, `MetricCard.vue`, `StatCardSkeleton.vue`, `CardSkeleton.vue`, `AppModal.vue`, `EmptyState.vue`.
* **المكونات التابعة للصفحة (داخل `resources/js/Components/Stores/`):**
  * `StoresMetricsGrid.vue`, `StoresSearchFilterBar.vue`, `StoresGrid.vue`, `StoreFormModal.vue`, `StoreStaffModal.vue`.

---

### 3. التجاوب وتجربة اللمس (Responsive & Touch Ergonomics)
* **📱 هواتف (360px - 430px):** شبكة أحادية العمود `grid-cols-1` مع مساحات لمسية $\ge 44\text{px}$.
* **📱 تابلت وديسكتوب (768px - 1280px+):** شبكة متجاوبة ثنائية وثلاثية الأعمدة (`grid-cols-2`, `grid-cols-3`) مع قائمة إجراءات منسدلة `ActionMenu.vue` تمنع تداخل النصوص.
* **🌓 الوضع الداكن والفاتح:** تباين كامل لكافة الحقول والبطاقات وتكيف تلقائي لمتغيرات الثيم.

---

### 4. الترجمة والتعريب (100% Zero Hardcoded Localization)
* مفاتيح موثقة ومترجمة في `lang/ar/inventory.php`، `lang/en/inventory.php`، و `defaultTranslations.js`.
* نسبة التطابق: ✅ 100% بدون أي نصوص ثابتة أو Fallback Strings.

---

### 5. الاختبار والتحقق الآلي (Automated Tests Suite)
* ✅ **اختبار الفرونت إند الشامل (Playwright E2E Test):** تشغيل `e2e/flows/stores-full-page-audit.spec.js` -> نجاح 7/7 اختبارات عبر كافة مقاسات الشاشات الـ 5.
* ✅ **فحص البناء (Build Verification):** تشغيل `npm run build` -> تم البناء بنجاح وبدون أي خطأ في 4.6 ثانية.





---

## 📌 صفحة 7: حاسبة خلطات وتكاليف البن والأصناف المركبة (`CoffeeBlenderView.vue`) — بتاريخ 2026-08-24
* **المسار:** `/coffee-blender`
* **الحالة العامة:** ✅ مكتملة 100% (تفكيك 498 سطر إلى 3 مكونات فرعية + نمط المنسق النحيف Thin Orchestrator < 65 سطر + كبسولة المنطق useCoffeeBlender + تجاوب لمسي كامل لكافة المقاسات الـ 5 + استخدام مكتبة Form و Common بنسبة 100% + تعريب كامل بدون نصوص ثابتة + اختبارات E2E و Feature API ناجحة 100%).

---

### 1. التوثيق الكامل (Full Documentation)

#### نظرة عامة:
* **اسم الصفحة:** حاسبة خلطات وتكاليف البن والأصناف المركبة (Coffee Blender & Product Formulation Studio)
* **المسار (Route):** `/coffee-blender`
* **الملف الرئيسي:** `resources/js/views/CoffeeBlender/CoffeeBlenderView.vue` (Thin Orchestrator: ~65 سطر)
* **الغرض منها:** حساب وتوليف خلطات القهوة والمنتجات المجمعة، تحديد الأوزان المستهدفة ونسب الخامات، احتساب التكلفة الخام الفعلية وسعر البيع وهامش الربح فورياً، وإصدار فاتورة بيع مباشرة مع خصم الخامات بالجرام آلياً.

#### تقسيم الأجزاء (Sections & Components Hierarchy):
1. **رأس الصفحة والإجراءات العامة (`PageHeader.vue`):**
   * عنوان الصفحة وهوية الأيقونة وزر الانتقال السريع لسجل الفواتير.
2. **بطاقة مواصفات الخلطة (`CoffeeBlenderSpecsCard.vue`):**
   * حقل اسم الخلطة، أزرار الأوزان السريعة (125جم، 250جم، 500جم، 1000جم)، الوزن المخصص، نوع التحميص، ومستوى الطحن عبر `BaseInput`, `BaseNumberInput`, `BaseSelect`.
3. **بطاقة مكونات البن الخام والنسب (`CoffeeBlenderFormulationCard.vue`):**
   * اختيار الصنف، إضافة المكون، سلايدر النسبة المئوية المباشر، حقل النسبة الرقمي، حساب الجرامات والسعر، حقل تحويجة الحبهان، والملاحظات مع زر الحذف اللمسي.
4. **لوحة ملخص التكلفة والاعتماد (`CoffeeBlenderCostSummary.vue`):**
   * التلخيص المالي (الوزن الإجمالي، تكلفة الخامات، سعر البيع المقترح، هامش الربح)، اختيار العميل المسجل أو النقدي العام، وزر اعتماد وإصدار الفاتورة المباشر.

#### الاعتماديات والمصادر:
* **API Endpoints:** `GET /api/v1/items`, `GET /api/v1/customers`, `POST /api/v1/coffee-blender/calculate`, `POST /api/v1/coffee-blender/invoice`.
* **Actions:** `CalculateBlendCostAction`, `CreateBlenderInvoiceAction`.
* **Composables:** `useCoffeeBlender.js`, `useFormatters.js`, `useTrans.js`.
* **المكونات المشتركة:** `PageHeader.vue`, `BaseButton.vue`, `BaseInput.vue`, `BaseNumberInput.vue`, `BaseSelect.vue`.

---

### 2. المكونات المشتركة (Shared Components)
* **المكونات المشتركة المستخدمة من مكتبة `Form/` و `Common/`:**
  * `PageHeader.vue`, `BaseButton.vue`, `BaseInput.vue`, `BaseNumberInput.vue`, `BaseSelect.vue`.
* **المكونات التابعة للصفحة (داخل `resources/js/Components/CoffeeBlender/`):**
  * `CoffeeBlenderSpecsCard.vue`, `CoffeeBlenderFormulationCard.vue`, `CoffeeBlenderCostSummary.vue`.

---

### 3. التجاوب وتجربة اللمس (Responsive & Touch Ergonomics)
* **📱 هواتف (360px - 430px):** ترتيب عمودي كامل مع بطاقات لمسية متراصة ومساحات لمس $\ge 44	ext{px}$.
* **💻 تابلت وديسكتوب (768px - 1280px+):** توزيع 8:4 مع لوحة تلخيص ملتصقة وثابتة `sticky top-6`.
* **🌓 الوضع الداكن والفاتح:** تباين كامل لكافة العناصر والمدخلات.

---

### 4. الترجمة والتعريب (100% Zero Hardcoded Localization)
* ترجمة عربية وإنجليزية كاملة في `lang/ar/inventory.php` و `lang/en/inventory.php` و `defaultTranslations.js`.
* نسبة التطابق: ✅ 100%.

---

### 5. الاختبار والتحقق الآلي (Automated Tests Suite)
* ✅ **اختبار الباك إند API:** تشغيل `php artisan test tests/Feature/Api/CoffeeBlenderApiTest.php` -> نجاح 2/2 اختبارات و 6 تأكيدات في 1.2 ثانية.
* ✅ **اختبار الفرونت إند الشامل:** تشغيل `e2e/flows/coffee-blender-full-page-audit.spec.js` -> نجاح 7/7 اختبارات عبر كافة مقاسات الشاشات الـ 5 بدون أي خطأ Console.
* ✅ **فحص البناء:** تشغيل `npm run build` -> تم البناء بنجاح في 4.47 ثانية.


---

## 📌 صفحة 8: رادار إعادة الطلب الذكي ومساعد المشتريات (`SmartReorderView.vue`) — بتاريخ 2026-08-24
* **المسار:** `/purchases/smart-reorder` (والمسار البديل `/smart-reorder`)
* **الحالة العامة:** ✅ مكتملة 100% (تفكيك 354 سطر إلى 3 مكونات فرعية + نمط المنسق النحيف Thin Orchestrator < 65 سطر + كبسولة المنطق useSmartReorder + تجاوب لمسي كامل لكافة المقاسات الـ 5 + دعم هياكل الوميض Skeleton + تعريب كامل بدون نصوص ثابتة + اختبارات E2E ناجحة 7/7).

---

### 1. التوثيق الكامل (Full Documentation)

#### نظرة عامة:
* **اسم الصفحة:** رادار إعادة الطلب الذكي ومساعد المشتريات (Smart Reorder Radar)
* **المسار (Route):** `/purchases/smart-reorder`
* **الملف الرئيسي:** `resources/js/views/Purchases/SmartReorderView.vue` (Thin Orchestrator: ~65 سطر)
* **الغرض منها:** التنبؤ الذكي بالنواقص، قياس معدل الاستهلاك اليومي، حساب فترة صمود المخزون بالأيام، واقتراح كميات الشراء المثالية وتكلفتها التقديرية، وتصدير أوامر الشراء المجمعة بنقرة واحدة.

#### تقسيم الأجزاء (Sections & Components Hierarchy):
1. **رأس الصفحة والإجراءات العامة (`PageHeader.vue`):**
   * عنوان الصفحة وهوية الـ AI وزر إنشاء أمر الشراء المجمع للبنود المحددة.
2. **بطاقات مؤشرات مستويات الخطر والتكلفة (`SmartReorderMetricsGrid.vue`):**
   * 4 بطاقات KPI (النواقص الحرجة، تنبيهات التوريد، الأرصدة الآمنة، والتكلفة التقديرية لإعادة الشراء) مدعومة بـ `StatCardSkeleton.vue`.
3. **شريط الفلاتر والتحكم (`SmartReorderFilterBar.vue`):**
   * حقل البحث المباشر `BaseSearchInput`، وقوائم اختيار فترات التحليل والتغطية ومستويات الخطر عبر `BaseSelect`.
4. **جدول وبطاقات التوصيات الذكية (`SmartReorderTable.vue`):**
   * نمط مزدوج (Desktop Table + Mobile Tactile Cards Stack) مع التحديد المجمع، حساب الأيام المتبقية، الكميات المقترحة، التكلفة، وشارات الخطر، مع دعم `TableSkeleton.vue` و `EmptyState.vue`.

#### الاعتماديات والمصادر:
* **API Endpoints:** `GET /api/v1/purchases/smart-reorder`.
* **Composables:** `useSmartReorder.js`, `useFormatters.js`, `useTrans.js`.
* **المكونات المشتركة:** `PageHeader.vue`, `BaseButton.vue`, `BaseSearchInput.vue`, `BaseSelect.vue`, `StatCardSkeleton.vue`, `TableSkeleton.vue`, `EmptyState.vue`.

---

### 2. المكونات المشتركة (Shared Components)
* **المكونات المشتركة المستخدمة:**
  * `PageHeader.vue`, `BaseButton.vue`, `BaseSearchInput.vue`, `BaseSelect.vue`, `StatCardSkeleton.vue`, `TableSkeleton.vue`, `EmptyState.vue`.
* **المكونات التابعة للصفحة (داخل `resources/js/Components/SmartReorder/`):**
  * `SmartReorderMetricsGrid.vue`, `SmartReorderFilterBar.vue`, `SmartReorderTable.vue`.

---

### 3. التجاوب وتجربة اللمس (Responsive & Touch Ergonomics)
* **📱 هواتف (360px - 430px):** بطاقات لمسية متكاملة ومربعات اختيار بارتفاع $\ge 44	ext{px}$.
* **💻 تابلت وديسكتوب (768px - 1280px+):** جدول منظم عالي الكثافة ومحاذاة مالية سليمة.
* **🌓 الوضع الداكن والفاتح:** تباين كامل للبطاقات وألوان شارات التحذير.

---

### 4. الترجمة والتعريب (100% Zero Hardcoded Localization)
* ترجمة عربية وإنجليزية كاملة في `lang/ar/purchases.php` و `lang/en/purchases.php` و `defaultTranslations.js`.
* نسبة التطابق: ✅ 100%.

---

### 5. الاختبار والتحقق الآلي (Automated Tests Suite)
* ✅ **اختبار الفرونت إند الشامل:** تشغيل `e2e/flows/smart-reorder-full-page-audit.spec.js` -> نجاح 7/7 اختبارات عبر كافة مقاسات الشاشات الـ 5 بدون أي خطأ Console.
* ✅ **فحص البناء:** تشغيل `npm run build` -> تم البناء بنجاح في 4.29 ثانية.


---

## 📌 صفحة 9: جرد وأرصدة الفروع والمخازن (`StoreStocksView.vue`) — بتاريخ 2026-08-24
* **المسار:** `/stores/stocks`
* **الحالة العامة:** ✅ مكتملة 100% (تفكيك 274 سطر إلى مكونين فرعيين + نمط المنسق النحيف Thin Orchestrator < 50 سطر + كبسولة المنطق useStoreStocks + تجاوب لمسي كامل لكافة المقاسات الـ 5 + دعم هياكل الوميض TableSkeleton + تعريب كامل بدون نصوص ثابتة + اختبارات E2E ناجحة 7/7).

---

### 1. التوثيق الكامل (Full Documentation)

#### نظرة عامة:
* **اسم الصفحة:** جرد وأرصدة الفروع والمخازن (Branch Stock Valuation & Inventory Audit)
* **المسار (Route):** `/stores/stocks`
* **الملف الرئيسي:** `resources/js/views/Stores/StoreStocksView.vue` (Thin Orchestrator: ~50 سطر)
* **الغرض منها:** الرقابة المباشرة على بضائع كل مخزن وفرع بيع على حدة، متابعة حدود الأمان، واحتساب القيمة المالية والتقييم الإجمالي للأصناف.

#### تقسيم الأجزاء (Sections & Components Hierarchy):
1. **رأس الصفحة والإجراءات العامة (`PageHeader.vue`):**
   * عنوان الصفحة وزر العودة لقائمة المخازن والفروع.
2. **شريط الفلاتر والتحكم (`StoreStocksFilterBar.vue`):**
   * قائمة اختيار الفرع/المخزن `BaseSelect`، حقل البحث السريع `BaseSearchInput`، وأقراص تصفية حالة الرصيد اللمسية.
3. **جدول وبطاقات الأرصدة وجرد المخازن (`StoreStocksTable.vue`):**
   * نمط مزدوج (Desktop Table + Mobile Tactile Cards Stack) مع شارات الحالة، تنسيق الأرقام، دعم `TableSkeleton.vue` و `EmptyState.vue` وشريط الترقيم.

#### الاعتماديات والمصادر:
* **API Endpoints:** `GET /api/v1/stores`, `GET /api/v1/stores/stocks`.
* **Composables:** `useStoreStocks.js`, `useFormatters.js`, `useTrans.js`.
* **المكونات المشتركة:** `PageHeader.vue`, `BaseSelect.vue`, `BaseSearchInput.vue`, `TableSkeleton.vue`, `EmptyState.vue`.

---

### 2. المكونات المشتركة (Shared Components)
* **المكونات المشتركة المستخدمة:**
  * `PageHeader.vue`, `BaseSelect.vue`, `BaseSearchInput.vue`, `TableSkeleton.vue`, `EmptyState.vue`.
* **المكونات التابعة للصفحة (داخل `resources/js/Components/StoreStocks/`):**
  * `StoreStocksFilterBar.vue`, `StoreStocksTable.vue`.

---

### 3. التجاوب وتجربة اللمس (Responsive & Touch Ergonomics)
* **📱 هواتف (360px - 430px):** بطاقات لمسية متكاملة وتراص مرن وأزرار بارتفاع $\ge 44	ext{px}$.
* **💻 تابلت وديسكتوب (768px - 1280px+):** جدول منظم عالي الكثافة ومحاذاة مالية دقيقة.
* **🌓 الوضع الداكن والفاتح:** تباين كامل للبطاقات وألوان شارات الرصيد.

---

### 4. الترجمة والتعريب (100% Zero Hardcoded Localization)
* ترجمة عربية وإنجليزية كاملة في `lang/ar/inventory.php` و `lang/en/inventory.php` و `defaultTranslations.js`.
* نسبة التطابق: ✅ 100%.

---

### 5. الاختبار والتحقق الآلي (Automated Tests Suite)
* ✅ **اختبار الفرونت إند الشامل:** تشغيل `e2e/flows/store-stocks-full-page-audit.spec.js` -> نجاح 7/7 اختبارات عبر كافة مقاسات الشاشات الـ 5 بدون أي خطأ Console.
* ✅ **فحص البناء:** تشغيل `npm run build` -> تم البناء بنجاح في 4.18 ثانية.


---

## 📌 صفحة 10: إنشاء إذن تحويل مخزني (`CreateStockTransferView.vue`) — بتاريخ 2026-08-24
* **المسار:** `/stock-transfers/create`
* **الحالة العامة:** ✅ مكتملة 100% (تفكيك 304 سطر إلى مكونين فرعيين + نمط المنسق النحيف Thin Orchestrator < 55 سطر + كبسولة المنطق useCreateStockTransfer + تجاوب لمسي كامل لكافة المقاسات الـ 5 + تعريب كامل بدون نصوص ثابتة + اختبارات E2E ناجحة 7/7).

---

### 1. التوثيق الكامل (Full Documentation)

#### نظرة عامة:
* **اسم الصفحة:** إنشاء إذن تحويل مخزني (Create Stock Transfer)
* **المسار (Route):** `/stock-transfers/create`
* **الملف الرئيسي:** `resources/js/views/StockTransfers/CreateStockTransferView.vue` (Thin Orchestrator: ~55 سطر)
* **الغرض منها:** نقل وتوزيع البضائع والأصناف والمواد الخام بين المستودعات المركزية وفروع البيع ذرياً وبشكل آمن داخل Transaction.

#### تقسيم الأجزاء (Sections & Components Hierarchy):
1. **رأس الصفحة والإجراءات العامة (`PageHeader.vue`):**
   * عنوان الصفحة وزر العودة لقائمة سجل التحويلات.
2. **بطاقة اختيار الفروع والتاريخ (`CreateStockTransferHeaderCard.vue`):**
   * قائمة اختيار المخزن المصدر والمستلم `BaseSelect`، حقل تاريخ الإذن `BaseInput`، وحقل الملاحظات.
3. **بطاقة وجدول الأصناف المحولة (`CreateStockTransferItemsCard.vue`):**
   * نمط مزدوج (Desktop Table + Mobile Tactile Cards Stack) لإضافة الأصناف، تعديل الكميات بدقة، وحذف البنود.

#### الاعتماديات والمصادر:
* **API Endpoints:** `GET /api/v1/stores`, `GET /api/v1/items`, `POST /api/v1/transfers`.
* **Composables:** `useCreateStockTransfer.js`, `useTrans.js`.
* **المكونات المشتركة:** `PageHeader.vue`, `BaseButton.vue`, `BaseSelect.vue`, `BaseInput.vue`.

---

### 2. المكونات المشتركة (Shared Components)
* **المكونات المشتركة المستخدمة:**
  * `PageHeader.vue`, `BaseButton.vue`, `BaseSelect.vue`, `BaseInput.vue`.
* **المكونات التابعة للصفحة (داخل `resources/js/Components/StockTransfers/`):**
  * `CreateStockTransferHeaderCard.vue`, `CreateStockTransferItemsCard.vue`.

---

### 3. التجاوب وتجربة اللمس (Responsive & Touch Ergonomics)
* **📱 هواتف (360px - 430px):** بطاقات لمسية متكاملة وتراص مرن وأزرار وحقول بارتفاع $\ge 44	ext{px}$.
* **💻 تابلت وديسكتوب (768px - 1280px+):** شبكة حقول متناسقة وجدول بيانات منظم.
* **🌓 الوضع الداكن والفاتح:** تباين كامل للبطاقات وألوان الحقول.

---

### 4. الترجمة والتعريب (100% Zero Hardcoded Localization)
* ترجمة عربية وإنجليزية كاملة في `lang/ar/inventory.php` و `lang/en/inventory.php` و `defaultTranslations.js`.
* نسبة التطابق: ✅ 100%.

---

### 5. الاختبار والتحقق الآلي (Automated Tests Suite)
* ✅ **اختبار الفرونت إند الشامل:** تشغيل `e2e/flows/create-stock-transfer-full-page-audit.spec.js` -> نجاح 7/7 اختبارات عبر كافة مقاسات الشاشات الـ 5 بدون أي خطأ Console.
* ✅ **فحص البناء:** تشغيل `npm run build` -> تم البناء بنجاح في 4.88 ثانية.


---

## 📌 صفحة 11: كارت حركة الصنف والبطاقة المخزنية (`ItemMovementsView.vue`) — بتاريخ 2026-08-24
* **المسار:** `/items/:id/movements`
* **الحالة العامة:** ✅ مكتملة 100% (تفكيك 318 سطر إلى 3 مكونات فرعية + نمط المنسق النحيف Thin Orchestrator < 60 سطر + كبسولة المنطق useItemMovements + تجاوب لمسي كامل لكافة المقاسات الـ 5 + دعم هياكل الوميض Skeletons + تعريب كامل بدون نصوص ثابتة + اختبارات E2E ناجحة 7/7).

---

### 1. التوثيق الكامل (Full Documentation)

#### نظرة عامة:
* **اسم الصفحة:** كارت حركة الصنف وسجل العمليات (Item Movements Ledger & Bin Card)
* **المسار (Route):** `/items/:id/movements`
* **الملف الرئيسي:** `resources/js/views/Items/ItemMovementsView.vue` (Thin Orchestrator: ~60 سطر)
* **الغرض منها:** تتبع الأستاذ المخزني للصنف، والتحقق من الأرصدة قبل وبعد الحركات، ومراقبة الوارد والمنصرف بدقة.

#### تقسيم الأجزاء (Sections & Components Hierarchy):
1. **رأس الصفحة والإجراءات العامة (`PageHeader.vue`):**
   * اسم الصنف وكوده وزر الطباعة وزر العودة لقائمة الأصناف.
2. **بطاقات ملخص الحركات الكمية (`ItemMovementsSummaryCards.vue`):**
   * 4 بطاقات (إجمالي الوارد، المنصرف، صافي الحركة، والرصيد الحالي) مدعومة بـ `StatCardSkeleton.vue`.
3. **شريط الفلاتر والتحكم (`ItemMovementsFilterBar.vue`):**
   * حقول التاريخ وزر التصفية وأقراص الفترات السريعة (اليوم، الشهر، الكل).
4. **جدول وبطاقات الحركات التفصيلية (`ItemMovementsTable.vue`):**
   * نمط مزدوج (Desktop Table + Mobile Tactile Cards Stack) مع شارات أنواع الحركات والرصيد قبل وبعد والفرع والمستخدم.

#### الاعتماديات والمصادر:
* **API Endpoints:** `GET /api/v1/items/{id}/movements`.
* **Composables:** `useItemMovements.js`, `useFormatters.js`, `useTrans.js`.
* **المكونات المشتركة:** `PageHeader.vue`, `BaseButton.vue`, `StatCardSkeleton.vue`, `TableSkeleton.vue`, `EmptyState.vue`.

---

### 2. المكونات المشتركة (Shared Components)
* **المكونات المشتركة المستخدمة:**
  * `PageHeader.vue`, `BaseButton.vue`, `StatCardSkeleton.vue`, `TableSkeleton.vue`, `EmptyState.vue`.
* **المكونات التابعة للصفحة (داخل `resources/js/Components/ItemMovements/`):**
  * `ItemMovementsSummaryCards.vue`, `ItemMovementsFilterBar.vue`, `ItemMovementsTable.vue`.

---

### 3. التجاوب وتجربة اللمس (Responsive & Touch Ergonomics)
* **📱 هواتف (360px - 430px):** بطاقات لمسية متكاملة وتراص مرن وأزرار بارتفاع $\ge 44	ext{px}$.
* **💻 تابلت وديسكتوب (768px - 1280px+):** جدول منظم عالي الكثافة ومحاذاة كمية دقيقة.
* **🌓 الوضع الداكن والفاتح:** تباين كامل للبطاقات وألوان شارات الحركات.

---

### 4. الترجمة والتعريب (100% Zero Hardcoded Localization)
* ترجمة عربية وإنجليزية كاملة في `lang/ar/inventory.php` و `lang/en/inventory.php` و `defaultTranslations.js`.
* نسبة التطابق: ✅ 100%.

---

### 5. الاختبار والتحقق الآلي (Automated Tests Suite)
* ✅ **اختبار الفرونت إند الشامل:** تشغيل `e2e/flows/item-movements-full-page-audit.spec.js` -> نجاح 7/7 اختبارات عبر كافة مقاسات الشاشات الـ 5 بدون أي خطأ Console.
* ✅ **فحص البناء:** تشغيل `npm run build` -> تم البناء بنجاح في 4.52 ثانية.


---

## 📌 صفحة 12: سجل فواتير المشتريات والتوريد (`PurchasesView.vue`) — بتاريخ 2026-08-24
* **المسار:** `/purchases`
* **الحالة العامة:** ✅ مكتملة 100% (تفكيك 461 سطر إلى 4 مكونات فرعية + نمط المنسق النحيف Thin Orchestrator < 70 سطر + كبسولة المنطق usePurchases + تجاوب لمسي كامل لكافة المقاسات الـ 5 + دعم هياكل الوميض Skeletons + تعريب كامل بدون نصوص ثابتة + اختبارات E2E ناجحة 7/7).

---

### 1. التوثيق الكامل (Full Documentation)

#### نظرة عامة:
* **اسم الصفحة:** سجل فواتير المشتريات والتوريد (Purchases Management & Supply Invoices)
* **المسار (Route):** `/purchases`
* **الملف الرئيسي:** `resources/js/views/Purchases/PurchasesView.vue` (Thin Orchestrator: ~70 سطر)
* **الغرض منها:** استقبال البضائع والخامات من الموردين، مراقبة المديونيات الآجلة وتوزيع التكاليف، وإلغاء الفواتير بأمان.

#### تقسيم الأجزاء (Sections & Components Hierarchy):
1. **رأس الصفحة والإجراءات العامة (`PageHeader.vue`):**
   * عنوان الصفحة، زر الانتقال لرادار الطلب الذكي، وزر إنشاء فاتورة شراء جديدة.
2. **بطاقات مؤشرات المشتريات (`PurchasesMetricsGrid.vue`):**
   * 3 بطاقات (إجمالي المشتريات، المستحقات غير المسددة، والفواتير المعتمدة) مدعومة بـ `StatCardSkeleton.vue`.
3. **شريط الفلاتر والتحكم (`PurchasesFilterBar.vue`):**
   * حقل البحث النصي `BaseSearchInput`، قائمة الحالة `BaseSelect`، وحقول نطاق التاريخ (من / إلى).
4. **جدول وبطاقات فواتير الشراء (`PurchasesTable.vue`):**
   * نمط مزدوج (Desktop Table + Mobile Tactile Cards Stack) مع أزرار المعاينة والإلغاء وشارات الحالات والترقيم.
5. **نافذة تفاصيل الفاتورة (`PurchaseDetailsModal.vue`):**
   * استعراض بنود الفاتورة والكميات والتكلفة المحملة والخصومات والملخص المالي عبر `AppModal.vue`.

#### الاعتماديات والمصادر:
* **API Endpoints:** `GET /api/v1/purchases`, `POST /api/v1/purchases/{id}/cancel`.
* **Composables:** `usePurchases.js`, `useFormatters.js`, `useTrans.js`.
* **المكونات المشتركة:** `PageHeader.vue`, `BaseSearchInput.vue`, `BaseSelect.vue`, `StatCardSkeleton.vue`, `TableSkeleton.vue`, `EmptyState.vue`, `AppModal.vue`.

---

### 2. المكونات المشتركة (Shared Components)
* **المكونات المشتركة المستخدمة:**
  * `PageHeader.vue`, `BaseSearchInput.vue`, `BaseSelect.vue`, `StatCardSkeleton.vue`, `TableSkeleton.vue`, `EmptyState.vue`, `AppModal.vue`.
* **المكونات التابعة للصفحة (داخل `resources/js/Components/Purchases/`):**
  * `PurchasesMetricsGrid.vue`, `PurchasesFilterBar.vue`, `PurchasesTable.vue`, `PurchaseDetailsModal.vue`.

---

### 3. التجاوب وتجربة اللمس (Responsive & Touch Ergonomics)
* **📱 هواتف (360px - 430px):** بطاقات لمسية متكاملة وتراص مرن وأزرار وحقول بارتفاع $\ge 44	ext{px}$.
* **💻 تابلت وديسكتوب (768px - 1280px+):** جدول منظم عالي الكثافة ومحاذاة مالية دقيقة.
* **🌓 الوضع الداكن والفاتح:** تباين كامل للبطاقات وألوان الشارات.

---

### 4. الترجمة والتعريب (100% Zero Hardcoded Localization)
* ترجمة عربية وإنجليزية كاملة في `lang/ar/purchases.php` و `lang/en/purchases.php` و `defaultTranslations.js`.
* نسبة التطابق: ✅ 100%.

---

### 5. الاختبار والتحقق الآلي (Automated Tests Suite)
* ✅ **اختبار الفرونت إند الشامل:** تشغيل `e2e/flows/purchases-full-page-audit.spec.js` -> نجاح 7/7 اختبارات عبر كافة مقاسات الشاشات الـ 5 بدون أي خطأ Console.
* ✅ **فحص البناء:** تشغيل `npm run build` -> تم البناء بنجاح في 3.85 ثانية.


---

## 📌 صفحة 13: إنشاء واعتماد فاتورة شراء جديدة (`CreatePurchaseView.vue`) — بتاريخ 2026-08-24
* **المسار:** `/purchases/create`
* **الحالة العامة:** ✅ مكتملة 100% (تفكيك 398 سطر إلى 3 مكونات فرعية + نمط المنسق النحيف Thin Orchestrator < 55 سطر + كبسولة المنطق useCreatePurchase + تجاوب لمسي كامل لكافة المقاسات الـ 5 + دعم التعبئة المسبقة من رادار الطلب + تعريب كامل بدون نصوص ثابتة + اختبارات E2E ناجحة 7/7).

---

### 1. التوثيق الكامل (Full Documentation)

#### نظرة عامة:
* **اسم الصفحة:** إنشاء واعتماد فاتورة شراء جديدة (Create Purchase Invoice & Stock Receiving)
* **المسار (Route):** `/purchases/create`
* **الملف الرئيسي:** `resources/js/views/Purchases/CreatePurchaseView.vue` (Thin Orchestrator: ~55 سطر)
* **الغرض منها:** توريد البضائع والخامات من الموردين، احتساب التكلفة والخصومات، وتحديث المخزون وحساب المورد ذرياً داخل Transaction.

#### تقسيم الأجزاء (Sections & Components Hierarchy):
1. **رأس الصفحة والإجراءات العامة (`PageHeader.vue`):**
   * عنوان الصفحة وزر العودة لسجل فواتير المشتريات.
2. **بطاقة بيانات المورد وتاريخ الفاتورة (`CreatePurchaseSupplierCard.vue`):**
   * قائمة اختيار المورد `BaseSelect`، حقل تاريخ الفاتورة، وحقل المرجع الدفتري `BaseInput`.
3. **بطاقة وجدول بنود الأصناف المستلمة (`CreatePurchaseItemsCard.vue`):**
   * نمط مزدوج (Desktop Table + Mobile Tactile Cards Stack) لإضافة وحذف بنود الأصناف وضبط الكميات وأسعار التكلفة.
4. **بطاقة ملخص الحسابات والمدفوع (`CreatePurchaseSummaryCard.vue`):**
   * حقول الملاحظات، المدفوع، الخصم المكتسب، ومحاسبة الإجمالي الصافي والمتبقي آجل على المنشأة.

#### الاعتماديات والمصادر:
* **API Endpoints:** `GET /api/v1/suppliers`, `GET /api/v1/items`, `POST /api/v1/purchases`.
* **Composables:** `useCreatePurchase.js`, `useFormatters.js`, `useTrans.js`.
* **المكونات المشتركة:** `PageHeader.vue`, `BaseButton.vue`, `BaseSelect.vue`, `BaseInput.vue`.

---

### 2. المكونات المشتركة (Shared Components)
* **المكونات المشتركة المستخدمة:**
  * `PageHeader.vue`, `BaseButton.vue`, `BaseSelect.vue`, `BaseInput.vue`.
* **المكونات التابعة للصفحة (داخل `resources/js/Components/Purchases/`):**
  * `CreatePurchaseSupplierCard.vue`, `CreatePurchaseItemsCard.vue`, `CreatePurchaseSummaryCard.vue`.

---

### 3. التجاوب وتجربة اللمس (Responsive & Touch Ergonomics)
* **📱 هواتف (360px - 430px):** بطاقات لمسية متكاملة وتراص مرن وأزرار وحقول بارتفاع $\ge 44	ext{px}$.
* **💻 تابلت وديسكتوب (768px - 1280px+):** شبكة حقول متناسقة وجدول بيانات منظم عالي الكثافة.
* **🌓 الوضع الداكن والفاتح:** تباين كامل للبطاقات وألوان الحقول.

---

### 4. الترجمة والتعريب (100% Zero Hardcoded Localization)
* ترجمة عربية وإنجليزية كاملة في `lang/ar/purchases.php` و `lang/en/purchases.php` و `defaultTranslations.js`.
* نسبة التطابق: ✅ 100%.

---

### 5. الاختبار والتحقق الآلي (Automated Tests Suite)
* ✅ **اختبار الفرونت إند الشامل:** تشغيل `e2e/flows/create-purchase-full-page-audit.spec.js` -> نجاح 7/7 اختبارات عبر كافة مقاسات الشاشات الـ 5 بدون أي خطأ Console.
* ✅ **فحص البناء:** تشغيل `npm run build` -> تم البناء بنجاح في 4.16 ثانية.


---

## 📌 صفحة 14: دليل وإدارة الموردين والتجار (`SuppliersView.vue`) — بتاريخ 2026-08-24
* **المسار:** `/suppliers`
* **الحالة العامة:** ✅ مكتملة 100% (تفكيك 742 سطر إلى 5 مكونات فرعية + نمط المنسق النحيف Thin Orchestrator < 70 سطر + كبسولة المنطق useSuppliers + نوافذ مودال موحدة لإضافة المورد وسداد الدفعات + تجاوب لمسي كامل لكافة المقاسات الـ 5 + تعريب كامل بدون نصوص ثابتة + اختبارات E2E ناجحة 7/7).

---

### 1. التوثيق الكامل (Full Documentation)

#### نظرة عامة:
* **اسم الصفحة:** دليل وإدارة الموردين والتجار (Suppliers Management & Payables Ledger)
* **المسار (Route):** `/suppliers`
* **الملف الرئيسي:** `resources/js/views/Suppliers/SuppliersView.vue` (Thin Orchestrator: ~70 سطر)
* **الغرض منها:** إدارة جهات اتصال الموردين، متابعة الديون والمستحقات، سداد دفعات التوريد، والوصول لكشوف الحساب التفصيلية.

#### تقسيم الأجزاء (Sections & Components Hierarchy):
1. **رأس الصفحة والإجراءات العامة (`PageHeader.vue`):**
   * عنوان الصفحة وزر إضافة مورد جديد `BaseButton`.
2. **شبكة بطاقات المؤشرات المالية (`SuppliersMetricsGrid.vue`):**
   * إجمالي المستحقات للموردين، عدد الموردين الدائنين، وإجمالي الموردين المسجلين مع `StatCardSkeleton.vue`.
3. **شريط الفلاتر والبحث (`SuppliersFilterBar.vue`):**
   * حقل البحث النصي `BaseSearchInput` وأقراص تصفية المديونية (الكل، الدائنون، المسددون).
4. **جدول وبطاقات الموردين (`SuppliersTable.vue`):**
   * جدول سطح المكتب وبطاقات الهواتف المتراصة اللمسية، شارات الحالة، وأزرار سداد دفعة، كشف الحساب، التعديل، والحذف مع `TableSkeleton.vue` و `EmptyState.vue`.
5. **نافذة إضافة وتعديل المورد (`SupplierFormModal.vue`):**
   * نافذة `AppModal` مخصصة لبيانات المورد والرصيد الافتتاحي.
6. **نافذة سداد وصرف دفعة للمورد (`SupplierPaymentModal.vue`):**
   * نافذة `AppModal` مخصصة لسداد الدفعات النقدية والبنكية.

#### الاعتماديات والمصادر:
* **API Endpoints:** `GET /api/v1/suppliers`, `POST /api/v1/suppliers`, `PUT /api/v1/suppliers/{id}`, `DELETE /api/v1/suppliers/{id}`, `POST /api/v1/suppliers/{id}/payments`.
* **Composables:** `useSuppliers.js`, `useFormatters.js`, `useTrans.js`.
* **المكونات المشتركة:** `PageHeader.vue`, `BaseButton.vue`, `BaseSearchInput.vue`, `StatCardSkeleton.vue`, `TableSkeleton.vue`, `EmptyState.vue`, `AppModal.vue`.

---

### 2. المكونات المشتركة (Shared Components)
* **المكونات المشتركة المستخدمة:**
  * `PageHeader.vue`, `BaseButton.vue`, `BaseSearchInput.vue`, `StatCardSkeleton.vue`, `TableSkeleton.vue`, `EmptyState.vue`, `AppModal.vue`.
* **المكونات التابعة للصفحة (داخل `resources/js/Components/Suppliers/`):**
  * `SuppliersMetricsGrid.vue`, `SuppliersFilterBar.vue`, `SuppliersTable.vue`, `SupplierFormModal.vue`, `SupplierPaymentModal.vue`.

---

### 3. التجاوب وتجربة اللمس (Responsive & Touch Ergonomics)
* **📱 هواتف (360px - 430px):** بطاقات لمسية متكاملة للموردين وأزرار سداد وكشف حساب بارتفاع $\ge 44	ext{px}$.
* **💻 تابلت وديسكتوب (768px - 1280px+):** جدول بيانات منظم عالي الكثافة مع تمييز لوني للحسابات الدائنة.
* **🌓 الوضع الداكن والفاتح:** تباين كامل للبطاقات والصفوف وحقول الإدخال.

---

### 4. الترجمة والتعريب (100% Zero Hardcoded Localization)
* ترجمة عربية وإنجليزية كاملة في `lang/ar/contacts.php` و `lang/en/contacts.php` و `defaultTranslations.js`.
* نسبة التطابق: ✅ 100%.

---

### 5. الاختبار والتحقق الآلي (Automated Tests Suite)
* ✅ **اختبار الفرونت إند الشامل:** تشغيل `e2e/flows/suppliers-full-page-audit.spec.js` -> نجاح 7/7 اختبارات عبر كافة مقاسات الشاشات الـ 5 بدون أي خطأ Console.
* ✅ **فحص البناء:** تشغيل `npm run build` -> تم البناء بنجاح في 4.69 ثانية.


---

## 📌 صفحة 15: كشف حساب وأستاذ المورد (`SupplierStatementView.vue`) — بتاريخ 2026-08-24
* **المسار:** `/suppliers/:id/statement`
* **الحالة العامة:** ✅ مكتملة 100% (تفكيك 282 سطر إلى 4 مكونات فرعية + نمط المنسق النحيف Thin Orchestrator < 50 سطر + كبسولة المنطق useSupplierStatement + تجاوب لمسي كامل لكافة المقاسات الـ 5 + دعم الطباعة A4 + تعريب كامل بدون نصوص ثابتة + اختبارات E2E ناجحة 7/7).

---

### 1. التوثيق الكامل (Full Documentation)

#### نظرة عامة:
* **اسم الصفحة:** كشف حساب وأستاذ المورد (Supplier Ledger & Account Statement)
* **المسار (Route):** `/suppliers/:id/statement`
* **الملف الرئيسي:** `resources/js/views/Suppliers/SupplierStatementView.vue` (Thin Orchestrator: ~50 سطر)
* **الغرض منها:** استعراض الحركات المالية للمورد من فواتير توريد وسندات صرف ومرتجعات، ومتابعة الرصيد التراكمي وتصفية الفترات الزمنية والطباعة.

#### تقسيم الأجزاء (Sections & Components Hierarchy):
1. **رأس الصفحة وزر الطباعة (`SupplierStatementHeader.vue`):**
   * عنوان الصفحة مع اسم المورد وزر الرجوع وزر الطباعة `BaseButton`.
2. **بطاقات المؤشرات المالية للفترة (`SupplierStatementSummaryCards.vue`):**
   * إجمالي المشتريات، إجمالي المدفوع، والرصيد الختامي مع `StatCardSkeleton.vue`.
3. **شريط الفلاتر الزمنية والأزرار السريعة (`SupplierStatementFilterBar.vue`):**
   * حقول التاريخ من/إلى وأزرار الفترات السريعة (اليوم، هذا الشهر، هذا العام، الكل).
4. **جدول الأستاذ المالي وبطاقات الحركات (`SupplierStatementTable.vue`):**
   * جدول سطح المكتب وبطاقات الهواتف المتراصة مع تتبع الرصيد التراكمي و `TableSkeleton.vue` و `EmptyState.vue`.

#### الاعتماديات والمصادر:
* **API Endpoints:** `GET /api/v1/suppliers/{id}/statement`.
* **Composables:** `useSupplierStatement.js`, `useFormatters.js`, `useTrans.js`.
* **المكونات المشتركة:** `BaseButton.vue`, `StatCardSkeleton.vue`, `TableSkeleton.vue`, `EmptyState.vue`.

---

### 2. المكونات المشتركة (Shared Components)
* **المكونات المشتركة المستخدمة:**
  * `BaseButton.vue`, `StatCardSkeleton.vue`, `TableSkeleton.vue`, `EmptyState.vue`.
* **المكونات التابعة للصفحة (داخل `resources/js/Components/Suppliers/`):**
  * `SupplierStatementHeader.vue`, `SupplierStatementSummaryCards.vue`, `SupplierStatementFilterBar.vue`, `SupplierStatementTable.vue`.

---

### 3. التجاوب وتجربة اللمس (Responsive & Touch Ergonomics)
* **📱 هواتف (360px - 430px):** بطاقات لمسية متكاملة للحركات وأزرار فلترة سريعة بارتفاع $\ge 44	ext{px}$.
* **💻 تابلت وديسكتوب (768px - 1280px+):** جدول أستاذ مالي محاسبي منظم عالي الكثافة مع تمييز دائن/مدين.
* **🌓 الوضع الداكن والفاتح:** تباين كامل للبطاقات والصفوف وحقول الإدخال.

---

### 4. الترجمة والتعريب (100% Zero Hardcoded Localization)
* ترجمة عربية وإنجليزية كاملة في `lang/ar/contacts.php` و `lang/en/contacts.php` و `defaultTranslations.js`.
* نسبة التطابق: ✅ 100%.

---

### 5. الاختبار والتحقق الآلي (Automated Tests Suite)
* ✅ **اختبار الفرونت إند الشامل:** تشغيل `e2e/flows/supplier-statement-full-page-audit.spec.js` -> نجاح 7/7 اختبارات عبر كافة مقاسات الشاشات الـ 5 بدون أي خطأ Console.
* ✅ **فحص البناء:** تشغيل `npm run build` -> تم البناء بنجاح في 4.08 ثانية.


---

## 📌 صفحة 16: دليل وإدارة العملاء والزبائن (`CustomersView.vue`) — بتاريخ 2026-08-24
* **المسار:** `/customers`
* **الحالة العامة:** ✅ مكتملة 100% (تفكيك 751 سطر إلى 5 مكونات فرعية + نمط المنسق النحيف Thin Orchestrator < 70 سطر + كبسولة المنطق useCustomers + نوافذ مودال موحدة لإضافة العميل وتحصيل الدفعات + تجاوب لمسي كامل لكافة المقاسات الـ 5 + تعريب كامل بدون نصوص ثابتة + اختبارات E2E ناجحة 7/7).

---

### 1. التوثيق الكامل (Full Documentation)

#### نظرة عامة:
* **اسم الصفحة:** دليل وإدارة العملاء والزبائن (Customers Management & Receivables Ledger)
* **المسار (Route):** `/customers`
* **الملف الرئيسي:** `resources/js/views/Customers/CustomersView.vue` (Thin Orchestrator: ~70 سطر)
* **الغرض منها:** إدارة جهات اتصال العملاء، متابعة مديونيات الآجل والتحصيل، تسجيل سندات القبض، والوصول لكشوف الحساب التفصيلية.

#### تقسيم الأجزاء (Sections & Components Hierarchy):
1. **رأس الصفحة والإجراءات العامة (`PageHeader.vue`):**
   * عنوان الصفحة وزر إضافة عميل جديد `BaseButton`.
2. **شبكة بطاقات المؤشرات المالية (`CustomersMetricsGrid.vue`):**
   * إجمالي مديونيات العملاء، عدد العملاء المدينين، وإجمالي العملاء المسجلين مع `StatCardSkeleton.vue`.
3. **شريط الفلاتر والبحث (`CustomersFilterBar.vue`):**
   * حقل البحث النصي `BaseSearchInput` وأقراص تصفية المديونية (الكل، المدينون، المسددون، الدائنون).
4. **جدول وبطاقات العملاء (`CustomersTable.vue`):**
   * جدول سطح المكتب وبطاقات الهواتف المتراصة اللمسية، شارات الحالة، وأزرار تحصيل دفعة، كشف الحساب، التعديل، والحذف مع `TableSkeleton.vue` و `EmptyState.vue`.
5. **نافذة إضافة وتعديل العميل (`CustomerFormModal.vue`):**
   * نافذة `AppModal` مخصصة لبيانات العميل والرصيد الافتتاحي.
6. **نافذة تحصيل وسند قبض من العميل (`CustomerPaymentModal.vue`):**
   * نافذة `AppModal` مخصصة لتحصيل الدفعات النقدية والإلكترونية.

#### الاعتماديات والمصادر:
* **API Endpoints:** `GET /api/v1/customers`, `POST /api/v1/customers`, `PUT /api/v1/customers/{id}`, `DELETE /api/v1/customers/{id}`, `POST /api/v1/customers/{id}/payments`.
* **Composables:** `useCustomers.js`, `useFormatters.js`, `useTrans.js`.
* **المكونات المشتركة:** `PageHeader.vue`, `BaseButton.vue`, `BaseSearchInput.vue`, `StatCardSkeleton.vue`, `TableSkeleton.vue`, `EmptyState.vue`, `AppModal.vue`.

---

### 2. المكونات المشتركة (Shared Components)
* **المكونات المشتركة المستخدمة:**
  * `PageHeader.vue`, `BaseButton.vue`, `BaseSearchInput.vue`, `StatCardSkeleton.vue`, `TableSkeleton.vue`, `EmptyState.vue`, `AppModal.vue`.
* **المكونات التابعة للصفحة (داخل `resources/js/Components/Customers/`):**
  * `CustomersMetricsGrid.vue`, `CustomersFilterBar.vue`, `CustomersTable.vue`, `CustomerFormModal.vue`, `CustomerPaymentModal.vue`.

---

### 3. التجاوب وتجربة اللمس (Responsive & Touch Ergonomics)
* **📱 هواتف (360px - 430px):** بطاقات لمسية متكاملة للعملاء وأزرار تحصيل وكشف حساب بارتفاع $\ge 44	ext{px}$.
* **💻 تابلت وديسكتوب (768px - 1280px+):** جدول بيانات منظم عالي الكثافة مع تمييز لوني للحسابات المدينة والدائنة.
* **🌓 الوضع الداكن والفاتح:** تباين كامل للبطاقات والصفوف وحقول الإدخال.

---

### 4. الترجمة والتعريب (100% Zero Hardcoded Localization)
* ترجمة عربية وإنجليزية كاملة في `lang/ar/contacts.php` و `lang/en/contacts.php` و `defaultTranslations.js`.
* نسبة التطابق: ✅ 100%.

---

### 5. الاختبار والتحقق الآلي (Automated Tests Suite)
* ✅ **اختبار الفرونت إند الشامل:** تشغيل `e2e/flows/customers-full-page-audit.spec.js` -> نجاح 7/7 اختبارات عبر كافة مقاسات الشاشات الـ 5 بدون أي خطأ Console.
* ✅ **فحص البناء:** تشغيل `npm run build` -> تم البناء بنجاح في 4.15 ثانية.


---

## 📌 صفحة 17: كشف حساب وأستاذ العميل (`CustomerStatementView.vue`) — بتاريخ 2026-08-24
* **المسار:** `/customers/:id/statement`
* **الحالة العامة:** ✅ مكتملة 100% (تفكيك 282 سطر إلى 4 مكونات فرعية + نمط المنسق النحيف Thin Orchestrator < 50 سطر + كبسولة المنطق useCustomerStatement + تجاوب لمسي كامل لكافة المقاسات الـ 5 + دعم الطباعة A4 + تعريب كامل بدون نصوص ثابتة + اختبارات E2E ناجحة 7/7).

---

### 1. التوثيق الكامل (Full Documentation)

#### نظرة عامة:
* **اسم الصفحة:** كشف حساب وأستاذ العميل (Customer Ledger & Account Statement)
* **المسار (Route):** `/customers/:id/statement`
* **الملف الرئيسي:** `resources/js/views/Customers/CustomerStatementView.vue` (Thin Orchestrator: ~50 سطر)
* **الغرض منها:** استعراض الحركات المالية للعميل من فواتير مبيعات آجلة وسندات تحصيل ومرتجعات، ومتابعة الرصيد التراكمي وتصفية الفترات الزمنية والطباعة.

#### تقسيم الأجزاء (Sections & Components Hierarchy):
1. **رأس الصفحة وزر الطباعة (`CustomerStatementHeader.vue`):**
   * عنوان الصفحة مع اسم العميل وزر الرجوع وزر الطباعة `BaseButton`.
2. **بطاقات المؤشرات المالية للفترة (`CustomerStatementSummaryCards.vue`):**
   * إجمالي المسحوبات، إجمالي المقبوض، والرصيد الختامي مع `StatCardSkeleton.vue`.
3. **شريط الفلاتر الزمنية والأزرار السريعة (`CustomerStatementFilterBar.vue`):**
   * حقول التاريخ من/إلى وأزرار الفترات السريعة (اليوم، هذا الشهر، هذا العام، الكل).
4. **جدول الأستاذ المالي وبطاقات الحركات (`CustomerStatementTable.vue`):**
   * جدول سطح المكتب وبطاقات الهواتف المتراصة مع تتبع الرصيد التراكمي و `TableSkeleton.vue` و `EmptyState.vue`.

#### الاعتماديات والمصادر:
* **API Endpoints:** `GET /api/v1/customers/{id}/statement`.
* **Composables:** `useCustomerStatement.js`, `useFormatters.js`, `useTrans.js`.
* **المكونات المشتركة:** `BaseButton.vue`, `StatCardSkeleton.vue`, `TableSkeleton.vue`, `EmptyState.vue`.

---

### 2. المكونات المشتركة (Shared Components)
* **المكونات المشتركة المستخدمة:**
  * `BaseButton.vue`, `StatCardSkeleton.vue`, `TableSkeleton.vue`, `EmptyState.vue`.
* **المكونات التابعة للصفحة (داخل `resources/js/Components/Customers/`):**
  * `CustomerStatementHeader.vue`, `CustomerStatementSummaryCards.vue`, `CustomerStatementFilterBar.vue`, `CustomerStatementTable.vue`.

---

### 3. التجاوب وتجربة اللمس (Responsive & Touch Ergonomics)
* **📱 هواتف (360px - 430px):** بطاقات لمسية متكاملة للحركات وأزرار فلترة سريعة بارتفاع $\ge 44	ext{px}$.
* **💻 تابلت وديسكتوب (768px - 1280px+):** جدول أستاذ مالي محاسبي منظم عالي الكثافة مع تمييز مدين/دائن.
* **🌓 الوضع الداكن والفاتح:** تباين كامل للبطاقات والصفوف وحقول الإدخال.

---

### 4. الترجمة والتعريب (100% Zero Hardcoded Localization)
* ترجمة عربية وإنجليزية كاملة في `lang/ar/contacts.php` و `lang/en/contacts.php` و `defaultTranslations.js`.
* نسبة التطابق: ✅ 100%.

---

### 5. الاختبار والتحقق الآلي (Automated Tests Suite)
* ✅ **اختبار الفرونت إند الشامل:** تشغيل `e2e/flows/customer-statement-full-page-audit.spec.js` -> نجاح 7/7 اختبارات عبر كافة مقاسات الشاشات الـ 5 بدون أي خطأ Console.
* ✅ **فحص البناء:** تشغيل `npm run build` -> تم البناء بنجاح في 4.50 ثانية.


---

## 📌 صفحة 18: إدارة المصروفات العامة والتكاليف التشغيلية (`ExpensesView.vue`) — بتاريخ 2026-08-24
* **المسار:** `/expenses`
* **الحالة العامة:** ✅ مكتملة 100% (تفكيك 628 سطر إلى 4 مكونات فرعية + نمط المنسق النحيف Thin Orchestrator < 75 سطر + كبسولة المنطق useExpenses + نوافذ مودال موحدة لإضافة وتعديل المصروف + مراكز التكلفة والتصنيفات السريعة + تجاوب لمسي كامل لكافة المقاسات الـ 5 + تعريب كامل بدون نصوص ثابتة + اختبارات E2E ناجحة 7/7).

---

### 1. التوثيق الكامل (Full Documentation)

#### نظرة عامة:
* **اسم الصفحة:** إدارة المصروفات العامة والتكاليف التشغيلية (Expenses & Operational Cost Centers)
* **المسار (Route):** `/expenses`
* **الملف الرئيسي:** `resources/js/views/Expenses/ExpensesView.vue` (Thin Orchestrator: ~75 سطر)
* **الغرض منها:** توثيق وقيد المصروفات اليومية والتشغيلية وربطها بمراكز التكلفة وتتبع المسحوبات النقدية من الخزينة.

#### تقسيم الأجزاء (Sections & Components Hierarchy):
1. **رأس الصفحة والإجراءات العامة (`PageHeader.vue`):**
   * عنوان الصفحة وزر إضافة مصروف جديد `BaseButton`.
2. **شبكة بطاقات المؤشرات المالية (`ExpensesMetricsGrid.vue`):**
   * إجمالي مصروفات الشهر، مصروفات الدرج النقدية، وإجمالي نتائج الفلترة مع `StatCardSkeleton.vue`.
3. **شريط الفلاتر والبحث ومراكز التكلفة (`ExpensesFilterBar.vue`):**
   * حقل البحث النصي `BaseSearchInput`، قائمة مركز التكلفة `BaseSelect`، اختيار التواريخ، وأقراص التصنيفات السريعة.
4. **جدول وبطاقات المصروفات (`ExpensesTable.vue`):**
   * جدول سطح المكتب وبطاقات الهواتف المتراصة، شارات قنوات الدفع، وأزرار التعديل والحذف مع `TableSkeleton.vue` و `EmptyState.vue`.
5. **نافذة إضافة وتعديل المصروف (`ExpenseFormModal.vue`):**
   * نافذة `AppModal` مخصصة لبيانات المصروف ومركز التكلفة والمبلغ وتاريخ وطريقة الصرف.

#### الاعتماديات والمصادر:
* **API Endpoints:** `GET /api/v1/expenses`, `POST /api/v1/expenses`, `PUT /api/v1/expenses/{id}`, `DELETE /api/v1/expenses/{id}`.
* **Composables:** `useExpenses.js`, `useFormatters.js`, `useTrans.js`.
* **المكونات المشتركة:** `PageHeader.vue`, `BaseButton.vue`, `BaseSearchInput.vue`, `BaseSelect.vue`, `BaseInput.vue`, `StatCardSkeleton.vue`, `TableSkeleton.vue`, `EmptyState.vue`, `AppModal.vue`.

---

### 2. المكونات المشتركة (Shared Components)
* **المكونات المشتركة المستخدمة:**
  * `PageHeader.vue`, `BaseButton.vue`, `BaseSearchInput.vue`, `BaseSelect.vue`, `BaseInput.vue`, `StatCardSkeleton.vue`, `TableSkeleton.vue`, `EmptyState.vue`, `AppModal.vue`.
* **المكونات التابعة للصفحة (داخل `resources/js/Components/Expenses/`):**
  * `ExpensesMetricsGrid.vue`, `ExpensesFilterBar.vue`, `ExpensesTable.vue`, `ExpenseFormModal.vue`.

---

### 3. التجاوب وتجربة اللمس (Responsive & Touch Ergonomics)
* **📱 هواتف (360px - 430px):** بطاقات لمسية متكاملة للمصروفات وقنوات الصرف وأزرار تعديل وحذف بارتفاع $\ge 44	ext{px}$.
* **💻 تابلت وديسكتوب (768px - 1280px+):** جدول بيانات مالي منظم عالي الكثافة مع تمييز لوني للمبالغ وطرق الدفع.
* **🌓 الوضع الداكن والفاتح:** تباين كامل للبطاقات والصفوف وحقول الإدخال.

---

### 4. الترجمة والتعريب (100% Zero Hardcoded Localization)
* ترجمة عربية وإنجليزية كاملة في `lang/ar/expenses.php` و `lang/en/expenses.php` و `defaultTranslations.js`.
* نسبة التطابق: ✅ 100%.

---

### 5. الاختبار والتحقق الآلي (Automated Tests Suite)
* ✅ **اختبار الفرونت إند الشامل:** تشغيل `e2e/flows/expenses-full-page-audit.spec.js` -> نجاح 7/7 اختبارات عبر كافة مقاسات الشاشات الـ 5 بدون أي خطأ Console.
* ✅ **فحص البناء:** تشغيل `npm run build` -> تم البناء بنجاح في 4.58 ثانية.


---

## 📌 صفحة 19: دفتر اليومية وحركات الخزينة والورديات (`DailyJournalView.vue`) — بتاريخ 2026-08-24
* **المسار:** `/daily-journal`
* **الحالة العامة:** ✅ مكتملة 100% (تفكيك 724 سطر إلى 6 مكونات فرعية + نمط المنسق النحيف Thin Orchestrator < 75 سطر + كبسولة المنطق useDailyJournal + نوافذ مودال موحدة لفتح وإغلاق الوردية Z-Report وقيد المصروف السريع + تجاوب لمسي كامل لكافة المقاسات الـ 5 + تعريب كامل بدون نصوص ثابتة + اختبارات E2E ناجحة 7/7).

---

### 1. التوثيق الكامل (Full Documentation)

#### نظرة عامة:
* **اسم الصفحة:** دفتر اليومية وحركات الخزينة والورديات (Daily Journal & Cash Shifts Ledger)
* **المسار (Route):** `/daily-journal`
* **الملف الرئيسي:** `resources/js/views/DailyJournal/DailyJournalView.vue` (Thin Orchestrator: ~75 سطر)
* **الغرض منها:** مطابقة التدفقات النقدية لليومية ومراقبة وتتبع حركة الدرج وفتح وإغلاق ورديات الكاشير واعتماد تقارير Z-Report.

#### تقسيم الأجزاء (Sections & Components Hierarchy):
1. **رأس الصفحة والإجراءات العامة (`PageHeader.vue`):**
   * عنوان الصفحة، منتقي التاريخ، زر قيد مصروف سريع، وزر فتح/إغلاق الوردية `BaseButton`.
2. **شريط حالة الوردية المفتوحة (`DailyJournalShiftBanner.vue`):**
   * بيانات الوردية النشطة، الكاشير، العهدة الافتتاحية، وزر طباعة تقرير Z، أو تنبيه فتح وردية جديدة.
3. **شبكة بطاقات المؤشرات المالية للسيولة (`DailyJournalMetricsGrid.vue`):**
   * إجمالي المقبوضات (الوارد)، إجمالي المدفوعات (المنصرف)، صافي التدفق اليومي، والرصيد المتوقع في الدرج مع `StatCardSkeleton.vue`.
4. **تبويبات وجداول فواتير ومصروفات اليومية (`DailyJournalTabs.vue`):**
   * تبويبات المبيعات والمصروفات، جداول سطح المكتب وبطاقات الهواتف المتراصة اللمسية مع `TableSkeleton.vue` و `EmptyState.vue`.
5. **نافذة فتح وردية جديدة (`OpenShiftModal.vue`):**
   * نافذة `AppModal` مخصصة لإدخال الرصيد الافتتاحي للعهدة وبدء الوردية.
6. **نافذة إغلاق الوردية Z-Report (`CloseShiftModal.vue`):**
   * نافذة `AppModal` مخصصة لمقارنة الرصيد المتوقع بالرصيد الفعلي وحساب العجز أو الزيادة فورياً.
7. **نافذة قيد مصروف سريع (`QuickExpenseModal.vue`):**
   * نافذة `AppModal` مخصصة لقيد المصروف النثري المباشر من اليومية.

#### الاعتماديات والمصادر:
* **API Endpoints:** `GET /api/v1/daily-journal`, `POST /api/v1/shifts/open`, `POST /api/v1/shifts/close`, `POST /api/v1/expenses`, `GET /api/v1/shifts/{id}/z-report`.
* **Composables:** `useDailyJournal.js`, `useFormatters.js`, `useTrans.js`.
* **المكونات المشتركة:** `PageHeader.vue`, `BaseButton.vue`, `BaseNumberInput.vue`, `StatCardSkeleton.vue`, `TableSkeleton.vue`, `EmptyState.vue`, `AppModal.vue`.

---

### 2. المكونات المشتركة (Shared Components)
* **المكونات المشتركة المستخدمة:**
  * `PageHeader.vue`, `BaseButton.vue`, `BaseNumberInput.vue`, `StatCardSkeleton.vue`, `TableSkeleton.vue`, `EmptyState.vue`, `AppModal.vue`.
* **المكونات التابعة للصفحة (داخل `resources/js/Components/DailyJournal/`):**
  * `DailyJournalShiftBanner.vue`, `DailyJournalMetricsGrid.vue`, `DailyJournalTabs.vue`, `OpenShiftModal.vue`, `CloseShiftModal.vue`, `QuickExpenseModal.vue`.

---

### 3. التجاوب وتجربة اللمس (Responsive & Touch Ergonomics)
* **📱 هواتف (360px - 430px):** بطاقات لمسية متكاملة للورديات والسيولة وأزرار تحكم بالوردية بارتفاع $\ge 44	ext{px}$.
* **💻 تابلت وديسكتوب (768px - 1280px+):** جداول بيانات مالية ومحاسبية عالية الكثافة مع تمييز لوني دقيق.
* **🌓 الوضع الداكن والفاتح:** تباين كامل للبطاقات والصفوف وحقول الإدخال.

---

### 4. الترجمة والتعريب (100% Zero Hardcoded Localization)
* ترجمة عربية وإنجليزية كاملة في `lang/ar/treasury.php` و `lang/en/treasury.php` و `defaultTranslations.js`.
* نسبة التطابق: ✅ 100%.

---

### 5. الاختبار والتحقق الآلي (Automated Tests Suite)
* ✅ **اختبار الفرونت إند الشامل:** تشغيل `e2e/flows/daily-journal-full-page-audit.spec.js` -> نجاح 7/7 اختبارات عبر كافة مقاسات الشاشات الـ 5 بدون أي خطأ Console.
* ✅ **فحص البناء:** تشغيل `npm run build` -> تم البناء بنجاح في 4.58 ثانية.


---

## 📌 صفحة 20: التقارير الشاملة والإحصائيات والتحليلات (`ReportsView.vue`) — بتاريخ 2026-08-24
* **المسار:** `/reports`
* **الحالة العامة:** ✅ مكتملة 100% (تفكيك 536 سطر إلى 9 مكونات فرعية متخصصة + نمط المنسق النحيف Thin Orchestrator < 75 سطر + كبسولة المنطق useReports + شريط فلاتر زمني ومخزني شامل + 7 تبويبات تحليلية لقائمة الدخل وربحية الأصناف ومقارنة الفروع ومسحوبات العملاء والمصروفات وتقييم المخزون والسيولة + تجاوب لمسي كامل لكافة المقاسات الـ 5 + تعريب كامل بدون نصوص ثابتة + اختبارات E2E ناجحة 7/7).

---

### 1. التوثيق الكامل (Full Documentation)

#### نظرة عامة:
* **اسم الصفحة:** التقارير الشاملة والإحصائيات والتحليلات (Comprehensive Reports & Analytics)
* **المسار (Route):** `/reports`
* **الملف الرئيسي:** `resources/js/views/Reports/ReportsView.vue` (Thin Orchestrator: ~75 سطر)
* **الغرض منها:** توفير لوحة تحكم تحليلية استراتيجية متكاملة وقائمة الدخل والأرباح والمقارنات بين الفروع وتقييم المخزون والسيولة النقدية.

#### تقسيم الأجزاء (Sections & Components Hierarchy):
1. **رأس الصفحة والإجراءات العامة (`PageHeader.vue`):**
   * عنوان الصفحة وزر طباعة التقرير الشامل A4.
2. **شريط الفلاتر المتقدم (`ReportsFilterBar.vue`):**
   * أزرار الفترات الزمنية السريعة، اختيار الفرع، منتقي التواريخ من/إلى، وفلتر حالة المخزون.
3. **شريط التبويبات السبعة (`ReportsNavigationTabs.vue`):**
   * أزرار التبديل بين محاور التقارير مع تمييز لوني دقيق.
4. **تبويب المبيعات وقائمة الدخل (`ReportsSalesTab.vue`):**
   * بطاقات إجمالي المبيعات، تكلفة البضاعة المباعة (COGS)، مجمل الربح وهامش الربحية %، المصروفات، وصافي الربح الحقيقي مع `StatCardSkeleton.vue`.
5. **تبويب ربحية الأصناف (`ReportsItemsTab.vue`):**
   * جداول مبيعات وتكلفة وربحية كل منتج بالتفصيل مع شاشات الهواتف اللمسية المتراصة `TableSkeleton.vue` و `EmptyState.vue`.
6. **تبويب مقارنة الفروع (`ReportsStoresTab.vue`):**
   * جدول مقارنة الفروع والمخازن وحساب الحصة السوقية وهامش الربح.
7. **تبويب مسحوبات العملاء (`ReportsCustomersTab.vue`):**
   * جدول كبار العملاء والمسحوبات والمديونيات والرصيد التراكمي.
8. **تبويب المصروفات التشغيلية (`ReportsExpensesTab.vue`):**
   * شبكة بطاقات تصنيف المصروفات ومراكز التكلفة.
9. **تبويب تقييم المخزون (`ReportsInventoryTab.vue`):**
   * بطاقات تقييم المخزون بسعر التكلفة وسعر البيع وجدول المخزون بالتفصيل.
10. **تبويب حركة الخزينة والسيولة (`ReportsTreasuryTab.vue`):**
    * بطاقات المقبوضات والمدفوعات وصافي التدفق النقدي.

#### الاعتماديات والمصادر:
* **API Endpoints:** `GET /api/v1/reports/comprehensive`, `GET /api/v1/stores`.
* **Composables:** `useReports.js`, `useFormatters.js`, `useTrans.js`.
* **المكونات المشتركة:** `PageHeader.vue`, `StatCardSkeleton.vue`, `TableSkeleton.vue`, `EmptyState.vue`.

---

### 2. المكونات المشتركة (Shared Components)
* **المكونات المشتركة المستخدمة:**
  * `PageHeader.vue`, `StatCardSkeleton.vue`, `TableSkeleton.vue`, `EmptyState.vue`.
* **المكونات التابعة للصفحة (داخل `resources/js/Components/Reports/`):**
  * `ReportsFilterBar.vue`, `ReportsNavigationTabs.vue`, `ReportsSalesTab.vue`, `ReportsItemsTab.vue`, `ReportsStoresTab.vue`, `ReportsCustomersTab.vue`, `ReportsExpensesTab.vue`, `ReportsInventoryTab.vue`, `ReportsTreasuryTab.vue`.

---

### 3. التجاوب وتجربة اللمس (Responsive & Touch Ergonomics)
* **📱 هواتف (360px - 430px):** بطاقات لمسية متكاملة للمؤشرات والجداول وشريط تمرير تبويبات أفقي مرن.
* **💻 تابلت وديسكتوب (768px - 1280px+):** جداول بيانات محاسبية وإحصائية عالية الكثافة.
* **🌓 الوضع الداكن والفاتح:** تباين كامل للبطاقات والصفوف وحقول الإدخال.

---

### 4. الترجمة والتعريب (100% Zero Hardcoded Localization)
* ترجمة عربية وإنجليزية كاملة في `lang/ar/reports.php` و `lang/en/reports.php` و `defaultTranslations.js`.
* نسبة التطابق: ✅ 100%.

---

### 5. الاختبار والتحقق الآلي (Automated Tests Suite)
* ✅ **اختبار الفرونت إند الشامل:** تشغيل `e2e/flows/reports-full-page-audit.spec.js` -> نجاح 7/7 اختبارات عبر كافة مقاسات الشاشات الـ 5 بدون أي خطأ Console.
* ✅ **فحص البناء:** تشغيل `npm run build` -> تم البناء بنجاح في 6.47 ثانية.


---

## 📌 صفحة 21: إدارة المستخدمين والموظفين (`UsersView.vue`) — بتاريخ 2026-08-24
* **المسار:** `/users`
* **الحالة العامة:** ✅ مكتملة 100% (تفكيك 471 سطر إلى 3 مكونات فرعية متخصصة + نمط المنسق النحيف Thin Orchestrator < 75 سطر + كبسولة المنطق useUsers + نافذة AppModal موحدة لإضافة وتعديل المستخدمين + شريط بحث وتصفية فورية للأدوار + زر تبديل النشاط toggle-active الفوري + تجاوب لمسي كامل لكافة المقاسات الـ 5 + تعريب كامل بدون نصوص ثابتة + اختبارات E2E ناجحة 7/7).

---

### 1. التوثيق الكامل (Full Documentation)

#### نظرة عامة:
* **اسم الصفحة:** إدارة حسابات المستخدمين والموظفين (Users & Staff Management)
* **المسار (Route):** `/users`
* **الملف الرئيسي:** `resources/js/views/Users/UsersView.vue` (Thin Orchestrator: ~75 سطر)
* **الغرض منها:** إدارة موظفي المنظومة، تعيين الأدوار والصلاحيات، وتحديد الفروع الافتراضية ومتابعة وتعديل بيانات الدخول والحالة.

#### تقسيم الأجزاء (Sections & Components Hierarchy):
1. **رأس الصفحة والإجراءات العامة (`PageHeader.vue`):**
   * عنوان الصفحة، زر مصفوفة الصلاحيات، وزر إضافة موظف جديد `BaseButton`.
2. **شريط الفلاتر والبحث (`UsersFilterBar.vue`):**
   * حقل البحث الفوري `BaseSearchInput` وقائمة فلترة الأدوار الوظيفية.
3. **جدول وبطاقات المستخدمين (`UsersTable.vue`):**
   * جدول سطح المكتب وبطاقات الهواتف اللمسية المتراصة مع شارات الأدوار، زر التفعيل السريع، وأزرار التعديل والحذف والترقيم `TableSkeleton.vue` و `EmptyState.vue`.
4. **نافذة إنشاء وتعديل المستخدم (`UserFormModal.vue`):**
   * نافذة `AppModal` مخصصة لإدخال بيانات المستخدم، الهاتف، البريد، الدور، الفرع، كلمة المرور، وتفعيل الحساب.

#### الاعتماديات والمصادر:
* **API Endpoints:** `GET /api/v1/users`, `POST /api/v1/users`, `PUT /api/v1/users/{id}`, `PATCH /api/v1/users/{id}/toggle-active`, `DELETE /api/v1/users/{id}`.
* **Composables:** `useUsers.js`, `useTrans.js`.
* **المكونات المشتركة:** `PageHeader.vue`, `BaseButton.vue`, `BaseSearchInput.vue`, `BaseInput.vue`, `TableSkeleton.vue`, `EmptyState.vue`, `AppModal.vue`.

---

### 2. المكونات المشتركة (Shared Components)
* **المكونات المشتركة المستخدمة:**
  * `PageHeader.vue`, `BaseButton.vue`, `BaseSearchInput.vue`, `BaseInput.vue`, `TableSkeleton.vue`, `EmptyState.vue`, `AppModal.vue`.
* **المكونات التابعة للصفحة (داخل `resources/js/Components/Users/`):**
  * `UsersFilterBar.vue`, `UsersTable.vue`, `UserFormModal.vue`.

---

### 3. التجاوب وتجربة اللمس (Responsive & Touch Ergonomics)
* **📱 هواتف (360px - 430px):** بطاقات لمسية متكاملة للمستخدمين مع أزرار تفعيل الحالة وتعديل وحذف مريحة للإبهام.
* **💻 تابلت وديسكتوب (768px - 1280px+):** جدول مستخدمين محاسبي متكامل مع صور رمزية وشارات ملونة للأدوار.
* **🌓 الوضع الداكن والفاتح:** تباين كامل للبطاقات والصفوف وحقول الإدخال.

---

### 4. الترجمة والتعريب (100% Zero Hardcoded Localization)
* ترجمة عربية وإنجليزية كاملة في `lang/ar/users.php` و `lang/en/users.php` و `defaultTranslations.js`.
* نسبة التطابق: ✅ 100%.

---

### 5. الاختبار والتحقق الآلي (Automated Tests Suite)
* ✅ **اختبار الفرونت إند الشامل:** تشغيل `e2e/flows/users-full-page-audit.spec.js` -> نجاح 7/7 اختبارات عبر كافة مقاسات الشاشات الـ 5 بدون أي خطأ Console.
* ✅ **فحص البناء:** تشغيل `npm run build` -> تم البناء بنجاح في 5.08 ثانية.


---

## 📌 صفحة 22: مصفوفة الأدوار والصلاحيات (`RolesView.vue`) — بتاريخ 2026-08-24
* **المسار:** `/roles`
* **الحالة العامة:** ✅ مكتملة 100% (تفكيك 213 سطر إلى 3 مكونات فرعية متخصصة + نمط المنسق النحيف Thin Orchestrator < 65 سطر + كبسولة المنطق useRoles + شبكة بطاقات الأدوار التفاعلية مع عداد الصلاحيات الحي + شبكة موديولات الصلاحيات مع تحديد/إلغاء تحديد الكل + قفل حماية الأدمن + تجاوب لمسي كامل لكافة المقاسات الـ 5 + تعريب كامل بدون نصوص ثابتة + اختبارات E2E ناجحة 7/7).

---

### 1. التوثيق الكامل (Full Documentation)

#### نظرة عامة:
* **اسم الصفحة:** مصفوفة الأدوار والصلاحيات (Roles & Permissions Matrix)
* **المسار (Route):** `/roles`
* **الملف الرئيسي:** `resources/js/views/Roles/RolesView.vue` (Thin Orchestrator: ~65 سطر)
* **الغرض منها:** تحديد الصلاحيات الدقيقة لكل دور وظيفي بالمنظومة وإدارة صلاحيات الفروع والعمليات الحساسة.

#### تقسيم الأجزاء (Sections & Components Hierarchy):
1. **رأس الصفحة والإجراءات العامة (`PageHeader.vue`):**
   * عنوان الصفحة، زر الانتقال للمستخدمين، وزر حفظ الصلاحيات `BaseButton`.
2. **شبكة اختيار الأدوار (`RolesSelectorGrid.vue`):**
   * بطاقات الأدوار مع عداد الصلاحيات النشطة وشارة الصلاحيات الكاملة للأدمن مع `StatCardSkeleton.vue`.
3. **تنبيه حماية الأدمن (`RoleAdminNotice.vue`):**
   * رسالة تنبيه عند اختيار دور الأدمن لتوضيح امتلاكه الصلاحيات الكاملة تلقائياً.
4. **شبكة موديولات الصلاحيات (`PermissionModulesGrid.vue`):**
   * بطاقات موديولات النظام مع أزرار تحديد/إلغاء تحديد الكل وصناديق الاختيار التفاعلية.

#### الاعتماديات والمصادر:
* **API Endpoints:** `GET /api/v1/roles`, `PUT /api/v1/roles/{id}/permissions`.
* **Composables:** `useRoles.js`, `useTrans.js`.
* **المكونات المشتركة:** `PageHeader.vue`, `BaseButton.vue`, `StatCardSkeleton.vue`.

---

### 2. المكونات المشتركة (Shared Components)
* **المكونات المشتركة المستخدمة:**
  * `PageHeader.vue`, `BaseButton.vue`, `StatCardSkeleton.vue`.
* **المكونات التابعة للصفحة (داخل `resources/js/Components/Roles/`):**
  * `RolesSelectorGrid.vue`, `RoleAdminNotice.vue`, `PermissionModulesGrid.vue`.

---

### 3. التجاوب وتجربة اللمس (Responsive & Touch Ergonomics)
* **📱 هواتف (360px - 430px):** بطاقات أدوار واضحة وصناديق اختيار واسعة سهلة النقر بالإبهام.
* **💻 تابلت وديسكتوب (768px - 1280px+):** شبكة بعمودين للموديولات مع أيقونات تعبيرية.
* **🌓 الوضع الداكن والفاتح:** تباين كامل للبطاقات والصفوف وحقول الإدخال.

---

### 4. الترجمة والتعريب (100% Zero Hardcoded Localization)
* ترجمة عربية وإنجليزية كاملة في `lang/ar/roles.php` و `lang/en/roles.php` و `defaultTranslations.js`.
* نسبة التطابق: ✅ 100%.

---

### 5. الاختبار والتحقق الآلي (Automated Tests Suite)
* ✅ **اختبار الفرونت إند الشامل:** تشغيل `e2e/flows/roles-full-page-audit.spec.js` -> نجاح 7/7 اختبارات عبر كافة مقاسات الشاشات الـ 5 بدون أي خطأ Console.
* ✅ **فحص البناء:** تشغيل `npm run build` -> تم البناء بنجاح في 3.91 ثانية.


---

## 📌 صفحة 23: سجل التدقيق الأمني والعمليات (`ActivityLogsView.vue`) — بتاريخ 2026-08-24
* **المسار:** `/activity-logs`
* **الحالة العامة:** ✅ مكتملة 100% (تفكيك 304 أسطر إلى 4 مكونات فرعية متخصصة + نمط المنسق النحيف Thin Orchestrator < 70 سطر + كبسولة المنطق useActivityLogs + شبكة مؤشرات النشاط اليومي + شريط فلاتر متعددة للأقسام والموظفين والفروع + جدول زمني تفاعلي مع شارات العمليات + نافذة AppModal لفحص الـ JSON Payload والتفاصيل + تجاوب لمسي كامل لكافة المقاسات الـ 5 + تعريب كامل بدون نصوص ثابتة + اختبارات E2E ناجحة 7/7).

---

### 1. التوثيق الكامل (Full Documentation)

#### نظرة عامة:
* **اسم الصفحة:** سجل التدقيق الأمني والعمليات (System Activity & Audit Logs)
* **المسار (Route):** `/activity-logs`
* **الملف الرئيسي:** `resources/js/views/ActivityLogs/ActivityLogsView.vue` (Thin Orchestrator: ~70 سطر)
* **الغرض منها:** تتبع العمليات اليومية والحركات الحساسة (إلغاءات، شطب، تعديل أسعار، دخول نظام) لضمان أعلى مستويات الرقابة والشفافية.

#### تقسيم الأجزاء (Sections & Components Hierarchy):
1. **رأس الصفحة والإجراءات العامة (`PageHeader.vue`):**
   * عنوان الصفحة، وزر تحديث السجل `BaseButton`.
2. **شبكة بطاقات المؤشرات الأربعة (`ActivityLogsMetricsGrid.vue`):**
   * بطاقات إجمالي العمليات اليوم، العمليات الحساسة، الموظفين المتفاعلين، والفروع النشطة مع `StatCardSkeleton.vue`.
3. **شريط الفلاتر المتقدم (`ActivityLogsFilterBar.vue`):**
   * حقل البحث الفوري `BaseSearchInput` وقوائم تصفية الأقسام والموظفين والفروع `BaseSelect`.
4. **الجدول الزمني للنشاطات (`ActivityLogsTimeline.vue`):**
   * قائمة العمليات والنشاطات وشارات الإجراءات وزر فحص التفاصيل والترقيم التلقائي `TableSkeleton.vue` و `EmptyState.vue`.
5. **نافذة تفاصيل العملية (`ActivityLogDetailsModal.vue`):**
   * نافذة `AppModal` مخصصة لعرض الوصف الكامل ومحتوى الـ JSON Payload مع منسق الشيفرة.

#### الاعتماديات والمصادر:
* **API Endpoints:** `GET /api/v1/activity-logs`.
* **Composables:** `useActivityLogs.js`, `useTrans.js`.
* **المكونات المشتركة:** `PageHeader.vue`, `BaseButton.vue`, `BaseSearchInput.vue`, `BaseSelect.vue`, `StatCardSkeleton.vue`, `TableSkeleton.vue`, `EmptyState.vue`, `AppModal.vue`.

---

### 2. المكونات المشتركة (Shared Components)
* **المكونات المشتركة المستخدمة:**
  * `PageHeader.vue`, `BaseButton.vue`, `BaseSearchInput.vue`, `BaseSelect.vue`, `StatCardSkeleton.vue`, `TableSkeleton.vue`, `EmptyState.vue`, `AppModal.vue`.
* **المكونات التابعة للصفحة (داخل `resources/js/Components/ActivityLogs/`):**
  * `ActivityLogsMetricsGrid.vue`, `ActivityLogsFilterBar.vue`, `ActivityLogsTimeline.vue`, `ActivityLogDetailsModal.vue`.

---

### 3. التجاوب وتجربة اللمس (Responsive & Touch Ergonomics)
* **📱 هواتف (360px - 430px):** بطاقات نشاط لمسية واضحة ومتباعدة، زر التفاصيل بارتفاع $\ge 40	ext{px}$.
* **💻 تابلت وديسكتوب (768px - 1280px+):** جدول زمني أفقي مريح مع أيقونات الموديولات وتاريخ العملية وعنوان الـ IP وشارات الحالة.
* **🌓 الوضع الداكن والفاتح:** تباين كامل للبطاقات والصفوف وحقول الإدخال.

---

### 4. الترجمة والتعريب (100% Zero Hardcoded Localization)
* ترجمة عربية وإنجليزية كاملة في `lang/ar/activity.php` و `lang/en/activity.php` و `defaultTranslations.js`.
* نسبة التطابق: ✅ 100%.

---

### 5. الاختبار والتحقق الآلي (Automated Tests Suite)
* ✅ **اختبار الفرونت إند الشامل:** تشغيل `e2e/flows/activity-logs-full-page-audit.spec.js` -> نجاح 7/7 اختبارات عبر كافة مقاسات الشاشات الـ 5 بدون أي خطأ Console.
* ✅ **فحص البناء:** تشغيل `npm run build` -> تم البناء بنجاح في 2.96 ثانية.


---

## 📌 صفحة 24: سلة المحذوفات والاسترجاع الآمن (`TrashView.vue`) — بتاريخ 2026-08-24
* **المسار:** `/trash`
* **الحالة العامة:** ✅ مكتملة 100% (تفكيك 261 سطر إلى 3 مكونات فرعية متخصصة + نمط المنسق النحيف Thin Orchestrator < 65 سطر + كبسولة المنطق useTrash + تبويبات الموديولات الـ 6 التفاعلية مع عدادات حية وشارات حمراء + شريط بحث فوري + جدول وسطح مكتب وبطاقات هواتف لمسية مع استرجاع آمن وحذف نهائي + تجاوب لمسي كامل لكافة المقاسات الـ 5 + تعريب كامل بدون نصوص ثابتة + اختبارات E2E ناجحة 7/7).

---

### 1. التوثيق الكامل (Full Documentation)

#### نظرة عامة:
* **اسم الصفحة:** سلة المحذوفات والاسترجاع الآمن (Trash & Soft Deletes Recovery)
* **المسار (Route):** `/trash`
* **الملف الرئيسي:** `resources/js/views/Trash/TrashView.vue` (Thin Orchestrator: ~65 سطر)
* **الغرض منها:** توفير طبقة أمان لمنع ضياع البيانات، استعراض العناصر المحذوفة واستعادتها أو حذفها نهائياً.

#### تقسيم الأجزاء (Sections & Components Hierarchy):
1. **رأس الصفحة والإجراءات العامة (`PageHeader.vue`):**
   * عنوان الصفحة، وزر تحديث السلة `BaseButton`.
2. **شريط تبويبات الموديولات الستة (`TrashModuleTabs.vue`):**
   * تبويبات الأصناف، العملاء، الموردين، الفروع، المصروفات، المرتجعات مع عداد حي لكل قسم وشارة نشطة.
3. **شريط البحث الفوري (`TrashFilterBar.vue`):**
   * حقل البحث الفوري `BaseSearchInput`.
4. **جدول وبطاقات العناصر المحذوفة (`TrashTable.vue`):**
   * جدول سطح المكتب وبطاقات الهواتف اللمسية المتراصة مع أزرار الاسترجاع والحذف النهائي والترقيم التلقائي `TableSkeleton.vue` و `EmptyState.vue`.

#### الاعتماديات والمصادر:
* **API Endpoints:** `GET /api/v1/trash`, `POST /api/v1/trash/{module}/{id}/restore`, `DELETE /api/v1/trash/{module}/{id}/force`.
* **Composables:** `useTrash.js`, `useTrans.js`.
* **المكونات المشتركة:** `PageHeader.vue`, `BaseButton.vue`, `BaseSearchInput.vue`, `TableSkeleton.vue`, `EmptyState.vue`.

---

### 2. المكونات المشتركة (Shared Components)
* **المكونات المشتركة المستخدمة:**
  * `PageHeader.vue`, `BaseButton.vue`, `BaseSearchInput.vue`, `TableSkeleton.vue`, `EmptyState.vue`.
* **المكونات التابعة للصفحة (داخل `resources/js/Components/Trash/`):**
  * `TrashModuleTabs.vue`, `TrashFilterBar.vue`, `TrashTable.vue`.

---

### 3. التجاوب وتجربة اللمس (Responsive & Touch Ergonomics)
* **📱 هواتف (360px - 430px):** تبويبات أفقية قابلة للتمرير وبطاقات لمسية متكاملة لكل عنصر محذوف.
* **💻 تابلت وديسكتوب (768px - 1280px+):** جدول محاسبي أنيق مع توقيت الحذف وأزرار واضحة.
* **🌓 الوضع الداكن والفاتح:** تباين كامل للبطاقات والصفوف وحقول الإدخال.

---

### 4. الترجمة والتعريب (100% Zero Hardcoded Localization)
* ترجمة عربية وإنجليزية كاملة في `lang/ar/trash.php` و `lang/en/trash.php` و `defaultTranslations.js`.
* نسبة التطابق: ✅ 100%.

---

### 5. الاختبار والتحقق الآلي (Automated Tests Suite)
* ✅ **اختبار الفرونت إند الشامل:** تشغيل `e2e/flows/trash-full-page-audit.spec.js` -> نجاح 7/7 اختبارات عبر كافة مقاسات الشاشات الـ 5 بدون أي خطأ Console.
* ✅ **فحص البناء:** تشغيل `npm run build` -> تم البناء بنجاح في 4.15 ثانية.


---

## 📌 صفحة 25: إعدادات النظام والتحكم الشامل (`SettingsView.vue`) — بتاريخ 2026-08-24
* **المسار:** `/settings`
* **الحالة العامة:** ✅ مكتملة 100% (تفكيك 823 سطر إلى 7 مكونات فرعية متخصصة + نمط المنسق النحيف Thin Orchestrator < 75 سطر + كبسولة المنطق useSettings + التنقل الذكي بنمط Mobile Hub وشاشات التفاصيل Drill-Down + تخصيص الهوية والشعار + باليتات الألوان واللون المخصص و EyeDropper + خيارات الطباعة الحرارية + ربط بوت تيليجرام وفحص الاتصال + إدارة وحدات القياس المعتمدة + تجاوب لمسي كامل لكافة المقاسات الـ 5 + تعريب كامل بدون نصوص ثابتة + اختبارات E2E ناجحة 7/7).

---

### 1. التوثيق الكامل (Full Documentation)

#### نظرة عامة:
* **اسم الصفحة:** إعدادات النظام والتحكم الشامل (System Settings & Customization)
* **المسار (Route):** `/settings`
* **الملف الرئيسي:** `resources/js/views/Settings/SettingsView.vue` (Thin Orchestrator: ~75 سطر)
* **الغرض منها:** لوحة التحكم الشاملة لتخصيص هوية المنشأة، ألوان الواجهة، خيارات الطباعة الحرارية، إشعارات تيليجرام، ووحدات قياس المخزون.

#### تقسيم الأجزاء (Sections & Components Hierarchy):
1. **رأس الصفحة والإجراءات العامة (`PageHeader.vue`):**
   * عنوان القسم وزر حفظ التعديلات وزر الرجوع لمركز الإعدادات على الهواتف.
2. **القائمة الجانبية للشاشات الكبيرة (`SettingsNavigationSidebar.vue`):**
   * قائمة الأقسام الخمسة مع أيقونات ملونة وصندوق معلومات الحماية.
3. **مركز الإعدادات للهواتف (`SettingsMobileHub.vue`):**
   * شبكة بطاقات لمسية أنيقة للأقسام مع أسهم التنقل.
4. **قسم الهوية المؤسسية (`SettingsBrandingSection.vue`):**
   * اسم المنشأة، الوصف، الهاتف الرسمي، والعنوان `BaseInput.vue`.
5. **قسم المظهر ونظام الألوان (`SettingsAppearanceSection.vue`):**
   * 8 باليتات ألوان جاهزة، حقل اللون المخصص HEX، أداة EyeDropper، وتبديل الوضع الفاتح والداكن.
6. **قسم الطباعة الحرارية (`SettingsPrintingSection.vue`):**
   * مفاتيح تبديل إظهار اسم المنشأة، الوصف، رصيد العميل، رمز الـ QR، وملاحظة تذييل الفاتورة.
7. **قسم إشعارات تيليجرام (`SettingsTelegramSection.vue`):**
   * مفتاح التفعيل الرئيسي، توكن البوت، معرف المحادثة، وزر الفحص التجريبي.
8. **قسم وحدات القياس (`SettingsUnitsSection.vue`):**
   * شرائح الوحدات المعتمدة، حقل إضافة وحدة جديدة، وأزرار الوحدات الجاهزة.

#### الاعتماديات والمصادر:
* **API Endpoints:** `GET /api/v1/settings`, `POST /api/v1/settings`, `POST /api/v1/settings/telegram/test`.
* **Composables:** `useSettings.js`, `useTrans.js`.
* **المكونات المشتركة:** `PageHeader.vue`, `BaseButton.vue`, `BaseInput.vue`.

---

### 2. المكونات المشتركة (Shared Components)
* **المكونات المشتركة المستخدمة:**
  * `PageHeader.vue`, `BaseButton.vue`, `BaseInput.vue`.
* **المكونات التابعة للصفحة (داخل `resources/js/Components/Settings/`):**
  * `SettingsNavigationSidebar.vue`, `SettingsMobileHub.vue`, `SettingsBrandingSection.vue`, `SettingsAppearanceSection.vue`, `SettingsPrintingSection.vue`, `SettingsTelegramSection.vue`, `SettingsUnitsSection.vue`.

---

### 3. التجاوب وتجربة اللمس (Responsive & Touch Ergonomics)
* **📱 هواتف (360px - 430px):** نمط Drill-Down ببطاقات هب تفاعلية وزر رجوع ومفاتيح Toggles لمسية بارتفاع $\ge 44	ext{px}$.
* **💻 تابلت وديسكتوب (768px - 1280px+):** تخطيط مقسوم أنيق (Split View) بقائمة جانبية ذات أيقونات ملونة.
* **🌓 الوضع الداكن والفاتح:** تباين كامل للبطاقات والصفوف وحقول الإدخال.

---

### 4. الترجمة والتعريب (100% Zero Hardcoded Localization)
* ترجمة عربية وإنجليزية كاملة في `lang/ar/settings.php` و `lang/en/settings.php` و `defaultTranslations.js`.
* نسبة التطابق: ✅ 100%.

---

### 5. الاختبار والتحقق الآلي (Automated Tests Suite)
* ✅ **اختبار الفرونت إند الشامل:** تشغيل `e2e/flows/settings-full-page-audit.spec.js` -> نجاح 7/7 اختبارات عبر كافة مقاسات الشاشات الـ 5 بدون أي خطأ Console.
* ✅ **فحص البناء:** تشغيل `npm run build` -> تم البناء بنجاح وبدون أي خطأ.


---

## 📌 صفحة 26: سجل المرتجعات وإشعارات الخصم والإرجاع (`ReturnsView.vue`) — بتاريخ 2026-08-24
* **المسار:** `/returns`
* **الحالة العامة:** ✅ مكتملة 100% (تفكيك 439 سطر إلى 4 مكونات فرعية متخصصة + نمط المنسق النحيف Thin Orchestrator < 70 سطر + كبسولة المنطق useReturns + شبكة المؤشرات المالية الأربعة + شريط فلاتر متعددة للنوع وتاريخ البداية والنهاية + جدول محاسبي وشاشات هواتف لمسية + نافذة AppModal لمعاينة تفاصيل الأصناف المرجعة + تجاوب لمسي كامل لكافة المقاسات الـ 5 + تعريب كامل بدون نصوص ثابتة + اختبارات E2E ناجحة 7/7).

---

### 1. التوثيق الكامل (Full Documentation)

#### نظرة عامة:
* **اسم الصفحة:** سجل المرتجعات وإشعارات الخصم والإرجاع (Returns & Credit Notes Ledger)
* **المسار (Route):** `/returns`
* **الملف الرئيسي:** `resources/js/views/Returns/ReturnsView.vue` (Thin Orchestrator: ~70 سطر)
* **الغرض منها:** تتبع وإدارة حركات المرتجعات للعملاء والموردين وضبط المخزون وحسابات الأطراف بدقة.

#### تقسيم الأجزاء (Sections & Components Hierarchy):
1. **رأس الصفحة والإجراءات العامة (`PageHeader.vue`):**
   * عنوان الصفحة، وزر تسجيل مرتجع جديد `router-link`.
2. **شبكة المؤشرات المالية الأربعة (`ReturnsMetricsGrid.vue`):**
   * إجمالي قيمة المرتجعات، عدد مرتجعات المبيعات، عدد مرتجعات المشتريات، وإجمالي المستندات مع `StatCardSkeleton.vue`.
3. **شريط البحث والفلاتر (`ReturnsFilterBar.vue`):**
   * حقل البحث الفوري `BaseSearchInput`، قائمة تصفية النوع `BaseSelect`، وحقول التاريخ.
4. **جدول وبطاقات المرتجعات (`ReturnsTable.vue`):**
   * جدول سطح المكتب وبطاقات الهواتف مع شارات النوع الملونة وزر المعاينة والحذف والترقيم `TableSkeleton.vue` و `EmptyState.vue`.
5. **نافذة تفاصيل المرتجع (`ReturnDetailsModal.vue`):**
   * نافذة `AppModal` مخصصة لعرض معلومات المسؤول والفرع وجدول الأصناف المرجعة بالكميات والأسعار.

#### الاعتماديات والمصادر:
* **API Endpoints:** `GET /api/v1/returns`, `GET /api/v1/returns/{id}`, `DELETE /api/v1/returns/{id}`.
* **Composables:** `useReturns.js`, `useTrans.js`.
* **المكونات المشتركة:** `PageHeader.vue`, `BaseSearchInput.vue`, `BaseSelect.vue`, `StatCardSkeleton.vue`, `TableSkeleton.vue`, `EmptyState.vue`, `AppModal.vue`.

---

### 2. المكونات المشتركة (Shared Components)
* **المكونات المشتركة المستخدمة:**
  * `PageHeader.vue`, `BaseSearchInput.vue`, `BaseSelect.vue`, `StatCardSkeleton.vue`, `TableSkeleton.vue`, `EmptyState.vue`, `AppModal.vue`.
* **المكونات التابعة للصفحة (داخل `resources/js/Components/Returns/`):**
  * `ReturnsMetricsGrid.vue`, `ReturnsFilterBar.vue`, `ReturnsTable.vue`, `ReturnDetailsModal.vue`.

---

### 3. التجاوب وتجربة اللمس (Responsive & Touch Ergonomics)
* **📱 هواتف (360px - 430px):** بطاقات مؤشرات ولمسية متراصة لكل مستند إرجاع.
* **💻 تابلت وديسكتوب (768px - 1280px+):** جدول محاسبي أنيق مع شارات ملونة وقيم مالية واضحة.
* **🌓 الوضع الداكن والفاتح:** تباين كامل للبطاقات والصفوف وحقول الإدخال.

---

### 4. الترجمة والتعريب (100% Zero Hardcoded Localization)
* ترجمة عربية وإنجليزية كاملة في `lang/ar/returns.php` و `lang/en/returns.php` و `defaultTranslations.js`.
* نسبة التطابق: ✅ 100%.

---

### 5. الاختبار والتحقق الآلي (Automated Tests Suite)
* ✅ **اختبار الفرونت إند الشامل:** تشغيل `e2e/flows/returns-full-page-audit.spec.js` -> نجاح 7/7 اختبارات عبر كافة مقاسات الشاشات الـ 5 بدون أي خطأ Console.
* ✅ **فحص البناء:** تشغيل `npm run build` -> تم البناء بنجاح في 3.08 ثانية.


---

## 📌 صفحة 27: تسجيل وإنشاء مستند مرتجع جديد (`CreateReturnView.vue`) — بتاريخ 2026-08-24
* **المسار:** `/returns/create`
* **الحالة العامة:** ✅ مكتملة 100% (تفكيك 404 أسطر إلى 3 مكونات فرعية متخصصة + نمط المنسق النحيف Thin Orchestrator < 65 سطر + كبسولة المنطق useCreateReturn + محول نوع المرتجع التفاعلي + اختيار الطرف والربط بالعملاء والموردين + جدول الأصناف التفاعلي مع حساب الإجمالي الفوري + كارد الملخص المالي وخيار استرداد النقدية + تجاوب لمسي كامل لكافة المقاسات الـ 5 + تعريب كامل بدون نصوص ثابتة + اختبارات E2E ناجحة 7/7).

---

### 1. التوثيق الكامل (Full Documentation)

#### نظرة عامة:
* **اسم الصفحة:** إنشاء وتسجيل مستند مرتجع جديد (Create Return Document)
* **المسار (Route):** `/returns/create`
* **الملف الرئيسي:** `resources/js/views/Returns/CreateReturnView.vue` (Thin Orchestrator: ~65 سطر)
* **الغرض منها:** توفير واجهة سريعة ودقيقة لتسجيل حركات المرتجعات وعكس الأرصدة المخزنية والمالية.

#### تقسيم الأجزاء (Sections & Components Hierarchy):
1. **رأس الصفحة والإجراءات العامة (`PageHeader.vue`):**
   * عنوان الصفحة، وزر العودة لسجل المرتجعات `router-link`.
2. **قسم نوع المرتجع والطرف (`ReturnPartySection.vue`):**
   * محول المبيعات/المشتريات، اختيار العميل أو المورد، تاريخ الإرجاع، وحقل السبب `BaseInput.vue`.
3. **جدول أصناف المرتجع (`ReturnItemsTable.vue`):**
   * قائمة اختيار وإضافة الأصناف، جدول الكميات والأسعار والإجمالي، وزر حذف السطر.
4. **الملخص المالي والاعتماد (`ReturnFinancialSummary.vue`):**
   * إجمالي قيمة المرتجع، حقل استرداد النقدية من الخزينة، وزر الحفظ والاعتماد `BaseButton.vue`.

#### الاعتماديات والمصادر:
* **API Endpoints:** `GET /api/v1/customers`, `GET /api/v1/suppliers`, `GET /api/v1/items`, `POST /api/v1/returns`.
* **Composables:** `useCreateReturn.js`, `useTrans.js`.
* **المكونات المشتركة:** `PageHeader.vue`, `BaseButton.vue`, `BaseInput.vue`.

---

### 2. المكونات المشتركة (Shared Components)
* **المكونات المشتركة المستخدمة:**
  * `PageHeader.vue`, `BaseButton.vue`, `BaseInput.vue`.
* **المكونات التابعة للصفحة (داخل `resources/js/Components/Returns/`):**
  * `ReturnPartySection.vue`, `ReturnItemsTable.vue`, `ReturnFinancialSummary.vue`.

---

### 3. التجاوب وتجربة اللمس (Responsive & Touch Ergonomics)
* **📱 هواتف (360px - 430px):** تدفق عمودي سلس، أزرار ومفاتيح لمسية بارتفاع $\ge 40	ext{px}$.
* **💻 تابلت وديسكتوب (768px - 1280px+):** تقسيم شاشتي 2/3 للبيانات و 1/3 للملخص المالي الثابت.
* **🌓 الوضع الداكن والفاتح:** تباين كامل للبطاقات والصفوف وحقول الإدخال.

---

### 4. الترجمة والتعريب (100% Zero Hardcoded Localization)
* ترجمة عربية وإنجليزية كاملة في `lang/ar/returns.php` و `lang/en/returns.php` و `defaultTranslations.js`.
* نسبة التطابق: ✅ 100%.

---

### 5. الاختبار والتحقق الآلي (Automated Tests Suite)
* ✅ **اختبار الفرونت إند الشامل:** تشغيل `e2e/flows/create-return-full-page-audit.spec.js` -> نجاح 7/7 اختبارات عبر كافة مقاسات الشاشات الـ 5 بدون أي خطأ Console.
* ✅ **فحص البناء:** تشغيل `npm run build` -> تم البناء بنجاح في 2.94 ثانية.


---

## 📌 صفحة 28: الملف الشخصي وإعدادات الحساب والأمان (`ProfileView.vue`) — بتاريخ 2026-08-24
* **المسار:** `/profile`
* **الحالة العامة:** ✅ مكتملة 100% (تفكيك 203 أسطر إلى 3 مكونات فرعية متخصصة + نمط المنسق النحيف Thin Orchestrator < 55 سطر + كبسولة المنطق useProfile + بطاقة البيانات الأساسية والاسم ورقم الهاتف + بطاقة أمان كلمة المرور والتأكيد + بطاقة تفضيل المظهر التفاعلية + تجاوب لمسي كامل لكافة المقاسات الـ 5 + تعريب كامل بدون نصوص ثابتة + اختبارات E2E ناجحة 7/7).

---

### 1. التوثيق الكامل (Full Documentation)

#### نظرة عامة:
* **اسم الصفحة:** الملف الشخصي وإعدادات الحساب والأمان (User Profile & Account Settings)
* **المسار (Route):** `/profile`
* **الملف الرئيسي:** `resources/js/views/Profile/ProfileView.vue` (Thin Orchestrator: ~55 سطر)
* **الغرض منها:** تمكين المستخدم من إدارة بياناته الشخصية، وتغيير كلمة المرور، وتحديد المظهر المفضل.

#### تقسيم الأجزاء (Sections & Components Hierarchy):
1. **رأس الصفحة (`PageHeader.vue`):**
   * عنوان الصفحة وأيقونة المستخدم.
2. **بطاقة البيانات الأساسية (`ProfileBasicInfoCard.vue`):**
   * الاسم الكامل، رقم الهاتف للدخول، والبريد الإلكتروني `BaseInput.vue`.
3. **بطاقة الأمان وكلمة المرور (`ProfileSecurityCard.vue`):**
   * كلمة المرور الحالية، كلمة المرور الجديدة، وتأكيدها `BaseInput.vue`.
4. **بطاقة تفضيل المظهر (`ProfileThemeCard.vue`):**
   * تبديل الوضع الليلي (Dark Slate) والوضع الفاتح (Light Shell).

#### الاعتماديات والمصادر:
* **API Endpoints:** `GET /api/v1/profile`, `PUT /api/v1/profile`.
* **Composables:** `useProfile.js`, `useTrans.js`.
* **المكونات المشتركة:** `PageHeader.vue`, `BaseButton.vue`, `BaseInput.vue`.

---

### 2. المكونات المشتركة (Shared Components)
* **المكونات المشتركة المستخدمة:**
  * `PageHeader.vue`, `BaseButton.vue`, `BaseInput.vue`.
* **المكونات التابعة للصفحة (داخل `resources/js/Components/Profile/`):**
  * `ProfileBasicInfoCard.vue`, `ProfileSecurityCard.vue`, `ProfileThemeCard.vue`.

---

### 3. التجاوب وتجربة اللمس (Responsive & Touch Ergonomics)
* **📱 هواتف (360px - 430px):** بطاقات متراصة، حقول إدخال وأزرار بارتفاع $\ge 40	ext{px}$.
* **💻 تابلت وديسكتوب (768px - 1280px+):** حاوية مركزية بحد أقصى `max-w-3xl`.
* **🌓 الوضع الداكن والفاتح:** تباين كامل للبطاقات والصفوف وحقول الإدخال.

---

### 4. الترجمة والتعريب (100% Zero Hardcoded Localization)
* ترجمة عربية وإنجليزية كاملة في `lang/ar/profile.php` و `lang/en/profile.php` و `defaultTranslations.js`.
* نسبة التطابق: ✅ 100%.

---

### 5. الاختبار والتحقق الآلي (Automated Tests Suite)
* ✅ **اختبار الفرونت إند الشامل:** تشغيل `e2e/flows/profile-full-page-audit.spec.js` -> نجاح 7/7 اختبارات عبر كافة مقاسات الشاشات الـ 5 بدون أي خطأ Console.
* ✅ **فحص البناء:** تشغيل `npm run build` -> تم البناء بنجاح في 2.84 ثانية.


---

## 📌 صفحة 29: لوحة تحكم السوبر أدمن المركزية (`SuperAdminDashboardView.vue`) — بتاريخ 2026-08-24
* **المسار:** `/super-admin`
* **الحالة العامة:** ✅ مكتملة 100% (تفكيك 425 سطراً إلى 5 مكونات فرعية متخصصة + نمط المنسق النحيف Thin Orchestrator < 75 سطر + كبسولة المنطق useSuperAdminDashboard + شبكة المؤشرات الخمسة للمنصة و MRR + كارد توزيع المشتركين على الباقات + تدفق أحدث المستأجرين المسجلين بشارات الحالة + كارد إعدادات المنصة والهوية المركزية والتحديث الفوري للـ Store + كارد مواصفات السيرفر المركزي + تجاوب لمسي كامل لكافة المقاسات الـ 5 + تعريب كامل بدون نصوص ثابتة + اختبارات E2E ناجحة 7/7).

---

### 1. التوثيق الكامل (Full Documentation)

#### نظرة عامة:
* **اسم الصفحة:** لوحة تحكم السوبر أدمن المركزية (Super Admin Central Dashboard)
* **المسار (Route):** `/super-admin`
* **الملف الرئيسي:** `resources/js/views/SuperAdmin/SuperAdminDashboardView.vue` (Thin Orchestrator: ~75 سطر)
* **الغرض منها:** متابعة المستأجرين والاشتراكات والإيراد الشهري والتحكم في إعدادات المنصة ومواصفات السيرفر.

#### تقسيم الأجزاء (Sections & Components Hierarchy):
1. **رأس الصفحة وإجراءات التنقل (`PageHeader.vue`):**
   * عنوان الصفحة وأزرار الانتقال لإدارة المستأجرين والباقات.
2. **شبكة مؤشرات المنصة الخمسة (`SuperAdminMetricsGrid.vue`):**
   * إجمالي المستأجرين، النشطين، التجريبيين، الموقوفين، و MRR مع `StatCardSkeleton.vue`.
3. **توزيع الباقات والاشتراكات (`SuperAdminPlansDistribution.vue`):**
   * قائمة الباقات وعدد المشتركين والأسعار الشهرية.
4. **أحدث المستأجرين المسجلين (`SuperAdminRecentTenants.vue`):**
   * قائمة المستأجرين مع الدومين والباقة وشارات الحالة الملونة.
5. **إعدادات المنصة والهوية المركزية (`SuperAdminPlatformSettingsCard.vue`):**
   * نموذج تعديل اسم المنصة والوصف وبيانات الدعم مع `BaseInput.vue` و `BaseButton.vue`.
6. **مواصفات السيرفر المركزي (`SuperAdminServerSpecsCard.vue`):**
   * بطاقة مواصفات إصدار PHP، Laravel، MySQL، وبيئة التشغيل.

#### الاعتماديات والمصادر:
* **API Endpoints:** `GET /api/v1/super-admin/dashboard`, `GET /api/v1/super-admin/settings`, `POST /api/v1/super-admin/settings`.
* **Composables:** `useSuperAdminDashboard.js`, `useTrans.js`.
* **المكونات المشتركة:** `PageHeader.vue`, `BaseButton.vue`, `BaseInput.vue`, `StatCardSkeleton.vue`.

---

### 2. المكونات المشتركة (Shared Components)
* **المكونات المشتركة المستخدمة:**
  * `PageHeader.vue`, `BaseButton.vue`, `BaseInput.vue`, `StatCardSkeleton.vue`.
* **المكونات التابعة للصفحة (داخل `resources/js/Components/SuperAdmin/`):**
  * `SuperAdminMetricsGrid.vue`, `SuperAdminPlansDistribution.vue`, `SuperAdminRecentTenants.vue`, `SuperAdminPlatformSettingsCard.vue`, `SuperAdminServerSpecsCard.vue`.

---

### 3. التجاوب وتجربة اللمس (Responsive & Touch Ergonomics)
* **📱 هواتف (360px - 430px):** بطاقات مؤشرات ولمسية متراصة، حقول إدخال وأزرار بارتفاع $\ge 40	ext{px}$.
* **💻 تابلت وديسكتوب (768px - 1280px+):** شبكة خماسية لبطاقات المؤشرات، تقسيم 1/3 للباقات و 2/3 للمستأجرين، وشبكة رباعية لمعلومات السيرفر.
* **🌓 الوضع الداكن والفاتح:** تباين كامل للبطاقات والصفوف وحقول الإدخال.

---

### 4. الترجمة والتعريب (100% Zero Hardcoded Localization)
* ترجمة عربية وإنجليزية كاملة في `lang/ar/super.php` و `lang/en/super.php` و `defaultTranslations.js`.
* نسبة التطابق: ✅ 100%.

---

### 5. الاختبار والتحقق الآلي (Automated Tests Suite)
* ✅ **اختبار الفرونت إند الشامل:** تشغيل `e2e/flows/super-admin-dashboard-full-page-audit.spec.js` -> نجاح 7/7 اختبارات عبر كافة مقاسات الشاشات الـ 5 بدون أي خطأ Console.
* ✅ **فحص البناء:** تشغيل `npm run build` -> تم البناء بنجاح في 4.16 ثانية.


---

## 📌 صفحة 30: إدارة المستأجرين والشركات المركزية (`SuperAdminTenantsView.vue`) — بتاريخ 2026-08-24
* **المسار:** `/super-admin/tenants`
* **الحالة العامة:** ✅ مكتملة 100% (تفكيك 584 سطراً إلى 4 مكونات فرعية متخصصة + نمط المنسق النحيف Thin Orchestrator < 75 سطر + كبسولة المنطق useSuperAdminTenants + شريط فلاتر للبحث والحالة والباقة + جدول الشركات وبطاقات الهواتف مع روابط النطاقات وشارات الحالة + نافذة AppModal لإنشاء وتهيئة المستأجر وقاعدة البيانات Auto-Provisioning + نافذة AppModal لتعديل الحالة وتمديد الاشتراك + تجاوب لمسي كامل لكافة المقاسات الـ 5 + تعريب كامل بدون نصوص ثابتة + اختبارات E2E ناجحة 7/7).

---

### 1. التوثيق الكامل (Full Documentation)

#### نظرة عامة:
* **اسم الصفحة:** إدارة المستأجرين والشركات المركزية (Super Admin Tenants Management)
* **المسار (Route):** `/super-admin/tenants`
* **الملف الرئيسي:** `resources/js/views/SuperAdmin/SuperAdminTenantsView.vue` (Thin Orchestrator: ~75 سطر)
* **الغرض منها:** إنشاء وتهيئة حسابات الشركات وتفعيل وإيقاف الاشتراكات والتحكم في قواعد البيانات.

#### تقسيم الأجزاء (Sections & Components Hierarchy):
1. **رأس الصفحة وإجراءات التنقل (`PageHeader.vue`):**
   * عنوان الصفحة، وزر العودة للوحة القيادة، وزر إنشاء مستأجر جديد.
2. **شريط البحث والفلاتر (`TenantsFilterBar.vue`):**
   * حقل البحث الفوري `BaseSearchInput` وقوائم تصفية الحالة والباقة `BaseSelect`.
3. **جدول وبطاقات المستأجرين (`TenantsTable.vue`):**
   * جدول سطح المكتب وبطاقات الهواتف مع شارات الحالة، زر الدخول للتحكم، وزر تعديل الحالة وزر الحذف، مع `TableSkeleton.vue` و `EmptyState.vue`.
4. **نافذة إنشاء المستأجر (`CreateTenantModal.vue`):**
   * نافذة `AppModal` لإنشاء الحساب وتحديد الباقة وإعدادات قاعدة البيانات.
5. **نافذة تعديل الحالة والاشتراك (`EditTenantStatusModal.vue`):**
   * نافذة `AppModal` لتغيير الحالة وتمديد مدة الاشتراك.

#### الاعتماديات والمصادر:
* **API Endpoints:** `GET /api/v1/super-admin/tenants`, `POST /api/v1/super-admin/tenants`, `POST /api/v1/super-admin/tenants/{id}/toggle-status`, `DELETE /api/v1/super-admin/tenants/{id}`.
* **Composables:** `useSuperAdminTenants.js`, `useTrans.js`.
* **المكونات المشتركة:** `PageHeader.vue`, `BaseButton.vue`, `BaseInput.vue`, `BaseSelect.vue`, `BaseSearchInput.vue`, `TableSkeleton.vue`, `EmptyState.vue`, `AppModal.vue`.

---

### 2. المكونات المشتركة (Shared Components)
* **المكونات المشتركة المستخدمة:**
  * `PageHeader.vue`, `BaseButton.vue`, `BaseInput.vue`, `BaseSelect.vue`, `BaseSearchInput.vue`, `TableSkeleton.vue`, `EmptyState.vue`, `AppModal.vue`.
* **المكونات التابعة للصفحة (داخل `resources/js/Components/SuperAdmin/`):**
  * `TenantsFilterBar.vue`, `TenantsTable.vue`, `CreateTenantModal.vue`, `EditTenantStatusModal.vue`.

---

### 3. التجاوب وتجربة اللمس (Responsive & Touch Ergonomics)
* **📱 هواتف (360px - 430px):** بطاقات لمسية متراصة لكل مستأجر، أزرار بارتفاع $\ge 40	ext{px}$.
* **💻 تابلت وديسكتوب (768px - 1280px+):** جدول مركزي عالي الكثافة مع روابط خارجية للنطاقات وشارات ملونة واضحة.
* **🌓 الوضع الداكن والفاتح:** تباين كامل للبطاقات والصفوف وحقول الإدخال.

---

### 4. الترجمة والتعريب (100% Zero Hardcoded Localization)
* ترجمة عربية وإنجليزية كاملة في `lang/ar/super.php` و `lang/en/super.php` و `defaultTranslations.js`.
* نسبة التطابق: ✅ 100%.

---

### 5. الاختبار والتحقق الآلي (Automated Tests Suite)
* ✅ **اختبار الفرونت إند الشامل:** تشغيل `e2e/flows/super-admin-tenants-full-page-audit.spec.js` -> نجاح 7/7 اختبارات عبر كافة مقاسات الشاشات الـ 5 بدون أي خطأ Console.
* ✅ **فحص البناء:** تشغيل `npm run build` -> تم البناء بنجاح في 4.93 ثانية.


---

## 📌 صفحة 31: تفاصيل المستأجر والتحكم والمحاكاة (`SuperAdminTenantShowView.vue`) — بتاريخ 2026-08-24
* **المسار:** `/super-admin/tenants/:id`
* **الحالة العامة:** ✅ مكتملة 100% (تفكيك 571 سطراً إلى 5 مكونات فرعية متخصصة + نمط المنسق النحيف Thin Orchestrator < 75 سطر + كبسولة المنطق useSuperAdminTenantShow + رأس تنفيذي تفاعلي مع شارات الحالة والدخول كأدمن وتشغيل الميجريشن + شبكة مؤشرات تشغيلية حية + بطاقة إدارة وتخصيص وحدات القياس المعتمدة + بطاقة مصفوفة الميزات والـ Feature Flags + نافذة AppModal لتعديل الحالة وتمديد الاشتراك + تجاوب لمسي كامل لكافة المقاسات الـ 5 + تعريب كامل بدون نصوص ثابتة + اختبارات E2E ناجحة 7/7).

---

### 1. التوثيق الكامل (Full Documentation)

#### نظرة عامة:
* **اسم الصفحة:** تفاصيل المستأجر والتحكم والمحاكاة (Super Admin Tenant Show & Control)
* **المسار (Route):** `/super-admin/tenants/:id`
* **الملف الرئيسي:** `resources/js/views/SuperAdmin/SuperAdminTenantShowView.vue` (Thin Orchestrator: ~75 سطر)
* **الغرض منها:** الإشراف الدقيق على حساب المؤسسة الفردية والتحكم في ميزاتها ووحدات قياسها وتمديد اشتراكها وتشغيل الميجريشن.

#### تقسيم الأجزاء (Sections & Components Hierarchy):
1. **الرأس التنفيذي وأزرار الإجراءات (`TenantShowHeader.vue`):**
   * عرض الاسم والشارة والمعرف والنطاق والباقة، وأزرار الدخول كأدمن، تعديل الحالة، تشغيل الميجريشن، والحذف.
2. **شبكة المؤشرات التشغيلية (`TenantStatsGrid.vue`):**
   * بطاقات المؤشرات الـ 5 (المستخدمين، الفروع، الأصناف، الفواتير، وحجم العمليات الإجمالي).
3. **بطاقة إدارة وتخصيص وحدات القياس (`TenantUnitsCard.vue`):**
   * استعراض الوحدات النشطة، الإضافة من القوالب العامة، وإضافة وحدة مخصصة مع زر الحفظ.
4. **بطاقة مصفوفة الميزات والـ Feature Flags (`TenantFeaturesMatrixCard.vue`):**
   * استعراض وتبديل ميزات الباقة الاستثنائية للمستأجر بشكل فوري.
5. **نافذة تعديل الحالة والاشتراك (`TenantStatusModal.vue`):**
   * نافذة `AppModal` لتغيير حالة الحساب وتمديد أيام الاشتراك.

#### الاعتماديات والمصادر:
* **API Endpoints:** `GET /api/v1/super-admin/tenants/{id}`, `POST /api/v1/super-admin/tenants/{id}/override-feature`, `POST /api/v1/super-admin/tenants/{id}/update-units`, `POST /api/v1/super-admin/tenants/{id}/run-migrations`, `POST /api/v1/super-admin/tenants/{id}/toggle-status`, `DELETE /api/v1/super-admin/tenants/{id}`.
* **Composables:** `useSuperAdminTenantShow.js`, `useFormatters.js`, `useTrans.js`.
* **المكونات المشتركة:** `BaseButton.vue`, `BaseInput.vue`, `AppModal.vue`.

---

### 2. المكونات المشتركة (Shared Components)
* **المكونات المشتركة المستخدمة:**
  * `BaseButton.vue`, `BaseInput.vue`, `AppModal.vue`.
* **المكونات التابعة للصفحة (داخل `resources/js/Components/SuperAdmin/`):**
  * `TenantShowHeader.vue`, `TenantStatsGrid.vue`, `TenantUnitsCard.vue`, `TenantFeaturesMatrixCard.vue`, `TenantStatusModal.vue`.

---

### 3. التجاوب وتجربة اللمس (Responsive & Touch Ergonomics)
* **📱 هواتف (360px - 430px):** ترتيب عمودي للرأس التنفيذي، شبكة مؤشرات مرنة، بطاقات ملائمة للمس مع أزرار بارتفاع $\ge 40	ext{px}$.
* **💻 تابلت وديسكتوب (768px - 1280px+):** تقسيم عمودين 6/6 لوحدات القياس ومصفوفة الميزات، شبكة خماسية للمؤشرات.
* **🌓 الوضع الداكن والفاتح:** تباين كامل للبطاقات والصفوف وحقول الإدخال.

---

### 4. الترجمة والتعريب (100% Zero Hardcoded Localization)
* ترجمة عربية وإنجليزية كاملة في `lang/ar/super.php` و `lang/en/super.php` و `defaultTranslations.js`.
* نسبة التطابق: ✅ 100%.

---

### 5. الاختبار والتحقق الآلي (Automated Tests Suite)
* ✅ **اختبار الفرونت إند الشامل:** تشغيل `e2e/flows/super-admin-tenant-show-full-page-audit.spec.js` -> نجاح 7/7 اختبارات عبر كافة مقاسات الشاشات الـ 5 بدون أي خطأ Console.
* ✅ **فحص البناء:** تشغيل `npm run build` -> تم البناء بنجاح في 5.24 ثانية.


---

## 📌 صفحة 32: إدارة باقات الاشتراك والأسعار المركزية (`SuperAdminPlansView.vue`) — بتاريخ 2026-08-24
* **المسار:** `/super-admin/plans`
* **الحالة العامة:** ✅ مكتملة 100% (تفكيك 320 سطراً إلى 3 مكونات فرعية متخصصة + نمط المنسق النحيف Thin Orchestrator < 65 سطر + كبسولة المنطق useSuperAdminPlans + شبكة بطاقات الباقات مع شارات الشعبية والحالة والأسعار الشهرية والسنوية وحدود الموارد + نافذة AppModal لتعديل الباقة والأسعار والحدود + تجاوب لمسي كامل لكافة المقاسات الـ 5 + تعريب كامل بدون نصوص ثابتة + اختبارات E2E ناجحة 7/7).

---

### 1. التوثيق الكامل (Full Documentation)

#### نظرة عامة:
* **اسم الصفحة:** إدارة باقات الاشتراك والأسعار المركزية (Super Admin Plans & Pricing)
* **المسار (Route):** `/super-admin/plans`
* **الملف الرئيسي:** `resources/js/views/SuperAdmin/SuperAdminPlansView.vue` (Thin Orchestrator: ~65 سطر)
* **الغرض منها:** حوكمة باقات المنظومة وتعديل أسعارها وحدود الموارد المخصصة لكل باقة.

#### تقسيم الأجزاء (Sections & Components Hierarchy):
1. **رأس الصفحة وإجراءات التنقل (`PageHeader.vue`):**
   * عنوان الصفحة، وزر العودة للوحة القيادة وإدارة المستأجرين.
2. **شبكة بطاقات الباقات (`PlansGrid.vue`):**
   * عرض البطاقات الثلاثية مع شارات الأكثر طلباً والحالة، وهيكل التحميل الوميضي `CardSkeleton.vue`.
3. **بطاقة الباقة الفردية (`PlanCard.vue`):**
   * عرض الأسعار والحدود (مستخدمين، فروع، أصناف، فواتير) وزر التعديل.
4. **نافذة تعديل الباقة (`EditPlanModal.vue`):**
   * نافذة `AppModal` لتعديل الأسعار والحدود وتفعيل الباقة وتحديد الشعبية.

#### الاعتماديات والمصادر:
* **API Endpoints:** `GET /api/v1/super-admin/plans`, `PUT /api/v1/super-admin/plans/{id}`.
* **Composables:** `useSuperAdminPlans.js`, `useFormatters.js`, `useTrans.js`.
* **المكونات المشتركة:** `PageHeader.vue`, `BaseButton.vue`, `BaseInput.vue`, `CardSkeleton.vue`, `AppModal.vue`.

---

### 2. المكونات المشتركة (Shared Components)
* **المكونات المشتركة المستخدمة:**
  * `PageHeader.vue`, `BaseButton.vue`, `BaseInput.vue`, `CardSkeleton.vue`, `AppModal.vue`.
* **المكونات التابعة للصفحة (داخل `resources/js/Components/SuperAdmin/`):**
  * `PlansGrid.vue`, `PlanCard.vue`, `EditPlanModal.vue`.

---

### 3. التجاوب وتجربة اللمس (Responsive & Touch Ergonomics)
* **📱 هواتف (360px - 430px):** ترتيب عمودي لبطاقات الباقات، أزرار بارتفاع $\ge 40	ext{px}$، ونوافذ منبثقة ملائمة للمس.
* **💻 تابلت وديسكتوب (768px - 1280px+):** شبكة ثلاثية الأبعاد لباقات الاشتراك مع تأثيرات Hover متناسقة.
* **🌓 الوضع الداكن والفاتح:** تباين كامل للبطاقات والصفوف وحقول الإدخال.

---

### 4. الترجمة والتعريب (100% Zero Hardcoded Localization)
* ترجمة عربية وإنجليزية كاملة في `lang/ar/super.php` و `lang/en/super.php` و `defaultTranslations.js`.
* نسبة التطابق: ✅ 100%.

---

### 5. الاختبار والتحقق الآلي (Automated Tests Suite)
* ✅ **اختبار الفرونت إند الشامل:** تشغيل `e2e/flows/super-admin-plans-full-page-audit.spec.js` -> نجاح 7/7 اختبارات عبر كافة مقاسات الشاشات الـ 5 بدون أي خطأ Console.
* ✅ **فحص البناء:** تشغيل `npm run build` -> تم البناء بنجاح في 5.18 ثانية.
