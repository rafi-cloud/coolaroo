# AGENTS.md — Coolaroo RMS

Cross-tool project rules. Read by Claude Code, Antigravity, Cursor and Codex.
**Always read `docs/PROGRESS.md` first, then only the SDD sections your task names.**

## The project
Laravel 11+ / PHP 8.5 / MySQL 8 QR ordering, kitchen display and reservation system for a bistro and sports bar. Blade + vanilla JS + Laravel Echo. Laravel Reverb for real-time. Stripe **test mode, no webhooks** (payment confirmed by retrieving the Checkout Session). AI via **GitHub Models** (OpenAI-compatible). Selenium for end-to-end tests.

## Source of truth
| What | Where |
|---|---|
| Design, rules, schema | `docs/sdd/` (12 files + README) |
| Build tasks in execution order | `docs/sdd/12-task-list.md` |
| Current state and history | `docs/PROGRESS.md` |
| Approved ERD | `docs/erd/Coolaroo_RMS_ERD_v2.drawio` |
| Homepage and admin prototypes | `docs/design/` |

If code and the SDD disagree, fix both in the same change.

## How I want to work
- **I write the code myself.** Explain the approach, then show the code. Do not edit files or run commands unless I explicitly say so.
- **Give me terminal commands to run**, do not run them yourself. Put each command directly under the step it belongs to, not gathered in one list at the end — makes it obvious which step needs it and avoids doing something twice.
- **Write every per-task guide to `notes/<Txxx>-guide.md`** (approach, file-by-file code, commands inline per step) — not just in chat. `notes/` is gitignored; it's a local scratch folder, not part of the submitted project.
- One task per session, from `docs/sdd/12-task-list.md`, by task number.
- Show the plan before the code. Keep reasoning short: a few bullets, not essays.
- Ask when the SDD is unclear or silent. Never invent a rule.
- I am new to Laravel tooling, so name the artisan commands and file paths exactly.

## Hard rules
1. **No schema changes** without asking. Tables and columns are fixed by `docs/sdd/06-data-design.md` and the approved ERD.
2. **Business logic lives in `app/Services`.** Controllers stay thin. Reference BR IDs in docblocks.
3. **Status changes only through enum transition maps** (`docs/sdd/05-state-machines.md`). Never set a status column directly.
4. **CSS:** `public/css/style.css` (public) and `public/css/dashboard.css` (staff/admin). No Tailwind, Bootstrap or any framework. `style.css` must pass W3C validation. The Uena admin template is a visual reference only; never copy its markup or CSS.
5. **HTML must be W3C valid:** `lang` set, unique ids, no self-closing void tags, labels on inputs.
6. **Every interactive control gets `data-testid="<screen>-<action>"`** for Selenium.
7. **IDs are stable:** FR, BR, NFR, UC, S and TC numbers are never renumbered.
8. **Money** is `decimal(8,2)`, AUD, GST-inclusive; GST = total / 11. Timezone Australia/Melbourne.
9. **Never commit secrets.** Keys live in `.env`.
10. **No `Co-Authored-By` or other AI attribution trailer in any commit message, ever** — including any default your harness adds automatically. This is a solo assessment submitted as group work; an AI co-author line undermines that framing.

## Session protocol
**Start**
1. Read `docs/PROGRESS.md`.
2. Run `git log --oneline -15` (or ask me to).
3. Read only the SDD sections referenced by the task.
Do not scan the repo or read all SDD files.

**End**
1. Update the "Current state" block in `docs/PROGRESS.md`.
2. Append a Log entry: date, task ID, status, files, deviations with FR/BR IDs, follow-ups.
3. Give me the commit command with the task ID in the message, e.g. `T061: checkout service`.

## Token discipline
- Name one file and section, never "read the SDD".
- Clear context between tasks.
- Check work against rule IDs (`Check against BR09 and BR10`) instead of re-reading requirement files.
- No end-of-response summaries of what you just said.

## Deviations
Any deviation from the SDD is recorded in `docs/PROGRESS.md` under "Open deviations", with the FR or BR ID and the reason, and flagged to me for approval.
