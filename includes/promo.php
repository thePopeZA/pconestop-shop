<?php
/**
 * Promo feed — shared data layer for the WhatsApp promo system.
 * Used by api/deals.json.php (JSON endpoint) and promo-82f02098/ (gallery),
 * so the product selection + caption logic lives in exactly one place.
 *
 * "Deal" in this shop = our selling price (products.price) is below the
 * supplier RRP (products.rrp) — the same definition as promo_products() and
 * the storefront "SAVE Rxx" badge. price_now = price, price_was = rrp.
 */

declare(strict_types=1);

require_once BASE_PATH . '/includes/shop.php';

// Only promote deals on real products, not cheap accessories with inflated
// RRPs. Deals are ranked by actual Rand saved (below RRP), highest first.
const PROMO_MIN_PRICE = 750.00;
// Ignore token discounts — a deal must save at least this much (Rand) to qualify.
const PROMO_MIN_SAVING = 100.00;
// Promo product links always point at the LIVE shop, even when the status
// maker / endpoint run on localhost — captions get frozen for public posting.
const PROMO_PUBLIC_BASE = 'https://shop.pconestop.co.za';

// Human-curated daily flow.
const PROMO_PICKER_DIR = 'pick-da5c1665';                      // secret picker folder
const PROMO_PICKER_SECRET = 'b7e0d33daf83d5592e01b3e312f2b13a'; // signs picker publish tokens
// Recipients for BOTH promo emails (morning picker invite + pack-ready).
// TESTING value below — switch to ['shop@pconestop.co.za'] for production.
const PROMO_RECIPIENTS = ['jurgsw@gmail.com'];

/**
 * A product's top-level (primary) category display name — the first segment of
 * its category_path ("Computers > Mini PCs > Barebone systems" -> "Computers").
 * category_path is the primary/first membership as resolved by the importer.
 */
function promo_category(array $p): string
{
    $path = trim((string)($p['category_path'] ?? ''));
    if ($path !== '') {
        $first = trim(explode('>', $path)[0]);
        if ($first !== '') {
            return $first;
        }
    }
    return 'Other';
}

/** Shape one product row into the public promo item structure. */
function promo_item(array $p, bool $isDeal): array
{
    $now  = (float)$p['price'];
    $was  = $isDeal && $p['rrp'] !== null ? (float)$p['rrp'] : null;
    $save = $was !== null ? round($was - $now, 2) : 0.0;
    $pct  = ($was !== null && $was > 0) ? (int)round(($was - $now) / $was * 100) : 0;

    $item = [
        'slug'        => (string)$p['slug'],
        'name'        => (string)$p['name'],
        'brand'       => (string)($p['brand'] ?? ''),
        'category'    => promo_category($p),
        'price_now'   => $now,
        'price_was'   => $was,
        'save_amount' => $save,
        'save_pct'    => $pct,
        'stock'       => ($p['stock_status'] === 'low_stock') ? 'Low stock' : 'In stock',
        'image'       => (string)($p['image_url'] ?? ''),
        'url'         => PROMO_PUBLIC_BASE . '/product.php?slug=' . rawurlencode((string)$p['slug']),
        'type'        => $isDeal ? 'deal' : 'arrival',
    ];
    $item['caption'] = promo_caption($item); // frozen at publish time by the maker
    return $item;
}

/**
 * Raw qualifying deal rows (active, in stock, priced below RRP, with an image),
 * ranked by Rand saving desc. $minPrice / $minSaving are the only filters —
 * one place so the auto-feed and the human picker share identical logic.
 */
function promo_query_deals(float $minPrice, float $minSaving): array
{
    $stmt = db()->prepare(
        "SELECT * FROM products
         WHERE active = 1 AND stock_qty > 0
           AND rrp IS NOT NULL AND rrp > price
           AND price >= ?
           AND (rrp - price) >= ?
           AND image_url IS NOT NULL AND image_url <> ''
         ORDER BY (rrp - price) DESC, (rrp - price) / rrp DESC, name ASC"
    );
    $stmt->execute([$minPrice, $minSaving]);
    return $stmt->fetchAll();
}

/**
 * EVERY qualifying deal, no price floor and no minimum saving — the human is
 * the filter in the picker flow. Returns promo items, Rand-saving desc.
 */
function promo_all_deals(): array
{
    $out = [];
    foreach (promo_query_deals(0.0, 0.0) as $p) {
        $out[] = promo_item($p, true);
    }
    return $out;
}

