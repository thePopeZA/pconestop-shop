<?php
require_once __DIR__ . '/config/config.php';
require_once BASE_PATH . '/includes/shop.php';

$catSlug = trim((string)($_GET['cat'] ?? ''));
$sort    = (string)($_GET['sort'] ?? 'relevance');
$page    = max(1, (int)($_GET['page'] ?? 1));
$inStock = !empty($_GET['in_stock']);

$category = $catSlug !== '' ? category_by_slug($catSlug) : null;
$pageTitle = $category ? $category['name'] : 'All Products';
$activeCat = $category ? $category['slug'] : 'all';

$result = query_products([
    'category_id'   => $category['id'] ?? null,
    'sort'          => $sort,
    'page'          => $page,
    'per_page'      => 24,
    'in_stock_only' => $inStock,
]);

// Build a query base for pagination that preserves filters.
$qs = [];
if ($catSlug !== '') $qs['cat'] = $catSlug;
if ($sort !== 'relevance') $qs['sort'] = $sort;
if ($inStock) $qs['in_stock'] = 1;
$baseQuery = url('shop.php') . ($qs ? '?' . http_build_query($qs) : '');

include BASE_PATH . '/includes/header.php';
?>
<div class="breadcrumb">
    <a href="<?= e(url('/')) ?>">Home</a> ›
    <a href="<?= e(url('shop.php')) ?>">Shop</a>
    <?php if ($category): ?> › <?= e($category['name']) ?><?php endif; ?>
</div>

<div class="shop-layout">
    <aside class="sidebar">
        <h3>Categories</h3>
        <ul class="cat-list">
            <li><a href="<?= e(url('shop.php')) ?>" class="<?= !$category ? 'active' : '' ?>">All Products</a></li>
            <?php foreach (sidebar_categories() as $c): ?>
                <li>
                    <a href="<?= e(category_url($c)) ?>" class="<?= ($category && $category['id'] == $c['id']) ? 'active' : '' ?>">
                        <span><?= e($c['name']) ?></span><span class="count"><?= (int)$c['product_count'] ?></span>
                    </a>
                    <?php
                    // Show children if we're within this top-level branch
                    if ($category && (int)$c['id'] === (int)($category['parent_id'] ?? $category['id'])) {
                        $kids = child_categories((int)$c['id']);
                        if ($kids) {
                            echo '<ul class="cat-list sub">';
                            foreach ($kids as $k) {
                                $act = ($category['id'] == $k['id']) ? 'active' : '';
                                echo '<li><a class="' . $act . '" href="' . e(category_url($k)) . '"><span>' . e($k['name']) . '</span><span class="count">' . (int)$k['product_count'] . '</span></a></li>';
                            }
                            echo '</ul>';
                        }
                    }
                    ?>
                </li>
            <?php endforeach; ?>
        </ul>
    </aside>

    <div>
        <div class="toolbar">
            <h1><?= e($pageTitle) ?> <span class="muted" style="font-size:1rem;font-weight:400">(<?= number_format($result['total']) ?>)</span></h1>
            <form method="get" style="display:flex;gap:12px;align-items:center">
                <?php if ($catSlug !== ''): ?><input type="hidden" name="cat" value="<?= e($catSlug) ?>"><?php endif; ?>
                <label style="display:flex;gap:6px;align-items:center;font-size:.85rem;color:var(--ink-soft)">
                    <input type="checkbox" name="in_stock" value="1" <?= $inStock ? 'checked' : '' ?> onchange="this.form.submit()"> In stock only
                </label>
                <select id="sort-select" name="sort" class="field" style="padding:.5em .7em">
                    <option value="relevance" <?= $sort==='relevance'?'selected':'' ?>>Sort: Featured</option>
                    <option value="price_asc" <?= $sort==='price_asc'?'selected':'' ?>>Price: Low to High</option>
                    <option value="price_desc" <?= $sort==='price_desc'?'selected':'' ?>>Price: High to Low</option>
                    <option value="name" <?= $sort==='name'?'selected':'' ?>>Name: A–Z</option>
                    <option value="newest" <?= $sort==='newest'?'selected':'' ?>>Newest</option>
                </select>
            </form>
        </div>

        <?php if (!$result['items']): ?>
            <div class="empty"><h3>No products found</h3><p class="muted">Try a different category or clear your filters.</p></div>
        <?php else: ?>
            <div class="product-grid">
                <?php foreach ($result['items'] as $p) echo render_card($p); ?>
            </div>
            <?= paginate_links($result['page'], $result['pages'], $baseQuery) ?>
        <?php endif; ?>
    </div>
</div>
<?php include BASE_PATH . '/includes/footer.php'; ?>
