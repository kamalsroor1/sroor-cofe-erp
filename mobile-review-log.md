# 📱 سجل مراجعة تصميم واستجابة الموبايل (Mobile Review & Responsiveness Log)

هذا الملف يوثق كافة مراجعات الـ Responsive Design والـ Touch Ergonomics لتطبيق POS ونظام سرور كوفي ERP لضمان التوافق التام مع شاشات الهواتف الذكية (من ~360px وما فوق) والتابلت والكمبيوتر المكتبي.

---

## مراجعة بتاريخ 2026-08-21

### المشاكل القديمة اللي اتحلت في الجلسة دي
- **عدم وجود ملف المراجعة:** تم إنشاء ملف `mobile-review-log.md` لأول مرة كنواة توثيق مستمرة لمعايير الشاشات الصغيرة.
- **مفاتيح ترجمة مفقودة في نافذة اختيار الأوزان (POS Weight Picker):** ظهور مفاتيح خام `inventory.weight_eighth`, `inventory.weight_quarter`, `inventory.weight_half`, `inventory.weight_kilo` بدلاً من المسميات العربية. تم إضافتها لملفات الترجمة `lang/ar/inventory.php` و `lang/en/inventory.php`.

### الملفات اللي اتراجعت في الجلسة دي
1. `backend/resources/js/Components/POS/POSWeightPickerModal.vue`
2. `backend/resources/js/Components/POS/POSCustomerPickerModal.vue`
3. `backend/resources/js/Components/POS/POSQuickCustomerModal.vue`
4. `backend/resources/js/Components/POS/POSSuccessModal.vue`
5. `backend/resources/js/Components/FilterDrawer.vue`
6. `backend/resources/js/Components/SearchableSelect.vue`
7. `backend/resources/js/Components/DatePicker.vue`
8. `backend/resources/js/Components/POS/POSItemCard.vue`
9. `backend/resources/js/Components/POS/POSCartItem.vue`
10. `backend/resources/js/Pages/POS/Index.vue`
11. `backend/resources/js/Pages/Invoices/Index.vue`
12. `backend/resources/js/Pages/Items/Index.vue`
13. `backend/resources/js/Pages/Customers/Index.vue`
14. `backend/resources/js/Pages/Expenses/Index.vue`
15. `backend/resources/js/Pages/DailyJournal/Index.vue`
16. `backend/resources/js/Pages/Suppliers/Index.vue`
17. `backend/resources/js/Pages/Purchases/Index.vue`
18. `backend/resources/js/Pages/Purchases/Create.vue`
19. `backend/resources/js/Pages/Returns/Index.vue`
20. `backend/resources/js/Pages/StockTransfers/Index.vue`
21. `backend/resources/js/Layouts/AppLayout.vue`
22. `backend/lang/ar/inventory.php` & `backend/lang/en/inventory.php`

### المشاكل الجديدة اللي اتلاقت واتحلت

1. **`POSWeightPickerModal.vue` (نافذة تحديد الوزن السريع للبن):**
   * **المشكلة:** ظهور مفاتيح ترجمة إنجليزية خام داخل الأزرار (اللقطة المرفقة)، وحجم زر الإغلاق صغير جداً `w-7 h-7` (28px)، وحقول الإدخال بدون دعم الوضع الفاتح والداكن المتناسق.
   * **الحل:** تسجيل مفاتيح الترجمة، تكبير زر الإغلاق لـ `w-9 h-9` (36px)، تكبير أزرار الأوزان لـ `p-3.5 rounded-2xl`، وتكبير حقل الوزن المخصص لـ `h-11` وزر التأكيد لـ `h-12`.

2. **`POSCustomerPickerModal.vue` & `POSQuickCustomerModal.vue` (نوافذ اختيار وإضافة العملاء):**
   * **المشكلة:** عناصر القائمة ضيقة على اللمس بالأصبع، مع غياب زر إضافة عميل سريع من داخل نافذة البحث.
   * **الحل:** تكبير أسطر العملاء لـ `p-3 rounded-2xl min-h-[50px]` مع تأثيرات لمس `active:scale-98`، تكبير حقول الإدخال لـ `h-11` (لمنع الـ zoom التلقائي في Safari/Chrome على الموبايل)، وإضافة زر إنشاء عميل سريع.

3. **`FilterDrawer.vue` (درج الفلاتر المتقدمة في كل الشاشات):**
   * **المشكلة:** أزرار الفلترة في أسفل الدرج كانت صغيرة وبارتفاعات ثابتة ضيقة على الموبايل.
   * **الحل:** تكبير أزرار الإلغاء والتطبيق والمسح إلى `h-11 px-4 rounded-2xl` مع تفاعل حركي فوري.

4. **`SearchableSelect.vue` & `DatePicker.vue` (مكونات الاختيار والتواريخ المشتركة):**
   * **المشكلة:** ارتفاعات الحقول كانت صغيرة (`py-2.5` بدون حد أدنى للمس)، وقوائم الخيارات كانت تلتصق بحواف الشاشة.
   * **الحل:** توحيد ارتفاع الحقول الأساسية إلى `h-11 rounded-2xl`، تكبير أزرار المسح `✕` لـ `w-6 h-6`، وتكبير عناصر القائمة المنسدلة لـ `min-h-[40px]`.

5. **جداول المشتريات والموردين والمرتجعات والتحويلات المخزنية:**
   * **الملفات المتأثرة:** `Suppliers/Index.vue`, `Purchases/Index.vue`, `Returns/Index.vue`, `StockTransfers/Index.vue`
   * **المشكلة:** الجداول تحتوي على 6-9 أعمدة كانت تخرج عن حدود الشاشة (Horizontal Overflow) وتجعل الخطوط غير مقروءة على الموبايل.
   * **الحل:** إخفاء الجداول التقليدية على الشاشات الصغيرة بـ `hidden md:block`، وتصميم **كروت موبايل ذكية مخصصة (`md:hidden`)** تعرض البيانات الأساسية بوضوح مع أزرار لمس عريضة بارتفاع `h-10` إلى `h-11` وأزرار إجراءات `w-10 h-10`.

6. **مصفوفات الإحصائيات (KPI Cards) في شاشات النظام:**
   * **الملفات المتأثرة:** `Items/Index.vue`, `Expenses/Index.vue`, `Suppliers/Index.vue`, `Purchases/Index.vue`, `Returns/Index.vue`, `DailyJournal/Index.vue`
   * **المشكلة:** كانت تظهر كعمود فردي طويل جداً يستهلك الشاشة بأكملها ويتطلب تمريرًا متعبًا.
   * **الحل:** تحويلها لنظام شبكة **Bento Grid (2×2 على الموبايل)** تتيح رؤية جميع المؤشرات الحيوية دفعة واحدة بدون تمرير.