/**
 * Build the promo feed.
 * - deals: every active, in-stock product priced below RRP, with an image,
 *   sorted by discount percentage (highest first).
 * - arrivals: newest in-stock products (homepage "Just arrived" source),
 *   excluding anything already in deals.
 *
 * @return array{deals: array<int,array>, arrivals: array<int,array>}
 */
function promo_feed(int $arrivalsLimit = 12): array
{
    $dealRows = promo_query_deals(PROMO_MIN_PRICE, PROMO_MIN_SAVING);

    $deals = [];
    $inDeals = [];
    foreach ($dealRows as $p) {
        $deals[] = promo_item($p, true);
        $inDeals[$p['slug']] = true;
    }

    $arrivalRows = db()->query(
        "SELECT * FROM products
         WHERE active = 1 AND stock_qty > 0
           AND image_url IS NOT NULL AND image_url <> ''
         ORDER BY created_at DESC, id DESC
         LIMIT 60"
    )->fetchAll();

    $arrivals = [];
    foreach ($arrivalRows as $p) {
        if (isset($inDeals[$p['slug']])) {
            continue;
        }
        $arrivals[] = promo_item($p, false);
        if (count($arrivals) >= $arrivalsLimit) {
            break;
        }
    }

    return ['deals' => $deals, 'arrivals' => $arrivals];
}

/**
 * The ordered 10-card list for a promo run, with CATEGORY SPREAD so one
 * category can't dominate. Qualifying deals (already Rand-saving desc) are:
 *   Pass 1: best deal from each category, categories ordered by their best
 *           deal's saving; Pass 2: second-best per category; and so on until
 *           $count is reached or the deals run out. Only then do we fill with
 *           arrivals. Final pack order: deals by Rand saving desc, arrivals
 *           last (newest first).
 */
function promo_card_list(array $feed, int $count = 10): array
{
    // Group deals by category, preserving the incoming saving-desc order.
    // Because $feed['deals'] is sorted by saving desc, the order in which
    // categories first appear = ordered by each category's best deal.
    $byCat = [];
    foreach ($feed['deals'] as $d) {
        $cat = $d['category'] !== '' ? $d['category'] : 'Other';
        $byCat[$cat][] = $d;
    }
    $catOrder = array_keys($byCat);

    // Round-robin: pass N takes the (N+1)-th best deal from each category.
    $selected = [];
    $round = 0;
    $pickedThisRound = true;
    while (count($selected) < $count && $pickedThisRound) {
        $pickedThisRound = false;
        foreach ($catOrder as $cat) {
            if (isset($byCat[$cat][$round])) {
                $selected[] = $byCat[$cat][$round];
                $pickedThisRound = true;
                if (count($selected) >= $count) {
                    break;
                }
            }
        }
        $round++;
    }

    // Only if deals are exhausted do we top up with arrivals (newest first).
    if (count($selected) < $count) {
        foreach ($feed['arrivals'] as $a) {
            $selected[] = $a;
            if (count($selected) >= $count) {
                break;
            }
        }
    }
    $selected = array_slice($selected, 0, $count);

    // Final pack order: deals by Rand saving desc; arrivals after, newest first
    // (usort is stable in PHP 8+, so arrivals keep their incoming order).
    usort($selected, static function (array $a, array $b): int {
        $aDeal = $a['type'] === 'deal';
        $bDeal = $b['type'] === 'deal';
        if ($aDeal !== $bDeal) {
            return $aDeal ? -1 : 1;
        }
        if ($aDeal) {
            return $b['save_amount'] <=> $a['save_amount'];
        }
        return 0;
    });

    return $selected;
}

/** WhatsApp caption text for one promo item. Deals are framed vs RRP (honest —
 *  we never sold at RRP, so we say "RRP", not "was"). */
function promo_caption(array $it): string
{
    $url = $it['url'];
    if ($it['type'] === 'deal') {
        return "🔥 SAVE " . money((float)$it['save_amount']) . "! {$it['name']} — now "
            . money((float)$it['price_now']) . ' (RRP ' . money((float)$it['price_was']) . ").\n"
            . "Order: {$url}\n"
            . '🚚 Nationwide delivery · Yoco secure';
    }
    return "⚡ JUST LANDED: {$it['name']} — " . money((float)$it['price_now']) . ".\n"
        . "Order: {$url}\n"
        . '🚚 Nationwide delivery · Yoco secure';
}

/* ---------------------------------------------------------------------------
 * Picker publish tokens + shared promo email (both used by the daily flow).
 * ------------------------------------------------------------------------- */

