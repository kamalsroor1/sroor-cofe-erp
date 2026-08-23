# سجل تعديل: تحريكات وانتقالات فيزيائية سلسة (Smooth Native Transitions) للقوائم والـ Sidebar

* **التاريخ والوقت:** 2026-08-21 02:52
* **الدور المفعل:** Frontend / UI Agent & Native UX Specialist
* **الهدف:** القضاء تماماً على ظهور واختفاء القوائم الجانبية (Sidebar) والـ Dropdown Menus والنوافذ السفلية بشكل مفاجئ وجعل كافة الانتقالات فائقة النعومة والمرونة الفيزيائية (Spring Physics) مثل تطبيقات الموبايل الأصلية (Native iOS / Android).

---

## 1. الملفات المعدلة:
* [MODIFIED] ackend/resources/css/app.css - إضافة فئات الانتقال الفيزيائي الخاصة بـ Vue Transitions (.sidebar-drawer, .dropdown-pop, .sheet-slide, .fade, .modal-zoom) باستخدام منحنى الحركة الأصلي cubic-bezier(0.16, 1, 0.3, 1).
* [MODIFIED] ackend/resources/js/Components/ActionMenu.vue - تغليف القائمة المنسدلة في <Transition name="dropdown-pop"> وتغليف القائمة السفلية في <Transition name="sheet-slide">.
* [MODIFIED] ackend/resources/js/Layouts/AppLayout.vue - فصل الـ Desktop Sidebar عن Mobile Drawer، وتغليف درج الموبايل الجانبي في <Transition name="sidebar-drawer"> وخلفية التعتيم في <Transition name="fade">، وتغليف قوائم الإشعارات والمستخدم ونافذة تبديل الفرع في Transitions مخصصة.
* [MODIFIED] ackend/resources/js/Layouts/SuperAdminLayout.vue - تغليف درج الموبايل الجانبي السوبر أدمن في <Transition name="sidebar-drawer"> وخلفية التعتيم في <Transition name="fade">.

---

## 2. القرارات التقنية والفيزيائية:
1. **منحنى الحركة الانسيابي (Deceleration Spring Curve):**
   * استخدام cubic-bezier(0.16, 1, 0.3, 1) في حركة الانزلاق الجانبي لدرج الملاحة (Right-to-Left Slide in RTL) بدلاً من display: none أو الانتقال الخطي، مما يعطي إحساساً أصيلاً بتطبيقات الموبايل الفاخرة.
2. **انبثاق القوائم المنسدلة (Dropdown Spring Pop):**
   * إضافة حركة scale(0.92) translateY(-8px) مع تلاشي تدريجي opacity ونقطة ارتكاز 	ransform-origin: top right لتنبثق القائمة بنعومة من تحت زر ••• مباشرة وتختفي بنعومة.
3. **انزلاق القوائم السفلية (Bottom Sheet Slide):**
   * انزلاق الحاوية السفلية بسلاسة من الأسفل للأعلى مع تلاشي خلفية التعتيم ackdrop-blur-xs.

---

## 3. التحقق والاختبار:
* [x] تم بناء الأصول بنجاح تام عبر 
pm run build (0 أخطاء).
* [x] تمت المزامنة مع منصة أندرويد عبر 
px cap sync android.
* [x] تم الالتزام بهوية النظام واللغة العربية RTL وخطوط Cairo/Tajawal.
