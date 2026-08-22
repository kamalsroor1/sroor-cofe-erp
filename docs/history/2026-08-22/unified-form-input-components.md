# سجل تعديل: توحيد مكونات الإدخال Form Inputs عبر كامل النظام
* **التاريخ والوقت:** 2026-08-22 22:36
* **الدور المفعل:** Frontend UI & Code Quality Agent
* **الهدف:** توحيد كافة عناصر ونماذج الإدخال (Inputs, Selects, Checkboxes, Textareas, DatePickers, Searches) في حزمة components مشتركة مبنية بـ Vue 3 و Tailwind CSS لدعم الـ Validation والأخطاء وحجم الخط واللمس على الموبايل ونظام الألوان الديناميكي.

## 1. الملفات المعدلة والمضافة:
* `[NEW]` `backend/resources/js/Components/Form/BaseInput.vue` - حقل إدخال نصوص وكلمات مرور وأرقام هواتف مع زر إظهار/إخفاء تلقائي وزر مسح سريع وربط Accessibility.
* `[NEW]` `backend/resources/js/Components/Form/BaseNumberInput.vue` - حقل أرقام مالية مع `inputmode="decimal"` وأزرار stepper ورمز العملة `ج.م`.
* `[NEW]` `backend/resources/js/Components/Form/BaseSelect.vue` - قائمة منسدلة ذكية تدعم التصفية المحلية والبحث اللحظي Remote API Search مع Debounce و AbortController.
* `[NEW]` `backend/resources/js/Components/Form/BaseTextarea.vue` - حقل نصوص متعدد الأسطر مع عداد أحرف حي.
* `[NEW]` `backend/resources/js/Components/Form/BaseCheckbox.vue` - مربع اختيار بمساحة لمس مريحة للشاشات اللمسية (>= 44px) وألوان الثيم المختار.
* `[NEW]` `backend/resources/js/Components/Form/BaseRadioGroup.vue` - بطاقات خيارات راديو لمسية.
* `[NEW]` `backend/resources/js/Components/Form/BaseSwitch.vue` - مفتاح تبديل Toggle تفاعلي سلس.
* `[NEW]` `backend/resources/js/Components/Form/BaseSearchInput.vue` - شريط بحث موحد مع أيقونة البحث وزر مسح فوري.
* `[NEW]` `backend/resources/js/Components/Form/BaseFileUpload.vue` - رفع الملفات بالسحب والإفلات ومعاينة الصور الحية.
* `[NEW]` `backend/resources/js/Components/Form/BaseDatePicker.vue` - تقويم وتاريخ موحد مع دعم كامل للـ RTL والوضع الليلي.
* `[MODIFIED]` ترقية واستبدال المدخلات في:
  - `views/ActivityLogs/ActivityLogsView.vue`
  - `views/Auth/LoginView.vue`
  - `Components/Items/ItemFormModal.vue`
  - `views/Invoices/InvoicesView.vue`
  - `views/Expenses/ExpensesView.vue`
  - `views/Customers/CustomersView.vue`
  - `views/Suppliers/SuppliersView.vue`
  - `views/DailyJournal/DailyJournalView.vue`
  - `views/Stores/StoresView.vue`
  - `views/Stores/StoreStocksView.vue`
  - `views/Users/UsersView.vue`
  - `views/Purchases/PurchasesView.vue`
  - `views/Returns/ReturnsView.vue`
  - `views/StockTransfers/StockTransfersView.vue`
  - `views/Roles/RolesView.vue`
  - `views/Trash/TrashView.vue`
  - `views/Items/ItemMovementsView.vue`
  - `Components/POS/POSQuickCustomerModal.vue`
  - `views/SuperAdmin/SuperAdminTenantsView.vue`
  - `views/SuperAdmin/SuperAdminUnitsView.vue`
  - `Components/Settings/BrandingTab.vue`
  - `Components/Settings/TelegramTab.vue`

## 2. القرارات التقنية:
* فرض `defineModel()` لضمان الاتصال ثنائي الاتجاه بدون أخطاء.
* توحيد حجم الخط ليصبح 16px للموبايل لتجنب الـ iOS Safari auto-zoom المزعج.
* ارتفاع الحقول لا يقل عن 44px لتوفير أقصى سهولة في شاشات اللمس ونقاط البيع POS.
* تكامل تام مع متغيرات ألوان الثيم وتجربة المستخدم Dark/Light Mode.

## 3. التحقق والاختبار:
* [x] خلو الكود من الأخطاء وبناء Vite بنجاح تام لـ 1877 module.
* [x] خلو الكود من أي نصوص ثابتة واستخدام دوال الترجمة الرسمية `trans(...)` و `$t(...)`.
* [x] التوافق مع شاشات اللمس واللغة العربية RTL والوضعين الفاتح والداكن.
* [x] النشر التلقائي بنجاح على الخادم المباشر `baraa-solutions.com`.