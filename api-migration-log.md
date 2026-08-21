# سجل التحويل المعماري: Inertia.js إلى Pure API (Laravel API + Vue 3 SPA)

> **تاريخ الإنشاء:** 2026-08-21  
> **الفرع البرمجي (Git Branch):** `feature/api-migration`  
> **الحالة العامة:** المرحلة 4 - Module 02: العملاء وكشوف الحساب (`Customers & Statements`) مكتمل ومختبر بنجاح بنسبة 100%.

---

## 1. فحص وتحليل الوضع الحالي (Current State Audit)

### أ. فحص الـ Controllers الكلي (45 Controllers):
- **Web / Inertia Controllers (24):**
  1. `DashboardController` (لوحة التحكم الرئيسية للمستأجر)
  2. `SuperAdminController` (لوحة السوبر أدمن، إدارة المستأجرين والخطط والاشتراكات)
  3. `POSController` (محرك الكاشير ونقطة البيع السريعة)
  4. `InvoiceController` (إدارة وتعديل وإلغاء واسترجاع فواتير المبيعات)
  5. `ItemController` (الأصناف، كروت الصنف، الأسعار، وتتبع المخزون)
  6. `CustomerController` (بيانات العملاء، المديونيات، وكشوف الحساب التفصيلية)
  7. `SupplierController` (بيانات الموردين، المستحقات، وكشوف الحساب)
  8. `PurchaseController` (فواتير الشراء، التوريدات، وإعادة الطلب الذكي)
  9. `ReturnController` (مرتجعات المبيعات ومرتجعات المشتريات)
  10. `StockTransferController` (التحويلات المخزنية بين الفروع ومنافذ البيع)
  11. `StoreController` (الفروع، المخازن، العربيات، والأرصدة المستقلة)
  12. `DailyJournalController` (دفتر اليومية، جرد الدرج، الخزينة، وإغلاق الورديات)
  13. `ExpenseController` (المصروفات التشغيلية والعهد النثرية)
  14. `CoffeeBlenderController` (توليفات وخلطات البن وحساب تكلفة الطحن والهدر)
  15. `ReportController` (التقارير التحليلية، الأرباح والخسائر، ضريبة القيمة المضافة)
  16. `ReportPrintController` (طباعة التقارير المجمعة وفواتير A4 / Thermal)
  17. `UserController` (إدارة مستخدمي النظام، الكاشيرين، وتعيين الفروع)
  18. `RoleController` (إدارة الأدوار والصلاحيات Spatie Roles & Permissions)
  19. `SettingController` (إعدادات المؤسسة، الهوية، الطابعات، وتفضيلات النظام)
  20. `ActivityLogController` (سجل التدقيق وتتبع أنشطة المستخدمين الحساسة)
  21. `TrashController` (سلة المحذوفات للبيانات واستعادتها)
  22. `ProfileController` (الملف الشخصي، تغيير كلمة المرور، تفضيل المظهر)
  23. `ExportController` (محرك تصدير البيانات إلى Excel و PDF)
  24. `Controller` (Base Controller)

- **Auth Controllers (2):**
  1. `AuthenticatedSessionController` (تسجيل دخول وخروج مستخدمي المستأجر عبر الويب)
  2. `SuperAdminAuthController` (تسجيل دخول وخروج السوبر أدمن المركزي)

