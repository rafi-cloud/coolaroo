# Start here

## Day 0 — repo (30 minutes, once)
1. Create the Laravel project: `composer create-project laravel/laravel coolaroo` (task 1 / T001).
2. Copy into the project root: `AGENTS.md`, `CLAUDE.md`, `.gitignore`, `docs/`.
3. Copy `index.html`, `style.css`, `dashboard.html`, `dashboard.css` into `docs/design/`.
4. `git init` (if needed), then commit:
   - `chore: laravel scaffold`
   - `docs: SDD, ERD, design prototypes, agent rules`
5. Create the working branch: `git checkout -b develop`.

## Tools
| Tool | Use for | Setting |
|---|---|---|
| Antigravity (Google AI Pro) | Most tasks; burn the free/Pro quota first | Keep it planning, not executing; review the plan, type the code yourself |
| Claude Code in VS Code (Anthropic Pro) | Hard reasoning: services, state machines, payments, stubborn bugs | Plan mode, Sonnet by default, `/clear` between tasks |
| Claude chat | Design decisions, report writing, documents | Does not load the repo |

Rule: finish a task, commit, then switch tools. Never run both on the same uncommitted work.

## The loop, per task
1. Open the tool. Prompt:
   `Task <n> from docs/sdd/12-task-list.md. Read docs/PROGRESS.md, then only the sections that task references. Explain the approach, then show the code file by file. Do not edit files. Give me the commands to run.`
2. Ask questions until the approach makes sense.
3. Type the code yourself (do not paste). Run the commands in the VS Code terminal.
4. Check it: page loads, `php artisan test`, data looks right.
5. Review: `Check my implementation against <FR/BR IDs for this task>. List anything missing.`
6. Update `docs/PROGRESS.md`, then commit: `git commit -am "T0xx: <short description>"`.
7. Add two lines to `docs/learning-log.md`: what you learned, what confused you.

## First three tasks
| # | Task | Result |
|---|---|---|
| 1 | T001 scaffold and env config | `php artisan serve` shows the Laravel welcome page |
| 2 | T002 customer and staff guards | Two auth guards configured, default `users` migration deleted |
| 3 | T003 Reverb, Echo, Vite, drivers | `php artisan reverb:start` runs |

Then tasks 4 to 13 are migrations, models, enums and seeders: after them `php artisan migrate:fresh --seed` must run clean. That is milestone M1.

## If you get stuck
- Error: paste only the error line, plus the file and line number.
- Confused by a rule: ask the chat, not the coding agent; the chat has the design history.
- Out of quota: commit, switch tool, continue at the next task number.
