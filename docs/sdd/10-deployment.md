# 10. Deployment

## 10.1 Local (XAMPP) — development and report screenshots
1. Install XAMPP (PHP 8.5), Composer, Node LTS.
2. `composer install`, `npm install && npm run build`.
3. Create database `coolaroo`; copy `.env.example` → `.env`; `php artisan key:generate`.
4. `php artisan migrate --seed` (roles, admin, settings, menu, tables, slots, demo data).
5. `php artisan storage:link`.
6. Run in separate terminals: `php artisan serve` (or Apache vhost), `php artisan reverb:start`, `php artisan queue:work --queue=broadcasts,mail,default`, `php artisan schedule:work`.

## 10.2 VPS (Ubuntu) — deployment demo
1. Packages: Nginx, PHP 8.5-FPM (+ mbstring, xml, curl, mysql, redis, gd, zip, bcmath) from the ondrej/php PPA, MySQL 8, Redis, Supervisor, Certbot. Local and server PHP minor versions must match.
2. Deploy code to `/var/www/coolaroo`; `composer install --no-dev -o`; `npm ci && npm run build`.
3. `.env` production values; `php artisan migrate --force --seed`; `php artisan config:cache route:cache view:cache`.
4. Nginx site: root `public/`, PHP-FPM, `/app` WebSocket proxy to Reverb (port 8080) with Upgrade headers.
5. Supervisor programs: `reverb` (`php artisan reverb:start`), `queue` (`php artisan queue:work --queue=broadcasts,mail,default --tries=3`).
6. Cron: `* * * * * cd /var/www/coolaroo && php artisan schedule:run >> /dev/null 2>&1`.
7. HTTPS with Certbot; force HTTPS, and set `FORCE_HTTPS=true`, `SESSION_SECURE_COOKIE=true`, `REVERB_SCHEME=https` in `.env`.
8. Daily `mysqldump` backup kept 14 days — carried by the scheduler's `db:backup` (03:00), so step 6's cron entry is the only one needed. Set `MYSQLDUMP_PATH` if the binary is not on PATH.
No inbound Stripe configuration is needed (no webhooks).

## 10.3 Environment variables
| Key | Value / purpose |
|---|---|
| APP_URL | https://coolaroo.example |
| APP_TIMEZONE | Australia/Melbourne |
| DB_* | MySQL connection |
| QUEUE_CONNECTION | database (local) / redis (VPS) |
| CACHE_STORE | database / redis |
| SESSION_DRIVER | database |
| BROADCAST_CONNECTION | reverb |
| REVERB_APP_ID / KEY / SECRET / HOST / PORT / SCHEME | Reverb |
| STRIPE_KEY / STRIPE_SECRET | Stripe test keys |
| AI_BASE_URL | https://models.github.ai/inference |
| AI_MODEL | openai/gpt-4.1-mini |
| AI_API_KEY | GitHub token with Models access |
| MAIL_* | SMTP / Mailpit |


## 10.4 Seed accounts (demo)
| Role | Email |
|---|---|
| Admin | admin@coolaroo.test |
| Waitstaff | waiter@coolaroo.test |
| Kitchen | kitchen@coolaroo.test |
| Bar | bar@coolaroo.test |
| Customer | customer@coolaroo.test |
