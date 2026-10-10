# Operating guide for Antigravity agents (Sroor ERP)

This repo was set up for Claude Code, but everything is plain Markdown, so any agent can use it. Read this once, then follow it for every task.

## 1. AI rules (mandatory for every agent)

| File | What it is | When |
|---|---|---|
| `AGENTS.md` | Cross-tool mirror of all project rules | Always, first |
| `CLAUDE.md` | Entry point: stack, repo map, commands, 10 golden rules | Always |
| `.claude/rules/frontend-vue.md` | Vue/Tailwind/RTL/dark/touch/skeleton rules | Any `.vue`/`.js` change |
| `.claude/rules/localization.md` | ar/en lang files, no hardcoded text, key naming | Any user-facing text |
| `.claude/rules/testing.md` | Test conventions | Any test |
| `.claude/rules/security-and-operations.md` | Production off-limits, secrets, git hygiene | Always |
| `.claude/rules/backend-architecture.md`, `money-stock-integrity.md`, `multi-tenancy.md` | Backend rules | Only if a task touches PHP (UX task: lang files only) |

If two sources disagree, `.claude/rules/` wins.

## 2. Specialist agents (roles)

`.claude/agents/*.md` are role definitions. When you launch a sub-agent, **give it the matching role file as its system instructions** (read the file and paste/attach its body), plus the lane task.

| Role file | Use it for in the UX task |
|---|---|
| `.claude/agents/frontend-vue.md` | Lanes 1, 2, 3 (all Vue work) |
| `.claude/agents/i18n-guardian.md` | Lang keys ar/en parity, hardcoded-string hunt (lead runs it at review) |
| `.claude/agents/code-reviewer.md` | Lead's review step before committing (read-only review) |
| `.claude/agents/quality-gatekeeper.md` | ESLint/Prettier failures |
| `.claude/agents/qa-tester.md` | JS tests in `backend/tests/js/` |

Ignore the `model:`/`tools:`/`skills:` frontmatter lines — they are Claude Code settings. Models in Antigravity: lead = Claude Opus 5.5 (medium), sub-agents = Gemini 3.8 (high).

## 3. Skills (checklists)

`.claude/skills/<name>/SKILL.md` are step-by-step procedures. Follow them literally when they apply:

| Skill | When |
|---|---|
| `.claude/skills/quality-gate/SKILL.md` | Before every commit: run the checks on changed files, fix in the order it says |
| `.claude/skills/ci-pipeline/SKILL.md` | If the GitHub CI run on your PR fails |
| `larastan-fixing`, `production-ops` | Not needed for the UX task (PHP / production) |

## 4. Talking to the Claude Code coordinator through GitHub

The coordinator (Claude Code, working in `D:\projects\sroor` on `feature/multi-tenant`) reviews and merges your work. Communication happens **on one draft Pull Request**.

1. When your commits are ready (after the lead's review and verify step), push **only your branch**:
   `git push -u origin <your branch>`
   Never push any other branch. Never push to `main` or `feature/multi-tenant`. Never force-push.
2. Open a **draft** PR with the GitHub CLI:
   `gh pr create --draft --base feature/multi-tenant --head <your branch> --title "<conventional title>" --body-file <your report>`
   Never target `main` (pushing/merging to `main` deploys to a live shop).
3. The PR body is your report. CI (`.github/workflows/ci.yml`) runs automatically on the PR; if a job fails, follow `ci-pipeline` SKILL, fix, commit, push again.
4. **Message protocol** (PR comments, English):
   - You → coordinator: comment starting with `[antigravity]`, e.g. `[antigravity] ready for review`, `[antigravity] question: …`, `[antigravity] blocked: …`.
   - Coordinator → you: comments starting with `[claude-code]` (review findings, requested changes, answers).
   - Read new comments with `gh pr view --comments` (or `gh api repos/{owner}/{repo}/issues/<PR#>/comments`). Address each `[claude-code]` request with a new commit, then reply `[antigravity] fixed in <sha>: …`.
5. Never merge the PR, never approve it, never close it. The coordinator merges after review and only when the W2 batch in `feature/multi-tenant` is at a safe point.
6. Do not put secrets, `.env` values, tokens, phone numbers or customer workspace codes in PR text, comments or commits (the repo is public).

## 5. Hard limits
No production/server access, no root `*.py` scripts, no `deploy.yml`, no `.env` with real values, no `git add .`/`-A`, no history rewrite, no AI/model names in commits or PR text.
