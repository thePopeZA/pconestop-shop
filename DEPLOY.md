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
> `APP_URL` must be the exact public HTTPS URL: the Yoco success/cancel links and
> all admin/email links are built from it.

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

> **Upgrading an existing site?** The schema self-migrates on use — the admin
> `role` / `must_change_password` columns and `order_items.cost_price` are added
> automatically on the next admin login / Profit-split visit. No manual SQL needed.

## 5. Cron jobs

cPanel → **Cron Jobs**. Add the lines from `cron/crontab.txt`, adjusting the PHP
path (cPanel → *Select PHP Version*, or `which php` in Terminal):

| Schedule | Job | Purpose |
|---|---|---|
| every 30 min | `cron/fetch_feed.php update` | refresh stock & prices |
| `15 6,18 * * *` | `cron/fetch_feed.php full` | full catalogue rebuild twice daily |
| `0 6 1 * *` | `cron/commission_report.php` | **1st of month** — emails the partner report + the owner statement for the month just ended |

The commission cron sends nothing until both mail (section 7) and the report
recipients (section 6) are set — it is a safe no-op otherwise.

## 6. Admin accounts, roles & first login

Three roles, in ascending power: **Staff → Owner → Partner**.

| Role | Who | Can do | Cannot |
|---|---|---|---|
| **Partner** | You (build owner) | Everything: commission %, profit split, both monthly reports, manage **all** logins & roles | — |
| **Owner** (`admin`) | William Holliday (shop owner) | Run the shop, create/manage **staff**, change own password | See commission/profit, touch the partner account, change roles |
| **Staff** | Shop workers | Shop admin pages, change own password | Manage other users, see commission |

**First-time setup on production:**
1. Visit `https://shop.pconestop.co.za/admin/login.php`. On the very first run it
   asks for the `ADMIN_SETUP_KEY` plus a username/password — create **your own
   (partner)** account here.
2. That first account is created with the `admin` (owner) role by default. Promote
   it to **partner** once: phpMyAdmin → `admin_users` →
   `UPDATE admin_users SET role='partner' WHERE username='<you>';`
   (After this the 💰 Profit split and full 👤 Admin users controls appear.)
3. In **👤 Admin users**, create William with the **Owner** role and a temporary
   password. He is forced to set his own password on first login.
4. William then creates his own **Staff** logins the same way — each staffer sets
   their own password on first login.

**First-login password change:** any account created here (or whose password an
admin resets) must choose a new password before it can use the panel — enforced
on every page, not just shown. A **🔑 Change password** link is always in the nav.

**Commission privacy:** the commission rate, the profit split, and the monthly
report recipients live only on the partner **💰 Profit split** page. The owner
never sees them.

## 7. Payments — Yoco (what you need to actually take money)

The store uses **Yoco hosted checkout**: the customer fills in the cart/address,
is redirected to Yoco’s secure payment page, pays by card, and is sent back; a
webhook then marks the order paid, decrements stock, and fires the emails.

To accept **real** payments you need three things in `.env`, all from your Yoco
account (Yoco Dashboard → **Developers / API keys** and **Webhooks**):

```
YOCO_PUBLIC_KEY=pk_live_xxxxxxxx      # live publishable key
YOCO_SECRET_KEY=sk_live_xxxxxxxx      # live secret key  (server-side; keep private)
YOCO_WEBHOOK_SECRET=whsec_xxxxxxxx    # from registering the webhook (below)
```

**Steps to go live:**
1. **Create/verify a Yoco business account** at yoco.com and complete their
   onboarding (business details, bank account for payouts, FICA/verification).
   Card payments only work once Yoco has approved the account.
2. **Switch keys from test to live.** The shop currently ships with `pk_test_…` /
   `sk_test_…`. Replace both with the `pk_live_…` / `sk_live_…` pair.
