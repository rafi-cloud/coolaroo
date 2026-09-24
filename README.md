# Coolaroo — Restaurant Management System

QR table ordering, live kitchen and bar displays, reservations, refunds and an admin
dashboard for a bistro and sports bar.

This README is the only documentation in the repository. It takes you from a fresh clone
to a running system you can test as four different people at once.

- [What it does](#what-it-does)
- [Built with](#built-with)
- [Before you start](#before-you-start)
- [Setup](#setup) · [macOS and Linux](#macos-and-linux)
- [Running it](#running-it)
- [Logging in](#logging-in)
- [Testing it by hand](#testing-it-by-hand)
- [Automated tests](#automated-tests)
- [What the demo data contains](#what-the-demo-data-contains)
- [Project layout](#project-layout)
- [Command reference](#command-reference)
- [Troubleshooting](#troubleshooting)

---

## What it does

**Customers** scan the QR code on their table, browse the menu, order and pay by card or
cash, then watch a live tracker as the kitchen works. They can book tables, leave a
review, and request a refund within 24 hours of paying.

**Waitstaff** get a live floor plan — table status, cash requests, ready-to-serve alerts
and call-waiter pings arrive without refreshing. They seat walk-ins, assign bookings,
take orders at the table, collect cash and raise refund requests.

**Kitchen and bar** each get their own station display, showing only their own lines,
with per-item ready tickboxes and an ETA per ticket.

**Admins** manage the menu, tables and QR codes, staff accounts, reservations, reviews,
refunds and settings, with reports on sales, items, operations, bookings and feedback.
Every significant change is written to an audit log.

An AI dining assistant and meal builder are available on the public site when an API key
is configured.

## Built with

| Layer | Choice |
|---|---|
| Framework | Laravel 13 (PHP 8.3+) |
| Database | MySQL 8 |
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

| Tool | Version | Notes |
|---|---|---|
| PHP | 8.3+ | Extensions: `pdo_mysql`, `mbstring`, `openssl`, `fileinfo`, `curl`, `zip`, plus `pdo_sqlite` and `sqlite3` for the tests |
| Composer | 2.x | |
| Node.js + npm | 18+ | Builds the front-end assets |
| MySQL | 8.x | XAMPP is fine — you need only its **MySQL**, not its PHP |
| Git | any | |

Check your extensions with `php -m`.

> **Do not use XAMPP's bundled PHP.** It is usually older than 8.3. Install PHP separately
> and make sure `php -v` reports the version you expect.

---

## Setup

Commands are PowerShell. See [macOS and Linux](#macos-and-linux) for the few that differ.

### 1. Clone and install

```powershell
git clone -b develop https://github.com/rafi-cloud/coolaroo.git
```

```powershell
cd coolaroo
```

```powershell
composer install
```

```powershell
npm install
```

```powershell
npm run build
```

`npm run build` is required — compiled assets are not committed.

### 2. Create your `.env`

The app will not boot without it. If a teammate sent you a filled-in `.env`, drop it in
the project root and skip to step 3. Otherwise:

```powershell
Copy-Item .env.example .env
```

```powershell
php artisan key:generate
```

The template works as-is. Set `DB_PASSWORD` if your MySQL root user has one — XAMPP's
default is blank. **Never commit `.env`**; it is already ignored.

Two features need keys that are deliberately **not** in the repository:

| Feature | Key | Without it |
|---|---|---|
| Card payments | `STRIPE_KEY`, `STRIPE_SECRET` (test keys, `pk_test_…` / `sk_test_…`) | Card payment is unavailable. **Cash works end to end**, so you can still test the full order flow |
| AI assistant | `AI_API_KEY` — free from [aistudio.google.com/apikey](https://aistudio.google.com/apikey) | Shows "The assistant is busy right now". That is the designed fallback, not a bug |

### 3. Create and populate the database

Start **MySQL** from the XAMPP Control Panel, then:

```powershell
C:\xampp\mysql\bin\mysql.exe -u root -e "CREATE DATABASE coolaroo CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci"
```

```powershell
php artisan migrate --seed
```

```powershell
php artisan storage:link
```

One command does the whole database: `migrate --seed` builds the tables **and** loads the
demo data. See [what you get](#what-the-demo-data-contains).

### 4. Windows only — let PHP make HTTPS calls

PHP on Windows ships without a CA bundle, so Stripe and the AI fail with
`cURL error 60: unable to get local issuer certificate`. **Skip this if you are not
testing card payments or the AI.**

Find your `php.ini`:

```powershell
php --ini
```

Download the bundle (adjust `C:\php` to your PHP folder):

```powershell
Invoke-WebRequest -Uri https://curl.se/ca/cacert.pem -OutFile C:\php\cacert.pem
```

Then in `php.ini`, uncomment these two lines and point them at the file:

```ini
curl.cainfo = "C:\php\cacert.pem"
openssl.cafile = "C:\php\cacert.pem"
```

---

## macOS and Linux

Everything is identical except these. Install with [Homebrew](https://brew.sh):

```bash
brew install php composer node mysql && brew services start mysql
```

Step 2 — copy the env file:

```bash
cp .env.example .env
```

Step 3 — create the database:

```bash
mysql -u root -e "CREATE DATABASE coolaroo CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci"
```

Step 4 — skip it. Homebrew's PHP trusts HTTPS certificates already.

Homebrew's MySQL has no root password, so leave `DB_PASSWORD` blank.

---

## Running it

### The quick way

```powershell
php artisan dev
```

One terminal, running the web server, Reverb, a queue worker and Vite together. Good for
day-to-day work. **It does not run the scheduler**, and its queue worker ignores queue
priority — so use the four terminals below when testing anything time-based.

### The full way — four terminals

`cd` into the project in each and leave all four running.

| # | Command | What it does |
|---|---|---|
| 1 | `php artisan serve` | The site, at http://localhost:8000 |
| 2 | `php artisan reverb:start` | Live updates — station displays, floor plan, order tracker |
| 3 | `php artisan queue:work --queue=broadcasts,mail,default` | Delivers those updates and the emails |
| 4 | `php artisan schedule:work` | Payment reconciliation, table auto-clear, booking reminders, no-show suggestions |

The queue order is priority, not decoration: a floor or kitchen frame arriving late is
useless, a reminder email arriving late is not.

Without terminals 2 and 3, pages still work but **nothing updates live** — you would have
to refresh by hand.

---

## Logging in

**Every seeded account's password is `Hello@123`.**

| Role | URL | Email |
|---|---|---|
| Customer | http://localhost:8000/login | `customer@coolaroo.test` |
| Admin | http://localhost:8000/staff/login | `admin@coolaroo.test` |
| Waitstaff | http://localhost:8000/staff/login | `waiter@coolaroo.test` |
| Kitchen | http://localhost:8000/staff/login | `kitchen@coolaroo.test` |
| Bar | http://localhost:8000/staff/login | `bar@coolaroo.test` |

There are eleven more customer accounts with order history — `jack.t@coolaroo.test`,
`sarah.j@coolaroo.test` and so on, all the same password.

> If you register a **new** account, the password must be at least 8 characters with upper
> and lower case, a number and a symbol. `Hello@123` satisfies it.

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

On macOS use Safari and a Safari Private Window for 3 and 4 (`Cmd+Shift+N`).

### Get a table link — the customer's "QR scan"

Customers start by scanning the QR code on their table. On a laptop, print the link that
code contains and paste it into the **customer** window:

```powershell
php artisan tinker --execute="echo app(App\Services\TableQrService::class)->signedUrl(App\Models\RestaurantTable::where('table_number','T1')->first());"
```

On macOS and Linux, swap the quoting:

```bash
php artisan tinker --execute='echo app(App\Services\TableQrService::class)->signedUrl(App\Models\RestaurantTable::where("table_number","T1")->first());'
```

Use any table from `T1`–`T9`, or `B1`–`B3` for the bar. Change one character of the link
and you should get a **403** — that is the tamper protection working.

### The walkthrough

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
  order and fill in the modal. Track its progress on the same page. As the admin, approve
  or decline it under **Refunds**.
- **Reservations** — book from the homepage as a customer; approve it as waiter or admin.
  The venue trades seven days a week, half-hourly from 11:30 to 21:00.
- **Sold out** — as the kitchen, toggle an item unavailable; it greys out on the
  customer's menu **live**.
- **Call waiter** — as a seated customer, press **Call waiter**; the alert appears on the
  floor screen.
- **Audit log** — as admin, see every change recorded.

### Where the emails go

Mail is written to a log, not sent. Look in **`storage/logs/mail-<date>.log`**.

Stripe and AI failures are logged separately, in `storage/logs/integrations-<date>.log`.

---

## Automated tests

```powershell
php artisan test
```

668 tests. They run against **SQLite in memory**, so MySQL does not need to be running and
your demo data is never touched.

Useful variations:

```powershell
php artisan test --filter=RefundRequest
```

```powershell
php artisan test --testsuite=Unit
```

Check code style before pushing:

```powershell
vendor\bin\pint --test
```

```powershell
vendor\bin\pint
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

To reset at any point — **this wipes the database**:

```powershell
php artisan migrate:fresh --seed
```

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

**Conventions** if you are contributing: business logic goes in `app/Services`, not
controllers. Validation goes in Form Requests. Status changes go through the enum
transition maps, never by setting a column directly. Views use Blade components — no
`@extends` or `@include`. Every interactive control carries a `data-testid`. No CSS
framework.

---

## Command reference

| Task | Command |
|---|---|
| Start everything (quick) | `php artisan dev` |
| Reset the database | `php artisan migrate:fresh --seed` |
| Run the tests | `php artisan test` |
| Fix code style | `vendor\bin\pint` |
| Rebuild assets | `npm run build` |
| Clear caches after editing `.env` | `php artisan config:clear` |
| Clear compiled Blade | `php artisan view:clear` |
| List all routes | `php artisan route:list` |
| Back up the database | `php artisan db:backup` |

---

## Troubleshooting

| Problem | Fix |
|---|---|
| `No connection could be made … actively refused it` | MySQL is not running. Start it in XAMPP |
| `cURL error 60` in `storage/logs/integrations-*.log` | Do [setup step 4](#4-windows-only--let-php-make-https-calls) |
| Pages load but nothing updates live | Reverb and the queue worker must both be running |
| Live updates broke after editing `REVERB_*` | `npm run build` again, then restart everything |
| Changed `.env`, nothing happened | `php artisan config:clear`, then restart `php artisan serve` |
| A Blade edit has no effect | `php artisan view:clear` |
| "The assistant is busy right now" | No `AI_API_KEY`, or the model is briefly overloaded. Real reason is in `storage/logs/integrations-*.log` |
| Port 8000 already in use | `php artisan serve --port=8001`, and set `APP_URL=http://localhost:8001` in `.env` so table links match |
| Tests fail on `pdo_sqlite` | Enable `pdo_sqlite` and `sqlite3` in `php.ini` |
| No email arrived | It never sends. Read `storage/logs/mail-<date>.log` |
| Want the demo data back | `php artisan migrate:fresh --seed`. **This wipes the database** |
