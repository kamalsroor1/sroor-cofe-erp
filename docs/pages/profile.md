# 👤 وثيقة المكون والصفحة: الملف الشخصي وإعدادات الحساب (`ProfileView.vue`)

> **المسار (Route):** `/profile`  
> **الملف الرئيسي:** `resources/js/views/Profile/ProfileView.vue` (Thin Orchestrator: ~55 سطر)  
> **تاريخ المراجعة الشاملة:** 2026-08-24  
> **الحالة:** ✅ مكتملة وموثقة 100% عبر المحاور الأربعة المتزامنة.

---

## 1. التحليل التشغيلي والمعماري (Operational & Architectural Analysis)

### 🎯 الغرض من الصفحة:
تُمثل شاشة **الملف الشخصي وإعدادات الحساب والأمان (`/profile`)** مركز إدارة بيانات المستخدم المسجل في المنظومة:
1. **البيانات الأساسية ومعلومات الدخول (Basic Information):** الاسم الكامل، رقم الهاتف المستخدم كمعرف دخول رئيسي، والبريد الإلكتروني الاختياري.
2. **الأمان وكلمة المرور (Security & Password):** تحديث كلمة المرور عبر التحقق من كلمة المرور الحالية وإدخال وتأكيد كلمة المرور الجديدة.
3. **تفضيلات المظهر والواجهة (Appearance & Theme):** اختيار المظهر الليلي (Dark Slate) أو النهاري (Light Shell) وحفظ التفضيل في الحساب.

---

## 2. هيكلية وشجرة المكونات (Component Tree & Architecture)

تم تفكيك الصفحة من ملف بـ 203 أسطر إلى **Thin Orchestrator** ومكونات أحادية المسؤولية:

```text
resources/js/
├── views/Profile/
│   └── ProfileView.vue                        <-- Thin Orchestrator (~55 lines)
├── Components/Profile/
│   ├── ProfileBasicInfoCard.vue               <-- بطاقة البيانات الأساسية (الاسم، الهاتف، البريد)
│   ├── ProfileSecurityCard.vue                <-- بطاقة الأمان وكلمة المرور
│   └── ProfileThemeCard.vue                   <-- بطاقة تفضيلات المظهر النهاري/الليلي
└── Composables/
    └── useProfile.js                          <-- كبسولة المنطق وجلب وتحديث بيانات الملف الشخصي
```

---

## 3. عناصر النماذج والواجهات المشتركة المستخدمة

* `PageHeader.vue`: ترويسة الصفحة الموحدة.
* `BaseButton.vue`: زر الحفظ مع مؤشرات التحميل والتعطيل.
* `BaseInput.vue`: حقول إدخال النصوص وكلمات المرور والبريد.

---

## 4. الاعتماديات والـ APIs المرتبطة

| العملية | الـ Endpoint | الطلب (Request Payload) | الاستجابة |
| :--- | :--- | :--- | :--- |
| **جلب بيانات الملف الشخصي** | `GET /api/v1/profile` | - | بيانات المستخدم والتفضيلات |
| **تحديث الملف الشخصي** | `PUT /api/v1/profile` | `name`, `phone`, `email`, `current_password`, `new_password`, `new_password_confirmation`, `theme_preference` | تحديث البيانات ورسالة النجاح |

---

## 5. فحص التجاوب وتجربة اللمس والوضعين (Responsive & Touch Ergonomics)

* **📱 هواتف (360px - 430px):**
  * ترتيب عمودي للبطاقات، حقول إدخال وأزرار بارتفاع $\ge 40	ext{px}$، وأزرار تبديل المظهر لمسية واضحة.
* **💻 تابلت وديسكتوب (768px - 1280px+):**
  * حاوية مركزية بحد أقصى `max-w-3xl` مع تباعد متناسق وبطاقات محمية.
* **🌓 الوضع الداكن والفاتح:** تباين كامل للبطاقات والصفوف وحقول الإدخال.

---

## 6. قاموس الترجمة (100% Zero Hardcoded Localization)

كافة النصوص تستند إلى ملفات الترجمة المركزية في `lang/ar/profile.php` و `lang/en/profile.php`:
* `profile.title`: الملف الشخصي وإعدادات الحساب / User Profile & Account Settings
* `profile.basic_info`: البيانات الأساسية / Basic Information
* `profile.security_password_title`: الأمان وتغيير كلمة المرور / Security & Change Password
* `profile.theme_pref`: المظهر المفضل للواجهة / Appearance & Theme Preference
* `profile.save_changes`: حفظ التعديلات / Save Changes

---

## 7. سجل الاختبارات والتحقق (Test Results)

* ✅ **Playwright E2E Test:** `e2e/flows/profile-full-page-audit.spec.js` -> نجاح 7/7 اختبارات عبر كافة مقاسات الشاشات الـ 5 بدون أي خطأ Console.
* ✅ **Build Verification:** `npm run build` -> تم البناء بنجاح 100% في 2.84 ثانية.
