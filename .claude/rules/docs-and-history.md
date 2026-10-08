---
paths:
  - "docs/**"
  - "README.md"
  - "AGENTS.md"
  - "AI_START.md"
---

# Documentation & history rules

Docs are written in **Arabic** (technical terms, paths, and code stay English), matching the existing `docs/` tree.

## When to write what
| Work done | Required docs |
|---|---|
| Typo, tiny fix, config tweak | nothing (the commit message is enough) |
| Bug fix with a non-obvious cause | history log |
| New feature / endpoint / screen / schema change | history log + update the affected page/module doc |
| Full page audit (`docs/PAGE_AUDIT_PROMPT.md`) | page doc + module doc + master doc + `full-page-review-log.md` + history log |
| Full controller audit (`docs/CONTROLLER_AUDIT_PROMPT.md`) | `controller-review-log.md` + history log |

Don't generate documentation nobody asked for on trivial changes — noise buries the useful history.

## History log — `docs/history/YYYY-MM-DD/NN-short-slug.md`
- Folder = today's real date. `NN` = next two-digit sequence in that folder (check what exists). Slug in English kebab-case.
- One file per coherent task, written **after** the work is verified. Template:

```markdown
# سجل تعديل: [اسم المهمة باختصار]
* **التاريخ والوقت:** YYYY-MM-DD HH:MM
* **الدور المفعل:** backend-architect / frontend-vue / qa-tester / debugger / …
* **الهدف:** [سطر أو سطران]

## 1. الملفات المعدلة
* `[NEW]` path — الوصف
* `[MODIFIED]` path — الوصف
* `[DELETED]` path — السبب

## 2. القرارات التقنية
* لماذا هذا الحل، وما البدائل التي رُفضت، وأي أثر مالي/مخزني/tenancy.

## 3. التحقق والاختبار
* الأوامر التي شُغّلت فعلاً ونتيجتها الحقيقية (عدد الاختبارات، نجاح/فشل البناء).
* ما لم يتم اختباره، بصراحة.

## 4. ملاحظات / ديون تقنية
* أي مخالفة قديمة لوحظت ولم تُعالج، أو خطوة متابعة مطلوبة.
```

- Record **what actually happened**. Never tick a checkbox for a check that wasn't run. Never paste secrets, tokens, server IPs, or customer data.
- If a day has many logs, maintain that folder's `summary.md`.

## Three-tier system docs
1. `docs/pages/<page-name>.md` — purpose, component tree, used common/form components, endpoints + DTOs + Actions, responsive/dark behaviour, translation keys, test record.
2. `docs/modules/<module>.md` — workflow between the module's screens, accounting/stock rules in force.
3. `docs/system-architecture-master.md` — the index tying modules/pages together with the live audit-status table.

Update the tier(s) your change affects; keep them describing the **current** system. Old history files are immutable records — don't rewrite them even if they mention Livewire/Inertia/NativePHP.

## Keeping AI guidance in sync
`CLAUDE.md` + `.claude/rules/` are the source of truth; `AGENTS.md` mirrors them for other tools. When a rule changes, change it in `.claude/rules/` first, then reflect it in `AGENTS.md` in the same commit.
