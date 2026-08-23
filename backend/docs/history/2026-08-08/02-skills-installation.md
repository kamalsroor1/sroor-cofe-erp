# سجل تعديل: تثبيت حزم مهارات الذكاء الاصطناعي للمشروع (Skills Installation)

* **التاريخ والوقت:** 2026-08-08 17:39
* **الدور المفعل:** Docs & PM Agent (مع التنسيق مع Backend & UI Agents)
* **الهدف من التعديل:** البحث عن وتثبيت حزم المهارات (Agent Skills) المطلوبة والمحددة في `docs/06-ai-collaboration/required-skills.md` لتمكين الوكلاء من استخدام أفضل الممارسات المعتمدة في Laravel, Livewire, Security, و Tailwind CSS.

---

## 1. المهارات التي تم تثبيتها (Installed Agent Skills)

| اسم المهارة (Skill) | المصدر وعدد التثبيتات | المسار المحلي داخل المشروع | الغرض ودور المهارة في المشروع |
| :--- | :--- | :--- | :--- |
| **`laravel-specialist`** | `jeffallan/claude-skills` (19.7K installs) | `.\.agents\skills\laravel-specialist\` | إرشادات متقدمة لتطوير Laravel الحديث، ضبط الـ Service Layer والـ Eloquent. |
| **`laravel-patterns`** | `affaan-m/everything-claude-code` (6.6K installs) | `.\.agents\skills\laravel-patterns\` | أنماط التصميم المعماري، فصل الـ Business Logic، وضبط المعاملات. |
| **`laravel-security`** | `affaan-m/everything-claude-code` (6.6K installs) | `.\.agents\skills\laravel-security\` | معايير الأمان الصارمة، تأمين الـ DB Transactions، والتحقق من الصلاحيات. |
| **`livewire-development`** | `spatie/freek.dev` | `.\.agents\skills\livewire-development\` | أفضل ممارسات Livewire، إدارة الـ State، الـ Validation، والربط الحي. |
| **`tailwind-design-system`**| `wshobson/agents` (58.4K installs) | `.\.agents\skills\tailwind-design-system\` | بناء Design Tokens المتجاوبة، دعم الـ Dark Mode، وتنسيقات RTL. |

---

## 2. القرارات المعمارية والمنطق البرمجي (Key Decisions)
* تم اختيار الحزم الأكثر موثوقية وشهرة (من مصادر معتمدة مثل Spatie و Vercel/Agent ecosystems ومجتمع Laravel).
* تم تثبيت المهارات داخل مجلد `.agents/skills/` لضمان أن أي وكيل ذكاء اصطناعي يعمل على هذا المستودع يمتلك هذه القدرات تلقائيًا وبشكل دائم.

---

## 3. الاختبارات والتحقق (Verification & Testing)
* [x] تم التحقق من نجاح أمر `npx skills add` لكافة الحزم.
* [x] تم فحص مجلد `.\.agents\skills\` والتأكد من وجود المجلدات الخمسة كاملة.
* [x] تم فحص خلو الحزم من أي تنبيهات أمنية (Safe risk assessment).

---

## 4. الخطوات التالية المقترحة (Next Recommended Steps)
1. البدء في إنشاء مشروع Laravel وتثبيت Laravel Breeze و Livewire 4 و Spatie Laravel Permission.
2. إنشاء أول دفعة من ملفات الـ Migrations للأصناف والعملاء والمخزون (`items`, `customers`, `stock_movements`) بدقة `DECIMAL(12,3)`.
