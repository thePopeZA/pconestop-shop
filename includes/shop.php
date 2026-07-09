<?php
/**
 * Storefront data-access helpers.
 */

declare(strict_types=1);

/** Top-level categories that have products, for the nav. */
function nav_categories(int $limit = 10): array
{
    static $cache = null;
    if ($cache === null) {
        $stmt = db()->query(
            'SELECT id, name, slug, product_count FROM categories
             WHERE parent_id IS NULL AND product_count > 0
             ORDER BY product_count DESC, name ASC'
        );
        $cache = $stmt->fetchAll();
    }
    return array_slice($cache, 0, $limit);
}

/** Full category record by slug. */
function category_by_slug(string $slug): ?array
{
    $stmt = db()->prepare('SELECT * FROM categories WHERE slug = ? LIMIT 1');
    $stmt->execute([$slug]);
    $row = $stmt->fetch();
    return $row ?: null;
}

/** All descendant category ids (inclusive) for a given category. */
function descendant_category_ids(int $rootId): array
{
    static $childrenMap = null;
    if ($childrenMap === null) {
        $childrenMap = [];
        foreach (db()->query('SELECT id, parent_id FROM categories') as $r) {
            $pid = $r['parent_id'] !== null ? (int)$r['parent_id'] : 0;
            $childrenMap[$pid][] = (int)$r['id'];
        }
    }
    $ids = [$rootId];
    $stack = [$rootId];
    while ($stack) {
        $cur = array_pop($stack);
        foreach ($childrenMap[$cur] ?? [] as $child) {
            $ids[] = $child;
            $stack[] = $child;
        }
    }
    return array_unique($ids);
}

/** Child categories of a category (for sidebar). */
function child_categories(int $parentId): array
{
    $stmt = db()->prepare(
        'SELECT id, name, slug, product_count FROM categories
         WHERE parent_id = ? AND product_count > 0 ORDER BY name'
    );
    $stmt->execute([$parentId]);
    return $stmt->fetchAll();
}

/** Sidebar top-level list. */
function sidebar_categories(): array
{
    return db()->query(
        'SELECT id, name, slug, product_count FROM categories
         WHERE parent_id IS NULL AND product_count > 0
         ORDER BY name ASC'
    )->fetchAll();
}

/**
 * Query products with filters + pagination.
 * $opts: category_id, search, brand, in_stock_only, sort, page, per_page, featured
 * Returns ['items'=>[], 'total'=>int, 'pages'=>int, 'page'=>int]
 */
function query_products(array $opts = []): array
{
    $where = ['p.active = 1'];
    $params = [];

    if (!empty($opts['category_id'])) {
        $ids = descendant_category_ids((int)$opts['category_id']);
        $in = implode(',', array_map('intval', $ids));
        $where[] = "p.category_id IN ($in)";
    }
    if (!empty($opts['brand'])) {
        $where[] = 'p.brand = ?';
        $params[] = $opts['brand'];
    }
    if (!empty($opts['featured'])) {
        $where[] = 'p.featured = 1';
    }
    if (!empty($opts['in_stock_only'])) {
        $where[] = 'p.stock_qty > 0';
    }
    $search = trim((string)($opts['search'] ?? ''));
    if ($search !== '') {
        // Use LIKE for reliability across short terms; fall back-friendly.
        $where[] = '(p.name LIKE ? OR p.brand LIKE ? OR p.sku LIKE ?)';
        $like = '%' . $search . '%';
        $params[] = $like; $params[] = $like; $params[] = $like;
    }

    $whereSql = implode(' AND ', $where);

    // Sorting
    $sort = $opts['sort'] ?? 'relevance';
    $orderSql = match ($sort) {
        'price_asc'  => 'p.price ASC',
        'price_desc' => 'p.price DESC',
        'name'       => 'p.name ASC',
        'newest'     => 'p.created_at DESC',
        default      => 'p.stock_qty > 0 DESC, p.name ASC',
    };

    $perPage = max(1, (int)($opts['per_page'] ?? 24));
    $page    = max(1, (int)($opts['page'] ?? 1));
    $offset  = ($page - 1) * $perPage;

    // Count
    $countStmt = db()->prepare("SELECT COUNT(*) FROM products p WHERE $whereSql");
    $countStmt->execute($params);
    $total = (int)$countStmt->fetchColumn();
    $pages = (int)ceil($total / $perPage);

    // Fetch
    $sql = "SELECT p.* FROM products p WHERE $whereSql ORDER BY $orderSql LIMIT $perPage OFFSET $offset";
    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    $items = $stmt->fetchAll();

    return ['items' => $items, 'total' => $total, 'pages' => $pages, 'page' => $page];
}

