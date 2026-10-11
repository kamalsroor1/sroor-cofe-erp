# 🚀 وثيقة المكون والصفحة: إدارة إصدارات التطبيق وحزم الـ APK (`SuperAdminAppVersionsView.vue`)

> **المسار (Route):** `/super-admin/app-versions`  
> **الملف الرئيسي:** `resources/js/views/SuperAdmin/SuperAdminAppVersionsView.vue` (Thin Orchestrator: ~65 سطر)  
> **تاريخ المراجعة الشاملة:** 2026-08-24  
> **الحالة:** ✅ مكتملة وموثقة 100% وفق المحاور الأربعة المتزامنة.

---

## 1. التحليل التشغيلي والمعماري (Operational & Architectural Analysis)

### 🎯 الغرض من الصفحة:
تُمثل شاشة **إدارة إصدارات التطبيق وحزم الـ APK (`/super-admin/app-versions`)** مركز التحكم في التحديثات الهوائية (OTA Releases) للتطبيقات المصاحبة للمنظومة:
1. **رأس الصفحة وإجراءات النشر السريع:** عنوان الصفحة وزر رفع ونشر إصدار APK جديد.
2. **مؤشرات الحزم والتنزيلات (Releases & Downloads KPI Summary):** الإصدار النشط حالياً، إجمالي عدد التنزيلات عبر التطبيق، وإجمالي عدد الإصدارات المنشورة.
3. **سجل وجدول حزم الـ APK المنشورة (Releases History Ledger):** استعراض رقم الإصدار (Version Name & Code)، المنصة (Android, Windows, iOS)، نوع التحديث (إلزامي Force Update أو اختياري)، الحجم، التحميلات، تاريخ النشر، تفعيل/تعطيل الإصدار، وزر التحميل المباشر وزر الحذف.
4. **نافذة رفع ونشر إصدار APK جديد (Upload & Publish APK Modal):** نافذة `AppModal` لإدخال اسم الإصدار، رقم الكود، المنصة، الحد الأدنى المقبول، تبديل التحديث الإلزامي، ملاحظات الإصدار بالعربية، ورفع ملف الـ `.apk`.

---

## 2. هيكلية وشجرة المكونات (Component Tree & Architecture)

تم تفكيك الصفحة من ملف بـ 452 سطراً إلى **Thin Orchestrator** ومكونات أحادية المسؤولية:

```text
resources/js/
├── views/SuperAdmin/
│   └── SuperAdminAppVersionsView.vue          <-- Thin Orchestrator (~65 lines)
├── Components/SuperAdmin/
│   ├── AppVersionsSummaryGrid.vue             <-- بطاقات المؤشرات الثلاثية للحزم والتحميلات
│   ├── AppVersionsTable.vue                   <-- جدول وسجل الحزم وبطاقات الهواتف مع الشارات
│   └── UploadApkModal.vue                     <-- نافذة AppModal لرفع ونشر إصدار APK جديد
└── Composables/
    └── useSuperAdminAppVersions.js            <-- كبسولة المنطق وإدارة البيانات والعمليات
```

---

## 3. عناصر النماذج والواجهات المشتركة المستخدمة

* `PageHeader.vue`: ترويسة الصفحة مع شارة "OTA Updater" وزر النشر السريع.
* `BaseButton.vue`: أزرار الحفظ والإلغاء والرفع.
* `BaseInput.vue`: حقول إدخال اسم الإصدار، كود الإصدار، والحد الأدنى.
* `TableSkeleton.vue`: هيكل التحميل الوميضي للجداول.
* `EmptyState.vue`: حالة عدم وجود إصدارات منشورة.
* `AppModal.vue`: الحاوية الموحدة للنوافذ المنبثقة.
* `DarkSwal`: التنبيهات الموحدة للنجاح والخطأ والحذف.

---

## 4. الاعتماديات والـ APIs المرتبطة

| العملية | الـ Endpoint | الطلب (Request Payload) | الاستجابة |
| :--- | :--- | :--- | :--- |
| **جلب قائمة الإصدارات والإحصائيات** | `GET /api/v1/super-admin/app-versions` | - | قائمة الإصدارات وملخص التنزيلات |
| **نشر إصدار APK جديد** | `POST /api/v1/super-admin/app-versions` | `FormData` (platform, version_name, version_code, min_version_code, is_force_update, release_notes_ar, apk_file) | نشر الحزمة وحفظها |
| **تبديل تفعيل الإصدار** | `PATCH /api/v1/super-admin/app-versions/{id}/toggle-active` | - | تحديث حالة التفعيل |
| **حذف إصدار** | `DELETE /api/v1/super-admin/app-versions/{id}` | - | حذف الحزمة من السيرفر |

---

## 5. فحص التجاوب وتجربة اللمس والوضعين (Responsive & Touch Ergonomics)

* **📱 هواتف (360px - 430px):**
  * ترتيب عمودي للبطاقات، بطاقات لمسية متراصة لكل إصدار APK مع أزرار تحميل وتفعيل بارتفاع $\ge 40	ext{px}$.
* **💻 تابلت وديسكتوب (768px - 1280px+):**
  * جدول مركزي عالي الكثافة مع روابط مباشرة لتنزيل الحزم وشارات ملونة.
* **🌓 الوضع الداكن والفاتح:** تباين كامل للبطاقات والصفوف وحقول الإدخال.

---

## 6. قاموس الترجمة (100% Zero Hardcoded Localization)

كافة النصوص تستند إلى ملفات الترجمة المركزية في `lang/ar/super.php` و `lang/en/super.php`:
* `super.app_versions_page_title`: إدارة إصدارات التطبيق وحزم الـ APK / App Versions & APK Management
* `super.publish_new_apk_btn`: نشر إصدار APK جديد / Publish New APK
* `super.current_active_version`: الإصدار النشط حالياً / Currently Active Version
* `super.upload_apk_modal_title`: نشر إصدار APK جديد للتطبيق / Publish New APK Release
* `super.mandatory_update_badge`: إلزامي / Mandatory

---

## 7. سجل الاختبارات والتحقق (Test Results)

* ✅ **Playwright E2E Test:** `e2e/flows/super-admin-app-versions-full-page-audit.spec.js` -> نجاح 7/7 اختبارات عبر كافة مقاسات الشاشات الـ 5 بدون أي خطأ Console.
* ✅ **Build Verification:** `npm run build` -> تم البناء بنجاح 100% في 5.32 ثانية.