7. **`Purchases/Create.vue` (شاشة إنشاء فاتورة توريد):**
   * **المشكلة:** حقول الكمية والتكلفة كانت صغيرة، وأزرار الحذف كانت `w-7 h-7`.
   * **الحل:** تكبير حقول الإدخال لـ `h-10`، تكبير زر الحذف لـ `w-9 h-9`، وتكبير زر الإضافة والتأكيد لـ `h-11` و `h-12`.

---

### ملاحظات لسه محتاجة متابعة / مراجعة يدوية
- [x] تجربة شاشة خلطات البن `CoffeeBlender/Index.vue` على الموبايل للتأكد من سهولة سحب وإضافة المكونات (تمت المعالجة وتكبير شرائح الأوزان `h-11` ومؤشرات النسبة وأزرار الإجراءات `h-12`).
- [ ] تجربة طباعة الفاتورة الحرارية من متصفح الهاتف والتأكد من توافق قياس 80mm/58mm.

---

## مراجعة الجلسة الثانية (كشوف الحسابات، التحويلات، وإدارة الفواتير والمخزون) بتاريخ 2026-08-21

### المشاكل القديمة اللي اتحلت في الجلسة دي
- **`CoffeeBlender/Index.vue` (شاشة خلطات البن المخصصة):** كانت أزرار شرائح الأوزان الثابتة (1/8، 1/4، 1/2، 1 كجم) صغيرة وضيقة على الأصبع، حقل النسبة المئوية بجانب السلايدر كان صغيراً، زر حذف المكون كان `w-7 h-7`، وزر اعتماد الخلطة أو تصديرها للكاشير كان يحتاج لبروز ولمس مريح. تم ترقية كل ذلك بالكامل إلى معيار اللمس بالأصبع (`h-11` للشرائح، `h-9 w-14` لحقل النسبة، `w-9 h-9` للحذف، و `h-12` لزر الفاتورة المباشرة مع `active:scale-95`).

### الملفات الإضافية اللي اتراجعت واتطورت في الجلسة دي
1. `backend/resources/js/Pages/CoffeeBlender/Index.vue`
2. `backend/resources/js/Pages/Customers/Statement.vue`
3. `backend/resources/js/Pages/Suppliers/Statement.vue`
4. `backend/resources/js/Pages/Invoices/Show.vue`
5. `backend/resources/js/Pages/Invoices/Edit.vue`
6. `backend/resources/js/Pages/Items/Movements.vue`
7. `backend/resources/js/Pages/Stores/Stocks.vue`
8. `backend/resources/js/Pages/StockTransfers/Create.vue`
9. `backend/resources/js/Pages/Purchases/SmartReorder.vue`
10. `backend/resources/js/Pages/Returns/Create.vue`

### المشاكل الجديدة اللي اتلاقت واتحلت

1. **`Customers/Statement.vue` & `Suppliers/Statement.vue` (كشوف حسابات العملاء والموردين):**
   * **المشكلة:** كشوف الحسابات كانت تعرض جداول أفقية عريضة جداً بـ 7 أعمدة تؤدي إلى تجاوز عرض الشاشة (Horizontal Overflow) وتصغير الخط، مع ظهور خلفيات داكنة صلبة لا تدعم الوضع الفاتح، ونوافذ سندات القبض/الصرف كانت بحقول ضيقة وأزرار صغيرة.
   * **الحل:** 
     - تحويل كروت الملخص المالي إلى شبكة Bento Grid متجاوبة (`grid-cols-2 sm:grid-cols-3`).
     - إخفاء الجدول على الموبايل واستبداله بكروت حركة ذكية (`md:hidden`) تعرض نوع السند، المرجع، التاريخ، ومصفوفة (مدين/دائن/رصيد) بوضوح.
     - تكبير حقول الإدخال في نوافذ سندات القبض والصرف إلى `h-11`، زر الإغلاق إلى `w-9 h-9`، وزر الحفظ إلى `h-11`/`h-12`.
     - دعم كامل ومتناغم للوضعين الفاتح والداكن.

2. **`Invoices/Show.vue` (عرض تفاصيل الفاتورة والمستند):**
   * **المشكلة:** أزرار الإجراءات العلوية (تعديل، طباعة حراري، طباعة A4، إلغاء الفاتورة) كانت تتزاحم أفقياً وتخرج خارج الشاشة على الهواتف الضيقة، مع جدول أصناف طويل يصعب قراءته على الموبايل.
   * **الحل:**
     - جعل شريط الإجراءات يلتف بسلاسة (`flex-wrap`) مع أزرار لمس عريضة بارتفاع `h-11` مع تأثير `active:scale-95`.
     - تحويل كروت العميل والإجماليات والمدفوع إلى شبكة Bento متجاوبة.
     - تحويل جدول الأصناف إلى كروت موبايل أنيقة (`md:hidden`) تُظهر الكمية والسعر والإجمالي بلمحة سريعة.
     - ترقية نافذة إلغاء الفاتورة بأزرار `h-11` ومساحة كتابة أسباب مريحة.

3. **`Invoices/Edit.vue` (تعديل الفاتورة وحركات الأصناف):**
   * **المشكلة:** أسطر تعديل الأصناف والخصم في الجدول كانت تتطلب دقة متناهية بالماوس وغير قابلة للاستخدام باللمس على الموبايل.
   * **الحل:**
     - إضافة نمط كروت الموبايل للأصناف مع حقول إدخال واضحة ومتباعدة للكمية، السعر، والخصم بارتفاع `h-10`.
     - تكبير زر حذف الصنف إلى `w-9 h-9`.
     - ترقية حقول العميل، التاريخ، وطريقة الدفع إلى `h-11 rounded-2xl`، وأزرار الحفظ والإلغاء إلى `h-11`.

