# سجل تعديل: ترقية الـ Layout والهيدر والسايدبار ونقل موديول فواتير المبيعات إلى Vue 3
* **التاريخ والوقت:** 2026-08-20 01:30
* **الدور المفعل:** Mobile Backend & Frontend UI/UX Specialist
* **الهدف:** إعادة بناء السايدبار والهيدر العلوي بنسق وتنسيق مطابق للنظام القديم ونقل شاشة فواتير المبيعات Invoices/Index & Show إلى Vue 3 + Inertia.js.

## 1. الملفات المعدلة والمنشأة:
* [MODIFIED] backend/resources/js/Layouts/AppLayout.vue - إعادة بناء السايدبار، زر البيع الذهبي F2، الساعة اللحظية بالعربية، مؤشرات الوردية والمخزن، واختصارات الكيبورد.
* [MODIFIED] backend/lang/ar/nav.php & backend/lang/en/nav.php - ترجمة كافة أسماء القوائم والحالات 100%.
* [NEW] backend/app/Http/Controllers/InvoiceController.php - متحكم معالجة فواتير المبيعات وفلاتر البحث والعرض.
* [MODIFIED] backend/app/Http/Resources/InvoiceSummaryResource.php - تزويد بيانات الفاتورة الموسعة للواجهة.
* [NEW] backend/resources/js/Pages/Invoices/Index.vue - شاشة سجل فواتير المبيعات بكافة فلاتر الدفع والبحث والطباعة.
* [NEW] backend/resources/js/Pages/Invoices/Show.vue - شاشة عرض تفاصيل الفاتورة وحساب العميل وطباعة A4 والحراري.
* [MODIFIED] backend/routes/tenant.php & backend/routes/web.php - توجيه مسارات /invoices إلى المتحكم الجديد.

## 2. القرارات التقنية:
* الالتزام بنمط Clean Architecture وفصل التحويل عبر JsonResource.
* الاحتفاظ بكامل ملفات Livewire السابقة دون حذف.
* تفعيل اختصار F2 السريع في عموم النظام.

## 3. التحقق والاختبار:
* [x] تم بناء ملفات Vite بنجاح تام (npm run build).
* [x] تم فحص الروابط والحصول على 200 OK.
