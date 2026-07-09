<?php
/**
 * Core helper functions.
 */

declare(strict_types=1);

/* ---------- Output / escaping ---------- */
function e(?string $s): string
{
    return htmlspecialchars((string)$s, ENT_QUOTES | ENT_HTML5, 'UTF-8');
}

function money(float $amount): string
{
    return 'R' . number_format($amount, 2, '.', ' ');
}

function url(string $path = ''): string
{
    return APP_URL . '/' . ltrim($path, '/');
}

function asset(string $path): string
{
    return url('assets/' . ltrim($path, '/'));
}

function redirect(string $path): never
{
    $to = preg_match('#^https?://#', $path) ? $path : url($path);
    header('Location: ' . $to);
    exit;
}

/* ---------- Slugs ---------- */
function slugify(string $text): string
{
    $text = strtolower(trim($text));
    $text = preg_replace('/[^a-z0-9]+/', '-', $text);
    $text = trim((string)$text, '-');
    return $text === '' ? 'item' : $text;
}

/* ---------- Pricing ---------- */
function calc_sell_price(float $cost): float
{
    return round($cost * MARKUP_MULTIPLIER * VAT_MULTIPLIER, 2);
}

function calc_shipping(float $subtotal): float
{
    if ($subtotal <= 0) {
        return 0.0;
    }
    return $subtotal >= SHIPPING_FREE_OVER ? 0.0 : SHIPPING_FLAT;
}

/* ---------- Settings ---------- */
function setting(string $key, $default = null)
{
    static $cache = null;
    if ($cache === null) {
        $cache = [];
        try {
            foreach (db()->query('SELECT skey, svalue FROM settings') as $row) {
                $cache[$row['skey']] = $row['svalue'];
            }
        } catch (Throwable $e) {
            // table may not exist yet
        }
    }
    return $cache[$key] ?? $default;
}

/* ---------- CSRF ---------- */
function csrf_token(): string
{
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="csrf" value="' . e(csrf_token()) . '">';
}

function csrf_check(): bool
{
    return isset($_POST['csrf'], $_SESSION['csrf'])
        && hash_equals($_SESSION['csrf'], (string)$_POST['csrf']);
}

/* ---------- Flash messages ---------- */
function flash(string $msg, string $type = 'info'): void
{
    $_SESSION['flash'][] = ['msg' => $msg, 'type' => $type];
}

function get_flashes(): array
{
    $f = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    return $f;
}

/* ---------- Stock helpers ---------- */
function stock_label(string $status): string
{
    return match ($status) {
        'in_stock'     => 'In stock',
        'low_stock'    => 'Low stock',
        'backorder'    => 'Backorder',
        default        => 'Out of stock',
    };
}

function stock_class(string $status): string
{
    return match ($status) {
        'in_stock'  => 'stock-in',
        'low_stock' => 'stock-low',
        'backorder' => 'stock-back',
        default     => 'stock-out',
    };
}

/* ---------- Pagination helper ---------- */
function paginate_links(int $page, int $totalPages, string $baseQuery): string
{
    if ($totalPages <= 1) {
        return '';
    }
    $out = '<nav class="pagination">';
    $sep = str_contains($baseQuery, '?') ? '&' : '?';
    if ($page > 1) {
        $out .= '<a href="' . e($baseQuery . $sep . 'page=' . ($page - 1)) . '">&laquo; Prev</a>';
    }
    $start = max(1, $page - 2);
    $end   = min($totalPages, $page + 2);
    if ($start > 1) {
        $out .= '<a href="' . e($baseQuery . $sep . 'page=1') . '">1</a>';
        if ($start > 2) $out .= '<span class="dots">…</span>';
    }
    for ($i = $start; $i <= $end; $i++) {
        $cls = $i === $page ? ' class="active"' : '';
        $out .= '<a' . $cls . ' href="' . e($baseQuery . $sep . 'page=' . $i) . '">' . $i . '</a>';
    }
    if ($end < $totalPages) {
        if ($end < $totalPages - 1) $out .= '<span class="dots">…</span>';
        $out .= '<a href="' . e($baseQuery . $sep . 'page=' . $totalPages) . '">' . $totalPages . '</a>';
    }
    if ($page < $totalPages) {
        $out .= '<a href="' . e($baseQuery . $sep . 'page=' . ($page + 1)) . '">Next &raquo;</a>';
    }
    $out .= '</nav>';
    return $out;
}

/* ---------- Product image fallback ---------- */
function product_image(?string $imageUrl): string
{
    if ($imageUrl && filter_var($imageUrl, FILTER_VALIDATE_URL)) {
        return $imageUrl;
    }
    return asset('img/placeholder.svg');
}

/* ---------- Cart count for header ---------- */
function cart_count(): int
{
    // Cart is stored as [product_id => qty].
    return array_sum(array_map('intval', $_SESSION['cart'] ?? []));
}