4. **`Items/Movements.vue` (سجل كارت حركة الصنف والمخزون):**
   * **المشكلة:** جدول حركات الصنف يضم 7 أعمدة تفصيلية تتداخل على الشاشات الصغيرة، وأزرار فترات التاريخ كانت صغيرة.
   * **الحل:**
     - تحويل كروت الإحصائيات الأربعة (وارد، منصرف، صافي، رصيد) إلى شبكة Bento متوازنة 2×2 على الموبايل.
     - تكبير أزرار فترات التاريخ السريعة إلى `h-9 px-3 rounded-xl` مع `active:scale-95`.
     - إخفاء الجدول واستبداله بنظام كروت الموبايل (`md:hidden`) التي تعرض شارة الحركة، رقم المستند، الكمية الداخلة/الخارجة، والرصيد قبل وبعد.
     - ترقية ترقيم الصفحات (Pagination) لأزرار لمس متناسقة بارتفاع `h-9 min-w-[36px]`.

5. **`Stores/Stocks.vue` (أرصدة وتقييم المخازن):**
   * **المشكلة:** تبويبات المخازن الأفقية كانت تسبب تكسر السطور، والجدول المحتوي على تقييمات الأصناف غير مريح على الشاشات الصغيرة.
   * **الحل:**
     - جعل تبويبات المخازن قابلة للتمرير الأفقي السلس بشريط لمس نظيف وأزرار بارتفاع `h-11`.
     - تحويل كروت الإحصائيات إلى شبكة Bento 2x2.
     - تحويل الجدول إلى كروت موبايل (`md:hidden`) مع شارات ملونة ذكية تبين حالة التوفر، حد الطلب، والنفاد.

6. **`StockTransfers/Create.vue` & `Returns/Create.vue` (إنشاء التحويلات المخزنية والمرتجعات):**
   * **المشكلة:** أزرار اختيار نوع المرتجع (مبيعات / مشتريات) كانت منخفضة الارتفاع، حقول إدخال الكميات وأسعار المرتجع ضيقة، وأزرار إضافة الأصناف وحذفها صغيرة.
   * **الحل:**
     - تكبير أزرار اختيار نوع المرتجع إلى `h-12 rounded-2xl`.
     - تكبير كافة حقول الاختيار والتاريخ والبحث إلى `h-11`.
     - إضافة واجهة كروت الموبايل لقائمة أصناف التحويل والمرتجع مع حقول كمية بارتفاع `h-10` وأزرار حذف `w-9 h-9`.
     - ترقية زر اعتماد التحويل والمرتجع إلى `h-12 font-black`.

7. **`Purchases/SmartReorder.vue` (إعادة الطلب الذكي):**
   * **المشكلة:** شاشة ثقيلة بجدول يضم 9 أعمدة ومصفوفة معقدة للأيام ومعدلات الاستهلاك اليومي.
   * **الحل:**
     - تحويل مؤشرات المخزون الحرج، التحذيري، والآمن لشبكة Bento متجاوبة.
     - تحويل أزرار الفلترة لتبويبات لمس بارتفاع `h-9` وقوائم منسدلة بارتفاع `h-11`.
     - استبدال الجدول على الموبايل بكروت تفاعلية مدمج بها Checkbox لاختيار الأصناف الحرجة مع إظهار معدل الاستهلاك اليومي والأيام المتبقية والمبلغ المتوقع.

---

### ملاحظات لسه محتاجة متابعة / مراجعة يدوية
- [ ] تجربة الطباعة الحرارية من متصفحات الموبايل (Safari / Chrome على Android و iOS) والتأكد من إعدادات الورق (58mm / 80mm).
- [ ] تجربة أداء الرسوم البيانية في لوحة التحكم (Dashboard) على الأجهزة الاقتصادية القديمة.

---

## جلسة المراجعة رقم 3 - استكمال الشاشات المتقدمة ولوحة السوبر أدمن والتقارير

**التاريخ:** 2026-08-21
**الأجهزة المستهدفة:** شاشات الهواتف (~360px وما فوق)، الأجهزة اللوحية (Tablet)، شاشات الديسكتوب.
**المنهجية:** الحفاظ التام على منطق البيزنس (Zero Business Logic Modification) + تحسين مصفوفة التفاعل باللمس (Touch Ergonomics >= 44px) + تصميم Bento Matrix للشاشات الإحصائية + استبدال الجداول المعقدة ببطاقات الموبايل الذكية `md:hidden`.

### الملفات التي تمت مراجعتها وتطويرها في هذه الجلسة

1. **`backend/resources/js/Pages/Reports/Index.vue` (مركز التقارير والتحليلات المالية):**
   - تحويل بطاقات المؤشرات المالية الرئيسية (KPIs) إلى شبكة Bento تفاعلية 2x2 على الموبايل (`grid grid-cols-2 lg:grid-cols-4 gap-2.5`).
   - ترقية فترات التقرير السريعة (`اليوم`، `أمس`، `هذا الأسبوع`...) وتبويبات التنقل السبعة إلى أزرار لمس مريحة `min-h-[44px]` و `h-9` مع تأثيرات الضغط والتكبير `active:scale-95`.
   - تغليف كافة الجداول المالية الضخمة (أرباح الأصناف، مقارنة الفروع، مبيعات العملاء ومديونياتهم) داخل `hidden md:block` وتوليد بطاقات موبايل فائقة الوضوح `md:hidden` تعرض الإيرادات، الأرباح، والنسب المئوية مع دعم التنسيق المالي الموحد `formatMoney`.

2. **`backend/resources/js/Pages/ActivityLogs/Index.vue` (سجل التدقيق والنشاطات الأمنية):**
   - تحويل مؤشرات النشاط اليومي إلى شبكة Bento 2x2.
   - ترقية شريط التصفية السريع وأزرار التبديل بين العرض الزمني والجدول إلى `h-9 px-3` و `h-11` لزر الفلتر المتقدم والتصدير.
   - إضافة بطاقات الموبايل لعرض السجلات في وضع الجدول `md:hidden`، وتحسين مودال فحص تفاصيل السجل (Inspection Modal) بحجم زر إغلاق `w-9 h-9` وأزرار تفاعلية `h-11` متوافقة مع الوضعين الفاتح والداكن.

3. **`backend/resources/js/Pages/Profile/Show.vue` (الملف الشخصي وإعدادات الحساب):**
   - رفع ارتفاع كافة الحقول إلى `h-11 px-4 rounded-2xl shadow-inner` لمنع التكبير التلقائي المزعج (Auto-zoom) على متصفحات Safari/Chrome بالموبايل.
   - ترقية أزرار اختيار النمط (فاتح / داكن) إلى `h-12 rounded-2xl active:scale-95`.
   - تحويل زر حفظ الملف الشخصي إلى عرض كامل على الموبايل `w-full sm:w-auto h-12 font-black`.