- **Api Controllers الحالية في `app/Http/Controllers/Api/` (21):**
  1. `AuthController` (تسجيل الدخول، استعلام me، تسجيل الخروج)
  2. `PermissionApiController` (استعلام مصفوفة وشجرة الصلاحيات والأدوار)
  3. `SystemContextApiController` (سياق النظام الموحد وقاموس الترجمات للـ SPA)
  4. `DashboardApiController` (الملخص اللحظي المالي والتشغيلي)
  5. `StoreController` (إدارة الفروع والمخازن، أرصدة المخازن، التعيينات، وتبديل الفروع)
  6. `CustomerController` (إدارة العملاء، كشف الحساب التفصيلي، تحصيل المديونيات)
  7. `ItemController` (قائمة الأصناف، النواقص، تفاصيل الصنف)
  8. `InvoiceController` (إنشاء وعرض وإلغاء فواتير المبيعات)
  9. `SupplierController` (الموردين، إضافة مورد، كشف الحساب)
  10. `PurchaseController` (المشتريات وإلغاؤها)
  11. `ReturnController` (تسجيل مرتجع مبيعات ومشتريات)
  12. `StockTransferController` (التحويلات بين الفروع)
  13. `ShiftController` (إدارة الورديات، الفتح، الإغلاق، وتقرير Z-Report)
  14. `PaymentController` (سندات القبض للعملاء وسندات الصرف للموردين)
  15. `ExpenseController` (المصروفات)
  16. `TreasuryController` (أرصدة الخزائن والبنوك)
  17. `ReportController` (ملخص الأرباح وأعلى الأصناف مبيعاً)
  18. `SettingController` (إعدادات المنشأة وتحديثها)
  19. `ActivityLogController` (سجل الأنشطة)
  20. `BlenderController` (توليفات البن)
  21. `AppUpdateController` (فحص إصدارات التطبيق وتحميل التحديثات)

---

## 2. القرارات المعمارية المعتمدة (Architecture Decisions)

| البند | القرار المعتمد | السبب والمبررات التقنية |
| :--- | :--- | :--- |
| **نوع الـ Auth للـ API** | **Laravel Sanctum Token-based (`Bearer <token>`)** | يدعم الـ Vue 3 SPA وتطبيق الموبايل (NativePHP / Capacitor) والـ PWA بشكل متطابق من دومين موحد أو دومينات متعددة دون تعقيدات الـ Cross-Site Cookies والـ CORS على مستوى Subdomains المستأجرين. |
| **هيكلة مسارات الـ API** | **Versioned Modular Routes (`/api/v1/...`)** | تقسيم المسارات داخل `routes/api/` إلى:<br>• `v1/auth.php` (المصادقة واستعادة الحساب)<br>• `v1/tenant.php` (المبيعات، المخزون، الحسابات، العملاء...)<br>• `v1/super-admin.php` (إدارة المستأجرين والخطط والاشتراكات) |
| **التعرف على المستأجر (Tenant Resolution)** | **Dual Strategy (Domain/Subdomain + `X-Tenant` Header)** | يتيح للـ SPA العمل بسلاسة سواء فُتح عبر Subdomain خاص بالمستأجر (`tenant1.domain.com`) أو عبر SPA مركزي يمرر هيدر `X-Tenant: tenant1`. |
| **هيكل الاستجابة الموحد (Response Format)** | **Standard JSON Envelope** | **نجاح:**<br>`{ "success": true, "message": "...", "data": {...}, "meta": {...} }`<br>**فشل:**<br>`{ "success": false, "message": "...", "errors": {...} }` |
| **معالجة الأخطاء (Global Error Handling)** | **Unified Exception Handler** | توحيد صياغة أخطاء (401, 403, 404, 422, 500) في `bootstrap/app.php` لترجع دائمًا بصيغة JSON موحدة تفهمها واجهة SPA. |
| **معمارية الـ Frontend** | **Vue 3 SPA مدمج في `resources/js` (Vite + Vue Router + Pinia)** | الاستفادة من كافة الـ Components والـ Composables والـ Tailwind CSS v4 الموجودة حالياً، مع استبدال التوجيه والـ Props بـ Vue Router و Pinia Store و Axios Service. |
| **إدارة الحالة (State Management)** | **Pinia Stores (`resources/js/stores/`)** | إنشاء Stores متخصصة:<br>• `useAuthStore` (المستخدم، الـ Token، الصلاحيات)<br>• `useStoreContextStore` (الفرع النشط وتبديل الفروع)<br>• `useAppConfigStore` (الثيم، إعدادات المنشأة، التنبيهات، والترجمات)<br>• `usePOSCartStore` (إدارة سلة البيع اللحظية) |
| **عميل الـ API الموحد** | **Axios Service Client (`resources/js/services/api.js`)** | يضيف تلقائياً: `Authorization: Bearer <token>`، `X-Store-Id`، `X-Tenant`، `Accept-Language`، مع Interceptor يعالج أخطاء 401 (توجيه لتسجيل الدخول) و 403 (إشعار صلاحيات). |
| **استراتيجية الترحيل (Migration Strategy)** | **ترحيل تدريجي (Module-by-Module) بنظام المحرك المزدوج (Dual-Engine)** | الإبقاء على Inertia بالتوازي مع الـ SPA الجديد لكل موديول، حتى يتم اختبار واستقرار كافة شاشات الـ SPA بنسبة 100%، ثم حذف Inertia في مرحلة التنظيف النهائية. |