3. **Register the webhook** so payments confirm even if the customer closes the
   browser. In the Yoco Dashboard (or via their Webhooks API) add:
   ```
   https://shop.pconestop.co.za/webhook.php
   ```
   Copy the returned signing secret (`whsec_…`) into `YOCO_WEBHOOK_SECRET`. The
   receiver verifies every event’s signature; if the secret is missing it falls
   back to confirming each payment against the Yoco API before trusting it.
4. **HTTPS is mandatory** — Yoco will not redirect back to a non-HTTPS site, and
   `APP_URL` must be `https://shop.pconestop.co.za` (the success/cancel URLs are
   derived from it: `checkout-success.php` / `checkout-cancel.php`).
5. **Do a live smoke test:** buy one cheap item with a real card (you can refund
   it in the Yoco dashboard). Confirm: you’re redirected to Yoco, payment
   succeeds, you land back on the success page, the order shows **Paid** in
   **🧾 Orders**, stock dropped by one, and the order/PO emails were sent
   (or logged if mail is still off).

Notes: amounts are sent in **cents, ZAR**; the order total includes the courier
fee (R180 ex VAT → R207 incl, free when the order’s Syntech cost > R2 500 ex VAT).
Every payment is written to `storage/logs/webhook.log` for audit.

> **Test mode:** keep the `pk_test_…`/`sk_test_…` keys to trial the full flow
> without real money — Yoco provides test card numbers in their docs. No real
> charge occurs on test keys.

## 8. Email (SMTP) — order, supplier PO & commission mails

Sending is **off** by default (`MAIL_ENABLED=false`): every message is instead
logged to `storage/logs/mail.log` and saved as a full HTML preview under
`storage/logs/mail/`, so you can check them before going live.

When ready, in `.env`:
```
MAIL_ENABLED=true
MAIL_HOST=mail.pconestop.co.za     # cPanel mail server (or smtp provider)
MAIL_PORT=465                       # 465 = implicit TLS, 587 = STARTTLS
MAIL_USER=orders@pconestop.co.za    # a real mailbox you created in cPanel
MAIL_PASS=your-mailbox-password
MAIL_FROM=orders@pconestop.co.za
MAIL_FROM_NAME="PC One Stop Shop"
ORDER_NOTIFY_EMAIL=orders@pconestop.co.za
```
When `MAIL_HOST` is set the app sends via authenticated SMTP; otherwise it falls
back to PHP `mail()`. **Create the `orders@pconestop.co.za` mailbox in cPanel
first** (Email Accounts), then use its password above.

What gets sent on a paid order:
- **Customer** — order confirmation.
- **You (orders@)** — “new paid order” notification.
- **Syntech rep** — drop-ship purchase order (dealer prices only, delivery
  address, big red *don’t invoice the customer* banner); copy to orders@.
  Set the rep’s name/email in **⚙️ Settings → Syntech sales rep**.

Monthly, on the 1st (needs section 5’s cron + recipients set on the **Profit
split** page):
- **You (partner)** — your commission income report.
- **William (owner)** — a sales & commission statement explaining his invoice.

## 9. Go-live checklist

- [ ] `APP_DEBUG=false` and `APP_URL=https://shop.pconestop.co.za` in `.env`.
- [ ] Live Yoco keys in place, webhook registered, `YOCO_WEBHOOK_SECRET` set.
- [ ] One real card purchase tested end-to-end, then refunded.
- [ ] `orders@pconestop.co.za` mailbox created; `MAIL_ENABLED=true`; a test order
      email received.
- [ ] Syntech rep name/email set in Settings.
- [ ] Commission report recipients set on the Profit split page; commission cron added.
- [ ] Partner + owner accounts created; both changed their first-login password.
- [ ] `.env`, `/config`, `/includes`, `/lib`, `/cron`, `/storage` not web-accessible —
      the shipped `.htaccess` blocks them; verify `https://shop.pconestop.co.za/.env`
      returns **403**, and `…/storage/logs/mail/` is not listable.
- [ ] `install.php` deleted; `ADMIN_SETUP_KEY` changed after setup.
- [ ] HTTPS forced (cPanel → Domains → enforce SSL).
- [ ] Dev/test passwords rotated.
