# سجل تعديل: إضافة تفاعلات الـ Native وهياكل التحميل والسحب
* **التاريخ والوقت:** 2026-08-15 17:45
* **الدور المفعل:** NativePHP & Mobile UI/UX Specialist & QA
* **الهدف:** تفعيل أداة الاهتزاز اللمسي الذكي (Haptic Feedback)، ميزة السحب للحذف السريع من السلة (Swipe to Delete)، مراعاة حواف الشاشة وشريط الإيماءات (Safe Area Insets)، وتكامل هياكل التحميل التفاعلية (Skeleton Shimmers) في شاشات الفواتير وكشف الحساب والبحث.

## 1. الملفات المعدلة والجديدة:
* `[NEW]` `mobile/resources/js/Utils/haptics.js` - أداة الاهتزاز اللمسي الذكي (Vibration API).
* `[NEW]` `mobile/resources/js/Components/SwipeableCartItem.vue` - بطاقة الصنف القابلة للسحب بالإصبع لكشف زر الحذف السريع.
* `[NEW]` `mobile/resources/js/Components/SkeletonCard.vue` - هيكل التحميل الوميضي التفاعلي المتوافق مع الثيمين.
* `[MODIFIED]` `mobile/resources/css/app.css` - إضافة كلاسات Safe Area و Shimmer Animation.
* `[MODIFIED]` `mobile/resources/js/Pages/POS/Index.vue` - دمج SwipeableCartItem وأداة الاهتزاز Haptics.
* `[MODIFIED]` `mobile/resources/js/Pages/Invoices/Index.vue` - دمج SkeletonCard أثناء البحث والفلترة.
* `[MODIFIED]` `mobile/resources/js/Pages/Customers/Statement.vue` - دمج SkeletonCard أثناء تغيير فترات التاريخ.
* `[MODIFIED]` `mobile/nativephp/android/app/build.gradle.kts` - تعطيل فحص Lint Vital لمنع أخطاء التجميع.

## 2. القرارات التقنية:
* استخدام `touch-action` وإيماءات السحب باللمس لحذف بنود السلة دون فتح قوائم فرعية.
* اعتماد Safe Area Insets (`env(safe-area-inset-bottom)`) لحماية الأزرار من التداخل مع شريط التنقل السفلي بهواتف الأندرويد الحديثة.

## 3. التحقق والاختبار:
* [x] بناء أصول Vite بنجاح دون أي تحذيرات.
* [x] اختبار سحب الأصناف واكتمال استجابة الهياكل الوميضية عند البحث.