---

## 3. خطة وترتيب ترحيل الوحدات (Modules Migration Roadmap)

```text
[المرحلة 0: التخطيط ✅] ──► [المرحلة 1: Auth API ✅] ──► [المرحلة 2: Permissions/Context API ✅] ──► [المرحلة 3: Frontend SPA Core ✅]
                                                                                                        │
┌───────────────────────────────────────────────────────────────────────────────────────────────────────┘
▼
[المرحلة 4: ترحيل الموديولات تدريجياً (Module by Module)]
  ├── 1. الفروع والمخازن (Stores & Stocks) ✅
  ├── 2. العملاء وكشوف الحساب (Customers & Statements) ✅
  ├── 3. الموردين وكشوف الحساب (Suppliers & Statements) ⏳
  ├── 4. المصروفات والعهد (Expenses & Petty Cash)
  ├── 5. الأصناف وحركة المخزون والنواقص (Items & Low Stock Radar)
  ├── 6. الورديات ودفتر اليومية والخزينة (Cash Shifts & Daily Journal)
  ├── 7. المشتريات والتوريدات وإعادة الطلب الذكي (Purchases & Smart Reorder)
  ├── 8. نقطة البيع السريعة وفواتير المبيعات (POS Engine & Invoices)
  ├── 9. المرتجعات (Sales & Purchase Returns)
  ├── 10. التحويلات المخزنية بين الفروع (Stock Transfers)
  ├── 11. توليفات وتصنيع البن (Coffee Blender Engine)
  ├── 12. التقارير المالية والأرباح والخسائر (Reports & Analytics)
  ├── 13. لوحة التحكم والتحليلات اللحظية (Dashboard Analytics)
  ├── 14. المستخدمين والأدوار والأنشطة (Users, Roles & Logs)
  ├── 15. الإعدادات والملف الشخصي وسلة المهملات (Settings, Profile & Trash)
  └── 16. لوحة السوبر أدمن والمستأجرين والاشتراكات (SuperAdmin Dashboard, Tenants & Plans)
▼
[المرحلة 5: التنظيف النهائي وإزالة Inertia.js بالكامل]
```

---

## 4. Auth API — بتاريخ 2026-08-21
* **الملفات المنفذة:** Migrations, `ApiLoginDTO`, `ApiLoginRequest`, `ApiLoginAction`, `ApiLogoutAction`, `ApiMeAction`, `ResolveApiTenancy`, `UserResource`, `AuthController`.
* **الاختبارات:** `AuthApiTest` (7/7 ناجحة).
* **الحالة:** ✅ مكتملة.

---

## 5. Permissions & System Context API — بتاريخ 2026-08-21
* **الملفات المنفذة:** `GetPermissionsTreeAction`, `GetTranslationsAction`, `GetSystemContextAction`, `PermissionApiController`, `SystemContextApiController`, Routes & Exception handlers.
* **الاختبارات:** `PermissionsAndContextApiTest` (5/5 ناجحة).
* **الحالة:** ✅ مكتملة.

---

