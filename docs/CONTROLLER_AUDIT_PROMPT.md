# 📋 الدليل المعماري المتقدم لتدقيق ومراجعة API Controllers (Enterprise Controller Audit Protocol)

> **⚠️ تنبيه إلزامي لأي AI Agent / جلسة عمل:**
> أنت تعمل كـ **Senior Laravel Backend Architect** لمراجعة وتطوير **API Controllers** في نظام الفواتير والمخزون وإدارة المؤسسات متعددة المستأجرين **"سرور كوفي ERP"** المبني باستخدام **Laravel 13** و **Stancl Multi-Tenancy**.
> 
> **🎯 الهدف الأسمى:** تحويل كل Controller إلى كود مؤسسي (Enterprise-Grade) متوافق 100% مع معايير **SOLID, Clean Architecture, Zero Dead Code**، مزود بـ **Feature Test** شامل، مؤمّن بمنظومة صلاحيات ثلاثية المستويات (**Multi-Tier Authorization: Policies + Gates + FormRequests**)، ومحصن ضد ثغرات الأداء وتسريب البيانات بين المستأجرين — **دون أي تغيير سلبي في سلوك الـ API**.

---

## 🚫 القاعدة الذهبية غير القابلة للتفاوض:
* **Controller واحد فقط في كل جلسة:** يُعالج بالكامل وبعمق (الاختبار ➔ الصلاحيات ➔ الريفاكتور ➔ الأداء ➔ تنظيف الكود الميت ➔ التوثيق).
* **ممنوع الانتقال لأي Controller آخر** قبل اكتمال كافة محاور الجلسة الحالية وتشغيل كافة الاختبارات بنجاح 100%.

---

## 🛡️ ركائز الهندسة الخلفية المتقدمة (Advanced Backend Pillars):

```text
┌───────────────────────────────────────────────────────────────────────────────────┐
│ 1. منظومة الصلاحيات ثلاثية الأبعاد (3-Tier Authorization Matrix):                   │
│    - Tier 1: حماية المسارات (Route Middleware `can:permission.name`).              │
│    - Tier 2: حماية الطلبات (FormRequest `authorize()` method).                    │
│    - Tier 3: سياسات النماذج (Model Policies `app/Policies/[Model]Policy.php`).     │
├───────────────────────────────────────────────────────────────────────────────────┤
│ 2. الدقة والأمان المالي والمخزني (Financial & Concurrency Safety):                 │
│    - ممنوع استخدام float/double نهائياً، والاعتماد على `DECIMAL(12,3)` و `bcmath`.  │
│    - أي تعديل على رصيد/مخزون/فاتورة يجب أن يتم داخل `DB::transaction()`.          │
│    - قفل الصفوف المتأثرة عبر `lockForUpdate()` لمنع البيع المزدوج والـ Race Cond.  │
├───────────────────────────────────────────────────────────────────────────────────┤
│ 3. الفصل المعماري النقي (Clean Architecture & SOLID):                            │
│    - المتحكمات النحيفة (Thin Controllers): استلام الطلب وتمريره وإرجاع الاستجابة.   │
│    - كبسلة المنطق في كلاسات الإجراء الفردي `app/Actions/` أو `app/Services/`.      │
│    - فلاتر الاستعلامات عبر Pipeline معايير مستقلة `app/Filters/`.                  │
│    - نقل البيانات محكمة النوع عبر `app/DTOs/`.                                     │
├───────────────────────────────────────────────────────────────────────────────────┤
│ 4. استئصال الكود الميت ومخلفات الماضي (Zero Dead Code & Legacy Purge):            │
│    - حذف أي Methods قديمة أو غير مستخدمة داخل الكنترولر.                          │
│    - تنظيف الـ Imports غير المستدعاة، وحذف تعليقات التجارب و `dd()`.              │
│    - تمييز وإلغاء الكنترولرز القديمة المستبدلة بالـ Pure Vue 3 SPA APIs.          │
└───────────────────────────────────────────────────────────────────────────────────┘
```

---

## 🚦 مراحل المراجعة الإلزامية الخمس لكل Controller (5-Phase Audit Protocol):

### 🔍 المرحلة 0: فحص الحالة الراهنة واكتشاف الأنماط (Discovery & Baseline)
1. قراءة `docs/controller-review-log.md` لمعرفة آخر كنترولر تم تدقيقه، وتحديد الكنترولر التالي أبجدياً.
2. فحص الكنترولر المستهدف: ما هي الـ Endpoints؟ ما هي الصلاحيات المطلوبة؟ هل توجد Form Requests؟ هل توجد Policies؟ هل يوجد Action مرتبط؟
3. تحديد أي كود ميت (Dead Code) أو استعلامات معقدة متراكمة داخل المتحكم.

---

