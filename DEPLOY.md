# Deploying to shop.pconestop.co.za (cPanel)

## 1. Get the files onto the server

**Option A — cPanel Git Version Control (recommended):**
1. cPanel → **Git™ Version Control** → *Create*.
2. Clone URL: `https://github.com/thepopeZA/pconestop-shop.git`
   (use a Personal Access Token as the password when prompted).
3. Set the repository path to the subdomain docroot: `/home/pconeurd/shop.pconestop.co.za`
4. To update later: cPanel → Git → **Pull**.

**Option B — File Manager / FTP:**
Upload the whole project into `/home/pconeurd/shop.pconestop.co.za/` (so `index.php` sits at the docroot).

## 2. Environment file

Upload `.env.production` and **rename it to `.env`** in the docroot.
It already has the production DB credentials and `APP_URL=https://shop.pconestop.co.za`.
> `.env` is git-ignored, so it is never committed — you must place it manually.

## 3–4. Database + first import (easy way: the web installer)

Once the files and `.env` are in place, open in your browser:
```
https://shop.pconestop.co.za/install.php?key=YOUR_ADMIN_SETUP_KEY
```
(`YOUR_ADMIN_SETUP_KEY` = the `ADMIN_SETUP_KEY` value in your `.env`.)

Click **“Run full setup”** — it creates all tables and imports the catalogue
(~30–60s). When products show, **delete `install.php`**.

> Prefer to do it manually? Import `database/schema.sql` via phpMyAdmin, then run
> `php cron/fetch_feed.php full` from cPanel Terminal.

## 5. Cron jobs (auto feed refresh)

cPanel → **Cron Jobs**. Add the two lines from `cron/crontab.txt`
(full sync every 3h, update sync hourly). Adjust the PHP path to match step 4.

## 6. Create your admin account

Visit `https://shop.pconestop.co.za/admin/login.php`. On first run it asks for the
`ADMIN_SETUP_KEY` (in your `.env`) plus a username/password you choose.

## 7. Yoco

- The store currently uses **TEST** keys. To go live, replace `YOCO_PUBLIC_KEY` /
  `YOCO_SECRET_KEY` in `.env` with your **live** keys (`pk_live_…` / `sk_live_…`).
- **Webhook (recommended):** register `https://shop.pconestop.co.za/webhook.php`
  as a Yoco webhook (via Yoco dashboard or their webhooks API) and paste the
  returned `whsec_…` secret into `YOCO_WEBHOOK_SECRET`. This confirms payments
  reliably even if the customer closes the browser after paying.

## 8. Email

Order emails are **off** (`MAIL_ENABLED=false`) — everything is logged to
`storage/logs/mail.log` instead. When ready, set `MAIL_ENABLED=true` and fill the
`MAIL_*` settings (use the cPanel mailbox for `shop@pconestop.co.za`).

## 9. Security checklist

- [ ] `APP_DEBUG=false` in production `.env` (already set).
- [ ] Confirm `.env`, `/config`, `/includes`, `/lib`, `/cron`, `/storage` are not
      web-accessible — the shipped `.htaccess` blocks them; verify by visiting
      `https://shop.pconestop.co.za/.env` (should be 403).
- [ ] Force HTTPS (cPanel → Domains → enforce SSL, or add a redirect).
- [ ] Change `ADMIN_SETUP_KEY` after creating your admin.
- [ ] Rotate the passwords that were shared during development.
```