## 6. Frontend Core Infrastructure — بتاريخ 2026-08-21
* **الملفات المنفذة:** `resources/js/services/api.js`, `resources/js/stores/auth.js`, `resources/js/stores/appConfig.js`, `resources/js/router/index.js`, `resources/js/App.vue`, `resources/js/Layouts/SpaLayout.vue`, `resources/js/views/Auth/LoginView.vue`, `resources/js/views/DashboardView.vue`, `resources/js/spa.js`, `resources/views/spa.blade.php`.
* **الاختبارات:** `SpaInfrastructureTest` (2/2 ناجحة) و `npm run build` ناجح.
* **الحالة:** ✅ مكتملة.

---

## 7. Module 01: الفروع والمخازن والأرصدة (`Stores & Stocks`) — بتاريخ 2026-08-21
* **الملفات المنفذة:** `StoreDTO`, `CreateStoreAction`, `UpdateStoreAction`, `DeleteStoreAction`, `ToggleStoreActiveAction`, `AssignStoreUsersAction`, `GetStoreStocksAction`, `StoreResource`, `StoreStockResource`, `StoreController`, `StoresView.vue`, `StoreStocksView.vue`.
* **الاختبارات:** `StoresApiTest` (9/9 ناجحة).
* **الحالة:** ✅ مكتملة.

---

## 8. Module 02: العملاء وكشوف الحساب التفصيلية (`Customers & Statements`) — بتاريخ 2026-08-21

### أ. الـ Backend (Laravel Pure API):
1. `[NEW]` `app/DTOs/Customers/CustomerDTO.php` (Strictly Typed DTO لبيانات العملاء).
2. `[NEW]` `app/DTOs/Customers/CollectCustomerPaymentDTO.php` (Strictly Typed DTO لتحصيل سداد المديونيات).
3. `[NEW]` `app/Actions/Customers/CreateCustomerAction.php` (Single Action لإنشاء العميل وإثبات الرصيد الافتتاحي).
4. `[NEW]` `app/Actions/Customers/UpdateCustomerAction.php` (Single Action لتعديل بيانات العميل).
5. `[NEW]` `app/Actions/Customers/DeleteCustomerAction.php` (Single Action لحذف العميل مع فحص موانع الحذف والمديونيات).
6. `[NEW]` `app/Actions/Customers/ToggleCustomerActiveAction.php` (Single Action لتفعيل / تعطيل حساب العميل).
7. `[NEW]` `app/Actions/Customers/CollectCustomerPaymentAction.php` (Single Action لتحصيل السداد وربطه بـ PaymentService).
8. `[NEW]` `app/Actions/Customers/GetCustomerStatementAction.php` (Single Action لتوليد كشف الحساب التفصيلي وحساب الرصيد اللحظي التراكمي بدقة bcmath).
9. `[NEW]` `app/Http/Resources/CustomerResource.php` (تنسيق بيانات العميل، إحصائيات الفواتير والسدادات، وموانع الحذف).
10. `[REFACTORED]` `app/Http/Controllers/Api/CustomerController.php` (متحكم API متكامل للعملاء وكشف الحساب والتحصيل).
11. `[MODIFIED]` `routes/api.php` (تسجيل مسارات العملاء والتحصيل وكشف الحساب).
12. `[NEW]` `tests/Feature/Api/CustomersApiTest.php` (حزمة 7 اختبارات Feature شاملة لكافة العمليات بنسبة نجاح 100%).

### ب. الـ Frontend (Pure Vue 3 SPA):
1. `[NEW]` `resources/js/views/Customers/CustomersView.vue` (شاشة إدارة العملاء، بطاقات المؤشرات المالية، فلاتر المديونيات، نوافذ الإضافة والتعديل والتحصيل السريع).
2. `[NEW]` `resources/js/views/Customers/CustomerStatementView.vue` (شاشة كشف الحساب التفصيلي مع الفلاتر الزمنية السريعة والطباعة والرصيد التراكمي).
3. `[MODIFIED]` `resources/js/router/index.js` (تسجيل مسارات `/customers` و `/customers/:id/statement` مع حماية الصلاحيات).
4. `[MODIFIED]` `resources/js/Layouts/SpaLayout.vue` (ربط زر العملاء في القائمة الجانبية بالـ Router).

