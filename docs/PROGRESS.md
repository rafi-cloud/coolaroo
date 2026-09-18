# Progress

Single source of truth for "where are we". Read this first in every session. Git is the ground truth: if this file and `git log` disagree, believe git and fix this file.

## Current state
- Last completed task: 1 (T001 — Laravel project scaffold, environment config, .env.example)
- Next task: 2 (T002 Customer and staff auth guards; remove default users migration — FR03, 06.1)
- In progress: none
- Branch: develop — created per docs/START-HERE.md Day 0 step 5.
- Environment: Laravel 13.17 / PHP ^8.3. `.env` and `.env.example` both point at MySQL (`coolaroo` database, `127.0.0.1:3306`, `root`/no password, matching 07.2 local dev), `APP_NAME="Coolaroo RMS"`, `APP_TIMEZONE=Australia/Melbourne` set in both `.env` and `config/app.php` (NFR13). `php artisan migrate` confirmed working against the new `coolaroo` MySQL database (created via phpMyAdmin) — created the default `users`/`cache`/`jobs` tables, which T002 removes/replaces. Reverb broadcast driver still not installed (T003); Stripe/GitHub Models keys not set (T004).
- Blocked: none

## Open deviations from the SDD
- None. (The Tailwind scaffold flagged previously was removed in T001 — see log.)

## Log
<!-- Newest first. One entry per task, one task at a time.
### YYYY-MM-DD — Task <n> (<Txxx>) — done — <tool>
- Added / changed: <files>
- Notes: <decisions, rule IDs applied>
- Deviation: <FR/BR ID + what changed + why>  (or: none)
- Follow-up: <what is deliberately left to a later task>
-->

### 2026-09-18 — Task 1 (T001) — done — Claude Code
- Added / changed: `.env`, `.env.example`, `config/app.php`, `package.json`, `package-lock.json`, `vite.config.js`, `resources/css/app.css`, `.gitignore`
- Notes: DB switched from SQLite to MySQL (`coolaroo`, 07.2). `APP_TIMEZONE=Australia/Melbourne` set in `.env` and in `config/app.php`'s `'timezone'` key (NFR13) — Laravel 11+'s slimmed `config/app.php` hardcodes `'timezone' => 'UTC'` instead of reading `.env`, so both had to change for the setting to actually take effect. `APP_NAME` set to "Coolaroo RMS". Removed the Tailwind 4 scaffold (`package.json`, `vite.config.js`, `resources/css/app.css`) per AGENTS.md rule 4 (no CSS frameworks). Created the `develop` branch. Added `/notes/` to `.gitignore` for local task-guide files (kept outside the SDD-tracked `docs/` folder).
- Deviation: none
- Follow-up: `resources/css/app.css` still has two leftover `@source '...'` directives from the removed Tailwind setup — inert without the Tailwind plugin, but should be deleted when T006 wires up the real `public/css/style.css` / `dashboard.css` pipeline. T002 removes the default `users`/`cache`/`jobs` tables created by today's `migrate`.