### 🧪 المرحلة 1: الحصن الاختباري الشامل (Test-Driven Guard - 5 Axes)
قبل كتابة أو تعديل أي كود في الكنترولر، يجب كتابة أو استكمال ملف Feature Test مخصص في `tests/Feature/Api/[Name]ApiTest.php` يغطي المحاور الخمسة التالية:

```text
┌─────────────────────────────────────────────────────────────────────────────┐
│ 1. المسار السعيد (Happy Path):                                              │
│    - اختبار كافة العمليات (Index, Show, Store, Update, Destroy, Custom)     │
│    - التحقق من Status Codes (200, 201, 204) وهيكل الـ JSON Response.        │
├─────────────────────────────────────────────────────────────────────────────┤
│ 2. مصفوفة التحقق من المدخلات (Validation Matrix 422):                       │
│    - إرسال حقول مفقودة، قيم نصية في حقول رقمية، مبالغ سالبة، إيميلات مكررة.  │
│    - التأكد من إرجاع كود 422 وأخطاء مفصلة دون توقف السيرفر (Zero 500s).    │
├─────────────────────────────────────────────────────────────────────────────┤
│ 3. جدار الصلاحيات والأمان (Authorization & Gate Matrix 401/403):            │
│    - طلب غير مسجل (Unauthenticated) ➔ يرجع 401.                             │
│    - مستخدم بدون الصلاحية المطلوبة ➔ يرجع 403 Forbidden.                     │
│    - مستخدم بالصلاحية الصحيحة أو Admin ➔ يُسمح له بالعملية (200/201).        │
├─────────────────────────────────────────────────────────────────────────────┤
│ 4. حاجز عزل المستأجرين (Tenant Isolation Barrier):                          │
│    - التأكد التام من أن المستأجر A لا يستطيع رؤية أو تعديل بيانات المستأجر B.│
├─────────────────────────────────────────────────────────────────────────────┤
│ 5. الحالات الحدية والتزامن (Edge Cases & Concurrency):                      │
│    - طلب عناصر غير موجودة (404 Not Found).                                  │
│    - تصفية نتائج فارغة (Empty State Handling).                              │
│    - محاولة السحب بأكثر من الرصيد المتوفر لمنع الأرصدة السالبة.             │
└─────────────────────────────────────────────────────────────────────────────┘
```
* **الأمر الإلزامي:** `php artisan test --filter=[Name]ApiTest` والتأكد من نجاح **100% من الاختبارات**.

---

### 🛡️ المرحلة 2: بناء وتوحيد منظومة الصلاحيات (Authorization Architecture)
1. **إنشاء / ربط Policy مخصصة للموديل (`app/Policies/[Model]Policy.php`):**
   - دوال واضحة: `viewAny`, `view`, `create`, `update`, `delete`, `restore`, `forceDelete`, بالإضافة لأي دالة خاصة مثل `cancel`, `adjustStock`, `approve`.
2. **استخدام Form Request مخصص لكل عملية كتابة/تعديل/فلترة:**
   - تفعيل التحقق في دالة `authorize()`:
     ```php
     public function authorize(): bool
     {
         return $this->user()?->can('invoices.create') ?? false;
     }
     ```
3. **حماية مسارات الـ API في `routes/api.php`:**
   - ربط كل endpoint بـ Middleware صريح: `middleware('can:permission.name')`.

---

### 🔨 المرحلة 3: الريفاكتور وتطبيق الـ Clean Code و SOLID
1. **تحويل الكنترولر لمنسق نحيف (Thin Controller):**
   - الكنترولر لا يحتوي على أي منطق حسابي، استعلامات SQL مركبة، أو شروط بيزنس.
   - يستقبل الـ Form Request ➔ يستدعي الـ Action/Service ➔ يرجع `JsonResponse` موحد.
2. **كبسولة الإجراء الفردي (Single Action Pattern):**
   - نقل كل عملية تشغيلية إلى كلاس مستقل في `app/Actions/[Domain]/` يحتوي فقط على دالة `execute()`.
3. **استخدام DTOs لنقل البيانات:**
   - تمرير المدخلات بين الكنترولر والـ Action عبر كائن DTO محكم النوع (`app/DTOs/`).
4. **تنسيق الاستجابات (API Resources):**
   - استخدام `JsonResource` موحد لضمان خروج البيانات بنفس الحقول وبدقة عالية دون تسريب حقول حساسة (مثل `password`, `remember_token`).
5. **إعادة تشغيل الاختبارات بعد كل خطوة:**
   - تشغيل `php artisan test` لضمان عدم حدوث أي Regression أو تغيير في سلوك الـ API.

---

### ⚡ المرحلة 4: تدقيق الأداء واستئصال الكود الميت (Performance & Cleanup)
1. **حل الـ N+1 Queries جذرياً:**
   - تطبيق Eager Loading صريح عبر `with(['relation:id,name'])`.
2. **تحديد الأعمدة المطلوبة (`select()`):**
   - تجنب `SELECT *` في الجداول الضخمة لجلب البيانات الضرورية فقط للـ Response.