### ج. نقاط النهاية المعتمدة (API Endpoints):
* `GET /api/v1/customers` — قائمة العملاء مع الفلاتر والبحث والمؤشرات المالية
* `POST /api/v1/customers` — إضافة عميل جديد
* `GET /api/v1/customers/{id}` — تفاصيل بروفايل عميل
* `PUT /api/v1/customers/{id}` — تعديل بيانات العميل
* `DELETE /api/v1/customers/{id}` — حذف العميل (مع الحماية من حذف العملاء ذوي المديونيات)
* `PATCH /api/v1/customers/{id}/toggle-active` — تفعيل / تعطيل العميل
* `POST /api/v1/customers/{id}/collect-payment` — تسجيل سند قبض وتحصيل مديونية
* `GET /api/v1/customers/{id}/statement` — كشف الحساب التفصيلي والرصيد التراكمي

### حالة الموديول: ✅ مكتمل بنجاح 100%

---

## 9. مصفوفة تتبع حالة الترحيل (Migration Tracking Matrix)

- [x] **المرحلة 0: التخطيط والقرارات المعمارية (Architectural Planning & Log Setup)** ✅ (2026-08-21)
- [x] **المرحلة 1: بناء Auth API (Backend) - Sanctum Tokens & Authentication Service** ✅ (2026-08-21)
- [x] **المرحلة 2: بناء Permissions & System Context API (Backend)** ✅ (2026-08-21)
- [x] **المرحلة 3: إعداد الـ Frontend Core (Vue Router + Pinia + API Client + Guards + Login)** ✅ (2026-08-21)
- [ ] **المرحلة 4: تحويل الموديولات تدريجياً (Module by Module):**
  - [x] **Module 01: الفروع والمخازن (`Stores & Stocks`)** ✅ (2026-08-21)
  - [x] **Module 02: العملاء وكشوف الحساب (`Customers & Statements`)** ✅ (2026-08-21)
  - [ ] **Module 03: الموردين وكشوف الحساب (`Suppliers & Statements`)** ⏳ (التالي في الترتيب)
  - [ ] Module 04: المصروفات والعهد النثرية (`Expenses & Petty Cash`)
  - [ ] Module 05: الأصناف وحركات المخزون والنواقص (`Items & Stock Movements`)
  - [ ] Module 06: الورديات ودفتر اليومية والخزينة (`Shifts & Daily Journal`)
  - [ ] Module 07: المشتريات والتوريد وإعادة الطلب الذكي (`Purchases & Smart Reorder`)
  - [ ] Module 08: نقطة البيع السريعة والفواتير (`POS & Sales Invoices`)
  - [ ] Module 09: مرتجعات المبيعات والمشتريات (`Returns`)
  - [ ] Module 10: التحويلات المخزنية بين الفروع (`Stock Transfers`)
  - [ ] Module 11: توليفات البن والتصنيع (`Coffee Blender Engine`)
  - [ ] Module 12: التقارير المالية والأرباح والخسائر (`Reports & Profit Analytics`)
  - [ ] Module 13: لوحة التحكم والتحليلات اللحظية (`Dashboard Analytics`)
  - [ ] Module 14: إدارة المستخدمين والأدوار والأنشطة (`Users, Roles & Logs`)
  - [ ] Module 15: الإعدادات والملف الشخصي وسلة المهملات (`Settings, Profile & Trash`)
  - [ ] Module 16: لوحة تحكم السوبر أدمن والمستأجرين (`SuperAdmin Dashboard, Tenants & Plans`)
- [ ] **المرحلة 5: التنظيف النهائي وإزالة حزم وأكواد Inertia.js بالكامل**

---
**آخر مرحلة مكتملة:** المرحلة 4 — Module 02: العملاء وكشوف الحساب (`Customers & Statements`)  
**الموديول التالي المستهدف:** **Module 03: الموردين وكشوف الحساب (`Suppliers & Statements`)**
