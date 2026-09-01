# 💼 وثيقة المكون والصفحة: إدارة باقات الاشتراك والأسعار المركزية (`SuperAdminPlansView.vue`)

> **المسار (Route):** `/super-admin/plans`  
> **الملف الرئيسي:** `resources/js/views/SuperAdmin/SuperAdminPlansView.vue` (Thin Orchestrator: ~65 سطر)  
> **تاريخ المراجعة الشاملة:** 2026-08-24  
> **الحالة:** ✅ مكتملة وموثقة 100% وفق المحاور الأربعة المتزامنة.

---

## 1. التحليل التشغيلي والمعماري (Operational & Architectural Analysis)

### 🎯 الغرض من الصفحة:
تُمثل شاشة **إدارة باقات الاشتراك والأسعار المركزية (`/super-admin/plans`)** مركز التحكم في خطط التسعير وحدود الموارد لمنظومة الـ Multi-Tenant SaaS:
1. **شبكة بطاقات الباقات والأسعار (Subscription Plans Grid):** استعراض الباقات المتاحة (مثل البداية، الاحترافية، المؤسسية)، وحالتها (مفعلة/معطلة)، شارة الأكثر طلباً (Popular)، الأسعار الشهرية والسنوية.
2. **حدود الموارد لكل باقة (Resource Limits):** عرض الحد الأقصى للمستخدمين، الفروع والمخازن، الأصناف، والفواتير الشهرية.
3. **نافذة تعديل الأسعار وحدود الموارد (Edit Plan Modal):** نافذة `AppModal` لتعديل اسم الباقة، السعر الشهري، السعر السنوي، وحدود الموارد (مستخدمين، فروع، أصناف، فواتير)، وتحديد ما إذا كانت مفعلة أو الأكثر طلباً.

---

## 2. هيكلية وشجرة المكونات (Component Tree & Architecture)

تم تفكيك الصفحة من ملف بـ 320 سطراً إلى **Thin Orchestrator** ومكونات أحادية المسؤولية:

```text
resources/js/
├── views/SuperAdmin/
│   └── SuperAdminPlansView.vue                <-- Thin Orchestrator (~65 lines)
├── Components/SuperAdmin/
│   ├── PlansGrid.vue                          <-- شبكة بطاقات الباقات وهياكل التحميل
│   ├── PlanCard.vue                           <-- بطاقة الباقة الفردية مع الأسعار والحدود
│   └── EditPlanModal.vue                      <-- نافذة AppModal لتعديل الباقة والأسعار والحدود
└── Composables/
    └── useSuperAdminPlans.js                  <-- كبسولة المنطق وإدارة البيانات والعمليات
```

---

## 3. عناصر النماذج والواجهات المشتركة المستخدمة

* `PageHeader.vue`: ترويسة الصفحة وزر العودة للوحة القيادة وإدارة المستأجرين.
* `BaseButton.vue`: أزرار الحفظ والإلغاء وتعديل الأسعار.
* `BaseInput.vue`: حقول إدخال اسم الباقة والأسعار وحدود الموارد.
* `CardSkeleton.vue`: هيكل التحميل الوميضي للبطاقات.
* `AppModal.vue`: الحاوية الموحدة للنوافذ المنبثقة.
* `DarkSwal`: التنبيهات الموحدة للنجاح والخطأ.

---

## 4. الاعتماديات والـ APIs المرتبطة

| العملية | الـ Endpoint | الطلب (Request Payload) | الاستجابة |
| :--- | :--- | :--- | :--- |
| **جلب سجل الباقات** | `GET /api/v1/super-admin/plans` | - | قائمة الباقات مع الأسعار والحدود |
| **تحديث باقة محددة** | `PUT /api/v1/super-admin/plans/{id}` | `name`, `price_monthly`, `price_yearly`, `max_users`, `max_stores`, `max_items`, `max_invoices_per_month`, `is_active`, `is_popular` | تحديث بيانات الباقة |

---

## 5. فحص التجاوب وتجربة اللمس والوضعين (Responsive & Touch Ergonomics)

* **📱 هواتف (360px - 430px):**
  * ترتيب عمودي لبطاقات الباقات، أزرار بارتفاع $\ge 40	ext{px}$، ونوافذ منبثقة ملائمة للمس.
* **💻 تابلت وديسكتوب (768px - 1280px+):**
  * شبكة ثلاثية الأبعاد لباقات الاشتراك مع تأثيرات Hover متناسقة.
* **🌓 الوضع الداكن والفاتح:** تباين كامل للبطاقات والصفوف وحقول الإدخال.

---

## 6. قاموس الترجمة (100% Zero Hardcoded Localization)

كافة النصوص تستند إلى ملفات الترجمة المركزية في `lang/ar/super.php` و `lang/en/super.php`:
* `super.plans_page_title`: إدارة باقات الاشتراك والأسعار / Subscription Plans Management
* `super.popular_badge`: الأكثر طلباً / Popular
* `super.edit_prices_and_limits_btn`: تعديل الأسعار والحدود / Edit Prices & Limits
* `super.edit_plan_modal_title`: تعديل باقة :name / Edit Plan :name
* `super.max_users_label`: الحد الأقصى للمستخدمين / Max Users

---

## 7. سجل الاختبارات والتحقق (Test Results)

* ✅ **Playwright E2E Test:** `e2e/flows/super-admin-plans-full-page-audit.spec.js` -> نجاح 7/7 اختبارات عبر كافة مقاسات الشاشات الـ 5 بدون أي خطأ Console.
* ✅ **Build Verification:** `npm run build` -> تم البناء بنجاح 100% في 5.18 ثانية.
