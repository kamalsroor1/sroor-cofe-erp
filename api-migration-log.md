# سجل التحويل المعماري: Inertia.js إلى Pure API (Laravel API + Vue 3 SPA)

> **تاريخ الإنشاء:** 2026-08-21  
> **الفرع البرمجي (Git Branch):** `feature/api-migration`  
> **الحالة العامة:** المرحلة 4 - Module 04: المصروفات والعهد النثرية وتصنيفاتها (`Expenses & Petty Cash`) مكتمل ومختبر بنجاح بنسبة 100%.

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
  7. `SupplierController` (إدارة الموردين، كشف الحساب التفصيلي، وسندات صرف مستحقات الموردين)
  8. `ExpenseController` (تسجيل وإدارة المصروفات والعهد ومراكز التكلفة)
  9. `ItemController` (قائمة الأصناف، النواقص، تفاصيل الصنف)
  10. `InvoiceController` (إنشاء وعرض وإلغاء فواتير المبيعات)
  11. `PurchaseController` (المشتريات وإلغاؤها)
  12. `ReturnController` (تسجيل مرتجع مبيعات ومشتريات)
  13. `StockTransferController` (التحويلات بين الفروع)
  14. `ShiftController` (إدارة الورديات، الفتح، الإغلاق، وتقرير Z-Report)
  15. `PaymentController` (سندات القبض للعملاء وسندات الصرف للموردين)
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
  ├── 3. الموردين وكشوف الحساب (Suppliers & Statements) ✅
  ├── 4. المصروفات والعهد (Expenses & Petty Cash) ✅
  ├── 5. الأصناف وحركة المخزون والنواقص (Items & Low Stock Radar) ⏳
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
* **الملفات المنفذة:** `CustomerDTO`, `CollectCustomerPaymentDTO`, `CreateCustomerAction`, `UpdateCustomerAction`, `DeleteCustomerAction`, `ToggleCustomerActiveAction`, `CollectCustomerPaymentAction`, `GetCustomerStatementAction`, `CustomerResource`, `CustomerController`, `CustomersView.vue`, `CustomerStatementView.vue`.
* **الاختبارات:** `CustomersApiTest` (7/7 ناجحة).
* **الحالة:** ✅ مكتملة.

---

## 9. Module 03: الموردين وكشوف الحساب وسندات الصرف (`Suppliers & Statements`) — بتاريخ 2026-08-21
* **الملفات المنفذة:** `SupplierDTO`, `PaySupplierDTO`, `CreateSupplierAction`, `UpdateSupplierAction`, `DeleteSupplierAction`, `ToggleSupplierActiveAction`, `PaySupplierAction`, `GetSupplierStatementAction`, `SupplierResource`, `SupplierController`, `SuppliersView.vue`, `SupplierStatementView.vue`.
* **الاختبارات:** `SuppliersApiTest` (7/7 ناجحة).
* **الحالة:** ✅ مكتملة.

---

## 10. Module 04: المصروفات والعهد النثرية وتصنيفاتها (`Expenses & Petty Cash`) — بتاريخ 2026-08-21

### أ. الـ Backend (Laravel Pure API):
1. `[NEW]` `app/DTOs/Expenses/ExpenseDTO.php` (Strictly Typed DTO لبيانات المصروفات والعهد).
2. `[NEW]` `app/Actions/Expenses/CreateExpenseAction.php` (Single Action لإنشاء المصروف وتوليد الكود التسلسلي EXP-ymd-0001 داخل Transaction).
3. `[NEW]` `app/Actions/Expenses/UpdateExpenseAction.php` (Single Action لتعديل بيانات المصروف).
4. `[NEW]` `app/Actions/Expenses/DeleteExpenseAction.php` (Single Action لحذف المصروف مع دعم الحذف الناعم SoftDeletes).
5. `[NEW]` `app/Actions/Expenses/GetExpensesSummaryAction.php` (Single Action لحساب إجمالي مصروفات الشهر، المصروفات النقدية، وإجمالي الفلترة بدقة bcmath).
6. `[NEW]` `app/Http/Resources/ExpenseResource.php` (تنسيق بيانات المصروف ومراكز التكلفة).
7. `[REFACTORED]` `app/Http/Controllers/Api/ExpenseController.php` (متحكم API متكامل للمصروفات مع الفلاتر والمؤشرات المالية).
8. `[MODIFIED]` `routes/api.php` (تسجيل مسارات المصروفات).
9. `[NEW]` `tests/Feature/Api/ExpensesApiTest.php` (حزمة 5 اختبارات Feature شاملة بنسبة نجاح 100%).

