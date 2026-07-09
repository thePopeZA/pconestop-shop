<?php
require_once __DIR__ . '/config/config.php';
require_once BASE_PATH . '/includes/shop.php';

$q    = trim((string)($_GET['q'] ?? ''));
$sort = (string)($_GET['sort'] ?? 'relevance');
$page = max(1, (int)($_GET['page'] ?? 1));

$pageTitle = $q !== '' ? "Search: $q" : 'Search';

$result = ['items' => [], 'total' => 0, 'pages' => 0, 'page' => 1];
if ($q !== '') {
    $result = query_products(['search' => $q, 'sort' => $sort, 'page' => $page, 'per_page' => 24]);
}
$baseQuery = url('search.php') . '?' . http_build_query(['q' => $q] + ($sort !== 'relevance' ? ['sort' => $sort] : []));

include BASE_PATH . '/includes/header.php';
?>
<div class="breadcrumb"><a href="<?= e(url('/')) ?>">Home</a> › Search</div>

<div class="toolbar">
    <h1>
        <?php if ($q !== ''): ?>
            Results for “<?= e($q) ?>” <span class="muted" style="font-size:1rem;font-weight:400">(<?= number_format($result['total']) ?>)</span>
        <?php else: ?>Search products<?php endif; ?>
    </h1>
    <?php if ($q !== '' && $result['total'] > 0): ?>
    <form method="get" style="display:flex;gap:12px;align-items:center">
        <input type="hidden" name="q" value="<?= e($q) ?>">
        <select id="sort-select" name="sort" class="field" style="padding:.5em .7em">
            <option value="relevance" <?= $sort==='relevance'?'selected':'' ?>>Best match</option>
            <option value="price_asc" <?= $sort==='price_asc'?'selected':'' ?>>Price: Low to High</option>
            <option value="price_desc" <?= $sort==='price_desc'?'selected':'' ?>>Price: High to Low</option>
            <option value="name" <?= $sort==='name'?'selected':'' ?>>Name: A–Z</option>
        </select>
    </form>
    <?php endif; ?>
</div>

<?php if ($q === ''): ?>
    <div class="empty"><h3>What are you looking for?</h3><p class="muted">Search by product name, brand or SKU.</p></div>
<?php elseif (!$result['items']): ?>
    <div class="empty">
        <h3>No results for “<?= e($q) ?>”</h3>
        <p class="muted">Check your spelling or try a more general term.</p>
        <a class="btn" href="<?= e(url('shop.php')) ?>">Browse all products</a>
    </div>
<?php else: ?>
    <div class="product-grid">
        <?php foreach ($result['items'] as $p) echo render_card($p); ?>
    </div>
    <?= paginate_links($result['page'], $result['pages'], $baseQuery) ?>
<?php endif; ?>

<?php include BASE_PATH . '/includes/footer.php'; ?>
