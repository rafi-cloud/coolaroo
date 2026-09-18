# Progress

Single source of truth for "where are we". Read this first in every session. Git is the ground truth: if this file and `git log` disagree, believe git and fix this file.

## Current state
- Last completed task: 2 (T002 — customer and staff auth guards; remove default users migration)
- Next task: 3 (T003 Install Reverb, Echo, Vite; queue, cache and session drivers — 07.2, NFR09)
- In progress: none
- Branch: develop
- Environment: Laravel 13.17 / PHP ^8.3, MySQL `coolaroo` database (127.0.0.1:3306), `APP_TIMEZONE=Australia/Melbourne` and `APP_NAME="Coolaroo RMS"` set. `config/auth.php` now defines `customer` and `staff` guards (with `customers`/`staff` Eloquent providers and password-reset brokers), pointing at `App\Models\Customer`/`App\Models\Staff`, which don't exist yet (created in T014) — safe, since `::class` is a compile-time string. Default `users` migration removed; `password_reset_tokens`/`sessions` framework tables kept in a renamed migration. `php artisan migrate:fresh` and `php artisan test` confirmed passing against MySQL. Reverb broadcast driver still not installed (T003); Stripe/GitHub Models keys not set (T004). No project schema (role/staff/customer/etc.) or Blade views yet.
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

### 2026-09-19 — Task 2 (T002) — done — Claude Code
- Added / changed: `config/auth.php`, `database/seeders/DatabaseSeeder.php`, `.obsidian/` untracked + gitignored, `.gitignore`, `AGENTS.md`
- Removed: `app/Models/User.php`, `database/factories/UserFactory.php`, `database/migrations/0001_01_01_000000_create_users_table.php`
- Added (new file): `database/migrations/0001_01_01_000000_create_password_reset_tokens_and_sessions_tables.php` — same file, renamed and trimmed to drop the `users` table while keeping `password_reset_tokens`/`sessions` (06.1: those are framework infra, not design entities, and stay)
- Notes: Two guards (`customer`, `staff`) per 07.5, each with its own Eloquent provider and password-reset broker (FR05 applies to all users). Providers point at `App\Models\Customer`/`App\Models\Staff`, which don't exist until T014 — harmless, since `::class` never autoloads. `php artisan migrate:fresh` rebuilds `cache`/`cache_locks`/`jobs`/`job_batches`/`failed_jobs`/`password_reset_tokens`/`sessions`/`migrations` with no `users` table; `php artisan test` still green. Also untracked an accidentally-committed `.obsidian/` folder (unrelated editor config) and added it to `.gitignore`. `AGENTS.md` updated with two new standing rules: task guides go in `notes/<Txxx>-guide.md`, and terminal commands belong inline within each guide step rather than a separate list at the end.
- Deviation: none
- Follow-up: both new password-reset brokers (`customers`, `staff`) share the single `password_reset_tokens` table, keyed only by email with no guard/type column (06.1 treats it as shared framework infra) — a customer and staff member with the same email would share a reset-token row. Accepted as-is per the SDD; not revisited unless it becomes a real problem. T010 creates the actual `customer`/`staff` tables; T014 creates the models the guards already point to.

### 2026-09-18 — Task 1 (T001) — done — Claude Code
- Added / changed: `.env`, `.env.example`, `config/app.php`, `package.json`, `package-lock.json`, `vite.config.js`, `resources/css/app.css`, `.gitignore`
- Notes: DB switched from SQLite to MySQL (`coolaroo`, 07.2). `APP_TIMEZONE=Australia/Melbourne` set in `.env` and in `config/app.php`'s `'timezone'` key (NFR13) — Laravel 11+'s slimmed `config/app.php` hardcodes `'timezone' => 'UTC'` instead of reading `.env`, so both had to change for the setting to actually take effect. `APP_NAME` set to "Coolaroo RMS". Removed the Tailwind 4 scaffold (`package.json`, `vite.config.js`, `resources/css/app.css`) per AGENTS.md rule 4 (no CSS frameworks). Created the `develop` branch. Added `/notes/` to `.gitignore` for local task-guide files (kept outside the SDD-tracked `docs/` folder).
- Deviation: none
- Follow-up: `resources/css/app.css` still has two leftover `@source '...'` directives from the removed Tailwind setup — inert without the Tailwind plugin, but should be deleted when T006 wires up the real `public/css/style.css` / `dashboard.css` pipeline. T002 removes the default `users`/`cache`/`jobs` tables created by today's `migrate`.
