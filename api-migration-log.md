# سجل التحويل المعماري: Inertia.js إلى Pure API (Laravel API + Vue 3 SPA)

> **تاريخ الإنشاء:** 2026-08-21  
> **الفرع البرمجي (Git Branch):** `feature/api-migration`  
> **الحالة العامة:** المرحلة 1 (بناء Auth API Backend) مكتملة ومختبرة بنجاح بنسبة 100%.

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

- **Api Controllers الحالية في `app/Http/Controllers/Api/` (19):**
  1. `AuthController` (تسجيل الدخول، استعلام me، تسجيل الخروج)
  2. `DashboardApiController` (الملخص اللحظي المالي والتشغيلي)
  3. `ItemController` (قائمة الأصناف، النواقص، تفاصيل الصنف)
  4. `InvoiceController` (إنشاء وعرض وإلغاء فواتير المبيعات)
  5. `CustomerController` (العملاء، إضافة عميل، كشف الحساب)
  6. `SupplierController` (الموردين، إضافة مورد، كشف الحساب)
  7. `PurchaseController` (المشتريات وإلغاؤها)
  8. `ReturnController` (تسجيل مرتجع مبيعات ومشتريات)
  9. `StockTransferController` (التحويلات بين الفروع)
  10. `StoreController` (قائمة الفروع وتبديل الفرع النشط)
  11. `ShiftController` (إدارة الورديات، الفتح، الإغلاق، وتقرير Z-Report)
  12. `PaymentController` (سندات القبض للعملاء وسندات الصرف للموردين)
  13. `ExpenseController` (المصروفات)
  14. `TreasuryController` (أرصدة الخزائن والبنوك)
  15. `ReportController` (ملخص الأرباح وأعلى الأصناف مبيعاً)
  16. `SettingController` (إعدادات المنشأة وتحديثها)
  17. `ActivityLogController` (سجل الأنشطة)
  18. `BlenderController` (توليفات البن)
  19. `AppUpdateController` (فحص إصدارات التطبيق وتحميل التحديثات)

### ب. حالة الـ API Routes الحالية:
- يوجد ملف `routes/api.php` معرف به مجموعة endpoints أساسية تحت البادئة `/api/v1` ومحمية بـ `ApiTokenAuth`، وهي مخصصة لتطبيق الموبايل، وتعتمد على حقل نصي `api_token` في جدول `users`.

### ج. فحص نظام الـ Auth الحالي:
- **في الويب (Inertia):** يعتمد على Session-based Cookie (`web` guard) عبر `Auth::attempt()`.
- **في الموبايل/API الحالي:** يعتمد على `ApiTokenAuth` بفحص `Bearer <token>` أو هيدر `X-API-TOKEN`.

### د. فحص نظام الـ Multi-Tenancy (`stancl/tenancy`):
- يتم التعرف على الـ Tenant عبر الـ Subdomain أو الـ Domain المستقل باستخدام:
  `Stancl\Tenancy\Middleware\InitializeTenancyByDomain`
- النطاقات المركزية (Central Domains) معرّفة في `config/tenancy.php` لخدمة السوبر أدمن، بينما ينعزل كل مستأجر في قاعدة بيانات SQLite/MySQL خاصة به.

### هـ. فحص نظام الصلاحيات (`spatie/laravel-permission`):
- مطبق على مستوى الباك إند عبر `can:` middleware وسياسات `Gate`/`Policy`.
- يُمرر للواجهة حالياً عبر `HandleInertiaRequests` كقائمة `permissions` و `roles` مصفوفية في كائن المستخدم.

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
[المرحلة 0: التخطيط ✅] ──► [المرحلة 1: Auth API ✅] ──► [المرحلة 2: Permissions/Context API] ──► [المرحلة 3: Frontend SPA Core]
                                                                                                        │
┌───────────────────────────────────────────────────────────────────────────────────────────────────────┘
▼
[المرحلة 4: ترحيل الموديولات تدريجياً (Module by Module)]
  ├── 1. الفروع والمخازن (Stores & Stocks)
  ├── 2. العملاء وكشوف الحساب (Customers & Statements)
  ├── 3. الموردين وكشوف الحساب (Suppliers & Statements)
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

