# سجل تعديل: التحول إلى Vue 3 + Inertia.js في المصادقة والمسارات
* **التاريخ والوقت:** 2026-08-20 01:23
* **الدور المفعل:** Mobile Backend & Inertia Vue 3 Specialist
* **الهدف:** استبدال مسارات Livewire بمحرك Inertia.js + Vue 3 لشاشات تسجيل الدخول والخروج والداشبورد، دون حذف ملفات Livewire.

## 1. الملفات المنشأة والمعدلة:
* [NEW] backend/resources/js/Pages/Auth/Login.vue - واجهة تسجيل الدخول الحديثة بـ Vue 3 و Inertia useForm.
* [NEW] backend/app/DTOs/Auth/LoginDTO.php - كائن نقل بيانات المصادقة.
* [NEW] backend/app/Http/Requests/Auth/LoginRequest.php - Form Request للتحقق وحماية معدل المحاولات.
* [NEW] backend/app/Actions/Auth/LoginAction.php - Single Action لتسجيل الدخول والمزامنة المركزية للسوبر أدمن.
* [NEW] backend/app/Http/Controllers/Auth/AuthenticatedSessionController.php - كنترولر Inertia للمصادقة والخروج.
* [MODIFIED] backend/routes/web.php - توجيه /login و /logout إلى AuthenticatedSessionController.
* [MODIFIED] backend/routes/tenant.php - توجيه /login و /logout في سياق المستأجر إلى AuthenticatedSessionController.
* [MODIFIED] backend/lang/ar/auth.php و backend/lang/en/auth.php - توفير كافة نصوص ومفاتيح الترجمة لشاشة الدخول.
* [MODIFIED] backend/app/Http/Middleware/HandleInertiaRequests.php - مشاركة مصفوفة ترجمات auth مع واجهة Vue 3.

## 2. القرارات التقنية:
* إيقاف مسارات Livewire الخاصة بالدخول والخروج تدريجياً دون حذف كود Livewire من المشروع.
* تطبيق Clean Architecture و Single Action Pattern في طبقة المصادقة الجديدة.

## 3. التحقق والاختبار:
* [x] خلو كود PHP و Vue 3 من أخطاء الـ Syntax.
* [x] نجاح بناء الحزم الإنتاجية npm run build وتوليد Login-*.js.
* [x] اختبار استجابة http://sroor.makhzani.test/login والحصول على 200 OK بمكون Auth/Login.
* [x] اختبار تسجيل الدخول بنجاح وإعادة التوجيه إلى الداشبورد (302 Found).