### ب. الـ Frontend (Pure Vue 3 SPA):
1. `[NEW]` `resources/js/views/Expenses/ExpensesView.vue` (شاشة إدارة المصروفات، بطاقات إجمالي مصروفات الشهر والنثريات النقدية، الفلترة حسب مركز التكلفة والتاريخ، والشرائح السريعة).
2. `[MODIFIED]` `resources/js/router/index.js` (تسجيل مسار `/expenses` مع حماية الصلاحيات).
3. `[MODIFIED]` `resources/js/Layouts/SpaLayout.vue` (إضافة رابط المصروفات والعهد في القائمة الجانبية).

### ج. نقاط النهاية المعتمدة (API Endpoints):
* `GET /api/v1/expenses` — قائمة المصروفات مع الفلاتر والبحث والمؤشرات المالية
* `POST /api/v1/expenses` — تسجيل مصروف جديد
* `GET /api/v1/expenses/{id}` — تفاصيل مصروف محدد
* `PUT /api/v1/expenses/{id}` — تعديل مصروف
* `DELETE /api/v1/expenses/{id}` — حذف مصروف

### حالة الموديول: ✅ مكتمل بنجاح 100%

---

## 11. Module 05: الأصناف وحركات المخزون والنواقص (`Items & Stock Movements`) — بتاريخ 2026-08-21

### أ. الـ Backend (Laravel Pure API):
1. `[NEW]` `app/DTOs/Items/ItemDTO.php` (Strictly Typed DTO لبيانات الصنف والأسعار وحدود المخزون).
2. `[NEW]` `app/DTOs/Items/AdjustStockDTO.php` (Strictly Typed DTO لتسويات وجرد المخزون والهدر).
3. `[NEW]` `app/Actions/Items/CreateItemAction.php` (Single Action لإنشاء الصنف والباركود وتهيئة أرصدة الفروع تلقائياً داخل Transaction).
4. `[NEW]` `app/Actions/Items/UpdateItemAction.php` (Single Action لتعديل بيانات الصنف).
5. `[NEW]` `app/Actions/Items/DeleteItemAction.php` (Single Action لحذف الصنف مع فحص الموانع التشغيلية).
6. `[NEW]` `app/Actions/Items/ToggleItemActiveAction.php` (Single Action لتفعيل وتعطيل الصنف).
7. `[NEW]` `app/Actions/Items/AdjustItemStockAction.php` (Single Action لتسوية المخزون مع قفل سطري `lockForUpdate` وحساب `bcmath` وتسجيل حركة المخزون `StockMovement`).
8. `[NEW]` `app/Actions/Items/GetItemMovementsAction.php` (Single Action لكشف حركات المخزون وحساب مجاميع الوارد والمنصرف وصافي الحركة).
9. `[NEW]` `app/Http/Resources/ItemResource.php` (تنسيق كروت الأصناف وأسعار وأرصدة الفروع).
10. `[NEW]` `app/Http/Resources/StockMovementResource.php` (تنسيق سجلات حركات المخزون للـ API).
11. `[REFACTORED]` `app/Http/Controllers/Api/ItemController.php` (متحكم API متكامل للأصناف والتسويات ورادار النواقص).
12. `[MODIFIED]` `routes/api.php` (تسجيل مسارات الأصناف، النواقص، التسويات، وكشف الحركات).
13. `[NEW]` `tests/Feature/Api/ItemsApiTest.php` (حزمة 9 اختبارات Feature شاملة بنسبة نجاح 100%).