### الملفات التي تم إنشاؤها وتعديلها:
1. `[NEW]` `database/migrations/2019_12_14_000001_create_personal_access_tokens_table.php` (قاعدة البيانات المركزية)
2. `[NEW]` `database/migrations/tenant/2019_12_14_000001_create_personal_access_tokens_table.php` (قواعد بيانات المستأجرين)
3. `[NEW]` `app/DTOs/Auth/ApiLoginDTO.php` (DTO مدقق النوع لاستقبال بيانات تسجيل الدخول والجهاز والمستأجر)
4. `[NEW]` `app/Http/Requests/Auth/ApiLoginRequest.php` (Form Request محكم التحقق مع حماية Rate Limiter)
5. `[NEW]` `app/Actions/Auth/ApiLoginAction.php` (Single Action لتسجيل الدخول والتحقق وإصدار توكن Sanctum وسياق المتجر والنظام)
6. `[NEW]` `app/Actions/Auth/ApiLogoutAction.php` (Single Action لإلغاء وإتلاف توكنات Sanctum وتسجيل النشاط)
7. `[NEW]` `app/Actions/Auth/ApiMeAction.php` (Single Action لاسترجاع بيانات المستخدم، الصلاحيات، الفروع، والوردية النشطة)
8. `[NEW]` `app/Http/Middleware/ResolveApiTenancy.php` (Middleware مرن للتعرف على المستأجر عبر Domain أو Header `X-Tenant`)
9. `[MODIFIED]` `app/Models/User.php` (إضافة Trait `Laravel\Sanctum\HasApiTokens`)
10. `[MODIFIED]` `app/Http/Resources/UserResource.php` (تنسيق مخرجات المستخدم، الصلاحيات، الأدوار، والفروع)
11. `[MODIFIED]` `app/Http/Middleware/ApiTokenAuth.php` (دعم مصادقة Sanctum Bearer Tokens وربطها بالـ user context)
12. `[MODIFIED]` `app/Http/Controllers/Api/AuthController.php` (إعادة بناء بالكامل لتطبيق SOLID وفصل الطبقات)
13. `[MODIFIED]` `routes/api.php` (تطبيق ResolveApiTenancy على مسارات `/api/v1`)
14. `[MODIFIED]` `bootstrap/app.php` (معالجة استثناءات التوثيق والتحقق بصيغة JSON موحدة)
15. `[MODIFIED]` `lang/ar/auth.php` و `lang/en/auth.php` (إضافة مفاتيح الترجمة لكافة رسائل الـ API Auth بدون أي نصوص ثابتة)
16. `[NEW]` `tests/Feature/Api/AuthApiTest.php` (حزمة اختبارات آلية تغطي كافة حالات Login, Me, Logout, Rate Limit, Inactive Account)

### طريقة الاختبار ونقاط النهاية (API Endpoints & Examples):

#### 1. تسجيل الدخول (Login):
* **Endpoint:** `POST /api/v1/auth/login`
* **Headers:** `Accept: application/json`, `X-Tenant: {tenant_id_or_domain}` (اختياري لو عبر دومين عام)
* **Request Body:**
```json
{
  "login": "01012316954",
  "password": "password",
  "device_name": "vue-spa-chrome"
}
```
* **Success Response (200 OK):**
```json
{
  "success": true,
  "message": "تم تسجيل الدخول بنجاح إلى منظومة ERP.",
  "data": {
    "token": "1|abcdef123456...",
    "user": {
      "id": 1,
      "name": "كمال سرور",
      "email": "kamal@sroor.test",
      "phone": "01012316954",
      "roles": ["admin"],
      "permissions": ["pos.access", "invoices.view", ...],
      "theme_preference": "dark",
      "show_print_subtitle": true,
      "default_store_id": 1,
      "is_active": true
    },
    "store": {
      "id": 1,
      "name": "المخزن الرئيسي",
      "code": "MAIN",
      "is_main": true
    },
    "stores": [...],
    "system": {
      "company_name": "سرور كوفي",
      "company_subtitle": "لتوريدات خامات مطاحن البن",
      "system_theme": "amber",
      "server_time": "2026-08-21 14:35:00"
    }
  }
}
```

