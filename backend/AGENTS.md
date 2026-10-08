# AI guidance — see the repo root

This directory is the Laravel application of **Sroor Coffee ERP**. The AI rules are maintained in **one place** at the repository root so they cannot drift:

- [`../CLAUDE.md`](../CLAUDE.md) — entry point: real stack, repo map, commands, the 10 golden rules, agent roster.
- [`../.claude/rules/`](../.claude/rules/) — detailed, path-scoped rules (backend architecture, money & stock integrity, multi-tenancy, frontend, localization, testing, security & operations, docs & history).
- [`../.claude/agents/`](../.claude/agents/) — specialist agents.
- [`../AGENTS.md`](../AGENTS.md) — the full Arabic playbook (cross-tool mirror).

**Read `../AGENTS.md` (or `../CLAUDE.md`) completely before changing any code here.**

Quick reminders that apply to every file under `backend/`:
1. Money/quantities = `DECIMAL(12,3)` + bcmath strings. No floats.
2. Stock/balance/treasury writes = inside `DB::transaction()` with `lockForUpdate()`.
3. Central models pin the central connection (`getConnectionName()`); tenant schema lives in `database/migrations/tenant/`.
4. Controller → FormRequest → DTO → Action `execute()` → Resource. Filters via Pipeline.
5. No hardcoded user-facing text; keys in `lang/ar` **and** `lang/en`; never hand-edit `resources/js/helpers/defaultTranslations.*`.
6. Views are thin orchestrators (50–80 lines); RTL, dark/light, skeleton loaders, touch-friendly.
7. Never run deploy / live-server scripts or `git push` without an explicit request.