### ب. الـ Frontend (Pure Vue 3 SPA):
1. `[NEW]` `resources/js/views/Items/ItemsView.vue` (شاشة كروت الأصناف، التقييم المالي للمخزون، فلتر النواقص ورصيد الصفر، نافذة الإضافة والتعديل، ونافذة التسوية المخزنية السريعة).
2. `[NEW]` `resources/js/views/Items/ItemMovementsView.vue` (شاشة كشف حركات الصنف التفصيلي، بطاقات إجمالي الوارد والمنصرف وصافي الرصيد، فلتر الفروع والفترات الزمنية مع الطباعة).
3. `[MODIFIED]` `resources/js/router/index.js` (تسجيل مسارات `/items` و `/items/:id/movements`).
4. `[MODIFIED]` `resources/js/Layouts/SpaLayout.vue` (تحويل رابط الأصناف والمخزون إلى router-link نشط).

### ج. نقاط النهاية المعتمدة (API Endpoints):
* `GET /api/v1/items` — قائمة الأصناف مع الفلاتر والبحث والمؤشرات المالية
* `POST /api/v1/items` — إضافة صنف جديد
* `GET /api/v1/items/low-stock` — رادار الأصناف الحرجة والنواقص
* `GET /api/v1/items/{id}` — تفاصيل صنف محدد
* `PUT /api/v1/items/{id}` — تعديل صنف
* `DELETE /api/v1/items/{id}` — حذف صنف (مع فحص الموانع)
* `PATCH /api/v1/items/{id}/toggle-active` — تفعيل/تعطيل الصنف
* `POST /api/v1/items/{id}/adjust-stock` — تسوية مخزنية وجرد
* `GET /api/v1/items/{id}/movements` — كشف حركات المخزون

### حالة الموديول: ✅ مكتمل بنجاح 100%

---

## 12. Module 06: الورديات ودفتر اليومية وجرد الدرج والخزينة (`Shifts & Daily Journal`) — بتاريخ 2026-08-21

### أ. الـ Backend (Laravel Pure API):
1. `[NEW]` `app/DTOs/Shifts/OpenShiftDTO.php` (Strictly Typed DTO لفتح وردية الكاشير والرصيد الافتتاحي).
2. `[NEW]` `app/DTOs/Shifts/CloseShiftDTO.php` (Strictly Typed DTO لتقفيل وردية الكاشير وجرد النقدية الفعلية).
3. `[NEW]` `app/Actions/Shifts/GetActiveShiftAction.php` (Single Action لاستعلام الوردية المفتوحة والمؤشرات اللحظية).
4. `[NEW]` `app/Actions/Shifts/OpenShiftAction.php` (Single Action لفتح وردية العمل وتوليد الكود التسلسلي داخل Transaction).
5. `[NEW]` `app/Actions/Shifts/CloseShiftAction.php` (Single Action لتقفيل الوردية وجرد النقدية وحساب الفارق بدقة `bcmath`).
6. `[NEW]` `app/Actions/Shifts/GetShiftZReportAction.php` (Single Action لتجهيز تقرير تقفيل الوردية Z-Report للطباعة الحرارية).
7. `[NEW]` `app/Actions/Shifts/GetDailyJournalAction.php` (Single Action لحساب دفتر اليومية، المقبوضات والمدفوعات وصافي النقدية ورصيد الدرج المتوقع).
8. `[NEW]` `app/Http/Resources/CashShiftResource.php` (تنسيق بيانات الورديات والمجاميع المالية للـ API).
9. `[MODIFIED]` `app/Http/Requests/OpenShiftRequest.php` & `CloseShiftRequest.php` (تحديث الصلاحيات).
10. `[REFACTORED]` `app/Http/Controllers/Api/ShiftController.php` (متحكم API متكامل للورديات وتقارير Z-Report).
11. `[NEW]` `app/Http/Controllers/Api/DailyJournalController.php` (متحكم API لدفتر اليومية وحسابات الخزينة اليومية).
12. `[MODIFIED]` `routes/api.php` (تسجيل مسارات الورديات، تقرير Z، ودفتر اليومية).
13. `[NEW]` `tests/Feature/Api/ShiftsAndDailyJournalApiTest.php` (حزمة 6 اختبارات Feature شاملة بنسبة نجاح 100%).

