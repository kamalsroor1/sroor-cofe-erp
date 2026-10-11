# 🚀 وثيقة المكون والصفحة: إدارة إصدارات التطبيق وحزم الـ APK (`SuperAdminAppVersionsView.vue`)

> **المسار (Route):** `/super-admin/app-versions`  
> **الملف الرئيسي:** `resources/js/views/SuperAdmin/SuperAdminAppVersionsView.vue` (Thin Orchestrator: ~65 سطر)  
> **تاريخ التحديث الشامل:** 2026-10-11  
> **الحالة المعمارية:** ✅ محدثة وموثقة 100% وفق بنية المنصة المركزية (`SuperAdminAppVersionController`).

---

## 1. التحليل التشغيلي والمعماري (Operational & Architectural Analysis)

### 🎯 الغرض من الصفحة:
تُمثل شاشة **إدارة إصدارات التطبيق وحزم الـ APK (`/super-admin/app-versions`)** مركز التحكم في التحديثات الهوائية (OTA Releases) لتطبيقات أندرويد وديسكتوب المصاحبة للمنظومة:
1. **رأس الصفحة وإجراءات النشر السريع:** عنوان الصفحة وزر رفع ونشر إصدار APK جديد محمي بالمصادقة الثنائية `RequireRecentTwoFactor`.
2. **مؤشرات الحزم والتنزيلات (Releases & Downloads KPI Summary):** الإصدار النشط حالياً، إجمالي عدد التنزيلات عبر التطبيق، وإجمالي عدد الإصدارات المنشورة.
3. **سجل وجدول حزم الـ APK المنشورة (Releases History Ledger):** استعراض رقم الإصدار (Version Name & Code)، المنصة (Android, Desktop)، نوع التحديث (إلزامي Force Update أو اختياري)، الحجم، التحميلات، تاريخ النشر، تفعيل/تعطيل الإصدار، وزر التحميل المباشر وزر الحذف.
4. **نافذة رفع ونشر إصدار APK جديد (Upload & Publish APK Modal):** نافذة `AppModal` لإدخال اسم الإصدار، رقم الكود، المنصة، الحد الأدنى المقبول، تبديل التحديث الإلزامي، ملاحظات الإصدار بالعربية، ورفع ملف الـ `.apk`. يتم فحص الملفات المرفوعة والتحقق من التوقيع الرقمي وصحة حزمة Android بواسطة `ReleaseBinaryInspector` وتخزينها على القرص الخاص المحمي.

---

## 2. هيكلية وشجرة المكونات (Component Tree & Architecture)

تم بناء الصفحة بنمط **Thin Orchestrator** ومكونات أحادية المسؤولية:

```text
resources/js/
├── views/SuperAdmin/
│   └── SuperAdminAppVersionsView.vue          <-- Thin Orchestrator (~65 lines)
├── Components/SuperAdmin/
│   ├── AppVersionsSummaryGrid.vue             <-- بطاقات المؤشرات الثلاثية للحزم والتحميلات
│   ├── AppVersionsTable.vue                   <-- جدول وسجل الحزم وبطاقات الهواتف مع الشارات
│   └── UploadApkModal.vue                     <-- نافذة AppModal لرفع ونشر إصدار APK جديد
└── Composables/
    └── useSuperAdminAppVersions.js            <-- كبسولة المنطق وإدارة البيانات والعمليات عبر centralApi
```

---

## 3. عناصر النماذج والواجهات المشتركة المستخدمة

* `PageHeader.vue`: ترويسة الصفحة وزر النشر السريع.
* `BaseButton.vue`: أزرار الحفظ والإلغاء والرفع.
* `BaseInput.vue`: حقول إدخال اسم الإصدار، كود الإصدار، والحد الأدنى.
* `TableSkeleton.vue`: هيكل التحميل للجداول.
* `EmptyState.vue`: حالة عدم وجود إصدارات منشورة.
* `AppModal.vue`: الحاوية الموحدة للنوافذ المنبثقة.
* `DarkSwal`: التنبيهات الموحدة للنجاح والخطأ والحذف.

