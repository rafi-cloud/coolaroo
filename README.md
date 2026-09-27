# Coolaroo — Restaurant Management System

QR table ordering, live kitchen and bar displays, reservations, refunds and an admin
dashboard for a bistro and sports bar.

This guide takes you from the unzipped folder to a running system you can test as four
different people at once. It assumes Windows and PowerShell throughout. Every command is
a single pasteable line.

- [Built with](#built-with)
- [Before you start](#before-you-start)
- [Setup](#setup)
- [Running it](#running-it)
- [Logging in](#logging-in)
- [Table QR codes](#table-qr-codes)
- [Testing it by hand](#testing-it-by-hand)
- [Automated tests](#automated-tests)
- [What the demo data contains](#what-the-demo-data-contains)
- [Project layout](#project-layout)
- [Command reference](#command-reference)
- [Troubleshooting](#troubleshooting)

---

## Built with

| Layer | Choice |
|---|---|
| Framework | Laravel 13 (PHP 8.3+) |
| Database | MySQL 8 / MariaDB 10.4+ |
| Live updates | Laravel Reverb (WebSockets) + Laravel Echo |
| Front end | Blade components, vanilla JS, Vite. **No CSS framework** — `public/css/style.css` (public) and `public/css/dashboard.css` (staff/admin) |
| Payments | Stripe, test mode. No webhooks — payment is confirmed by retrieving the Checkout Session |
| AI | Google Gemini via its OpenAI-compatible endpoint |
| PDFs / QR | dompdf, endroid/qr-code |
| Tests | PHPUnit, against SQLite in memory |
| Style | Laravel Pint |

Timezone is `Australia/Melbourne`. Money is AUD, GST-inclusive, GST = total ÷ 11.

---

## Before you start

You need four things. **Only MySQL comes from XAMPP** — read the PHP warning below, it is
the single most common reason this project fails to start.

| Tool | Version | Where |
|---|---|---|
| PHP | **8.3 or newer** | Installed separately — see below |
| Composer | 2.x | [getcomposer.org](https://getcomposer.org/download/) — use the Windows installer |
| Node.js + npm | 18+ | [nodejs.org](https://nodejs.org) — the LTS installer |
| MySQL | 8.x, or MariaDB 10.4+ | XAMPP is fine. You need only its **MySQL** |

### Do not use XAMPP's PHP

XAMPP ships PHP **8.2**. This project requires **8.3+** (Laravel 13), so `composer install`
will refuse to run at all with a platform requirement error. XAMPP's Apache is not used
either — the site is served by PHP's own built-in server on port 8000.

From XAMPP you start **MySQL only**. Leave Apache stopped.

### Installing PHP

1. Download the **Thread Safe** x64 zip of PHP 8.3 or newer from
   [windows.php.net/download](https://windows.php.net/download/).
2. Extract it to `C:\php`.
3. Create the config file from the bundled template:

```powershell
Copy-Item C:\php\php.ini-development C:\php\php.ini
```

4. Open `C:\php\php.ini` in a text editor. Find the `extension=` block and **remove the
   leading semicolon** from each of these nine lines:

```ini
extension=curl
extension=fileinfo
extension=gd
extension=mbstring
extension=openssl
extension=pdo_mysql
extension=pdo_sqlite
extension=sqlite3
extension=zip
```

Also set the extension directory near the top of the same block:

```ini
extension_dir = "C:\php\ext"
```

5. Add `C:\php` to your **PATH**: press `Win`, type *environment variables*, open
   **Edit the system environment variables** → **Environment Variables** → under *System
   variables* select **Path** → **Edit** → **New** → `C:\php` → OK.
6. **Open a new terminal** (PATH changes do not affect already-open ones) and check:

```powershell
php -v
```

It must report 8.3 or newer. If it reports 8.2, PATH is still finding XAMPP's PHP — move
`C:\php` above `C:\xampp\php` in the Path list.

Then confirm the extensions are loaded:

```powershell
php -m
```

What each one is for: `pdo_mysql` the database · `mbstring` text handling · `openssl` and
`curl` HTTPS calls to Stripe and the AI · `fileinfo` and `gd` menu image uploads · `zip`
Composer · `pdo_sqlite` and `sqlite3` the automated tests only.

If `gd` is missing, the app runs but uploading a menu photo fails. If `pdo_sqlite` or
`sqlite3` are missing, the app runs but `php artisan test` fails.

---

## Setup

Unzip the project and open a terminal **in the project folder** — the one containing
`artisan`. The `.env` configuration file is already included in the zip, so there is no
configuration step.

### 1. Install the PHP dependencies

```powershell
composer install
```

### 2. Install the front-end dependencies

```powershell
npm install
```

```powershell
npm run build
```

Both are required. Compiled assets are not included in the zip, so without `npm run build`
every page renders unstyled. `npm install` is also what provides the process runner used by
`php artisan dev` in the next section.

### 3. Start MySQL

Open the **XAMPP Control Panel** and press **Start** next to **MySQL**. Leave Apache
stopped.

If you skip this, the first page you open throws
`SQLSTATE[HY000] [2002] No connection could be made because the target machine actively
refused it`. That error always means MySQL is not running.

### 4. Create the database

The database must be named `coolaroo`. Either method works — pick whichever you prefer.

**From the terminal:**

```powershell
C:\xampp\mysql\bin\mysql.exe -u root -e "CREATE DATABASE coolaroo CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci"
```

**Or from phpMyAdmin:**

1. Open [http://localhost/phpmyadmin](http://localhost/phpmyadmin). This needs XAMPP's
   Apache running — start it just for this, then stop it again. Or use the terminal command
   above and skip Apache entirely.
2. Click **New** in the left sidebar.
3. Database name: `coolaroo`
4. Collation: `utf8mb4_unicode_ci`
5. Click **Create**. Leave it empty — the next step fills it.

The included `.env` expects MySQL on `127.0.0.1:3306`, user `root`, with a **blank
password**, which is the XAMPP default. If your root user has a password, set it on the
`DB_PASSWORD=` line in `.env`, then run `php artisan config:clear`.

### 5. Build the tables and load the demo data

```powershell
php artisan migrate --seed
```

One command does both jobs. `migrate` creates the tables; `--seed` fills them with a
complete, internally consistent demo restaurant — staff, customers, a 49-item menu, twelve
tables, and about 245 orders across the past eight weeks. See
[what you get](#what-the-demo-data-contains).

> **Why seed rather than import a `.sql` dump?** The demo data is generated relative to
> *today* — orders placed this morning, bookings across the next fortnight, a table seated
> 35 minutes ago. Seeding on your machine dates all of that correctly, so the dashboard and
> the live screens have something real to show. A fixed dump would show an empty dashboard
> and stale tickets.

To wipe and reload at any point:

```powershell
php artisan migrate:fresh --seed
```

### 6. Link the uploads folder

```powershell
php artisan storage:link
```

This lets newly uploaded menu photos be served. The seeded menu uses images already in
`public/images`, so the site looks complete without it — you only need this if you upload a
photo yourself while testing the admin menu editor.

### 7. Optional — only if you want to test card payments or the AI

Two features need API keys that are deliberately **not** included, because they are secrets:

| Feature | Key | Without it |
|---|---|---|
| Card payments | `STRIPE_KEY`, `STRIPE_SECRET` | Card payment is unavailable. **Cash works end to end**, so the full order flow is still testable |
| AI dining assistant | `AI_API_KEY` — free from [aistudio.google.com/apikey](https://aistudio.google.com/apikey) | Shows "The assistant is busy right now". That is the designed fallback, not a crash |

Add them to `.env`, then run `php artisan config:clear`.

PHP on Windows also ships without a certificate bundle, so any HTTPS call fails with
`cURL error 60: unable to get local issuer certificate`. **Skip this unless you are using
the keys above.** Download the bundle:

```powershell
Invoke-WebRequest -Uri https://curl.se/ca/cacert.pem -OutFile C:\php\cacert.pem
```

Then in `C:\php\php.ini`, uncomment these two lines and point them at the file:

```ini
curl.cainfo = "C:\php\cacert.pem"
openssl.cafile = "C:\php\cacert.pem"
```

---

## Running it

### One terminal — use this

```powershell
php artisan dev
```

That is the whole system. Leave it running and open
**[http://localhost:8000](http://localhost:8000)**. Press `Ctrl+C` to stop.

It starts five processes together in one window:

| Process | What it does |
|---|---|
| `artisan serve` | The site, on port 8000 |
| `artisan reverb:start` | WebSocket server — live station displays, floor plan, order tracker |
| `artisan queue:listen` | Delivers those live updates and the emails |
| `artisan pail` | Streams the application log into the terminal |
| `npm run dev` | Vite, rebuilding front-end assets as they change |

**What it does not start** is the task scheduler. That drives the time-based background
jobs — payment reconciliation, auto-clearing finished tables, booking reminders, no-show
detection, daily stock reset. None of them are needed to walk through the system; nothing
you click depends on them. If you want to see one, call it directly, for example:

```powershell
php artisan reservations:remind
```

Its queue worker also ignores queue **priority**, which only matters if you are timing how
fast a live update arrives versus an email.

### Four terminals — only for time-based behaviour

If you do want the scheduler running and the queue correctly prioritised, run these in four
separate terminals instead of `php artisan dev`, each opened in the project folder, all four
left running:

| # | Command | What it does |
|---|---|---|
| 1 | `php artisan serve` | The site, at http://localhost:8000 |
| 2 | `php artisan reverb:start` | Live updates — station displays, floor plan, order tracker |
| 3 | `php artisan queue:work --queue=broadcasts,mail,default` | Delivers those updates and the emails |
| 4 | `php artisan schedule:work` | Payment reconciliation, table auto-clear, booking reminders, no-show suggestions |

The queue order is priority, not decoration: a kitchen ticket arriving late is useless, a
reminder email arriving late is not.

Without terminals 2 and 3, pages still load but **nothing updates live** — you would have
to refresh by hand.

---

## Logging in

**Every seeded account's password is `Hello@123`.**

| Role | URL | Email |
|---|---|---|
| Customer | [localhost:8000/login](http://localhost:8000/login) | `customer@coolaroo.test` |
| Admin | [localhost:8000/staff/login](http://localhost:8000/staff/login) | `admin@coolaroo.test` |
| Waitstaff | [localhost:8000/staff/login](http://localhost:8000/staff/login) | `waiter@coolaroo.test` |
| Kitchen | [localhost:8000/staff/login](http://localhost:8000/staff/login) | `kitchen@coolaroo.test` |
| Bar | [localhost:8000/staff/login](http://localhost:8000/staff/login) | `bar@coolaroo.test` |

Staff and customers use **separate login pages**. There is no public staff sign-up — staff
accounts are created by an admin.

There are eleven more customer accounts with order history — `jack.t@coolaroo.test`,
`sarah.j@coolaroo.test` and so on, all with the same password.

> If you register a **new** account, the password must be at least 8 characters with upper
> and lower case, a number and a symbol. `Hello@123` satisfies it.

---

## Table QR codes

In the restaurant, each table has a printed QR code. Scanning it opens the menu already
bound to that table, so the kitchen knows where the order came from. The link is
cryptographically signed and carries a per-table secret token — change one character and
you get a **403**, which is the tamper protection working.

Because that token is generated freshly when you seed, **QR codes are per-installation**. A
printed code from someone else's machine will not work on yours. Get yours from the app.

### Getting a table's QR code

1. Log in as **admin** at [localhost:8000/staff/login](http://localhost:8000/staff/login).
2. Go to **Tables** in the sidebar.
3. Each row has **Download PNG** (the bare code) and **Download PDF** (a printable table
   tent). `T1` and `T2` are good ones to start with.

### Getting the link as text, to paste

Simplest for testing on one laptop. This prints `T1`'s link:

```powershell
php artisan tinker --execute="echo app(App\Services\TableQrService::class)->signedUrl(App\Models\RestaurantTable::where('table_number','T1')->first());"
```

And `T2`'s:

```powershell
php artisan tinker --execute="echo app(App\Services\TableQrService::class)->signedUrl(App\Models\RestaurantTable::where('table_number','T2')->first());"
```

Copy the whole line it prints — it is long, and truncating it invalidates the signature —
and paste it into your **customer** browser window. That is exactly what scanning the code
does.

Any table works: `T1`–`T9` for dining, `B1`–`B3` for the bar.

### Scanning with a real phone

The links point at `localhost:8000`, which on a phone means *the phone itself*, so a phone
scan will fail to connect. Two options:

- **Stay on the laptop** (recommended) — paste the link as above. Everything is testable
  this way, including scanning the downloaded PNG on screen with any desktop QR reader.
- **Use a real phone** — serve on your network instead. Find your laptop's IP with
  `ipconfig`, set `APP_URL=http://192.168.x.x:8000` in `.env`, run `php artisan config:clear`,
  and start with `php artisan serve --host=0.0.0.0`. Re-download the QR code afterwards so
  it encodes the new address, and make sure the phone is on the same Wi-Fi. Windows Firewall
  may prompt — allow it.

---

## Testing it by hand

### Use four browser sessions

A browser holds **one staff login at a time** — logging in as the kitchen replaces the
waiter. So give each role its own session:

| Window | Role | Browser |
|---|---|---|
| 1 | Customer | Chrome |
| 2 | Waiter | Chrome **Incognito** (`Ctrl+Shift+N`) |
| 3 | Kitchen | Edge |
| 4 | Admin | Edge **InPrivate** (`Ctrl+Shift+N`) |

Arrange them so you can see at least two at once — the point of the walkthrough is that
screens update **without being refreshed**.

### The walkthrough

Get `T1`'s table link first — see [Table QR codes](#table-qr-codes).

| Step | Window | Do this | Expect |
|---|---|---|---|
| 1 | Customer | Open the table link, add two items, check out | Order placed, awaiting payment |
| 2 | Customer | Choose **Pay with cash** | Request goes to the floor |
| 3 | Waiter | Open **Floor** — the cash request appears **without refreshing** — **Record cash payment** | Order marked paid |
| 4 | Kitchen | The ticket appears **live** — **Start**, then **Ready** | Ticket moves across the lanes |
| 5 | Customer | Watch the order page | Timeline advances paid → preparing → ready on its own |
| 6 | Waiter | **Serve** the ready order | Order complete |
| 7 | Admin | **Dashboard**, then **Orders** and **Reports** | Today's figures include the new order |

With Stripe test keys set, pay by card at step 2 instead: card `4242 4242 4242 4242`, any
future expiry, any 3-digit CVC.

### Also worth trying

- **Refunds** — as the customer, go to **My orders**, press **Request a refund** on a paid
  order and fill in the modal. Track its progress on the same page. As the admin, approve or
  decline it under **Refunds**.
- **Reservations** — book from the homepage as a customer; approve it as waiter or admin.
  The venue trades seven days a week, half-hourly from 11:30 to 21:00.
- **Sold out** — as the kitchen, toggle an item unavailable; it greys out on the customer's
  menu **live**.
- **Call waiter** — as a seated customer, press **Call waiter**; the alert appears on the
  floor screen.
- **Tamper protection** — change one character in a table link; expect a **403**.
- **Authorisation** — as the kitchen, try opening `/admin/reports`; expect a **403**.
- **Audit log** — as admin, see every significant change recorded, with who did it.
- **Responsive layout** — narrow a window to phone width, or use `F12` and the device
  toolbar. Every page works down to 360px, and every control is reachable by keyboard.

### Where the emails go

Mail is written to a log, not sent. Look in **`storage/logs/mail-<date>.log`**.

Stripe and AI failures are logged separately, in `storage/logs/integrations-<date>.log`.

---

## Automated tests

```powershell
php artisan test
```

668 tests. They run against **SQLite in memory**, so MySQL does not need to be running and
your demo data is never touched. That is why `pdo_sqlite` and `sqlite3` are in the extension
list.

Useful variations:

```powershell
php artisan test --filter=RefundRequest
```

```powershell
php artisan test --testsuite=Unit
```

Code style:

```powershell
vendor\bin\pint --test
```

---

## What the demo data contains

`php artisan migrate --seed` gives you a populated, internally consistent system:

| | |
|---|---|
| Staff | 4 — admin, waitstaff, kitchen, bar |
| Customers | 12, with history and trust badges (regulars, a flagged no-show, a late cancellation) |
| Tables | 12 — `T1`–`T9` dining, `B1`–`B3` bar |
| Menu | 49 items across 10 categories, with sizes, add-ons, allergens and dietary tags |
| Booking slots | 20 — every half hour, 11:30 to 21:00, seven days a week |
| Reservations | 56, including bookings on **every day of the next fortnight** |
| Orders | ~245 across 8 weeks, with line items, payments and full status histories |
| Reviews | 13 visible (3 featured) plus 1 hidden |

It is also seeded so every dashboard widget has something to show: a cash payment waiting,
an open refund request, a stock conflict, a late ticket, low stock and an unassigned
booking.

---

## Project layout

```
app/
  Enums/                  backed enums; status transitions live here
  Http/Controllers/       thin — Admin/, Customer/, Staff/, Public/
  Http/Requests/          all validation
  Models/
  Policies/               authorisation
  Services/               all business logic
database/
  migrations/  seeders/  factories/
public/css/               style.css (public) + dashboard.css (staff/admin)
resources/
  js/                     Echo, floor, KDS, order tracker, modals
  views/components/       Blade components — layouts, modals, drawers, badges
  views/{admin,staff,customer,public}/
routes/web.php
tests/{Feature,Unit}/
```

Business logic lives in `app/Services`, not controllers. Validation lives in Form Requests.
Status changes go through the enum transition maps, never by setting a column directly.
Views are Blade components — no `@extends` or `@include`. Every interactive control carries
a `data-testid`. No CSS framework.

---

## Command reference

| Task | Command |
|---|---|
| Start everything | `php artisan dev` |
| Reset and reload the demo data | `php artisan migrate:fresh --seed` |
| Run the tests | `php artisan test` |
| Rebuild assets | `npm run build` |
| Clear caches after editing `.env` | `php artisan config:clear` |
| Clear compiled Blade | `php artisan view:clear` |
| List all routes | `php artisan route:list` |
| Export the database to a `.sql` file | `php artisan db:backup` |

`php artisan db:backup` writes a timestamped mysqldump into `storage/backups/`, importable
through phpMyAdmin if you want to inspect the data outside the app.

---

## Troubleshooting

| Problem | Fix |
|---|---|
| `composer install` fails on a PHP version requirement | You are on XAMPP's PHP 8.2. Install PHP 8.3+ and put `C:\php` ahead of `C:\xampp\php` in PATH |
| `No connection could be made … actively refused it` | MySQL is not running. Start it in the XAMPP Control Panel |
| `Unknown database 'coolaroo'` | Do [setup step 4](#4-create-the-database) |
| `php -v` still shows 8.2 | Open a **new** terminal; PATH changes do not reach open ones |
| Pages load but are unstyled | `npm install`, then `npm run build` |
| `php artisan dev` exits immediately | `npm install` has not been run — it provides the process runner |
| Pages load but nothing updates live | Reverb and the queue worker must both be running. Use `php artisan dev`, or terminals 2 and 3 |
| Uploading a menu image fails | `gd` is not enabled in `php.ini` |
| Uploaded images 404 | `php artisan storage:link` |
| Tests fail on `pdo_sqlite` | Enable `pdo_sqlite` and `sqlite3` in `php.ini` |
| `cURL error 60` in `storage/logs/integrations-*.log` | Do [setup step 7](#7-optional--only-if-you-want-to-test-card-payments-or-the-ai) |
| A table link gives a 403 | The link was truncated when copied, or the database was re-seeded since. Get a fresh one |
| "The assistant is busy right now" | No `AI_API_KEY`. That is the designed fallback. The real reason is in `storage/logs/integrations-*.log` |
| Changed `.env`, nothing happened | `php artisan config:clear`, then restart |
| Port 8000 already in use | `php artisan serve --port=8001`, and set `APP_URL=http://localhost:8001` in `.env` so table links match |
| No email arrived | It never sends. Read `storage/logs/mail-<date>.log` |
| Want the demo data back | `php artisan migrate:fresh --seed`. **This wipes the database** |