/** Single product by slug (falls back to id). */
function product_by_slug(string $slug): ?array
{
    $stmt = db()->prepare('SELECT * FROM products WHERE slug = ? AND active = 1 LIMIT 1');
    $stmt->execute([$slug]);
    $row = $stmt->fetch();
    return $row ?: null;
}

function product_by_id(int $id): ?array
{
    $stmt = db()->prepare('SELECT * FROM products WHERE id = ? LIMIT 1');
    $stmt->execute([$id]);
    $row = $stmt->fetch();
    return $row ?: null;
}

/** Related products in the same category. */
function related_products(array $product, int $limit = 5): array
{
    if (empty($product['category_id'])) {
        return [];
    }
    $stmt = db()->prepare(
        'SELECT * FROM products WHERE active = 1 AND category_id = ? AND id <> ?
         ORDER BY stock_qty > 0 DESC, RAND() LIMIT ?'
    );
    $stmt->bindValue(1, (int)$product['category_id'], PDO::PARAM_INT);
    $stmt->bindValue(2, (int)$product['id'], PDO::PARAM_INT);
    $stmt->bindValue(3, $limit, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll();
}

/** Featured / newest products for the homepage. */
function homepage_products(int $limit = 10): array
{
    $stmt = db()->prepare(
        'SELECT * FROM products WHERE active = 1 AND stock_qty > 0 AND image_url IS NOT NULL
         ORDER BY created_at DESC, id DESC LIMIT ?'
    );
    $stmt->bindValue(1, $limit, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll();
}

/** On-promotion products (our price below RRP). */
function promo_products(int $limit = 10): array
{
    $stmt = db()->prepare(
        'SELECT * FROM products WHERE active = 1 AND stock_qty > 0
         AND rrp IS NOT NULL AND rrp > price
         ORDER BY (rrp - price) DESC LIMIT ?'
    );
    $stmt->bindValue(1, $limit, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll();
}

/** Build product page URL. */
function product_url(array $p): string
{
    return url('product.php?slug=' . urlencode($p['slug']));
}

function category_url(array $c): string
{
    return url('shop.php?cat=' . urlencode($c['slug']));
}

/** Render a product card (used across pages). */
function render_card(array $p): string
{
    $img = product_image($p['image_url']);
    $hasStock = (int)$p['stock_qty'] > 0;
    $save = ($p['rrp'] && $p['rrp'] > $p['price']) ? ((float)$p['rrp'] - (float)$p['price']) : 0;
    ob_start(); ?>
    <div class="card">
        <?php if ($save > 0): ?><span class="badge-promo">SAVE <?= money($save) ?></span><?php endif; ?>
        <a class="thumb" href="<?= e(product_url($p)) ?>">
            <img src="<?= e($img) ?>" alt="<?= e($p['name']) ?>" loading="lazy"
                 onerror="this.onerror=null;this.src='<?= e(asset('img/placeholder.svg')) ?>'">
        </a>
        <div class="body">
            <?php if ($p['brand']): ?><div class="brand"><?= e($p['brand']) ?></div><?php endif; ?>
            <h3 class="title"><a href="<?= e(product_url($p)) ?>"><?= e($p['name']) ?></a></h3>
            <div class="price-row">
                <span class="price"><?= money((float)$p['price']) ?></span>
                <?php if ($save > 0): ?><span class="rrp"><?= money((float)$p['rrp']) ?></span><?php endif; ?>
            </div>
            <div style="margin-top:8px">
                <span class="stock <?= e(stock_class($p['stock_status'])) ?>"><?= e(stock_label($p['stock_status'])) ?></span>
            </div>
            <div class="card-foot">
                <?php if ($hasStock): ?>
                    <form method="post" action="<?= e(url('cart.php')) ?>" style="flex:1">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="add">
                        <input type="hidden" name="id" value="<?= (int)$p['id'] ?>">
                        <button class="btn btn-sm btn-block" type="submit">Add to cart</button>
                    </form>
                <?php else: ?>
                    <a class="btn btn-sm btn-ghost btn-block" href="<?= e(product_url($p)) ?>">View</a>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <?php
    return (string)ob_get_clean();
}

/** Sanitize supplier HTML for display (strip scripts/styles/iframes). */
function clean_html(string $html): string
{
    // remove dangerous blocks
    $html = preg_replace('#<(script|style|iframe|object|embed)[^>]*>.*?</\1>#is', '', $html) ?? $html;
    $html = preg_replace('#\son\w+\s*=\s*("[^"]*"|\'[^\']*\'|[^\s>]+)#i', '', $html) ?? $html;
    $html = preg_replace('#(href|src)\s*=\s*("|\')?\s*javascript:[^"\'>]*("|\')?#i', '', $html) ?? $html;
    return $html;
}
