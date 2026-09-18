# Coolaroo RMS — Software Design Document (SDD)

Single source of truth for building Coolaroo Restaurant Management System: a Laravel + MySQL QR ordering, kitchen display and reservation system for a bistro / sports bar.

**Version:** 3.0 · **Date:** 16 September 2026 · **Supersedes:** Functional Requirements v2.0, Data Dictionary v2.0, earlier data dictionary drafts.

## How to read this

| File | Contents |
|---|---|
| [01-introduction.md](01-introduction.md) | Purpose, scope, glossary, references |
| [02-overview-and-decisions.md](02-overview-and-decisions.md) | System summary, decisions log, cut features, open items |
| [03-requirements.md](03-requirements.md) | Roles, permissions, 103 functional requirements, 64 business rules, 19 NFRs |
| [04-use-cases.md](04-use-cases.md) | Actors and 41 use cases |
| [05-state-machines.md](05-state-machines.md) | Order, line, payment, refund, table, visit, reservation |
| [06-data-design.md](06-data-design.md) | ERD, 25 tables with every column, enums, settings |
| [07-architecture.md](07-architecture.md) | Stack, environments, code structure, services, auth, routes, transactions, real-time, integrations, scheduler, errors |
| [08-ui-design.md](08-ui-design.md) | Screen inventory, flows, homepage and dashboard specs, UI standards |
| [09-test-design.md](09-test-design.md) | Selenium approach, test conventions, 72 test cases |
| [10-deployment.md](10-deployment.md) | Local XAMPP and VPS deployment, environment variables |
| [11-traceability.md](11-traceability.md) | FR → UC → screen → tables → tests |
| [12-task-list.md](12-task-list.md) | Build tasks in execution order, milestones, FR/NFR coverage |

Diagrams are Mermaid code blocks. They render on GitHub and in VS Code (Markdown Preview Mermaid Support extension).

## Working files outside this folder

| File | Purpose |
|---|---|
| `AGENTS.md` (repo root) | Cross-tool agent rules (Claude Code, Antigravity, Cursor, Codex) |
| `CLAUDE.md` (repo root) | Points to AGENTS.md plus Claude Code specifics |
| `docs/PROGRESS.md` | Current state, deviations and per-task log — read first in every session |
| `docs/START-HERE.md` | Setup, tool split and the per-task working loop |
| `docs/design/` | Homepage and admin prototypes (index.html, style.css, dashboard.html, dashboard.css) |

## Rules for anyone building from this

1. **Read `docs/PROGRESS.md` first**, then only the SDD sections your task names.
2. **IDs are stable.** FR, BR, NFR, UC, S (screen) and TC IDs never get renumbered. Removed items stay listed as *Removed*.
3. **Do not guess.** If something is missing or contradictory, stop and ask; record the answer in `02-overview-and-decisions.md`.
4. **Schema is fixed** to `06-data-design.md`. Table names follow the lecturer-approved ERD (`docs/erd/Coolaroo_RMS_ERD_v2.drawio`). Any schema change updates both.
5. **Business rules live in Services** (`07-architecture.md` 7.4). Controllers stay thin. Status changes go only through enum transition maps (`05-state-machines.md`).
6. **Styling:** public site uses `public/css/style.css`; staff/admin use `public/css/dashboard.css`. No CSS frameworks. The Uena template is visual reference only — never copy its markup or CSS.
7. **Every tested control** gets a `data-testid` (see `09-test-design.md`).
7. When code and this document disagree, fix one of them in the same change.