### ب. الـ Frontend (Pure Vue 3 SPA):
1. `[NEW]` `resources/js/views/DailyJournal/DailyJournalView.vue` (شاشة دفتر اليومية، بطاقة حالة الوردية اللحظية، بطاقات المقبوضات والمدفوعات وصافي النقدية والرصيد المتوقع بالدرج، جدولي فواتير المبيعات والمصروفات، نافذة فتح الوردية، ونافذة التقفيل وحساب الفارق المباشر).
2. `[MODIFIED]` `resources/js/router/index.js` (تسجيل مسار `/daily-journal`).
3. `[MODIFIED]` `resources/js/Layouts/SpaLayout.vue` (تحويل رابط دفتر اليومية والخزينة إلى router-link نشط).

### ج. نقاط النهاية المعتمدة (API Endpoints):
* `GET /api/v1/shifts/current` — الوردية المفتوحة حالياً والمؤشرات اللحظية
* `POST /api/v1/shifts/open` — فتح وردية جديدة
* `POST /api/v1/shifts/close` — تقفيل الوردية وجرد النقدية
* `GET /api/v1/shifts/{id}/z-report` — بيانات تقرير Z للطباعة الحرارية
* `GET /api/v1/shifts` — أرشيف وسجل الورديات السابقة
* `GET /api/v1/daily-journal` — دفتر اليومية وحسابات الخزينة لتاريخ محدد

### حالة الموديول: ✅ مكتمل بنجاح 100%

---

## 13. Module 07: المشتريات والتوريد وإعادة الطلب الذكي (`Purchases & Smart Reorder`) — بتاريخ 2026-08-21

### أ. الـ Backend (Laravel Pure API):
1. `[NEW]` `app/DTOs/Purchases/PurchaseDTO.php` (Strictly Typed DTO لفواتير المشتريات وتوريد الخامات والمصاريف المحملة).
2. `[NEW]` `app/DTOs/Purchases/CancelPurchaseDTO.php` (Strictly Typed DTO لإلغاء فواتير الشراء وعكس المخزون).
3. `[NEW]` `app/Actions/Purchases/CreatePurchaseAction.php` (Single Action لتسجيل واعتماد فاتورة المشتريات وتوزيع التكاليف وتحديث المخزون والـ WAC).
4. `[NEW]` `app/Actions/Purchases/CancelPurchaseAction.php` (Single Action لإلغاء الفاتورة وفحص كفاية الرصيد وعكس المخزون والمديونية).
5. `[NEW]` `app/Actions/Purchases/GetSmartReorderSuggestionsAction.php` (Single Action لمحرك ورادار إعادة الطلب التنبؤي ومعدلات السحب اليومي).
6. `[NEW]` `app/Http/Resources/PurchaseResource.php` (تنسيق بيانات فواتير المشتريات والمجاميع المالية).
7. `[NEW]` `app/Http/Resources/PurchaseItemResource.php` (تنسيق بنود وأصناف التوريد والتكاليف المحملة).
8. `[MODIFIED]` `app/Http/Requests/StorePurchaseRequest.php` (تحديث الصلاحيات والأدوار وقواعد التحقق).
9. `[REFACTORED]` `app/Http/Controllers/Api/PurchaseController.php` (متحكم API متكامل للمشتريات وإعادة الطلب الذكي).
10. `[MODIFIED]` `routes/api.php` (تسجيل مسارات المشتريات، رادار إعادة الطلب الذكي، والإلغاء).
11. `[NEW]` `tests/Feature/Api/PurchasesApiTest.php` (حزمة 5 اختبارات Feature شاملة بنسبة نجاح 100%).

