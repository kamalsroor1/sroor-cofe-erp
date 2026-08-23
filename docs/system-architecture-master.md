# 🏛️ الدليل الماستر لهيكل وتوثيق منظومة "سرور كوفي ERP & POS"

> **المرجع المركزي الموحد والشامل لكافة وحدات، صفحات، ومكونات النظام.**

---

## 🧭 خريطة الموديولات والصفحات وحالة التدقيق (Audit Progress Matrix):

| الموديول | الصفحة | المسار (Route) | ملف التوثيق التفصيلي | حالة التدقيق (4 المحاور) |
|---|---|---|---|---|
| **لوحة القيادة** | لوحة القيادة والمؤشرات | `/dashboard` | [dashboard.md](./pages/dashboard.md) | ✅ مكتمل (100%) |
| **نقاط البيع** | شاشة البيع السريع (POS) | `/pos` | [pos.md](./pages/pos.md) | ✅ مكتمل (100%) |
| **المبيعات** | سجل فواتير المبيعات | `/invoices` | [invoices.md](./pages/invoices.md) | ✅ مكتمل (100%) |
| **المخزون** | دليل الأصناف والمخزون الحي | `/items` | [items.md](./pages/items.md) | ✅ مكتمل (100%) |
| **المخزون** | فئات وتصنيفات المنتجات | `/categories` | [categories.md](./pages/categories.md) | ✅ مكتمل (100%) |
| **المخزون** | التحويلات المخزنية بين الفروع | `/stock-transfers` | [stock-transfers.md](./pages/stock-transfers.md) | ✅ مكتمل (100%) |
| **المخزون** | الفروع والمخازن | `/stores` | [stores.md](./pages/stores.md) | ✅ مكتمل (100%) |
| **المخزون** | حاسبة خلطات وتكاليف البن | `/coffee-blender` | `docs/pages/coffee-blender.md` | ⏳ مجدول |
| **المخزون** | مساعد المشتريات والطلب الذكي | `/smart-reorder` | `docs/pages/smart-reorder.md` | ⏳ مجدول |
| **المشتريات** | سجل فواتير المشتريات | `/purchases` | `docs/pages/purchases.md` | ⏳ مجدول |
| **المشتريات** | دليل الموردين وكشف الحساب | `/suppliers` | `docs/pages/suppliers.md` | ⏳ مجدول |
| **العملاء** | دليل العملاء والمديونيات | `/customers` | `docs/pages/customers.md` | ⏳ مجدول |
| **المرتجعات** | مرتجعات المبيعات والمشتريات | `/returns` | `docs/pages/returns.md` | ⏳ مجدول |
| **الخزينة** | المصروفات ومراكز التكلفة | `/expenses` | `docs/pages/expenses.md` | ⏳ مجدول |
| **الخزينة** | دفتر اليومية وحركة الصندوق | `/daily-journal` | `docs/pages/daily-journal.md` | ⏳ مجدول |
| **التقارير** | مركز التقارير المالية والضريبية | `/reports` | `docs/pages/reports.md` | ⏳ مجدول |
| **الإدارة** | إدارة المستخدمين والكاشيرات | `/users` | `docs/pages/users.md` | ⏳ مجدول |
| **الإدارة** | الأدوار ومصفوفة الصلاحيات | `/roles` | `docs/pages/roles.md` | ⏳ مجدول |
| **الإدارة** | سجل النشاطات والرقابة | `/activity-logs` | `docs/pages/activity-logs.md` | ⏳ مجدول |
| **الإدارة** | سلة المهملات واسترجاع السجلات | `/trash` | `docs/pages/trash.md` | ⏳ مجدول |
| **الإدارة** | إعدادات المؤسسة والفاتورة | `/settings` | `docs/pages/settings.md` | ⏳ مجدول |
| **المنصة** | لوحة تحكم السوبر أدمن | `/super-admin` | `docs/pages/super-admin.md` | ⏳ مجدول |
| **المنصة** | إدارة المستأجرين والفروع | `/super-admin/tenants` | `docs/pages/tenants.md` | ⏳ مجدول |
| **المنصة** | الباقات والاشتراكات | `/super-admin/plans` | `docs/pages/plans.md` | ⏳ مجدول |
| **المنصة** | إصدارات تطبيق الموبايل | `/super-admin/app-versions` | `docs/pages/app-versions.md` | ⏳ مجدول |

---

## 📁 هيكلية ملفات التوثيق في المشروع:
```text
docs/
├── system-architecture-master.md   <-- الدليل الماستر المجمع الشامل لكامل المنظومة
├── full-page-review-log.md         <-- سجل المراجعة اليومي الشامل (4 محاور)
├── PAGE_AUDIT_PROMPT.md            <-- برومبت ومعيار المراجعة والتدقيق الإلزامي
├── pages/                          <-- المستوى 1: ملف توثيق تفصيلي لكل شاشة
│   ├── dashboard.md
│   ├── pos.md
│   ├── invoices.md
│   ├── items.md
│   └── categories.md
└── modules/                        <-- المستوى 2: ملف توثيق لكل موديول
    ├── pos.md
    ├── sales.md
    └── inventory.md
```
