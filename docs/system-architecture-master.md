# 🏛️ الدليل الماستر لهيكل وتوثيق منظومة "سرور كوفي ERP & POS"

> **المرجع المركزي الموحد والشامل لكافة وحدات، صفحات، ومكونات النظام.**
> **الحالة العامة للمنظومة:** ✅ **100% مكتملة ومراجعة وموثقة بنجاح عبر المحاور الأربعة المتزامنة.**

---

## 🧭 خريطة الموديولات والصفحات وحالة التدقيق (Audit Progress Matrix - 100% Complete):

| # | الموديول | الصفحة | المسار (Route) | ملف التوثيق التفصيلي | حالة التدقيق (4 المحاور) |
|---|---|---|---|---|---|
| 1 | **المبيعات** | سجل فواتير المبيعات | `/invoices` | [invoices.md](./pages/invoices.md) | ✅ مكتمل (100%) |
| 2 | **نقاط البيع** | شاشة البيع السريع (POS) | `/pos` | [pos.md](./pages/pos.md) | ✅ مكتمل (100%) |
| 3 | **المخزون** | دليل الأصناف والمخزون الحي | `/items` | [items.md](./pages/items.md) | ✅ مكتمل (100%) |
| 4 | **المخزون** | فئات وتصنيفات المنتجات | `/categories` | [categories.md](./pages/categories.md) | ✅ مكتمل (100%) |
| 5 | **المخزون** | التحويلات المخزنية بين الفروع | `/stock-transfers` | [stock-transfers.md](./pages/stock-transfers.md) | ✅ مكتمل (100%) |
| 6 | **المخزون** | الفروع والمخازن | `/stores` | [stores.md](./pages/stores.md) | ✅ مكتمل (100%) |
| 7 | **المخزون** | حاسبة خلطات وتكاليف البن | `/coffee-blender` | [coffee-blender.md](./pages/coffee-blender.md) | ✅ مكتمل (100%) |
| 8 | **المخزون** | مساعد المشتريات والطلب الذكي | `/purchases/smart-reorder` | [smart-reorder.md](./pages/smart-reorder.md) | ✅ مكتمل (100%) |
| 9 | **المخزون** | رصيد الأصناف بالمخازن | `/stores/stocks` | [store-stocks.md](./pages/store-stocks.md) | ✅ مكتمل (100%) |
| 10 | **المخزون** | إنشاء تحويل مخزني | `/stock-transfers/create` | [create-stock-transfer.md](./pages/create-stock-transfer.md) | ✅ مكتمل (100%) |
| 11 | **المخزون** | حركات الصنف وسجل التتبع | `/items/:id/movements` | [item-movements.md](./pages/item-movements.md) | ✅ مكتمل (100%) |
| 12 | **المشتريات** | سجل فواتير المشتريات والتوريد | `/purchases` | [purchases.md](./pages/purchases.md) | ✅ مكتمل (100%) |
| 13 | **المشتريات** | إنشاء فاتورة مشتريات | `/purchases/create` | [create-purchase.md](./pages/create-purchase.md) | ✅ مكتمل (100%) |
| 14 | **المشتريات** | دليل الموردين | `/suppliers` | [suppliers.md](./pages/suppliers.md) | ✅ مكتمل (100%) |
| 15 | **المشتريات** | كشف حساب المورد | `/suppliers/:id/statement` | [supplier-statement.md](./pages/supplier-statement.md) | ✅ مكتمل (100%) |
| 16 | **العملاء** | دليل العملاء والمديونيات | `/customers` | [customers.md](./pages/customers.md) | ✅ مكتمل (100%) |
| 17 | **العملاء** | كشف حساب العميل | `/customers/:id/statement` | [customer-statement.md](./pages/customer-statement.md) | ✅ مكتمل (100%) |
| 18 | **الخزينة** | المصروفات ومراكز التكلفة | `/expenses` | [expenses.md](./pages/expenses.md) | ✅ مكتمل (100%) |
| 19 | **الخزينة** | دفتر اليومية وحركة الصندوق | `/daily-journal` | [daily-journal.md](./pages/daily-journal.md) | ✅ مكتمل (100%) |
| 20 | **التقارير** | مركز التقارير المالية والضريبية | `/reports` | [reports.md](./pages/reports.md) | ✅ مكتمل (100%) |
| 21 | **الإدارة** | إدارة المستخدمين والموظفين | `/users` | [users.md](./pages/users.md) | ✅ مكتمل (100%) |
| 22 | **الإدارة** | الأدوار ومصفوفة الصلاحيات | `/roles` | [roles.md](./pages/roles.md) | ✅ مكتمل (100%) |
| 23 | **الإدارة** | سجل النشاطات والتدقيق الأمني | `/activity-logs` | [activity-logs.md](./pages/activity-logs.md) | ✅ مكتمل (100%) |
| 24 | **الإدارة** | سلة المحذوفات والاسترجاع الآمن | `/trash` | [trash.md](./pages/trash.md) | ✅ مكتمل (100%) |
| 25 | **الإدارة** | إعدادات النظام والتحكم الشامل | `/settings` | [settings.md](./pages/settings.md) | ✅ مكتمل (100%) |

---

## 📁 هيكلية ملفات التوثيق المكتملة في المشروع:
```text
docs/
├── system-architecture-master.md   <-- الدليل الماستر المجمع الشامل لكامل المنظومة (25 صفحة)
├── full-page-review-log.md         <-- سجل المراجعة اليومي الشامل للمنظومة (4 محاور)
├── PAGE_AUDIT_PROMPT.md            <-- برومبت ومعيار المراجعة والتدقيق الإلزامي
├── pages/                          <-- المستوى 1: ملفات توثيق تفصيلية مستقلة لكافة الشاشات (25 ملف)
└── history/                        <-- سجلات التاريخ اليومية للتدقيق والمراجعة
```