---

## 4. الاعتماديات والـ APIs المرتبطة (Central Routes `backend/routes/central.php`)

كافة المسارات تعمل تحت وسيط المصادقة المركزية `AuthenticateCentral` وعزل الدومين المركزي `EnsureCentralContext`:

| العملية | الـ Endpoint | الكنترولر والوسائط | الطلب (Request Payload) | الاستجابة |
| :--- | :--- | :--- | :--- | :--- |
| **جلب قائمة الإصدارات والإحصائيات** | `GET /api/v1/super-admin/app-versions` | `SuperAdminAppVersionController`<br>`can:super_admin.app_versions.view` | - | قائمة الإصدارات وملخص التنزيلات |
| **نشر إصدار جديد** | `POST /api/v1/super-admin/app-versions` | `SuperAdminAppVersionController`<br>`can:super_admin.app_versions.manage`<br>`RequireRecentTwoFactor` | `FormData` (platform, version_name, version_code, min_version_code, is_force_update, release_notes_ar, apk_file) | 201 نشر الحزمة وحفظها |
| **تبديل تفعيل الإصدار** | `PATCH /api/v1/super-admin/app-versions/{id}/toggle-active` | `SuperAdminAppVersionController`<br>`can:super_admin.app_versions.manage`<br>`RequireRecentTwoFactor` | - | 200 تحديث حالة التفعيل |
| **حذف إصدار** | `DELETE /api/v1/super-admin/app-versions/{id}` | `SuperAdminAppVersionController`<br>`can:super_admin.app_versions.manage`<br>`RequireRecentTwoFactor` | - | 200 حذف الحزمة |

---

## 5. فحص التجاوب وتجربة اللمس والوضعين (Responsive & Touch Ergonomics)

* **📱 هواتف (360px - 430px):**
  * ترتيب عمودي للبطاقات، بطاقات لمسية متراصة لكل إصدار APK مع أزرار تحميل وتفعيل بارتفاع $\ge 40\text{px}$.
* **💻 تابلت وديسكتوب (768px - 1280px+):**
  * جدول مركزي عالي الكثافة مع روابط مباشرة لتنزيل الحزم وشارات ملونة.
* **🌓 الوضع الداكن والفاتح:** تباين كامل للبطاقات والصفوف وحقول الإدخال عبر Tailwind dark mode.

---

## 6. قاموس الترجمة (100% Zero Hardcoded Localization)

كافة النصوص تستند إلى ملفات الترجمة المركزية في `lang/ar/super.php` و `lang/en/super.php`:
* `super.app_versions_page_title`: إدارة إصدارات التطبيق وحزم الـ APK / App Versions & APK Management
* `super.publish_new_apk_btn`: نشر إصدار APK جديد / Publish New APK
* `super.current_active_version`: الإصدار النشط حالياً / Currently Active Version
* `super.upload_apk_modal_title`: نشر إصدار APK جديد للتطبيق / Publish New APK Release
* `super.mandatory_update_badge`: إلزامي / Mandatory

---

## 7. سجل الاختبارات والتحقق (Test Results & Audit Status)

* ✅ **Playwright E2E Test:** `e2e/flows/super-admin-app-versions-full-page-audit.spec.js` (تتطلب تشغيل خادم التحكم المركزي المحلي).
* ✅ **Feature & Unit Tests:**
  * `Tests\Unit\Support\AppReleases\ReleaseBinaryInspectorTest` -> نجاح فحص الحزم الثنائية.
  * `Tests\Unit\Support\AppReleases\SemverCodeTest` -> نجاح التحقق من أكواد الإصدارات.
* ⚠️ **غير متحقق:** التحميل المباشر لملفات APK الضخمة عبر السيرفر الحي (محظور بموجب قواعد حماية السيرفر الحي).