#### 2. استعلام المستخدم الحالي وسياق المتجر والصلاحيات (Get Current User / Me):
* **Endpoint:** `GET /api/v1/auth/me`
* **Headers:** `Authorization: Bearer 1|abcdef123456...`, `Accept: application/json`, `X-Store-Id: 1`
* **Success Response (200 OK):**
```json
{
  "success": true,
  "data": {
    "user": {
      "id": 1,
      "name": "كمال سرور",
      "roles": ["admin"],
      "permissions": ["pos.access", ...]
    },
    "store": { "id": 1, "name": "المخزن الرئيسي" },
    "active_shift": { "id": 5, "shift_number": "SH-005", "opened_at": "..." }
  }
}
```

#### 3. تسجيل الخروج وإلغاء التوكن (Logout):
* **Endpoint:** `POST /api/v1/auth/logout`
* **Headers:** `Authorization: Bearer 1|abcdef123456...`, `Accept: application/json`
* **Success Response (200 OK):**
```json
{
  "success": true,
  "message": "تم تسجيل الخروج بنجاح."
}
```

### حالة المرحلة: ✅ مكتملة بنجاح 100%

---

## 5. مصفوفة تتبع حالة الترحيل (Migration Tracking Matrix)

- [x] **المرحلة 0: التخطيط والقرارات المعمارية (Architectural Planning & Log Setup)** ✅ (2026-08-21)
- [x] **المرحلة 1: بناء Auth API (Backend) - Sanctum Tokens & Authentication Service** ✅ (2026-08-21)
- [ ] **المرحلة 2: بناء Permissions & System Context API (Backend)**
- [ ] **المرحلة 3: إعداد الـ Frontend Core (Vue Router + Pinia + API Client + Guards + Login)**
- [ ] **المرحلة 4: تحويل الموديولات:**
  - [ ] Module 01: الفروع والمخازن (`Stores`)
  - [ ] Module 02: العملاء وكشوف الحساب (`Customers`)
  - [ ] Module 03: الموردين وكشوف الحساب (`Suppliers`)
  - [ ] Module 04: المصروفات والعهد النثرية (`Expenses`)
  - [ ] Module 05: الأصناف وحركات المخزون والنواقص (`Items`)
  - [ ] Module 06: الورديات ودفتر اليومية والخزينة (`Shifts & Daily Journal`)
  - [ ] Module 07: المشتريات والتوريد وإعادة الطلب الذكي (`Purchases`)
  - [ ] Module 08: نقطة البيع السريعة والفواتير (`POS & Invoices`)
  - [ ] Module 09: مرتجعات المبيعات والمشتريات (`Returns`)
  - [ ] Module 10: التحويلات المخزنية بين الفروع (`Stock Transfers`)
  - [ ] Module 11: توليفات البن والتصنيع (`Coffee Blender`)
  - [ ] Module 12: التقارير المالية والأرباح والخسائر (`Reports`)
  - [ ] Module 13: لوحة التحكم والتحليلات اللحظية (`Dashboard`)
  - [ ] Module 14: إدارة المستخدمين والأدوار والأنشطة (`Users & Roles`)
  - [ ] Module 15: الإعدادات والملف الشخصي وسلة المهملات (`Settings & Profile`)
  - [ ] Module 16: لوحة تحكم السوبر أدمن والمستأجرين (`SuperAdmin`)
- [ ] **المرحلة 5: التنظيف النهائي وإزالة حزم وأكواد Inertia.js بالكامل**

---
**آخر مرحلة مكتملة:** المرحلة 1 (Auth API Backend)  
**المرحلة التالية المستهدفة:** المرحلة 2 (Permissions/Policies & System Context API)