### ب. الـ Frontend (Pure Vue 3 SPA):
1. `[NEW]` `resources/js/views/Purchases/PurchasesView.vue` (شاشة سجل فواتير المشتريات، بطاقات إجمالي المشتريات والمديونية الآجلة، فلاتر الموردين والحالات والتواريخ، نافذة المعاينة التفصيلية، وإلغاء الفواتير).
2. `[NEW]` `resources/js/views/Purchases/CreatePurchaseView.vue` (شاشة إنشاء فاتورة مشتريات جديدة، اختيار الموردين، جدول البنود الديناميكي، حساب التكاليف والخصومات والمدفوع والمتبقي الآجل، ودعم الـ Prefill من الرادار).
3. `[NEW]` `resources/js/views/Purchases/SmartReorderView.vue` (شاشة رادار إعادة الطلب الذكي بالذكاء الاصطناعي، بطاقات مستويات الخطورة، جدول النواقص ومعدلات الاستهلاك اليومي وفترة نفاد الرصيد، والتصدير المباشر لأمر شراء مجمع).
4. `[MODIFIED]` `resources/js/router/index.js` (تسجيل مسارات `/purchases` و `/purchases/create` و `/purchases/smart-reorder`).
5. `[MODIFIED]` `resources/js/Layouts/SpaLayout.vue` (تفعيل رابط المشتريات والتوريد في القائمة الجانبية).

### ج. نقاط النهاية المعتمدة (API Endpoints):
* `GET /api/v1/purchases` — قائمة فواتير المشتريات مع الفلاتر والمجاميع المالية
* `GET /api/v1/purchases/smart-reorder` — رادار واقتراحات إعادة الطلب الذكي
* `GET /api/v1/purchases/{id}` — تفاصيل فاتورة المشتريات وبنودها
* `POST /api/v1/purchases` — تسجيل واعتماد فاتورة مشتريات جديدة
* `POST /api/v1/purchases/{id}/cancel` — إلغاء الفاتورة وعكس المخزون

### حالة الموديول: ✅ مكتمل بنجاح 100%

---

## 14. Module 08: نقطة البيع السريعة وفواتير المبيعات (`POS & Sales Invoices`) — بتاريخ 2026-08-21

### أ. الـ Backend (Laravel Pure API):
1. `[NEW]` `app/DTOs/Invoices/CreateInvoiceDTO.php` (Strictly Typed DTO لفواتير المبيعات ونقاط البيع السريعة).
2. `[NEW]` `app/DTOs/Invoices/CancelInvoiceDTO.php` (Strictly Typed DTO لإلغاء فواتير المبيعات وعكس المخزون والمديونية).
3. `[NEW]` `app/Actions/Invoices/CreateSalesInvoiceAction.php` (Single Action لتسجيل واعتماد فاتورة المبيعات وخصم المخزون بقفل سطري `lockForUpdate` وتحديث رصيد العميل).
4. `[NEW]` `app/Actions/Invoices/CancelSalesInvoiceAction.php` (Single Action لإلغاء فاتورة المبيعات وإرجاع البضاعة للمخزن وعكس رصيد العميل).
5. `[NEW]` `app/Actions/Invoices/GetInvoiceDetailsAction.php` (Single Action لجلب تفاصيل الفاتورة وصياغة رسالة الواتساب المهيأة للعميل).
6. `[NEW]` `app/Http/Resources/InvoiceResource.php` (تنسيق تفاصيل فواتير المبيعات والبنود والدفعات للـ API).
7. `[NEW]` `app/Http/Requests/StoreSalesInvoiceRequest.php` (Form Request مخصص للتحقق من فواتير المبيعات ومنع `$request->validate()` في الكنترولر).
8. `[MODIFIED]` `app/Http/Requests/CancelInvoiceRequest.php` (تحديث الصلاحيات).
9. `[REFACTORED]` `app/Http/Controllers/Api/InvoiceController.php` (متحكم API متكامل لفواتير المبيعات).
10. `[NEW]` `app/Http/Controllers/Api/PosController.php` (متحكم API متكامل لعمليات الكاشير السريعة `bootstrap` و `checkout` و `quick-customer` و `last-price`).
11. `[MODIFIED]` `routes/api.php` (تسجيل كافة مسارات فواتير المبيعات وعمليات الـ POS).
12. `[NEW]` `tests/Feature/Api/InvoicesAndPosApiTest.php` (حزمة 7 اختبارات Feature شاملة بنسبة نجاح 100%).

