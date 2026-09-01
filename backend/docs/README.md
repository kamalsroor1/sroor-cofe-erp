# الفهرس العام لوثائق نظام إدارة الفواتير والمخزون (Documentation Index)

دليل وفهرس مرجعي شامل لكافة وثائق ومخططات ومعايير نظام إدارة الفواتير، المبيعات، المخزون، والعملاء، المنفذ باستخدام Laravel 11/12 و Livewire 4.

---

## 🤖 أدلة الذكاء الاصطناعي وسجل التعديلات (AI Playbook & History)

* 📄 [دليل تشغيل الذكاء الاصطناعي الشامل (AGENTS.md)](file:///d:/projects/sroor/AGENTS.md)
  * الدليل الإلزامي لأي AI Agent قبل بدء العمل، يوضح القواعد الصارمة، المحظورات، مصفوفة الأدوار، وبروتوكول تسجيل التعديلات.
* 📁 [سجل تاريخ التعديلات اليومية (Daily History Logs)](file:///d:/projects/sroor/docs/history/2026-08-08/01-documentation-initialization.md)
  * مجلدات مؤرشفة بتاريخ كل يوم تحتوي على تفاصيل كل جلسة تعديل تمت والقرارات المتخذة والملفات المتأثرة.

---

## 📁 01 - النظرة العامة والمفاهيم (Overview & Glossary)

* 📄 [نظرة عامة على المشروع (Project Overview)](file:///d:/projects/sroor/docs/01-overview/project-overview.md)
  * يوضح أهداف المشروع كـ MVP عملي وسريع، ملخص حزمة الـ Tech Stack، الهيكل المعماري للـ Monolith، ومسار العمل الأساسي والجمهور المستهدف.
* 📄 [قاموس المصطلحات (Project Glossary)](file:///d:/projects/sroor/docs/01-overview/glossary.md)
  * قاموس شامل يوضح المصطلحات التقنية والمالية والمخزنية المستخدمة (مثل Stock In/Out, Lock For Update, Audit Log, WAC) مع شرح مبسط باللغة العربية.

---

## 📁 02 - المتطلبات الفنية والتشغيلية (Requirements)

* 📄 [المتطلبات الوظيفية (Functional Requirements)](file:///d:/projects/sroor/docs/02-requirements/functional-requirements.md)
  * جدول مرقم ومفصل لجميع المتطلبات الوظيفية (REQ-001 إلى REQ-066) مصنفة حسب الوحدات ومحددة الأولوية وفق معيار MoSCoW.
* 📄 [المتطلبات غير الوظيفية (Non-Functional Requirements)](file:///d:/projects/sroor/docs/02-requirements/non-functional-requirements.md)
  * معايير الأداء والسرعة، الأمان، الدقة المالية الصارمة (`DECIMAL 12,3`)، التوافق مع العربية والوضع الليلي، والنسخ الاحتياطي و PWA.

---

## 📁 03 - البنية المعمارية وقاعدة البيانات (Architecture & Database)

* 📄 [بنية المعالجة الخلفية (Backend Architecture)](file:///d:/projects/sroor/docs/03-architecture/backend-architecture.md)
  * شرح طبقة الخدمات (Services Layer)، خوارزمية اعتماد الفاتورة الآمنة بـ `DB::transaction()` و `lockForUpdate()`، وسجل حركة المخزون.
* 📄 [بنية واجهة المستخدم (Frontend Architecture)](file:///d:/projects/sroor/docs/03-architecture/frontend-architecture.md)
  * قواعد استخدام Livewire 4 مقابل Alpine.js، شجرة المكونات، التنقل بـ `wire:navigate`، وهيكلة قوالب Blade وتخطيطات الطباعة.
* 📄 [هيكل قاعدة البيانات ومخطط الكيانات (Database Schema & ERD)](file:///d:/projects/sroor/docs/03-architecture/database-schema.md)
  * توثيق كامل لكافة الجداول والحقول مع الأنواع والفهارس والقيود ومخطط العلاقات الشامل (Mermaid ERD).

---

## 📁 04 - تجربة وتصميم الواجهة (UX & UI Design)

* 📄 [إرشادات تجربة المستخدم (UX Guidelines)](file:///d:/projects/sroor/docs/04-ux-ui/ux-guidelines.md)
  * مبادئ سرعة الكاشير، اختصارات لوحة المفاتيح والباركود، سيناريوهات الاستخدام الأساسية (User Flows)، والعمل بيد واحدة على الموبايل.
* 📄 [نظام التصميم والهوية البصرية (UI Design System)](file:///d:/projects/sroor/docs/04-ux-ui/ui-design-system.md)
  * لوحة ألوان Flowbite و Tailwind، الخطوط العربية المعتمدة (Cairo/Tajawal)، قواعد RTL، مقاسات اللمس، وحالات التحميل والأخطاء.
* 📄 [وصف الشاشات والمخططات الهيكلية (Screens & Wireframes Description)](file:///d:/projects/sroor/docs/04-ux-ui/screens-wireframes-description.md)
  * وصف نصي ومخططات ASCII Wireframes لكافة الشاشات الرئيسية (لوحة التحكم، الفاتورة، الأصناف، كشف الحساب، والطباعة).

---

## 📁 05 - التخطيط وخارطة الطريق (Planning & Roadmap)

* 📄 [خارطة طريق مراحل المشروع (Phases Roadmap)](file:///d:/projects/sroor/docs/05-planning/phases-roadmap.md)
  * تقسيم تنفيذ المشروع إلى 6 مراحل متتابعة مع توضيح الأهداف، المخرجات التفصيلية، المتطلبات السابقة، والمدد التقديرية.
* 📄 [قائمة المهام التفصيلية (Tasks Breakdown Checklist)](file:///d:/projects/sroor/docs/05-planning/tasks-breakdown.md)
  * تفكيك المراحل لمهام عملية بصيغة Checklist (`- [ ]`) مقسمة حسب قاعدة البيانات، الباك إند، مكونات Livewire، والاختبارات.

---

## 📁 06 - التعاون التقني والمهارات (AI Collaboration & Skills)

* 📄 [أدوار ومسؤوليات الوكلاء الأذكياء (AI Collaboration Roles)](file:///d:/projects/sroor/docs/06-ai-collaboration/ai-roles.md)
  * تحديد الأدوار التخصصية لـ AI Agents (الباك إند، الواجهات، الاختبارات، والتوثيق) مع بيان المسؤوليات والمحظورات الصارمة.
* 📄 [المهارات والحزم البرمجية المطلوبة (Required Skills & Packages)](file:///d:/projects/sroor/docs/06-ai-collaboration/required-skills.md)
  * جدول وشرح الحزم البرمجية والأدوات الأساسية (Livewire 4, Spatie Permission, Breeze, PWA, Print CSS) وأسباب اختيارها.

---

## 📁 07 - العمليات والتشغيل والأمان (Operations & Security)

* 📄 [إرشادات ومعايير الطباعة (Printing Guidelines)](file:///d:/projects/sroor/docs/07-operations/printing-guidelines.md)
  * الفروقات بين طباعة A4 وطباعة الرول الحراري 80mm، قواعد Print CSS، البيانات الإلزامية للفاتورة، وتفضيلات الطابعات.
* 📄 [الأمان والصلاحيات والنسخ الاحتياطي (Security, Permissions & Backup)](file:///d:/projects/sroor/docs/07-operations/security-permissions.md)
  * مصفوفة صلاحيات الأدوار (Admin و User/Cashier)، قواعد سجل التدقيق (Audit Log)، وسياسات النسخ الاحتياطي الدوري وخطط الاستعادة.
