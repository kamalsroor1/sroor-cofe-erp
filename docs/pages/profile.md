# 👤 وثيقة المكون والصفحة: الملف الشخصي وإعدادات الحساب (`ProfileView`)

## 1. النظرة العامة والتحليل التشغيلي:
* **اسم الصفحة:** الملف الشخصي وإعدادات الحساب (User Profile & Account Settings)
* **المسار (Route):** `/profile`
* **اسم المسار (Route Name):** `profile.show`
* **الصلاحية المطلوبة (Permission):** لا تتطلب صلاحية خاصة (متاحة لكافة المستخدمين المصادقين `auth:sanctum`).
* **الملف الرئيسي:** `resources/js/views/Profile/ProfileView.vue` (~51 سطرًا).
* **الغرض والتحليل التشغيلي:**
  * إدارة بيانات حساب المستخدم المسجل حالياً في المنظومة.
  * تعديل الاسم الكامل، رقم الهاتف (المعرف الرئيسي لتسجيل الدخول)، والبريد الإلكتروني الاختياري.
  * تحديث كلمة المرور بشكل آمن عبر طلب كلمة المرور الحالية وتأكيد كلمة المرور الجديدة.
  * حفظ تفضيلات المظهر المفضل للواجهة (الوضع الليلي `dark` أو النهاري `light`) واللغة المفضلة (`locale`).

---

## 2. هيكلية وشجرة المكونات (Component Tree):
```text
ProfileView.vue (~51 lines)
├── PageHeader.vue              <-- رأس الصفحة مع الأيقونة التعبيرية والعناوين
├── ProfileBasicInfoCard.vue     <-- بطاقة البيانات الأساسية (الاسم، الهاتف، البريد)
├── ProfileSecurityCard.vue      <-- بطاقة الأمان وتغيير كلمة المرور
├── ProfileThemeCard.vue         <-- بطاقة تفضيل المظهر النهاري/الليلي
└── BaseButton.vue               <-- زر حفظ التعديلات مع حالة التحميل
```

---

## 3. العناصر المشتركة ومخازن الحالة:
* **المكونات المشتركة:** `PageHeader.vue`, `BaseButton.vue`, `BaseInput.vue`.
* **الـ Composable:** `useProfile.js` لإدارة نموذج الإدخال وعمليات الاتصال بالـ API وتحديث بيانات المستخدم.
* **المخازن المستخدمة:** `useAuthStore` لتحديث حالة المستخدم وبيانات الجلسة عند تعديل الاسم أو الهاتف أو المظهر.

---

## 4. الاعتماديات والـ APIs:
* `GET /api/v1/profile` (أو مسار المستأجر `/profile`):
  * **الوصف:** جلب بيانات الملف الشخصي للمستخدم الحالي مع أدواره وفرعه الافتراضي.
  * **الكنترولر:** `App\Http\Controllers\Api\ProfileController@show`
  * **Resource:** `App\Http\Resources\UserResource`
* `PUT /api/v1/profile` (أو مسار المستأجر `/profile`):
  * **الوصف:** تحديث بيانات الملف الشخصي وتفضيلات المظهر واللغة وكلمة المرور.
  * **الكنترولر:** `App\Http\Controllers\Api\ProfileController@update`
  * **Form Request:** `App\Http\Requests\UpdateProfileRequest`
  * **Action:** `App\Actions\Profile\UpdateProfileAction`
  * **الحقول المدعومة:** `name`, `phone`, `email`, `current_password`, `new_password`, `new_password_confirmation`, `theme_preference`, `locale`.

---

## 5. الحماية الأمنية وعزل البيانات:
* **التحقق من كلمة المرور الحالية:** عند طلب تغيير كلمة المرور (`new_password`)، يشترط `UpdateProfileRequest` وجود `current_password` ويتم التحقق من صحتها بواسطة `Hash::check` قبل تطبيق التحديث.
* **تفرد الهاتف والبريد:** يتم التحقق من فرادة الهاتف والبريد مع تجاهل المعرف الحالي للمستخدم (`Rule::unique('users', ...)->ignore($userId)`).
* **عزل المستأجر:** تعديلات المستخدم تنعكس حصراً على سجله داخل قاعدة بيانات المستأجر المعزولة.