### ب. الـ Frontend (Pure Vue 3 SPA):
1. `[NEW]` `resources/js/views/Invoices/InvoicesView.vue` (شاشة سجل فواتير المبيعات، بطاقات الإجمالي والمحصل والآجل، فلاتر نوع السداد والحالة والتواريخ، نافذة التفاصيل، مشاركة الواتساب المباشرة والطباعة).
2. `[NEW]` `resources/js/views/POS/PosView.vue` (شاشة كاشير فائقة السرعة، رادار تصنيفات، كروت أصناف بالرصيد والأسعار، سلة بيع سريعة مع عداد الكميات والخصم وأنواع السداد، نافذة إضافة عميل سريع، واختصارات لوحة المفاتيح F9/F2).
3. `[MODIFIED]` `resources/js/router/index.js` (تسجيل مسارات `/invoices` و `/pos`).
4. `[MODIFIED]` `resources/js/Layouts/SpaLayout.vue` (تحويل روابط POS وفواتير المبيعات إلى router-link نشط).

### ج. نقاط النهاية المعتمدة (API Endpoints):
* `GET /api/v1/invoices` — قائمة فواتير المبيعات مع الفلاتر والمجاميع المالية
* `GET /api/v1/invoices/{id}` — تفاصيل الفاتورة وبنودها ورابط مشاركة الواتساب
* `POST /api/v1/invoices` — تسجيل واعتماد فاتورة مبيعات جديدة
* `POST /api/v1/invoices/{id}/cancel` — إلغاء الفاتورة وعكس المخزون ورصيد العميل
* `GET /api/v1/pos/bootstrap` — تجميع بيانات كاشير POS (الأصناف، الرصيد، التصنيفات، العملاء، الوردية)
* `POST /api/v1/pos/checkout` — اعتماد وتأكيد بيع POS فوري
* `POST /api/v1/pos/quick-customer` — تسجيل عميل سريع من شاشة POS
* `GET /api/v1/pos/last-price` — سعر آخر بيع للعميل على الصنف المحدد

### حالة الموديول: ✅ مكتمل بنجاح 100%

---

## 15. Module 09: مرتجعات المبيعات والمشتريات (`Returns: Sales & Purchase Returns`) — بتاريخ 2026-08-21

### أ. الـ Backend (Laravel Pure API):
1. `[NEW]` `app/DTOs/Returns/ReturnDocumentDTO.php` (Strictly Typed DTO لمستندات مرتجعات المبيعات والمشتريات).
2. `[NEW]` `app/Actions/Returns/CreateReturnAction.php` (Single Action لاعتماد المرتجع وتعديل المخزون وأرصدة العملاء والموردين داخل Transaction).
3. `[NEW]` `app/Actions/Returns/DeleteReturnAction.php` (Single Action لحذف وأرشفة مستند المرتجع).
4. `[NEW]` `app/Http/Resources/ReturnResource.php` & `ReturnItemResource.php` (تنسيق بيانات المرتجعات والبنود للـ API).
5. `[MODIFIED]` `app/Http/Requests/StoreReturnRequest.php` (التحقق الصارم عبر Form Request ومنع أي `$request->validate()` في الكنترولر).
6. `[REFACTORED]` `app/Http/Controllers/Api/ReturnController.php` (متحكم API متكامل لإدارة واستعلام المرتجعات).
7. `[MODIFIED]` `routes/api.php` (تسجيل مسارات `/returns` و `/returns/{id}`).
8. `[NEW]` `tests/Feature/Api/ReturnsApiTest.php` (حزمة 5 اختبارات Feature شاملة بنسبة نجاح 100%).