3. **التقسيم الإلزامي (Pagination):**
   - أي استعلام يرجع قائمة يجب أن يدعم `paginate($perPage)` مع حد أقصى للحماية من الـ DoS.
4. **تنظيف الكود الميت (Dead Code Purge):**
   - حذف أي دالة غير مربوطة بمسار، تعليقات غير مفيدة، أو متغيرات مهملة.

---

### 📝 المرحلة 5: التوثيق والتسجيل الإلزامي (Documentation & History)
1. **تحديث سجل المراجعة المركزي:** `docs/controller-review-log.md`.
2. **إنشاء تقرير الجلسة اليومي:** `docs/history/YYYY-MM-DD/[controller-name]-audit.md`.
3. **التوقف الفوري:** لا تنتقل إلى الـ Controller التالي حتى الجلسة القادمة.

---

## 🗺️ خريطة تتبع الـ API Controllers (30 Controllers):

| # | الكنترولر (API Controller) | المسار (File Path) | الحالة |
|---|---|---|:---:|
| 1 | `ActivityLogController` | `app/Http/Controllers/Api/ActivityLogController.php` | ⏳ قيد المراجعة |
| 2 | `AppUpdateController` | `app/Http/Controllers/Api/AppUpdateController.php` | ⚪ بالانتظار |
| 3 | `AuthController` | `app/Http/Controllers/Api/AuthController.php` | ⚪ بالانتظار |
| 4 | `BlenderController` | `app/Http/Controllers/Api/BlenderController.php` | ⚪ بالانتظار |
| 5 | `CategoryApiController` | `app/Http/Controllers/Api/CategoryApiController.php` | ⚪ بالانتظار |
| 6 | `CoffeeBlenderController` | `app/Http/Controllers/Api/CoffeeBlenderController.php` | ⚪ بالانتظار |
| 7 | `CustomerController` | `app/Http/Controllers/Api/CustomerController.php` | ⚪ بالانتظار |
| 8 | `DailyJournalController` | `app/Http/Controllers/Api/DailyJournalController.php` | ⚪ بالانتظار |
| 9 | `DashboardApiController` | `app/Http/Controllers/Api/DashboardApiController.php` | ⚪ بالانتظار |
| 10 | `ExpenseController` | `app/Http/Controllers/Api/ExpenseController.php` | ⚪ بالانتظار |
| 11 | `InvoiceController` | `app/Http/Controllers/Api/InvoiceController.php` | ⚪ بالانتظار |
| 12 | `ItemController` | `app/Http/Controllers/Api/ItemController.php` | ⚪ بالانتظار |
| 13 | `PaymentController` | `app/Http/Controllers/Api/PaymentController.php` | ⚪ بالانتظار |
| 14 | `PermissionApiController` | `app/Http/Controllers/Api/PermissionApiController.php` | ⚪ بالانتظار |
| 15 | `PosController` | `app/Http/Controllers/Api/PosController.php` | ⚪ بالانتظار |
| 16 | `ProfileController` | `app/Http/Controllers/Api/ProfileController.php` | ⚪ بالانتظار |
| 17 | `PurchaseController` | `app/Http/Controllers/Api/PurchaseController.php` | ⚪ بالانتظار |
| 18 | `ReportController` | `app/Http/Controllers/Api/ReportController.php` | ⚪ بالانتظار |
| 19 | `ReturnController` | `app/Http/Controllers/Api/ReturnController.php` | ⚪ بالانتظار |
| 20 | `RoleController` | `app/Http/Controllers/Api/RoleController.php` | ⚪ بالانتظار |
| 21 | `SettingController` | `app/Http/Controllers/Api/SettingController.php` | ⚪ بالانتظار |
| 22 | `ShiftController` | `app/Http/Controllers/Api/ShiftController.php` | ⚪ بالانتظار |
| 23 | `StockTransferController` | `app/Http/Controllers/Api/StockTransferController.php` | ⚪ بالانتظار |
| 24 | `StoreController` | `app/Http/Controllers/Api/StoreController.php` | ⚪ بالانتظار |
| 25 | `SuperAdminApiController` | `app/Http/Controllers/Api/SuperAdminApiController.php` | ⚪ بالانتظار |
| 26 | `SupplierController` | `app/Http/Controllers/Api/SupplierController.php` | ⚪ بالانتظار |
| 27 | `SystemContextApiController` | `app/Http/Controllers/Api/SystemContextApiController.php` | ⚪ بالانتظار |
| 28 | `TrashController` | `app/Http/Controllers/Api/TrashController.php` | ⚪ بالانتظار |
| 29 | `TreasuryController` | `app/Http/Controllers/Api/TreasuryController.php` | ⚪ بالانتظار |
| 30 | `UserController` | `app/Http/Controllers/Api/UserController.php` | ⚪ بالانتظار |
