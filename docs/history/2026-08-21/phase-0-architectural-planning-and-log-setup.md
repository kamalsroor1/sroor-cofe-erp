# سجل تعديل: المرحلة 0 - التخطيط والقرارات المعمارية للتحويل إلى Pure API
* **التاريخ والوقت:** 2026-08-21 14:30
* **الدور المفعل:** Backend & Architecture Specialist / PM Agent
* **الهدف:** فحص الوضع الحالي للمشروع بالكامل، صياغة واعتماد القرارات المعمارية (Auth, Routes, Tenancy, Error Handling, State Management, Response Envelopes)، وترتيب خطة ترحيل الموديولات تدريجياً، وإنشاء ملف التتبع الرسمي `api-migration-log.md`.

## 1. الملفات المنشأة / المعدلة:
* `[NEW]` `api-migration-log.md` - سجل الترحيل الشامل وخريطة طريق المراحل والـ 16 موديول.
* `[NEW]` `docs/history/2026-08-21/phase-0-architectural-planning-and-log-setup.md` - توثيق قرارات المرحلة 0 وفق بروتوكول الذكاء الاصطناعي.

## 2. القرارات التقنية المعمارية:
* **نوع المصادقة:** اعتماد Laravel Sanctum Token-based (`Bearer <token>`) لتوحيد بيئة الـ Web SPA والموبايل وتفادي تعقيدات الـ Subdomain Cookies مع stancl/tenancy.
* **التعرف على المستأجر:** استراتيجية مزدوجة تدعم النطاقات/النطاقات الفرعية وهيدر `X-Tenant`.
* **هيكل الاستجابة:** توحيد نمط الـ JSON Envelope مع معالجة استثناءات مركزية في `bootstrap/app.php`.
* **معمارية الواجهة:** Vue 3 SPA داخل `resources/js` بنظام Vite و Vue Router و Pinia Stores و Axios API Client.
* **استراتيجية الترحيل:** ترحيل تدريجي موديول بموديول بنظام المحرك المزدوج (Dual-Engine) دون تعطيل الواجهات الحالية حتى الاكتمال والاختبار التام.

## 3. التحقق والاختبار:
* [x] إنشاء الفرع البرمجي المخصص `feature/api-migration`.
* [x] فحص وحصر كامل وحدات الـ Controllers الـ 45 والمسارات والوسائط (Middlewares).
* [x] مطابقة القواعد الصارمة لـ `AGENTS.md` (المالية، الدقة، المعايير المعمارية).
