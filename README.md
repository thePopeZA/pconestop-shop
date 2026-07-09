# PC One Stop Shop

A custom PHP e-commerce store for **shop.pconestop.co.za** — no WooCommerce, no framework bloat. Pulls the live Syntech product feed, applies pricing, and sells with Yoco checkout.

## Features

- **Automated product feed** — imports Syntech's XML feed (2 500+ products), auto-categorised, with a configurable markup (`cost × 1.25 × 1.15` = markup + VAT).
- **Live stock** — aggregates Cape Town, Johannesburg & Durban warehouse stock; shows in/low/out badges and warehouse availability.
- **Storefront** — home, category browsing with sidebar + filters, product detail with image gallery, search, RRP "you save" pricing.
- **Cart & checkout** — session cart, Yoco hosted checkout, webhook-confirmed payments, automatic stock decrement.
- **Admin panel** — dashboard, product management, orders, manual feed sync + history, settings.
- **Order emails** — customer confirmation + shop notification (gated by `MAIL_ENABLED`).

## Tech

- PHP 8.2+, MySQL/MariaDB, PDO. No Composer dependencies.
- Front-end: hand-written CSS (`assets/css/style.css`), vanilla JS.

## Project layout

```
config/         config loader (.env) + PDO connection
includes/       shared: functions, shop queries, cart, orders, Yoco, mailer, header/footer
lib/            FeedImporter (Syntech XML/CSV/JSON parser)
cron/           fetch_feed.php (CLI feed sync) + crontab.txt
admin/          admin panel (login, dashboard, products, orders, feeds, settings)
assets/         css, js, images
database/       schema.sql
storage/        feed downloads, logs, cache (gitignored)
*.php           storefront pages (index, shop, product, search, cart, checkout, …)
```

## Local setup

1. `cp .env.example .env` and set your DB credentials + feed URL + Yoco keys.
2. Create the database, then `mysql -u root DBNAME < database/schema.sql`.
3. Serve it: `php -S localhost:8080` (or point Apache at the folder).
4. Import products: `php cron/fetch_feed.php full`.
5. Create your admin: visit `/admin/login.php` (first run asks for the `ADMIN_SETUP_KEY`).

## Pricing

`sell_price = feed_cost × MARKUP_MULTIPLIER × VAT_MULTIPLIER` (default `× 1.25 × 1.15`).
Change the multipliers in `.env` and run a full feed sync to recalculate.

See **DEPLOY.md** for production deployment to cPanel.
