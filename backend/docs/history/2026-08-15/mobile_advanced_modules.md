# سجل تعديل: إطلاق موديولات الفروع، الأصناف، كاشير POS، الفواتير، الواتساب، السندات والخزينة
* **التاريخ والوقت:** 2026-08-15 16:33
* **الدور المفعل:** Mobile Backend & NativePHP UI/UX Specialist
* **الهدف:** تنفيذ مصفوفة العمليات الكاملة لتطبيق الموبايل "سرور كوفي ERP": صلاحيات وتبديل الفروع، أصناف وخامات البن، نقاط البيع السريعة، إرسال الفواتير عبر واتساب، سندات القبض والصرف، وحركة الخزينة والتقارير.

## 1. الملفات المعدلة والجديدة:
* `[NEW]` `backend/app/Http/Controllers/Api/StoreController.php` - جلب الفروع المصرح بها وتبديل الفرع النشط.
* `[NEW]` `backend/app/Http/Controllers/Api/ItemController.php` - جلب الأصناف والرصيد المخزني بالفرع النشط.
* `[NEW]` `backend/app/Http/Controllers/Api/InvoiceController.php` - إنشاء الفواتير بالقفل السطري وتوليد رابط ورسالة الواتساب.
* `[NEW]` `backend/app/Http/Controllers/Api/PaymentController.php` - سندات القبض (تحصيل ديون) وسندات الصرف (سداد موردين).
* `[NEW]` `backend/app/Http/Controllers/Api/TreasuryController.php` - حركة الصندوق، رصيد الخزينة، ومؤشرات السوق.
* `[NEW]` `mobile/resources/js/Components/BranchSwitcherModal.vue` - نافذة اختيار وتبديل الفرع النشط.
* `[NEW]` `mobile/resources/js/Pages/Items/Index.vue` - دليل أصناف وخامات البن والمخزون بالفرع.
* `[NEW]` `mobile/resources/js/Pages/POS/Index.vue` - شاشة كاشير الموبايل السريعة (POS) وسلة المشتريات.
* `[NEW]` `mobile/resources/js/Pages/Invoices/Index.vue` - سجل فواتير المبيعات.
* `[NEW]` `mobile/resources/js/Pages/Invoices/Show.vue` - تفاصيل الفاتورة، الإيصال الحراري وزر WhatsApp المباشر.
* `[NEW]` `mobile/resources/js/Pages/Payments/Index.vue` - إدارة سندات القبض والصرف.
* `[NEW]` `mobile/resources/js/Pages/Treasury/Index.vue` - حركة الخزينة وصافي التدفق النقدي اليومي.
* `[MODIFIED]` `mobile/resources/js/Layouts/MobileLayout.vue` - شريط تنقل خماسي وزر الفرع في الهيدر.
* `[MODIFIED]` `mobile/resources/js/Components/SideMenu.vue` - ربط كافة الأقسام الجديدة وزر تبديل الفرع.

## 2. القرارات التقنية:
* اعتماد الدقة المالية الكاملة `DECIMAL(12,3)` ومعالجات `bcmath`.
* حصر ظهور الفروع للمستخدمين وفق صلاحياتهم (أو كافة الفروع للمدير العام).
* دعم مشاركة الفاتورة عبر رابط WhatsApp المباشر `https://wa.me/{phone}?text=...`.

## 3. التحقق والاختبار:
* [x] خلو الكود من الأخطاء وبناء Vite بنجاح في `1.20s`.
* [x] اجتياز فحص التكامل `test_modules_flow.php` بنسبة 100%.
* [x] التوافق مع شاشات اللمس واللغة العربية RTL والوضعين الفاتح والداكن.
