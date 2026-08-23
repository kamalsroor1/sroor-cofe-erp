# سجل تعديل: نظام تسجيل الدخول (Login & Authentication) وتحديث GitHub Actions CI إلى PHP 8.4

* **التاريخ والوقت:** 2026-08-08 22:40
* **الدور المفعل:** Full Stack AI Squad (Backend + UI + DevOps)
* **الهدف من التعديل:** بناء منظومة تسجيل الدخول الكاملة والأمان، حماية كافة شاشات النظام بـ `auth` middleware، وتزويد المستخدم ببيانات الدخول، وتحديث إعدادات GitHub Actions لتتوافق مع PHP 8.4 لحل تعارض حزم Symfony في الـ CI/CD.

---

## 1. بيانات تسجيل الدخول الرسمية للنظام (Default Credentials):

* 🌐 **صفحة الدخول المباشرة:** `https://sroor.baraa-solutions.com/login`
* 👤 **البريد الإلكتروني:** `admin@sroor.com`
* 🔑 **كلمة المرور:** `password`
* 👥 **اسم المدير:** `المدير العام (كمال سرور)`
* 🛡️ **نوع الحساب:** `مدير نظام كامل الصلاحيات (Super Admin)`

---

## 2. الميزات والتحصينات الأمنية المضافة (Security Decisions):
* **Rate Limiting (الحماية من التخمين):** قفل المحاولات بعد 5 محاولات خاطئة لمدة 60 ثانية مع تنبيه SweetAlert2.
* **Session Fixation Prevention:** استدعاء `session()->regenerate()` عند نجاح تسجيل الدخول.
* **Eye Password Toggle:** إمكانية إظهار/إخفاء كلمة المرور تفاعلياً بـ Alpine.js.
* **RequiresAuth Trait & Auth Middleware:** حماية كافة شاشات المبيعات والمخزن وكشف الحساب والتقارير بحيث لا يمكن لأي زائر غير مسجل الدخول الوصول إليها.
* **GitHub Actions CI/CD:** ترقية بيئة الفحص في `.github/workflows/deploy.yml` إلى **PHP 8.4** لاجتياز اختبارات الـ 35 Feature Tests بنجاح تام.