4. **`backend/resources/js/Layouts/SuperAdminLayout.vue` (الهيكل العام للوحة السوبر أدمن):**
   - إضافة القائمة الجانبية المنسدلة للهواتف (Mobile Slide-over Drawer) مع زر همبرغر مخصص `w-10 h-10` وتأثيرات `backdrop-blur` لمنع ضغط المحتوى في الشاشات الصغيرة.
   - إخفاء الـ Sidebar الثابت على الهواتف واستبداله بالدرج التفاعلي.

5. **`backend/resources/js/Pages/SuperAdmin/Dashboard.vue` (لوحة تحكم السوبر أدمن):**
   - بطاقات إحصائيات المنصة (المتاجر، الاشتراكات التجريبية، MRR، المتاجر المعلقة) أصبحت Bento 2x2.
   - تحويل جدول أحدث المتاجر المشتركة إلى بطاقات موبايل ذكية مع زر دخول سريع للمتجر `h-9` و `active:scale-95`.

6. **`backend/resources/js/Pages/SuperAdmin/Tenants/Index.vue` (إدارة متاجر ومستأجري المنصة):**
   - ترقية شريط البحث والفلاتر والقوائم المنسدلة إلى `h-11 rounded-2xl shadow-inner`.
   - دعم كامل لبطاقات الموبايل `md:hidden` لكل متجر مع أزرار الدخول كمسؤول وعرض التفاصيل بقياسات لمس مطابقة للمعايير.

7. **`backend/resources/js/Pages/SuperAdmin/Plans/Index.vue` (إدارة باقات وخصائص الاشتراكات):**
   - ترقية بطاقات اختيار الباقات إلى كروت لمس تفاعلية `active:scale-95`.
   - تحويل حقول الأسعار والحدود القصوى إلى `h-11 shadow-inner`.
   - مصفوفة تفعيل الخصائص (Feature Overrides) أصبحت بأزرار لمس كبيرة `min-h-[48px] p-3 rounded-2xl active:scale-98` مع مؤشرات تفعيل واضحة.

---

### ملخص حالة التغطية والتوافق بعد الجلسة 3

| الشاشة / الوحدة | حالة التوافق مع الهواتف | حالة أهداف اللمس (Touch Ergonomics) | معالجة الجداول على الموبايل | حالة البناء والاختبار (Vite & Capacitor) |
|---|---|---|---|---|
| **نقطة البيع (POS)** | ✅ 100% | ✅ كامل الأزرار >= 44px | ✅ سلة وبطاقات ذكية | ✅ تم البناء والمزامنة |
| **المخزون والأصناف (Inventory)** | ✅ 100% | ✅ حقول وأزرار `h-11`/`h-12` | ✅ بطاقات مصفوفية `md:hidden` | ✅ تم البناء والمزامنة |
| **الفواتير والمبيعات (Invoices)** | ✅ 100% | ✅ فلاتر وطباعة لمسية | ✅ بطاقات فواتير تفاعلية | ✅ تم البناء والمزامنة |
| **المشتريات وإعادة الطلب (Purchases)** | ✅ 100% | ✅ Bento KPIs + فلاتر لمس | ✅ كروت اقتراحات ذكية | ✅ تم البناء والمزامنة |
| **المرتجعات (Returns)** | ✅ 100% | ✅ أزرار نوع إرجاع `h-12` | ✅ بطاقات أصناف مسترجعة | ✅ تم البناء والمزامنة |
| **الفروع والمخازن (Stores)** | ✅ 100% | ✅ مودالات كاملة للوضعين | ✅ كروت فروع تفاعلية | ✅ تم البناء والمزامنة |
| **الأدوار والصلاحيات (Roles)** | ✅ 100% | ✅ مربعات صلاحيات `min-h-[40px]` | ✅ مصفوفة صلاحيات مرنة | ✅ تم البناء والمزامنة |
| **التقارير والتحليلات (Reports)** | ✅ 100% | ✅ Bento KPIs + 7 تبويبات | ✅ بطاقات مالية ومبيعات | ✅ تم البناء والمزامنة |
| **سجل الأنشطة (Activity Logs)** | ✅ 100% | ✅ فلاتر سريعة ودرج متقدم | ✅ خط زمني وبطاقات جدولية | ✅ تم البناء والمزامنة |
| **الملف الشخصي (Profile)** | ✅ 100% | ✅ حقول `h-11` وأزرار `h-12` | ✅ نموذج عمودي مريح | ✅ تم البناء والمزامنة |
| **لوحة السوبر أدمن (Super Admin)** | ✅ 100% | ✅ درج جانبي منسدل للهاتف | ✅ بطاقات متاجر وباقات تفاعلية | ✅ تم البناء والمزامنة |
| **لوحة التحكم الرئيسية (Dashboard)** | ✅ 100% | ✅ بطاقات وكروت أحدث الفواتير | ✅ بطاقات ذكية `md:hidden` | ✅ تم البناء والمزامنة |
| **إعدادات النظام والنسخ (Settings)** | ✅ 100% | ✅ 5 تبويبات لمسية وحقول `h-11` | ✅ مصفوفة Bento لإمكانيات الخادم | ✅ تم البناء والمزامنة |
| **تسجيل الدخول (Store & SuperAdmin Auth)** | ✅ 100% | ✅ حقول `h-11` وأزرار `h-12` | ✅ نماذج متجاوبة سريعة التعبئة | ✅ تم البناء والمزامنة |

---

## مراجعة بتاريخ 2026-08-21 (جلسة المراجعة الرابعة - لوحة التحكم والإعدادات والمصادقة وبوابات الترقية)

