# Progress

Single source of truth for "where are we". Read this first in every session. Git is the ground truth: if this file and `git log` disagree, believe git and fix this file.

## Current state
- Last completed task: 3 (T003 — install Reverb, Echo, Vite; queue, cache and session drivers)
- Next task: 4 (T004 Stripe and GitHub Models configuration (config/services.php) — 07.9)
- In progress: none
- Branch: develop
- Environment: Laravel 13.17 / PHP ^8.3, MySQL `coolaroo` database, `APP_TIMEZONE=Australia/Melbourne`, `APP_NAME="Coolaroo RMS"`. Auth guards (`customer`/`staff`) configured (T002). Reverb installed and wired: `BROADCAST_CONNECTION=reverb`, `REVERB_*`/`VITE_REVERB_*` set in `.env`/`.env.example`, `config/broadcasting.php` and `config/reverb.php` published, `resources/js/echo.js` created and imported from `resources/js/app.js`, `routes/channels.php` wired into `bootstrap/app.php`. `php artisan reverb:start`, `npm run build`, and `php artisan test` all confirmed working. Stripe/GitHub Models keys not set (T004). No project schema (role/staff/customer/etc.) or Blade views yet.
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

### 2026-09-19 — Task 3 (T003) — done — Claude Code
- Added / changed: `composer.json`, `composer.lock`, `package.json`, `package-lock.json`, `.env`, `.env.example`, `bootstrap/app.php`, `resources/js/app.js`, `routes/channels.php` (new), `config/broadcasting.php` (new), `config/reverb.php` (new), `resources/js/echo.js` (new), `docs/PROGRESS.md`
- Notes: `composer require laravel/reverb -W` — needed `-W` because every Reverb release requires `guzzlehttp/psr7 ^2.6` while `laravel/framework` had resolved `guzzlehttp/guzzle` to its 8.x line (requiring `psr7 ^3.1`); Composer re-resolved the graph and downgraded guzzle to 7.15.5. `php artisan install:broadcasting` (chose Reverb) scaffolded `config/broadcasting.php`, `routes/channels.php`, the `channels:` wiring in `bootstrap/app.php`, `laravel-echo`/`pusher-js`, and `resources/js/echo.js`, but left `REVERB_*` env vars unset and `config/reverb.php` unpublished. `php artisan reverb:install` then crashed (`Pusher\Pusher::__construct(): $auth_key ... null given`) because `BROADCAST_CONNECTION=reverb` was already set with no key yet — fixed by adding the `REVERB_*`/`VITE_REVERB_*` block to `.env` by hand first (values read from the installer's own source so they'd match its real defaults). Re-running it hung at its last interactive confirm (Windows/PowerShell + Laravel Prompts rendering issue, harmless to Ctrl+C) and — separately — duplicated the `REVERB_*` block instead of skipping it, because its "already present" check looks for `\r\n` + the var name but `.env` uses plain `\n` line endings; removed the duplicate by hand. Also found and fixed the same duplicate-key pattern on `BROADCAST_CONNECTION` itself (`install:broadcasting` appended a second `BROADCAST_CONNECTION=reverb` line near the end instead of updating the original `=log` line in place) — consolidated to one line. Mirrored all new `.env` keys into `.env.example` with placeholder values. Replaced the installer's default `routes/channels.php` example (`App.Models.User.{id}`, referencing the model deleted in T002) with a placeholder comment — real channels land in T110. Queue/cache/session drivers needed no changes; already `database` since T001, matching 07.2's local row.
- Deviation: none
- Follow-up: none. `config/reverb.php` and `config/broadcasting.php` are framework defaults, untouched beyond what the installer generated.

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
