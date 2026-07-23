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

/** Shape one product row into the public promo item structure. */
function promo_item(array $p, bool $isDeal): array
{
    $now  = (float)$p['price'];
    $was  = $isDeal && $p['rrp'] !== null ? (float)$p['rrp'] : null;
    $save = $was !== null ? round($was - $now, 2) : 0.0;
    $pct  = ($was !== null && $was > 0) ? (int)round(($was - $now) / $was * 100) : 0;

    return [
        'slug'        => (string)$p['slug'],
        'name'        => (string)$p['name'],
        'brand'       => (string)($p['brand'] ?? ''),
        'price_now'   => $now,
        'price_was'   => $was,
        'save_amount' => $save,
        'save_pct'    => $pct,
        'stock'       => ($p['stock_status'] === 'low_stock') ? 'Low stock' : 'In stock',
        'image'       => (string)($p['image_url'] ?? ''),
        'url'         => product_url($p),
        'type'        => $isDeal ? 'deal' : 'arrival',
    ];
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
    $stmt = db()->prepare(
        "SELECT * FROM products
         WHERE active = 1 AND stock_qty > 0
           AND rrp IS NOT NULL AND rrp > price
           AND price >= ?
           AND image_url IS NOT NULL AND image_url <> ''
         ORDER BY (rrp - price) DESC, (rrp - price) / rrp DESC, name ASC"
    );
    $stmt->execute([PROMO_MIN_PRICE]);
    $dealRows = $stmt->fetchAll();

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
 * The ordered card list a promo run uses: all deals (by % desc) topped up
 * with arrivals to exactly $count cards.
 */
function promo_card_list(array $feed, int $count = 10): array
{
    $cards = $feed['deals'];
    foreach ($feed['arrivals'] as $a) {
        if (count($cards) >= $count) {
            break;
        }
        $cards[] = $a;
    }
    return array_slice($cards, 0, $count);
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
