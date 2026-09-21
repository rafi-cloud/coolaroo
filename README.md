# Coolaroo — Restaurant Management System

QR table ordering, a kitchen and bar display, reservations, and an admin dashboard for
a bistro and sports bar. Built with Laravel, MySQL and Laravel Reverb for live updates.

This guide gets it running on your machine and walks you through testing it as a
customer, waiter, kitchen and admin at the same time.

---

## What you need installed

| Tool | Version | Notes |
|---|---|---|
| PHP | 8.3 or newer (built on 8.5) | Extensions: `pdo_mysql`, `mbstring`, `openssl`, `fileinfo`, `curl`, `zip`. For the automated tests also `pdo_sqlite` and `sqlite3` |
| Composer | 2.x | |
| Node.js + npm | 18 or newer | Only used once, to build the front-end assets |
| MySQL | 8.x | XAMPP is fine — you only need its MySQL, not its PHP |
| Git | any | |

Check your PHP extensions with `php -m`.

---

## On a Mac? Read this first

The steps below are written for Windows. Everything works the same on macOS; only these
commands differ. Use **Terminal**, and open new tabs with `Cmd+T`.

**Install the tools** with [Homebrew](https://brew.sh):

```bash
brew install php composer node mysql
```

```bash
brew services start mysql
```

Homebrew's PHP already includes the extensions you need. Homebrew's MySQL has no root
password, so leave `DB_PASSWORD` empty in `.env`. (XAMPP for macOS or Laravel Herd also
work if you already use one of them.)

**Step 2 — create `.env`** (then run `php artisan key:generate` as normal):

```bash
cp .env.example .env
```

**Step 3 — create the database** (in place of the XAMPP command):

```bash
mysql -u root -e "CREATE DATABASE coolaroo CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci"
```

**Step 4 — skip it.** The certificate problem is Windows-only; Homebrew's PHP trusts
HTTPS certificates out of the box.

**Step 6 — browser windows.** Use four separate sessions, for example:

| Window | Role | Browser |
|---|---|---|
| 1 | Customer | Chrome |
| 2 | Waiter | Chrome **Incognito** (`Cmd+Shift+N`) |
| 3 | Kitchen | Safari |
| 4 | Admin | Safari **Private Window** (`Cmd+Shift+N`) |

**Step 6 — getting a table link.** Use single quotes on a Mac:

```bash
php artisan tinker --execute='echo app(App\Services\TableQrService::class)->signedUrl(App\Models\RestaurantTable::where("table_number","T1")->first());'
```

Everything else — `composer install`, `npm run build`, the `php artisan` commands, the
logins and the walkthrough — is identical.

---

## 1. Get the code and install dependencies

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

`npm run build` is required — the compiled assets are not in the repository.

---

## 2. Create your `.env` file

The app will not start without a `.env` file in the project root.

**If Rafi sent you a `.env` file**, put it in the project root and skip to step 3.

**Otherwise**, copy the template and generate an app key:

```powershell
Copy-Item .env.example .env
```

```powershell
php artisan key:generate
```

(In Command Prompt instead of PowerShell, the first command is `copy .env.example .env`.)

The template works as-is. Two features need keys that are **not** in the repository:

| Feature | Key | Without it |
|---|---|---|
| Card payments | `STRIPE_KEY`, `STRIPE_SECRET` (Stripe **test** keys) | Card payment is unavailable. **Cash payment works fully**, so you can still test the whole order flow |
| AI chat and meal builder | `AI_API_KEY` (free Google Gemini key from [aistudio.google.com/apikey](https://aistudio.google.com/apikey)) | Shows "The assistant is busy right now" — the designed fallback, not a bug |

If your MySQL root user has a password, set `DB_PASSWORD` in `.env`. XAMPP's default is
no password.

**Never commit your `.env` file.** It is already in `.gitignore`.

---

## 3. Set up the database

Start **MySQL** from the XAMPP Control Panel, then create the database. Either open
phpMyAdmin (http://localhost/phpmyadmin) and create a database called `coolaroo`, or run:

```powershell
C:\xampp\mysql\bin\mysql.exe -u root -e "CREATE DATABASE coolaroo CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci"
```

Then build the tables and load the demo data:

```powershell
php artisan migrate --seed
```

```powershell
php artisan db:seed --class=DemoSeeder
```

```powershell
php artisan storage:link
```

The demo seeder gives you a realistic menu, customers, orders, reservations and reviews to
test with.

---

## 4. Windows only — let PHP make HTTPS calls (one-time)

PHP on Windows ships without a certificate bundle, so Stripe and the AI fail with
`cURL error 60: unable to get local issuer certificate`. Fix it once:

1. Find your `php.ini`:

   ```powershell
   php --ini
   ```

2. Download the certificate bundle into your PHP folder (adjust `C:\php` to your path):

   ```powershell
   Invoke-WebRequest -Uri https://curl.se/ca/cacert.pem -OutFile C:\php\cacert.pem
   ```

3. In `php.ini`, find these two lines, remove the leading `;`, and set the path:

   ```ini
   curl.cainfo = "C:\php\cacert.pem"
   openssl.cafile = "C:\php\cacert.pem"
   ```

You can skip this step if you are not testing card payments or the AI.

---

## 5. Start the app — four terminals

Open **four separate terminal windows**, `cd` into the project in each, and run one
command per window. Leave all four running while you test.

| Terminal | Command | What it does |
|---|---|---|
| 1 | `php artisan serve` | The website, at http://localhost:8000 |
| 2 | `php artisan reverb:start` | Live updates — kitchen screen, floor plan, order tracking |
| 3 | `php artisan queue:work --queue=broadcasts,mail,default` | Delivers the live updates and emails |
| 4 | `php artisan schedule:work` | Background jobs — payment checks, table auto-clear, reminders |

If terminals 2 or 3 are not running, pages still load but **nothing updates live** — you
would have to refresh by hand.

---

## 6. Test it — four browser windows

Test as four people at once: a **customer**, a **waiter**, the **kitchen**, and the
**admin**. Put the windows side by side so you can watch changes arrive live.

### Why four *separate* browser sessions

A browser keeps **one staff login at a time**. If you log in as the waiter and then as the
kitchen in the same browser, the kitchen login replaces the waiter's. So each role needs
its own session. The easiest way on Windows:

| Window | Role | Browser | Log in at | Email |
|---|---|---|---|---|
| 1 | **Customer** | Chrome | http://localhost:8000/login | `customer@coolaroo.test` |
| 2 | **Waiter** | Chrome **Incognito** (`Ctrl+Shift+N`) | http://localhost:8000/staff/login | `waiter@coolaroo.test` |
| 3 | **Kitchen** | Edge | http://localhost:8000/staff/login | `kitchen@coolaroo.test` |
| 4 | **Admin** | Edge **InPrivate** (`Ctrl+Shift+N`) | http://localhost:8000/staff/login | `admin@coolaroo.test` |

**Every password is `password`.**

There is also a bar account, `bar@coolaroo.test`, which sees drinks only. Swap it in for
the kitchen window to test the bar display.

### Getting a table link (the customer's "QR scan")

Customers start an order by scanning the QR code on their table. On a laptop, print the
link that QR code contains instead, and paste it into the **customer** window:

```powershell
php artisan tinker --execute="echo app(App\Services\TableQrService::class)->signedUrl(App\Models\RestaurantTable::where('table_number','T1')->first());"
```

Change `T1` to any table from `T1` to `T12`. If you edit even one character of the link,
you should get a **403** — that is the tamper protection working.

### A full walkthrough across all four windows

| Step | Window | Do this | You should see |
|---|---|---|---|
| 1 | Customer | Open the table link, add two items to the cart, check out | Order placed, awaiting payment |
| 2 | Customer | Choose **Pay with cash** | The request goes to the waitstaff |
| 3 | Waiter | Open **Floor** — the cash request appears **without refreshing** — click **Record cash payment** | Order marked paid |
| 4 | Kitchen | The ticket appears **live** — click **Start**, then **Ready** | Ticket moves across the lanes |
| 5 | Customer | Watch the order page | Timeline moves paid → preparing → ready on its own |
| 6 | Waiter | Click **Serve** on the ready order | Order complete |
| 7 | Admin | Open the **Dashboard**, then **Orders** and **Reports** | Today's figures include the new order |

If you have Stripe test keys set, choose to pay by card in step 2 instead, with card number
`4242 4242 4242 4242`, any future expiry date and any 3-digit CVC.

### More things to try

- **Reservations** — as the customer, click **Book a table** on the homepage. As the
  waiter or admin, approve it under **Reservations**.
- **Sold out** — as the kitchen, toggle an item unavailable. It greys out on the
  customer's menu **live**.
- **Call waiter** — as the customer at a table, press **Call waiter**. The alert pops up on
  the waiter's floor screen.
- **Admin** — manage the menu, tables and QR codes, staff accounts, reviews, and settings.
  Every change is recorded under **Audit log**.
- **Emails** go to `storage/logs/laravel.log` rather than a real inbox.

---

## 7. Run the automated tests

```powershell
php artisan test
```

This runs the full suite on a temporary in-memory database, so it does **not** need MySQL
running and will not touch your demo data. All tests should pass.

---

## Troubleshooting

| Problem | Fix |
|---|---|
| `No connection could be made … actively refused it` | MySQL is not running. Start it in XAMPP |
| `cURL error 60` in `storage/logs/integrations-*.log` | Do step 4 |
| Page loads but nothing updates live | Terminals 2 (Reverb) and 3 (queue) must both be running |
| Live updates stopped after editing the `REVERB_*` values | Run `npm run build` again, then restart all four terminals |
| Changed `.env` but nothing changed | Run `php artisan config:clear`, then restart `php artisan serve` |
| "The assistant is busy right now" | No `AI_API_KEY`, or Gemini is briefly overloaded — try again. The real reason is in `storage/logs/integrations-*.log` |
| Port 8000 already in use | Run `php artisan serve --port=8001`, and set `APP_URL=http://localhost:8001` in `.env` so table links match |
| Want the demo data back | `php artisan migrate:fresh --seed`, then `php artisan db:seed --class=DemoSeeder`. **This wipes the database** |