/** Signed, expiring token embedded in the picker page and required by publish. */
function promo_make_token(int $ttlSeconds = 7200): string
{
    $exp = time() + $ttlSeconds;
    return $exp . '.' . hash_hmac('sha256', (string)$exp, PROMO_PICKER_SECRET);
}

function promo_check_token(string $token): bool
{
    $parts = explode('.', $token, 2);
    if (count($parts) !== 2) {
        return false;
    }
    [$exp, $sig] = $parts;
    if (!ctype_digit($exp) || (int)$exp < time()) {
        return false;
    }
    return hash_equals(hash_hmac('sha256', $exp, PROMO_PICKER_SECRET), $sig);
}

/** Public URL of the secret picker page. */
function promo_picker_url(): string
{
    return PROMO_PUBLIC_BASE . '/' . PROMO_PICKER_DIR . '/';
}

/**
 * Are we running on the real production host? Real promo emails only send here;
 * everywhere else (localhost, staging, CLI off-prod) is a dry-run. Uses the
 * request host when available, else falls back to APP_URL (for CLI/cron).
 */
function promo_is_prod(): bool
{
    $host = strtolower(explode(':', (string)($_SERVER['HTTP_HOST'] ?? ''))[0]);
    if ($host === '') {
        $host = strtolower((string)parse_url((string)env('APP_URL', ''), PHP_URL_HOST));
    }
    return $host === 'shop.pconestop.co.za';
}

/**
 * Send a promo email to PROMO_RECIPIENTS, or dry-run off prod.
 * Returns a status array (also suitable for printing).
 */
function promo_send(string $subject, string $htmlBody, string $plainSummary): array
{
    if (!promo_is_prod()) {
        return [
            'sent' => false, 'dry_run' => true,
            'recipients' => PROMO_RECIPIENTS, 'subject' => $subject, 'summary' => $plainSummary,
        ];
    }
    require_once BASE_PATH . '/includes/mailer.php';
    $from = 'no-reply@pconestop.co.za';
    $fromName = 'PC One Stop Promos';
    $n = 0;
    foreach (PROMO_RECIPIENTS as $to) {
        $to = trim((string)$to);
        if ($to !== '' && filter_var($to, FILTER_VALIDATE_EMAIL)) {
            dispatch_mail($to, $subject, $htmlBody, $from, $fromName);
            $n++;
        }
    }
    return ['sent' => true, 'dry_run' => false, 'recipients' => PROMO_RECIPIENTS, 'dispatched' => $n, 'subject' => $subject];
}

/** Morning "pick today's deals" invite (link to the picker). */
function promo_send_picker_invite(): array
{
    $url = promo_picker_url();
    $subject = "Today's deals are in — pick 10 to post";
    $body = '<div style="font-family:Arial,sans-serif;color:#1a2233;max-width:520px">'
        . '<h2 style="color:#0E63D8;margin-bottom:6px">🛒 Today\'s deals are ready</h2>'
        . '<p>Open the picker, choose the 10 products you want to post today, and tap <strong>Generate pack</strong>. '
        . 'The cards publish automatically and the team gets the gallery link.</p>'
        . '<p style="font-size:1.05em">👉 <a href="' . e($url) . '">' . e($url) . '</a></p>'
        . '<p style="color:#999;font-size:12px">Automated 07:00 notice from shop.pconestop.co.za</p></div>';
    return promo_send($subject, $body, 'Pick today\'s 10 deals: ' . $url);
}

/** Pack-ready notice (link to the gallery) after a pack is published. */
function promo_send_pack_ready(int $count): array
{
    $url = PROMO_PUBLIC_BASE . '/promo-82f02098/';
    $subject = "PCOS promo cards ready — {$count} to post";
    $body = '<div style="font-family:Arial,sans-serif;color:#1a2233;max-width:520px">'
        . '<h2 style="color:#12A15E;margin-bottom:6px">🔥 New promo pack is live</h2>'
        . '<p><strong>' . (int)$count . '</strong> cards are ready to post to WhatsApp status.</p>'
        . '<p style="font-size:1.05em">👉 <a href="' . e($url) . '">' . e($url) . '</a></p>'
        . '<p style="color:#555">Open on your phone, tap <strong>Share</strong> on each card. Posted cards dim automatically.</p>'
        . '<p style="color:#999;font-size:12px">Automated notice from shop.pconestop.co.za</p></div>';
    return promo_send($subject, $body, "{$count} promo cards ready: {$url}");
}