### ب. الـ Frontend (Pure Vue 3 SPA):
1. `[NEW]` `resources/js/views/Returns/ReturnsView.vue` (شاشة سجل المرتجعات، بطاقات الإجمالي والعدد وتصنيف المرتجعات، فلاتر البحث والنوع والتواريخ، نافذة التفاصيل، وأرشفة المستندات).
2. `[NEW]` `resources/js/views/Returns/CreateReturnView.vue` (شاشة تسجيل مرتجع مبيعات أو مشتريات، التبديل السلس بين العملاء والموردين، إضافة بنود المرتجع بالرصيد والأسعار، واحتساب إجمالي المرتجع والنقدية المستردة).
3. `[MODIFIED]` `resources/js/router/index.js` (تسجيل مسارات `/returns` و `/returns/create`).
4. `[MODIFIED]` `resources/js/Layouts/SpaLayout.vue` (تفعيل رابط المرتجعات في القائمة الجانبية).

### ج. نقاط النهاية المعتمدة (API Endpoints):
* `GET /api/v1/returns` — قائمة مستندات المرتجعات مع الفلاتر والمؤشرات الإجمالية
* `GET /api/v1/returns/{id}` — تفاصيل مستند المرتجع وبنوده
* `POST /api/v1/returns` — تسجيل واعتماد مرتجع مبيعات أو مشتريات
* `DELETE /api/v1/returns/{id}` — أرشفة وحذف مستند المرتجع

### حالة الموديول: ✅ مكتمل بنجاح 100%

---

## 16. Module 10: التحويلات المخزنية بين الفروع والمخازن (`Stock Transfers`) — بتاريخ 2026-08-21

### أ. الـ Backend (Laravel Pure API):
1. `[NEW]` `app/DTOs/Transfers/CreateTransferDTO.php` (Strictly Typed DTO لبيانات التحويل المخزني).
2. `[NEW]` `app/DTOs/Transfers/CancelTransferDTO.php` (Strictly Typed DTO لإلغاء التحويل المخزني).
3. `[NEW]` `app/Actions/Transfers/CreateStockTransferAction.php` (Single Action لاعتماد التحويل المخزني ونقل الأرصدة وتسجيل حركات `transfer_out` و `transfer_in` داخل Transaction).
4. `[NEW]` `app/Actions/Transfers/CancelStockTransferAction.php` (Single Action لإلغاء التحويل وعكس حركة المخزون بأمان للمخزن المصدر).
5. `[NEW]` `app/Http/Resources/StockTransferResource.php` & `StockTransferItemResource.php` (تنسيق بيانات أذونات التحويل والبنود للـ API).
6. `[NEW]` `app/Http/Requests/CancelStockTransferRequest.php` & `[MODIFIED]` `StoreStockTransferRequest.php` (التحقق الصارم عبر Form Requests ومنع أي `$request->validate()` في الكنترولر).
7. `[REFACTORED]` `app/Http/Controllers/Api/StockTransferController.php` (متحكم API متكامل لإدارة واستعلام أذونات التحويل).
8. `[MODIFIED]` `routes/api.php` (تسجيل مسارات التحويل المخزني `/transfers` و `/transfers/{id}` و `/transfers/{id}/cancel`).
9. `[NEW]` `tests/Feature/Api/StockTransfersApiTest.php` (حزمة 4 اختبارات Feature شاملة بنسبة نجاح 100%).

### ب. الـ Frontend (Pure Vue 3 SPA):
1. `[NEW]` `resources/js/views/StockTransfers/StockTransfersView.vue` (شاشة سجل أذونات التحويل، كروت الإجمالي والمنفذ والملغي، فلاتر المخازن والتواريخ، نافذة المعاينة، وإلغاء وعكس التحويل).
2. `[NEW]` `resources/js/views/StockTransfers/CreateStockTransferView.vue` (شاشة إنشاء إذن تحويل مخزني، اختيار المخزن المصدر والمستلم، إضافة بنود الأصناف بالرصيد والكميات، والتنفيذ الفوري).
3. `[MODIFIED]` `resources/js/router/index.js` (تسجيل مسارات `/stock-transfers` و `/stock-transfers/create`).
4. `[MODIFIED]` `resources/js/Layouts/SpaLayout.vue` (تفعيل رابط التحويلات المخزنية في القائمة الجانبية).