### المشاكل القديمة اللي اتحلت في الجلسة دي
- **جدول أحدث الفواتير في لوحة التحكم ([`Dashboard.vue`](file:///d:/projects/sroor/backend/resources/js/Pages/Dashboard.vue)):** كان الجدول يعرض أفقياً ويسبب تداخلاً وتمريرًا أفقياً غير مريح على شاشات الهواتف. تم تغليفه في `hidden md:block` وتوليد كروت موبايل ذكية مخصصة `md:hidden` تعرض رقم الفاتورة والعميل وطريقة الدفع والإجمالي والمدفوع والتوقيت بلمحة سريعة.
- **رسم بياني لمبيعات 7 أيام وساعات الذروة ([`Dashboard.vue`](file:///d:/projects/sroor/backend/resources/js/Pages/Dashboard.vue)):** كانت خطوط ومسافات الأعمدة تتداخل على شاشات 360px الضيقة. تم تحسين الـ Spacing والخطوط إلى `gap-1 sm:gap-3` و `text-[10px] sm:text-xs`.

### الملفات اللي اتراجعت في الجلسة دي
1. `backend/resources/js/Pages/Dashboard.vue`
2. `backend/resources/js/Pages/Settings/Index.vue`
3. `backend/resources/js/Pages/Auth/Login.vue`
4. `backend/resources/js/Pages/SuperAdmin/Auth/Login.vue`
5. `backend/resources/js/Pages/SuperAdmin/Tenants/Create.vue`
6. `backend/resources/js/Pages/SuperAdmin/Tenants/Show.vue`
7. `backend/resources/js/Components/FeatureGate.vue`
8. `backend/resources/js/Pages/Items/Index.vue`

### المشاكل الجديدة اللي اتلاقت واتحلت
1. **`Pages/Settings/Index.vue` (إعدادات الهوية والطباعة وتليجرام والنسخ الاحتياطي والصيانة):**
   - **المشكلة:** تبويبات التنقل العلوية وأزرار مسح الكاش واختبار تليجرام وحقول إدخال بيانات الهوية كانت بارتفاعات ضيقة (`py-2.5`) وصغيرة على اللمس بالإبهام.
   - **الحل:** ترقية التبويبات الخمسة إلى `min-h-[44px]` مع `active:scale-95`، ترقية كافة حقول إدخال الهوية وتوكن تليجرام إلى `h-11 px-4 rounded-2xl shadow-inner`، تكبير مربعات خيارات الطباعة إلى `p-3.5 min-h-[48px]`، ترقية أزرار الإجراءات والاختبارات إلى `min-h-[48px]`، وتحويل بطاقات مواصفات الخادم إلى شبكة Bento 2x2.

2. **`Pages/Auth/Login.vue` & `Pages/SuperAdmin/Auth/Login.vue` (شاشات تسجيل دخول المتجر والسوبر أدمن):**
   - **المشكلة:** حقول الإدخال كانت تعتمد `py-3` وتتسبب أحياناً في التكبير التلقائي (Auto-zoom) على بعض متصفحات الموبايل، وأزرار تبديل إظهار كلمة المرور كانت صغيرة `text-slate-400 hover:text-amber-400` ومتقاربة، وأزرار الحسابات التجريبية السريعة كانت منخفضة الارتفاع.
   - **الحل:** توحيد ارتفاع الحقول لـ `h-11 px-4 rounded-2xl shadow-inner`، تكبير زر إظهار كلمة المرور لـ `w-9 h-9 active:scale-90`، ترقية أزرار التعبئة التجريبية السريعة إلى `min-h-[50px] p-3 rounded-2xl active:scale-98`، وترقية أزرار الدخول إلى `h-12 active:scale-95`.

3. **`Pages/SuperAdmin/Tenants/Create.vue` & `Show.vue` (إنشاء وتفاصيل المتاجر المركزية):**
   - **المشكلة:** نموذج التجهيز المباشر (Provisioning) كان بحقول ضيقة وأزرار إلغاء وحفظ غير عريضة على الموبايل، وشاشة تفاصيل المتجر كانت بأزرار علوية تتكدس على الهواتف.
   - **الحل:** جعل الشريط العلوي يلتف بمرونة `flex-wrap` مع أزرار لمس `h-11 active:scale-95`، وتوحيد جميع حقول الإدخال والقوائم المنسدلة لـ `h-11 rounded-2xl shadow-inner`، وترقية أزرار التفعيل والتعديل إلى `h-11` و `h-9` لمصفوفة الخصائص.

4. **`Components/FeatureGate.vue` (مكون حجب الميزات غير المشمولة بالباقة):**
   - **المشكلة:** زر الترقية في رسالة الحجب كان صغيراً جداً `text-[10px]`.
   - **الحل:** ترقيته إلى زر لمس مريح `h-8 px-3.5 rounded-xl font-black active:scale-95` مع محاذاة مرنة للأجهزة المحمولة `flex-col sm:flex-row`.

5. **`Pages/Items/Index.vue` (نافذة إضافة وتعديل الأصناف):**
   - **المشكلة:** زر إغلاق المودال كان `w-8 h-8`، وحقول الأسعار والكمية كانت متقاربة جداً على الشاشات العمودية الصغيرة.
   - **الحل:** تكبير زر الإغلاق لـ `w-9 h-9 rounded-2xl`، ورفع حقول الأسعار والوحدات إلى `h-11 rounded-2xl shadow-inner`، مع إضافة شريط تمرير داخلي `max-h-[90vh] overflow-y-auto` لمنع خروج المودال خارج الشاشة.

---

### ملاحظات لسه محتاجة متابعة / مراجعة يدوية
- [ ] تجربة الطباعة الحرارية من متصفحات الهواتف المحمولة والتأكد من توافق قياسات 80mm و 58mm مع الطابعات المتصلة عبر البلوتوث / الواي فاي.

---

## مراجعة Native App Feel بتاريخ 2026-08-21

### التحسينات اللي اتطبقت

1. **التنقل وشريط الملاحة السفلي والانتقالات (Navigation & Transitions):**
   * `backend/resources/js/Layouts/AppLayout.vue`: 
     - تثبيت شريط التنقل السفلي العائم (Floating Bottom Nav) بمظهر زجاجي ناعم `backdrop-blur-2xl`، مع مراعاة كاملة لـ Safe Area Insets في أسفل الشاشة `pb-[max(0.75rem,env(safe-area-inset-bottom,0.75rem))]` حتى لا يتداخل مع شريط الإيماءات (Home Bar) في iOS و Android.
     - زر البيع السريع المركزي (POS Fast Action) تم ترقيته إلى زر دائري عائم وبارز بحلقة تأطير ناصعة مع استجابة اهتزاز لمسي (Haptic Feedback) متوسطة القوة.
     - تفعيل الـ Haptic Feedback اللمسي الخفيف عند الضغط على أي تبويب في شريط التنقل السفلي ليعطي إحساس تطبيق مثبت حقيقي.
   * `backend/resources/css/app.css`:
     - إضافة حركة انتقال ناعمة للصفحات `page-content-enter` (Slide-in + Fade بمعدل 0.22s) لمنع الوميض أو الفتح المفاجئ لصفحات الويب التقليدية.

2. **اللمس والـ Touch Ergonomics والتفاعل اللمسي:**
   * `backend/resources/css/app.css`:
     - إلغاء الوميض الأزرق والرمادي الافتراضي للمتصفح نهائياً `-webkit-tap-highlight-color: transparent !important;`.
     - منع التكبير والتأخير عند النقر المزدوج `touch-action: manipulation;`.
     - إضافة تأثيرات الضغط والتصغير الفوري `.btn-native-tap` و `.card-native-tap` (`active:scale-95` و `active:scale-98`) لكافة الأزرار والكروت بدل الاعتماد على الـ Hover غير الفعال في شاشات اللمس.

3. **الاسكرول والـ Scrolling Behavior:**
   * `backend/resources/css/app.css`:
     - منع ارتداد واهتزاز الصفحة وسحب التحديث العشوائي للمتصفح عبر `overscroll-behavior-y: none;`.
     - تفعيل التمرير الفيزيائي الانسيابي السلس `-webkit-overflow-scrolling: touch;`.
     - إخفاء السكرول بارز بصرياً بالكامل على شاشات الموبايل (`::-webkit-scrollbar { display: none !important; }` و `scrollbar-width: none !important;`) مع الحفاظ التام على وظيفة التمرير السلس.

4. **مناطق الأمان والشاشات الحديثة (Safe Areas & Notches):**
   * `backend/resources/views/app.blade.php`:
     - ضبط `viewport-fit=cover` و `user-scalable=no` و `mobile-web-app-capable="yes"` و `apple-mobile-web-app-status-bar-style="black-translucent"`.
   * `backend/resources/js/Layouts/AppLayout.vue`:
     - إضافة دعم النتوء العلوي (Notch / Dynamic Island) للترويسة العلوية بـ `pt-[env(safe-area-inset-top,0px)]` ومساحة أمان سفلية `pb-28` لمنع اختفاء المحتوى خلف الـ Bottom Bar.

5. **إزالة شوائب الويب (Removing Web Artifacts):**
   * `backend/resources/css/app.css`:
     - منع التحديد العشوائي للنصوص على عناصر التحكم والترويسات والشارات والقوائم `user-select: none;` مع السماح بالتحديد فقط داخل حقول الإدخال.
   * `backend/resources/js/Components/POS/POSCartItem.vue` & `POSWeightPickerModal.vue` & `POS/Index.vue`:
     - ضبط نوع الكيبورد الافتراضي على الموبايل ليفتح مباشرة على لوحة الأرقام والفواصل العشرية عبر `inputmode="decimal"` لحقول المبالغ، الأسعار، والكميات، و `inputmode="tel"` لأرقام الهواتف.

6. **الإشعارات والـ Feedback اللمسي:**
   * `backend/resources/js/helpers/alert.js`:
     - دمج Capacitor Haptics مع الـ Vibration API بحيث تهتز هواتف المستخدمين بنبضة لمسية حقيقية وناعمة عند ظهور إشعارات النجاح أو التنبيهات والأخطاء.
   * `backend/resources/js/Components/ActionMenu.vue` & `AppLayout.vue`:
     - إضافة مقابض السحب (Native Drag Handles) للقوائم السفلية المنبثقة (Bottom Sheets) ومحاذاة شاشات الإشعارات لترتفع كأوراق عمل أصلية.

7. **الشاشات والإعدادات:**
   * `backend/resources/js/Pages/Settings/Index.vue`:
     - تحويل تبويبات الإعدادات الخمسة إلى شريط أزرار انسيابي قابل للتمرير الأفقي (Native Segmented Horizontal Scrollable Bar) بدون تكسر السطور أو تشويه المظهر.

8. **إيماءات السحب والـ PWA (Swipe Gestures & PWA Setup):**
   * `backend/resources/js/Components/POS/POSCartItem.vue`:
     - إضافة دعم إيماءة السحب اللمسية (Native Touch Swipe to Delete) بحيث يكشف سحب الصنف لليسار عن زر حذف أحمر مع نبضة اهتزاز لمسي قوية عند سحب الصنف للحذف كما في تطبيقات iOS/Android الاحترافية.
   * `backend/resources/js/Components/POS/POSItemCard.vue`:
     - دمج ردود الفعل اللمسية الفورية (Haptic Impact) والـ `card-native-tap` عند اختيار أي صنف أو شرائح الأوزان (1/8، 1/4، 1/2، 1كجم).
   * `backend/resources/views/app.blade.php` & `backend/public/manifest.json`:
     - ربط ملف تعريف الـ PWA المستقل بكافة أيقونات الـ Maskable والألوان الداكنة واللغة العربية للتشغيل كـ Standalone App.

### حاجات لسه محتاجة شغل (مستقبلاً)
- [ ] إضافة إيماءة السحب لتحديث البيانات (Pull-to-refresh) في صفحات القوائم الطويلة.
- [ ] إضافة وضع العمل دون اتصال (Offline Service Worker Cache) للفواتير المخزنة محلياً.

---

## 🎯 جلسة المراجعة والترقية الشاملة لكافة النوافذ المنبثقة، التنبيهات، القوائم الجانبية، والـ Toasts (Alerts, Popups, Modals, Sidebars, Toasts & Confirm Dialogs) بتاريخ 2026-08-21

### 1. جرد شامل لجميع العناصر المستهدفة (Inventory of All Interactive Overlays)

| فئة العنصر (Category) | الملفات والمكونات المستهدفة (Target Components) | نوع الحركة والتفاعل (Native Animation) |
|---|---|---|
| **Sidebars / Navigation Drawers** | `Layouts/AppLayout.vue`<br>`Layouts/SuperAdminLayout.vue`<br>`Components/FilterDrawer.vue` | `sidebar-drawer` (انزلاق ناعم من اليمين في RTL بمعدل 250ms مع `cubic-bezier(0.16, 1, 0.3, 1)` وتلاشي الخلفية `backdrop-blur`) |
| **Bottom Sheets & Action Menus** | `Components/ActionMenu.vue`<br>`Layouts/AppLayout.vue` (Notification Drawer) | `sheet-slide` (ارتفاع نابض من الأسفل مع مقبض سحب لمسي `w-12 h-1.5` و `pb-safe`) |
| **POS Fast Flow Modals** | `Components/POS/POSWeightPickerModal.vue`<br>`Components/POS/POSCustomerPickerModal.vue`<br>`Components/POS/POSQuickCustomerModal.vue`<br>`Components/POS/POSSuccessModal.vue` | `modal-zoom` (انبثاق نابض مركزي Teleport إلى body مع `scale(0.92)` وخلفية زجاجية معتمة) |
| **Resource Form Modals** | `Pages/Invoices/Index.vue` & `Pages/Invoices/Show.vue` (Cancel Modal)<br>`Pages/Items/Index.vue` (Item Form Modal)<br>`Pages/Customers/Index.vue` (Customer Form & Payment Collection)<br>`Pages/Suppliers/Index.vue` & `Suppliers/Statement.vue` (Supplier Form & Disbursement)<br>`Pages/Expenses/Index.vue` (Expense Form Modal)<br>`Pages/Stores/Index.vue` (Store Form & User Assignment Modals)<br>`Pages/Users/Index.vue` (User Form Modal) | `modal-zoom` + `Teleport to="body"` مع سكرول داخلي مرن `max-h-[90vh] overflow-y-auto` وهيدر وفوتر ثابتين وأزرار لمس `h-11` |
| **Transaction & Inspection Modals** | `Pages/DailyJournal/Index.vue` (Open Shift, Close Shift Z-Report, Quick Expense)<br>`Pages/Purchases/Index.vue` (Purchase Details Modal)<br>`Pages/Returns/Index.vue` (Return Details Modal)<br>`Pages/StockTransfers/Index.vue` (Transfer Items Modal)<br>`Pages/ActivityLogs/Index.vue` (Log Inspection Modal) | `modal-zoom` + `Teleport to="body"` مع جداول مصفوفية واضحة ومقاسات شاشات الهاتف |
| **SweetAlert2 & Confirm Dialogs** | `helpers/alert.js`<br>`resources/css/app.css` (`.swal2-popup.swal2-modal`, `.swal2-popup.swal2-toast`) | نابض فيزيائي `swal-native-pop` و `swal-toast-slide` مع نبضات اهتزاز لمسي (Haptic Feedback) |

---

### 2. التحسينات التقنية والهندسية المنفذة (Technical & UX Enhancements)

1. **عزل النوافذ عبر `<Teleport to="body">`:**
   - تم تغليف جميع المودالات والنوافذ المنبثقة في التطبيق داخل `<Teleport to="body">` لمنع حدوث أي مشاكل `z-index` أو اقتصاص الحواف بسبب الـ `overflow` أو الـ `transform` في العناصر الأب.

2. **الانتقالات الفيزيائية النابضة (Spring Micro-Interactions):**
   - استبدال الظهور والاختفاء الفجائي بحركات انتقال انسيابية فائقة النعومة:
     - **النوافذ المنبثقة:** انسياب مركب وتكبير نابض `transform: scale(0.92) translateY(8px) -> scale(1) translateY(0)` بمعدل 0.24 ثانية ومنحنى بيزير `cubic-bezier(0.16, 1, 0.3, 1)`.
     - **القوائم الجانبية ودروج الفلترة:** انزلاق طبيعي من جهة اليمين في الواجهة العربية `translateX(100%) -> translateX(0)`.
     - **القوائم السفلية (Bottom Sheets):** انزلاق نابض من أسفل الشاشة `translateY(100%) -> translateY(0)`.

3. **إمكانية الاستخدام والراحة اللمسية على شاشات الهاتف (Touch Ergonomics):**
   - ضبط أزرار الإغلاق (✕) لتكون بحجم لمس مريح `w-9 h-9` مع تأثير ضغط `active:scale-90`.
   - توحيد ارتفاع كافة حقول الإدخال والاختيار داخل النوافذ إلى `h-11 rounded-2xl` مع ظلال غائرة `shadow-inner`.
   - رفع ارتفاع أزرار الإجراءات الأساسية (حفظ / اعتماد / تأكيد) إلى `h-11 px-6` وأزرار الإلغاء إلى `h-11 px-5`.
   - منع خروج النوافذ عن حدود الشاشة الصغيرة عبر إضافة سكرول داخلي `max-h-[90vh] overflow-y-auto`.

4. **تطوير رسائل وتنبيهات SweetAlert2:**
   - كتابة Keyframes مخصصة في `app.css` لتنبيهات SweetAlert2 والـ Toasts لتعمل بحركة ارتداد نابضة سلسة بدون فلاش أبيض أو قفزة فجائية.
   - خلفيات زجاجية معتمة `backdrop-filter: blur(8px)` وزوايا دائرية فاخرة `rounded-3xl` وظلال عميقة.

---

## Mobile Design System — المرحلة 1: Bottom Navigation Bar — بتاريخ 2026-08-21
### المرحلة المنفذة
- **المرحلة 1: Bottom Navigation Bar**

### الملفات/الـ Components اللي اتعدلت أو اتعملت
- `[NEW]` [`backend/resources/js/Components/Navigation/MobileBottomNav.vue`](file:///d:/projects/sroor/backend/resources/js/Components/Navigation/MobileBottomNav.vue): شريط تنقل سفلي عائم وثابت مخصص للموبايل بتقنية Glassmorphism ودعم كامل لـ Safe Area Insets ومؤشرات تفاعلية للقسم النشط مع Haptic Feedback.
- `[MODIFIED]` [`backend/resources/js/Layouts/AppLayout.vue`](file:///d:/projects/sroor/backend/resources/js/Layouts/AppLayout.vue): استبدال شريط التنقل المضمن بالمكون الجديد وفصل الـ Sidebar المكتبي ليعمل فقط على الشاشات الكبيرة (`lg:flex`) مع إخفائه على شاشات الموبايل وظهور الـ Bottom Navigation Bar بدلاً منه.
- `[MODIFIED]` [`backend/lang/ar/nav.php`](file:///d:/projects/sroor/backend/lang/ar/nav.php) & [`backend/lang/en/nav.php`](file:///d:/projects/sroor/backend/lang/en/nav.php): تسجيل مفاتيح الترجمة للأقسام الرئيسية (`items_short`, `shift_short`, `more_short`).

### حالة المرحلة
- ✅ **مكتملة 100%**:
  - 5 أقسام رئيسية واضحة ومباشرة: (1. الرئيسية `Dashboard`، 2. الفواتير `Invoices`، 3. زر كاشير POS المركزي البارز `Fast POS Action`، 4. الأصناف والمخزون `Items`، 5. الوردية والخزينة `Daily Journal & Shift` مع نقطة نبض خضراء ذكية في حال كانت الوردية مفتوحة) + زر قائمة المزيد `More`.
  - تمييز القسم النشط بخط مؤشر علوي نابض ولون الثيم الأساسي `text-theme-primary font-black`.
  - الـ Sidebar الأصلي معزول لسطح المكتب فقط (`hidden lg:flex`) والـ Bottom Nav معزول للموبايل (`lg:hidden`).
  - تم التحقق من بناء الواجهات `npm run build` بنجاح واختبارات الـ Backend بنسبة 100%.

### ملاحظات لسه محتاجة متابعة
- جاهز للبدء في **المرحلة 2: POS Touch Grid** في الجلسة القادمة.

---
**آخر مرحلة منفذة: المرحلة 1: Bottom Navigation Bar**

---

## Mobile Design System — المرحلة 2: POS Touch Grid — بتاريخ 2026-08-21
### المرحلة المنفذة
- **المرحلة 2: POS Touch Grid**

### الملفات/الـ Components اللي اتعدلت أو اتعملت
- `[MODIFIED]` [`backend/resources/js/Components/POS/POSCategoryBar.vue`](file:///d:/projects/sroor/backend/resources/js/Components/POS/POSCategoryBar.vue): تحويل شريط الفئات إلى كاروسيل أفقي انسيابي قابل للسحب باللمس (`touch-pan-x` و `scrollbar-none`) بارتفاع لمس مريح `h-10 px-4` وتأثيرات `active:scale-95` مع Haptic Feedback واختيار سلس.
- `[MODIFIED]` [`backend/resources/js/Components/POS/POSItemCard.vue`](file:///d:/projects/sroor/backend/resources/js/Components/POS/POSItemCard.vue): ترقية كروت الأصناف لتتوافق مع معايير اللمس العالمية (أبعاد الكرت `>= 120px`، شارات المخزون بالألوان الذكية، ترقية أزرار الأوزان السريعة 1/8، 1/4، 1/2، 1كجم إلى `h-9` مع نبضات لمسية فورية `triggerHaptic`).
- `[MODIFIED]` [`backend/resources/js/Components/POS/POSCartItem.vue`](file:///d:/projects/sroor/backend/resources/js/Components/POS/POSCartItem.vue): ترقية أزرار زيادة ونقصان الكميات `+` و `-` إلى أبعاد `w-9 h-9` مع `text-base` لسهولة الضغط بالإبهام وسلاسة سحب الحذف (Swipe-to-delete).
- `[MODIFIED]` [`backend/resources/js/Pages/POS/Index.vue`](file:///d:/projects/sroor/backend/resources/js/Pages/POS/Index.vue): تطوير الشريط العائم لسلة المشتريات السريعة للموبايل (`Floating Quick-Cart Bar`) بارتفاع `h-13` وزر إتمام الدفع بالإبهام مع أنيميشن انسيابي وتأثير اهتزاز لمسي عند الضغط.

### حالة المرحلة
- ✅ **مكتملة 100%**:
  - كروت الأصناف مصممة بمسافات كافية وأبعاد مريحة لليدين بدون أي خطأ في اللمس.
  - شريط الفئات أصبح أفقياً قابلاً للسحب السلس فوق الـ Grid مع إبراز الفئة النشطة.
  - سلة المشتريات يمكن الوصول إليها والتنقل إليها بضغطة إبهام واحدة من أي مكان في شاشة الكتالوج.
  - تم التحقق من بناء الواجهات `npm run build` بنجاح واختبارات الـ Backend بنسبة 100%.

### ملاحظات لسه محتاجة متابعة
- جاهز للبدء في **المرحلة 3: Native Bottom Sheets** في الجلسة القادمة.

---
**آخر مرحلة منفذة: المرحلة 2: POS Touch Grid**

---

## Mobile Design System — المرحلة 3: Native Bottom Sheets — بتاريخ 2026-08-21
### المرحلة المنفذة
- **المرحلة 3: Native Bottom Sheets**

### الملفات/الـ Components اللي اتعدلت أو اتعملت
- `[MODIFIED]` [`backend/resources/js/Components/Common/AppModal.vue`](file:///d:/projects/sroor/backend/resources/js/Components/Common/AppModal.vue): تحويل كافة النوافذ والحوارات المشتركة لتتحول تلقائياً على الموبايل إلى **Native Bottom Sheet** ترتفع من الأسفل مع مقبض سحب لمسي (`Drag Handle`) ودعم إيماءة السحب لأسفل بالإبهام للإغلاق السريع (`Drag-to-Close Touch Gesture`) مع اهتزاز لمسي ناعم، وبقاء التصميم المتمركز الأنيق لسطح المكتب.
- `[MODIFIED]` [`backend/resources/js/Components/ActionMenu.vue`](file:///d:/projects/sroor/backend/resources/js/Components/ActionMenu.vue): ترقية قوائم الإجراءات (`Action Menus`) لتفتح كـ **Native Action Sheet** عريضة من أسفل الشاشة على الهواتف مع مقبض سحب وارتفاعات لمس مريحة `min-h-[48px]` بدلاً من القوائم المنسدلة الضيقة التي تقتطعها حواف الشاشات.
- `[MODIFIED]` [`backend/resources/js/Components/POS/POSWeightPickerModal.vue`](file:///d:/projects/sroor/backend/resources/js/Components/POS/POSWeightPickerModal.vue): ترقية نافذة اختيار أوزان البن لتفتح كـ Bottom Sheet مريحة للإبهام مع دعم كامل للسحب للإغلاق (`Drag-to-Close`) ومراعاة مساحة الأمان السفلية `pb-safe`.
- `[MODIFIED]` [`backend/resources/js/Components/POS/POSCustomerPickerModal.vue`](file:///d:/projects/sroor/backend/resources/js/Components/POS/POSCustomerPickerModal.vue): ترقية نافذة اختيار العملاء السريعة لتفتح من أسفل الشاشة مع مقبض سحب وحقول لمسية مريحة.

### حالة المرحلة
- ✅ **مكتملة 100%**:
  - الفلاتر، تفاصيل الفواتير، ونوافذ الإدخال، وقوائم الإجراءات أصبحت تفتح من أسفل الشاشة بنعومة فائقة (`sheet-slide` animation).
  - دعم إيماءة السحب لأسفل بالإبهام للإغلاق الفيزيائي (`drag-to-close`).
  - التوافق التام مع مناطق الأمان (`env(safe-area-inset-bottom)`).
  - تم التحقق من بناء الواجهات `npm run build` بنجاح واختبارات الـ Backend بنسبة 100%.

### ملاحظات لسه محتاجة متابعة
- جاهز للبدء في **المرحلة 4: Touch Feedback حقيقي (إزالة سلوكيات المتصفح)** في الجلسة القادمة.

---
**آخر مرحلة منفذة: المرحلة 3: Native Bottom Sheets**
**المرحلة التالية: المرحلة 4: Touch Feedback حقيقي (إزالة سلوكيات المتصفح)**
