# 💼 وثيقة المكون والصفحة: إدارة باقات الاشتراك والأسعار المركزية (`SuperAdminPlansView`)

## 1. النظرة العامة والتحليل التشغيلي:
* **اسم الصفحة:** إدارة الباقات والأسعار (Subscription Plans & Pricing)
* **المسار (Route):** `/super-admin/plans`
* **اسم المسار (Route Name):** `super_admin.plans`
* **الصلاحية المطلوبة (Permission):** `super_admin.access` (أو `super_admin.plans.view` / `super_admin.plans.manage` لمشغلي المنصة المركزية `CentralUser`).
* **الملف الرئيسي:** `resources/js/views/SuperAdmin/SuperAdminPlansView.vue` (~72 سطرًا).
* **الغرض والتحليل التشغيلي:**
  * إدارة خطط وباقات الاشتراك لمنظومة SaaS متعددة المستأجرين.
  * عرض بطاقات الباقات (مثل الأساسية، المتقدمة، الاحترافية)، وأسعارها الشهرية والسنوية، وشارة الباقة الأكثر طلباً (`is_popular`).
  * تحديد وإدارة قيود الموارد لكل باقة: الحد الأقصى للمستخدمين (`max_users`)، الفروع والمخازن (`max_stores`)، الأصناف (`max_items`)، والفواتير الشهرية (`max_invoices_per_month`).
  * تعديل حدود الميزات والأسعار وتفعيل أو إيقاف الباقات من خلال نافذة تعديل الباقة (`EditPlanModal`).

---

## 2. هيكلية وشجرة المكونات (Component Tree):
```text
SuperAdminPlansView.vue (~72 lines)
├── PageHeader.vue               <-- رأس الصفحة مع أزرار التنقل
├── PlansGrid.vue                <-- شبكة بطاقات الباقات مع هيكل التحميل الوميضي
│   └── PlanCard.vue             <-- بطاقة الباقة الفردية مع الأسعار والحدود وشارات الحالة
└── EditPlanModal.vue            <-- نافذة تعديل بيانات الباقة والأسعار والقيود
```

---

## 3. العناصر المشتركة ومخازن الحالة:
* **المكونات المشتركة:** `PageHeader.vue`, `BaseButton.vue`, `BaseInput.vue`, `CardSkeleton.vue`, `AppModal.vue`.
* **القالب العام:** `SuperAdminLayout.vue`.
* **الـ Composable:** `useSuperAdminPlans.js` لإدارة جلب الباقات وعمليات التعديل والحفظ.
* **المخازن المستخدمة:** `useAuthStore` للتحقق من صلاحية السوبر أدمن.

---

## 4. الاعتماديات والـ APIs:
* `GET /api/v1/super-admin/plans`:
  * **الوصف:** جلب قائمة الباقات المتاحة مع تفاصيل الأسعار وحدود الموارد.
  * **الكنترولر:** `App\Http\Controllers\Api\SuperAdminApiController@plans`
  * **Action:** `App\Actions\Plans\GetSuperAdminPlansDataAction`
  * **Resource:** `App\Http\Resources\PlanResource`
* `PUT /api/v1/super-admin/plans/{id}`:
  * **الوصف:** تحديث بيانات الباقة والأسعار والقيود التشغيلية.
  * **الكنترولر:** `App\Http\Controllers\Api\SuperAdminApiController@updatePlan`
  * **Form Request:** `App\Http\Requests\UpdatePlanRequest`
  * **Action:** `App\Actions\Plans\UpdatePlanAction`
  * **الحقول:** `name`, `price_monthly`, `price_yearly`, `max_users`, `max_stores`, `max_items`, `max_invoices_per_month`, `is_active`, `is_popular`.

---

## 5. الحماية الأمنية والدقة المالية:
* **حماية النطاق المركزي:** التحقق عبر `EnsureCentralContext` و `can:super_admin.access`.
* **الاتصال المركزي:** موديل `Plan` يرتبط بقاعدة البيانات المركزية `central`.
* **الدقة المالية للأسعار:** الأسعار الشهرية والسنوية تعالج بدقة `DECIMAL(12,3)` لضمان النزاهة المحاسبية عند احتساب الاشتراكات والفواتير.