### ج. نقاط النهاية المعتمدة (API Endpoints):
* `GET /api/v1/transfers` — قائمة أذونات التحويل المخزني مع الفلاتر والمؤشرات الإجمالية
* `GET /api/v1/transfers/{id}` — تفاصيل إذن التحويل وبنوده
* `POST /api/v1/transfers` — تسجيل واعتماد تحويل مخزني ونقل الرصيد فوراً
* `POST /api/v1/transfers/{id}/cancel` — إلغاء إذن التحويل وعكس رصيد الأصناف للمخزن المصدر

### حالة الموديول: ✅ مكتمل بنجاح 100%

---

## 17. مصفوفة تتبع حالة الترحيل (Migration Tracking Matrix)

- [x] **المرحلة 0: التخطيط والقرارات المعمارية (Architectural Planning & Log Setup)** ✅ (2026-08-21)
- [x] **المرحلة 1: بناء Auth API (Backend) - Sanctum Tokens & Authentication Service** ✅ (2026-08-21)
- [x] **المرحلة 2: بناء Permissions & System Context API (Backend)** ✅ (2026-08-21)
- [x] **المرحلة 3: إعداد الـ Frontend Core (Vue Router + Pinia + API Client + Guards + Login)** ✅ (2026-08-21)
- [ ] **المرحلة 4: تحويل الموديولات تدريجياً (Module by Module):**
  - [x] **Module 01: الفروع والمخازن (`Stores & Stocks`)** ✅ (2026-08-21)
  - [x] **Module 02: العملاء وكشوف الحساب (`Customers & Statements`)** ✅ (2026-08-21)
  - [x] **Module 03: الموردين وكشوف الحساب (`Suppliers & Statements`)** ✅ (2026-08-21)
  - [x] **Module 04: المصروفات والعهد النثرية (`Expenses & Petty Cash`)** ✅ (2026-08-21)
  - [x] **Module 05: الأصناف وحركات المخزون والنواقص (`Items & Stock Movements`)** ✅ (2026-08-21)
  - [x] **Module 06: الورديات ودفتر اليومية والخزينة (`Shifts & Daily Journal`)** ✅ (2026-08-21)
  - [x] **Module 07: المشتريات والتوريد وإعادة الطلب الذكي (`Purchases & Smart Reorder`)** ✅ (2026-08-21)
  - [x] **Module 08: نقطة البيع السريعة والفواتير (`POS & Sales Invoices`)** ✅ (2026-08-21)
  - [x] **Module 09: مرتجعات المبيعات والمشتريات (`Returns`)** ✅ (2026-08-21)
  - [x] **Module 10: التحويلات المخزنية بين الفروع (`Stock Transfers`)** ✅ (2026-08-21)
  - [ ] **Module 11: توليفات البن والتصنيع (`Coffee Blender Engine`)** ⏳ (التالي في الترتيب)
  - [ ] Module 12: التقارير المالية والأرباح والخسائر (`Reports & Profit Analytics`)
  - [ ] Module 13: لوحة التحكم والتحليلات اللحظية (`Dashboard Analytics`)
  - [ ] Module 14: إدارة المستخدمين والأدوار والأنشطة (`Users, Roles & Logs`)
  - [ ] Module 15: الإعدادات والملف الشخصي وسلة المهملات (`Settings, Profile & Trash`)
  - [ ] Module 16: لوحة تحكم السوبر أدمن والمستأجرين (`SuperAdmin Dashboard, Tenants & Plans`)
- [ ] **المرحلة 5: التنظيف النهائي وإزالة حزم وأكواد Inertia.js بالكامل**

---
**آخر مرحلة مكتملة:** المرحلة 4 — Module 10: التحويلات المخزنية بين الفروع (`Stock Transfers`)  
**الموديول التالي المستهدف:** **Module 11: توليفات البن والتصنيع (`Coffee Blender Engine`)**
