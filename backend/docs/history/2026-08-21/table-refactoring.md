# سجل تعديل: توحيد كافة الجداول في المكون المشترك والديناميكي DataTable.vue
* **التاريخ والوقت:** 2026-08-21 03:50
* **الدور المفعل:** Frontend UI / Full-Stack Agent
* **الهدف:** جرد جميع جداول تطبيق ERP/POS وتوحيدها بالكامل في المكون المشترك `DataTable.vue` وفقاً لأعلى معايير SOLID وClean Architecture وInertia.js مع دعم كامل لـ RTL واللغتين والوضع الداكن والفاتح وتجاوب الشاشات.

## 1. الملفات المعدلة والمنشأة:
* `[NEW]` `backend/resources/js/Components/Common/DataTable.vue` - المكون العام الشامل للجداول الديناميكية.
* `[MODIFIED]` `backend/resources/js/Pages/Invoices/Index.vue` - ترحيل جدول الفواتير.
* `[MODIFIED]` `backend/resources/js/Pages/Expenses/Index.vue` - ترحيل جدول المصروفات.
* `[MODIFIED]` `backend/resources/js/Pages/Customers/Index.vue` - ترحيل جدول العملاء.
* `[MODIFIED]` `backend/resources/js/Pages/Suppliers/Index.vue` - ترحيل جدول الموردين.
* `[MODIFIED]` `backend/resources/js/Pages/Purchases/Index.vue` - ترحيل جدول المشتريات.
* `[MODIFIED]` `backend/resources/js/Pages/Returns/Index.vue` - ترحيل جدول المرتجعات.
* `[MODIFIED]` `backend/resources/js/Pages/StockTransfers/Index.vue` - ترحيل جدول التحويلات المخزنية.
* `[MODIFIED]` `backend/resources/js/Pages/Users/Index.vue` - ترحيل جدول المستخدمين والموظفين.
* `[MODIFIED]` `backend/resources/js/Pages/Trash/Index.vue` - ترحيل سلة المحذوفات.
* `[MODIFIED]` `backend/resources/js/Pages/Stores/Stocks.vue` - ترحيل جدول أرصدة المخزن.
* `[MODIFIED]` `backend/resources/js/Pages/Items/Movements.vue` - ترحيل سجل حركات الصنف.
* `[MODIFIED]` `backend/resources/js/Pages/Customers/Statement.vue` - ترحيل كشف حساب العميل.
* `[MODIFIED]` `backend/resources/js/Pages/Suppliers/Statement.vue` - ترحيل كشف حساب المورد.
* `[MODIFIED]` `backend/resources/js/Pages/Purchases/SmartReorder.vue` - ترحيل إعادة الطلب الذكي وخاصية التحديد المتعدد.
* `[MODIFIED]` `backend/resources/js/Pages/SuperAdmin/Tenants/Index.vue` - ترحيل قائمة المستأجرين في السوبر أدمن.
* `[MODIFIED]` `backend/resources/js/Pages/SuperAdmin/Dashboard.vue` - ترحيل أحدث المستأجرين.
* `[MODIFIED]` `backend/resources/js/Pages/ActivityLogs/Index.vue` - ترحيل سجل النشاطات.
* `[MODIFIED]` `backend/resources/js/Pages/DailyJournal/Index.vue` - ترحيل فواتير ومصروفات اليومية.
* `[MODIFIED]` `backend/resources/js/Components/Dashboard/DashboardRecentInvoices.vue` - ترحيل أحدث فواتير لوحة التحكم.
* `[MODIFIED]` `backend/resources/js/Components/Reports/ReportItemsTab.vue` - ترحيل جدول ربحية الأصناف.
* `[MODIFIED]` `backend/resources/js/Components/Reports/ReportStoresTab.vue` - ترحيل مقارنة الفروع والمخازن.
* `[MODIFIED]` `backend/resources/js/Components/Reports/ReportCustomersTab.vue` - ترحيل تقرير مسحوبات العملاء.
* `[MODIFIED]` `code-review-log.md` - توثيق الجلسة الخامسة.

## 2. القرارات التقنية:
* تصميم الـ Component بنظام الـ Dynamic Scoped Slots (`#cell-[key]`) مما يسمح بتخصيص خلايا معينة بسهولة مع الحفاظ على الهيكل العام.
* دمج ميزة `selectable` و `v-model` لإدارة التحديد الفردي والجماعي (Select All) بسلاسة.
* دمج الترقيم الذكي `Pagination` والتحميل الهيكلي `Skeleton` وحالات الفراغ `EmptyState` تلقائياً.
* الحفاظ على جداول الإدخال التفاعلية الفورية مثل `InvoiceLineItemsTable.vue` كجداول متخصصة لا تتبع النمط العرضي لتفادي التعقيد غير الضروري.

## 3. التحقق والاختبار:
* [x] خلو الكود من الأخطاء ونجاح أمر `npm run build` بنسبة 100%.
* [x] خلو الكود من أي نصوص ثابتة وهندلة ملفات الترجمة للغتين (ar & en).
* [x] التوافق مع شاشات اللمس واللغة العربية RTL والوضعين الفاتح والداكن.
