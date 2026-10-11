# 💼 وثيقة المكون والصفحة: إدارة باقات الاشتراك والأسعار المركزية (`SuperAdminPlansView.vue`)

> **المسار (Route):** `/super-admin/plans`  
> **الملف الرئيسي:** `resources/js/views/SuperAdmin/SuperAdminPlansView.vue` (Thin Orchestrator: ~65 سطر)  
> **تاريخ التحديث الشامل:** 2026-10-11  
> **الحالة المعمارية:** ✅ محدثة وموثقة 100% وفق بنية المنصة المركزية (`backend/routes/central.php`).

---

## 1. التحليل التشغيلي والمعماري (Operational & Architectural Analysis)

### 🎯 الغرض من الصفحة:
تُمثل شاشة **إدارة باقات الاشتراك والأسعار المركزية (`/super-admin/plans`)** مركز التحكم في خطط التسعير وحدود الموارد لمنظومة الـ Multi-Tenant SaaS المدارة عبر المصادقة المركزية:
1. **شبكة بطاقات الباقات والأسعار (Subscription Plans Grid):** استعراض الباقات المتاحة (مثل البداية، الاحترافية، المؤسسية)، وحالتها (مفعلة/معطلة)، شارة الأكثر طلباً (Popular)، والأسعار الشهرية والسنوية بدقة `DECIMAL(12,3)`.
2. **حدود الموارد لكل باقة (Resource Limits):** عرض الحد الأقصى للمستخدمين، الفروع والمخازن، الأصناف، والفواتير الشهرية.
3. **نافذة تعديل الأسعار وحدود الموارد (Edit Plan Modal):** نافذة `AppModal` لتعديل اسم الباقة، السعر الشهري، السعر السنوي، وحدود الموارد، وتتطلب تأكيد المصادقة الثنائية `RequireRecentTwoFactor` لحماية أسعار وخطط المنظومة.

---

## 2. هيكلية وشجرة المكونات (Component Tree & Architecture)

تم بناء الصفحة بنمط **Thin Orchestrator** ومكونات أحادية المسؤولية:

```text
resources/js/
├── views/SuperAdmin/
│   └── SuperAdminPlansView.vue                <-- Thin Orchestrator (~65 lines)
├── Components/SuperAdmin/
│   ├── PlansGrid.vue                          <-- شبكة بطاقات الباقات وهياكل التحميل
│   ├── PlanCard.vue                           <-- بطاقة الباقة الفردية مع الأسعار والحدود
│   └── EditPlanModal.vue                      <-- نافذة AppModal لتعديل الباقة والأسعار والحدود
└── Composables/
    └── useSuperAdminPlans.js                  <-- كبسولة المنطق وإدارة البيانات والعمليات عبر centralApi
```

---

## 3. عناصر النماذج والواجهات المشتركة المستخدمة

* `PageHeader.vue`: ترويسة الصفحة وزر العودة للوحة القيادة وإدارة المستأجرين.
* `BaseButton.vue`: أزرار الحفظ والإلغاء وتعديل الأسعار.
* `BaseInput.vue`: حقول إدخال اسم الباقة والأسعار وحدود الموارد.
* `CardSkeleton.vue`: هيكل التحميل للجداول والبطاقات.
* `AppModal.vue`: الحاوية الموحدة للنوافذ المنبثقة.
* `DarkSwal`: التنبيهات الموحدة للنجاح والخطأ.

---

## 4. الاعتماديات والـ APIs المرتبطة (Central Routes `backend/routes/central.php`)

كافة المسارات تعمل تحت وسيط المصادقة المركزية `AuthenticateCentral` وعزل الدومين المركزي `EnsureCentralContext`:

| العملية | الـ Endpoint | الكنترولر والوسائط | الطلب (Request Payload) | الاستجابة |
| :--- | :--- | :--- | :--- | :--- |
| **جلب سجل الباقات** | `GET /api/v1/super-admin/plans` | `SuperAdminApiController`<br>`can:super_admin.plans.view` | - | قائمة الباقات مع الأسعار والحدود |
| **تحديث باقة محددة** | `PUT /api/v1/super-admin/plans/{id}` | `SuperAdminApiController`<br>`can:super_admin.plans.manage`<br>`RequireRecentTwoFactor` | `name`, `price_monthly`, `price_yearly`, `max_users`, `max_stores`, `max_items`, `max_invoices_per_month`, `is_active`, `is_popular` | 200 تحديث بيانات الباقة |

---

## 5. فحص التجاوب وتجربة اللمس والوضعين (Responsive & Touch Ergonomics)

* **📱 هواتف (360px - 430px):**
  * ترتيب عمودي لبطاقات الباقات، أزرار بارتفاع $\ge 40\text{px}$، ونوافذ منبثقة ملائمة للمس.
* **💻 تابلت وديسكتوب (768px - 1280px+):**
  * شبكة ثلاثية الأبعاد لباقات الاشتراك مع تأثيرات Hover متناسقة.
* **🌓 الوضع الداكن والفاتح:** تباين كامل للبطاقات والصفوف وحقول الإدخال عبر Tailwind dark mode.

---

## 6. قاموس الترجمة (100% Zero Hardcoded Localization)

كافة النصوص تستند إلى ملفات الترجمة المركزية في `lang/ar/super.php` و `lang/en/super.php`:
* `super.plans_page_title`: إدارة باقات الاشتراك والأسعار / Subscription Plans Management
* `super.popular_badge`: الأكثر طلباً / Popular
* `super.edit_prices_and_limits_btn`: تعديل الأسعار والحدود / Edit Prices & Limits
* `super.edit_plan_modal_title`: تعديل باقة :name / Edit Plan :name
* `super.max_users_label`: الحد الأقصى للمستخدمين / Max Users

---

## 7. سجل الاختبارات والتحقق (Test Results & Audit Status)

* ✅ **Playwright E2E Test:** `e2e/flows/super-admin-plans-full-page-audit.spec.js` (تتطلب تشغيل خادم التحكم المركزي المحلي).
* ✅ **Feature Tests:**
  * `Tests\Feature\SuperAdminSolidTest` -> نجاح اختبارات باقات السوبر أدمن.
* ⚠️ **غير متحقق:** تعديل أسعار باقات الإنتاج الحية (محظور بموجب قواعد حماية السيرفر الحي).
