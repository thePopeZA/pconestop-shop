<?php
/**
 * Session cart. Stored as $_SESSION['cart'] = [product_id => qty].
 * Prices/stock are always read fresh from the DB so feed updates apply.
 */

declare(strict_types=1);

function cart_add(int $productId, int $qty = 1): void
{
    $qty = max(1, $qty);
    $_SESSION['cart'][$productId] = ($_SESSION['cart'][$productId] ?? 0) + $qty;
}

function cart_set(int $productId, int $qty): void
{
    if ($qty <= 0) {
        cart_remove($productId);
        return;
    }
    $_SESSION['cart'][$productId] = $qty;
}

function cart_remove(int $productId): void
{
    unset($_SESSION['cart'][$productId]);
}

function cart_clear(): void
{
    $_SESSION['cart'] = [];
}

function cart_is_empty(): bool
{
    return empty($_SESSION['cart']);
}

/**
 * Resolve cart into line items with fresh product data.
 * Silently drops products that are gone/inactive; caps qty to available stock.
 * Returns ['items'=>[['product','qty','line_total','capped']], 'adjusted'=>bool]
 */
function cart_items(): array
{
    $cart = $_SESSION['cart'] ?? [];
    if (!$cart) {
        return ['items' => [], 'adjusted' => false];
    }
    $ids = array_map('intval', array_keys($cart));
    $in  = implode(',', $ids);
    $rows = db()->query("SELECT * FROM products WHERE id IN ($in) AND active = 1")->fetchAll();
    $byId = [];
    foreach ($rows as $r) {
        $byId[(int)$r['id']] = $r;
    }

    $items = [];
    $adjusted = false;
    foreach ($cart as $id => $qty) {
        $id = (int)$id;
        $qty = (int)$qty;
        if (!isset($byId[$id])) {
            // product no longer available
            unset($_SESSION['cart'][$id]);
            $adjusted = true;
            continue;
        }
        $p = $byId[$id];
        $capped = false;
        $stock = (int)$p['stock_qty'];
        if ($stock <= 0) {
            // out of stock — remove from cart
            unset($_SESSION['cart'][$id]);
            $adjusted = true;
            continue;
        }
        if ($qty > $stock) {
            $qty = $stock;
            $_SESSION['cart'][$id] = $qty;
            $capped = true;
            $adjusted = true;
        }
        $items[] = [
            'product'    => $p,
            'qty'        => $qty,
            'line_total' => round((float)$p['price'] * $qty, 2),
            'capped'     => $capped,
        ];
    }
    return ['items' => $items, 'adjusted' => $adjusted];
}

/** Totals from a resolved item list. */
function cart_totals(array $items): array
{
    $subtotal = 0.0;
    $costTotal = 0.0; // supplier (Syntech) cost ex VAT — decides free shipping
    foreach ($items as $it) {
        $subtotal  += $it['line_total'];
        $costTotal += (float)$it['product']['cost_price'] * $it['qty'];
    }
    $shipping = calc_shipping($costTotal);
    $total    = $subtotal + $shipping;
    // Prices are VAT-inclusive; VAT portion of the whole order:
    $vat = round($total - ($total / (1 + VAT_RATE)), 2);
    return [
        'subtotal'      => round($subtotal, 2),
        'shipping'      => round($shipping, 2),
        'free_shipping' => $subtotal > 0 && $shipping == 0.0,
        'total'         => round($total, 2),
        'vat'           => $vat,
        'count'         => array_sum(array_map(fn($i) => $i['qty'], $items)),
    ];
}
