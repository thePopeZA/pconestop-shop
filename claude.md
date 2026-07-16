# PC One Stop Shop — Claude Instructions

Custom-built PHP e-commerce site for **shop.pconestop.co.za**. No framework, no WooCommerce — plain PHP by design.

## Stack
- PHP 8.2 (XAMPP locally) · MariaDB 10.4 · PDO
- No framework, no Composer packages — plain PHP with `declare(strict_types=1)`
- Vanilla JS + CSS in `assets/` — no build step, no JS frameworks
- Payments: Yoco hosted checkout + webhook
- Product data: Syntech supplier feed (XML)

## Project layout
```
index.php, shop.php, product.php,
cart.php, checkout*.php, search.php  → public storefront pages
webhook.php                          → Yoco payment webhook
admin/                               → staff admin (login, orders, products, feeds, settings)
includes/                            → shared libs (shop.php, cart_lib.php, orders.php, Yoco.php, mailer.php, functions.php, header/footer)
lib/FeedImporter.php                 → Syntech feed importer
cron/fetch_feed.php                  → feed cron entry (`php cron/fetch_feed.php full|update`)
config/config.php                    → .env loader, app constants, session, error handling
config/database.php                  → PDO connection
database/schema.sql                  → full schema
install.php                          → one-click server installer (DB setup + import)
storage/                             → logs, cache (gitignored contents)
DEPLOY.md                            → deployment steps for cPanel
```

## Critical rules
- **Syntech UPDATE feed is a price/stock-only DELTA** — it has NO name/category/brand/images. `FeedImporter::processRecord` uses an `$isRich` check so delta rows only update price/stock and unknown SKUs are skipped. Never let a delta import overwrite rich fields — this previously wiped product names/categories.
- **Pricing rule (RRP-anchored):** sell price targets Syntech's `rrp_incl`, clamped so the ex-VAT margin over feed dealer cost stays between the floor and cap (settings `price_floor_margin_pct` 15 / `price_cap_margin_pct` 35 / `price_rrp_nudge_pct` 100). Products without an RRP fall back to cost × `MARKUP_MULTIPLIER` (1.25) × `VAT_MULTIPLIER` (1.15). All pricing goes through `calc_sell_price()` in `includes/functions.php` — never inline pricing math.
- **Shipping:** flat `SHIPPING_FLAT` (R99), free over `SHIPPING_FREE_OVER` (R1000).
- **Email sending is gated** by `MAIL_ENABLED` in .env — default OFF until owner says go. Never remove the guard.
- **Secrets live only in gitignored `.env`** (Yoco keys, DB creds, Syntech feed key). Never commit them; `.env.example` documents the shape.
- Yoco is on TEST keys until go-live.

## Coding conventions
- Follow the existing pattern: procedural pages that `require config/config.php`, shared logic in `includes/`, prepared PDO statements everywhere.
- Escape all output with the existing `e()` helper; never echo raw user/feed data.
- Admin pages go through `admin/_bootstrap.php` (auth check) and use `_header.php`/`_footer.php`.
- Timezone is `Africa/Johannesburg`; currency is ZAR.
- No new architectural patterns (routers, ORMs, templating engines) without asking.

## Feed details
- Full feed: ~2600 products, root `<syntechstock><stock><product>`; fields include sku, price (dealer cost), rrp_incl, promo_price, cpt/jhb/dbn stock, description (CDATA HTML), featured_image, all_images (pipe-separated), categorytree (`A > B|C` — pipe separates memberships, first is primary), brand/manufacturer inside `<attributes>`.
- Cron: update feed every 30 min, full feed twice daily (see `cron/crontab.txt`).

## Deployment
- Prod: cPanel account `pconeurd`, docroot `/home/pconeurd/shop.pconestop.co.za` — see `DEPLOY.md`.
- Local: XAMPP, DB `pconestop` (root, no password).
