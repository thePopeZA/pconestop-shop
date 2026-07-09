<?php
require_once __DIR__ . '/_bootstrap.php';
require_admin();

// Toggle actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && csrf_check()) {
    $id = (int)($_POST['id'] ?? 0);
    $field = $_POST['toggle'] ?? '';
    if ($id && in_array($field, ['featured', 'active'], true)) {
        db()->prepare("UPDATE products SET `$field` = 1 - `$field` WHERE id = ?")->execute([$id]);
        flash('Product updated.', 'success');
    }
    redirect('admin/products.php' . (!empty($_POST['q']) ? '?q=' . urlencode($_POST['q']) : ''));
}

$q = trim((string)($_GET['q'] ?? ''));
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 30;
$offset = ($page - 1) * $perPage;

$where = '1=1';
$params = [];
if ($q !== '') {
    $where = '(name LIKE ? OR sku LIKE ? OR brand LIKE ?)';
    $like = "%$q%";
    $params = [$like, $like, $like];
}
$total = (int)(function () use ($where, $params) {
    $s = db()->prepare("SELECT COUNT(*) FROM products WHERE $where");
    $s->execute($params);
    return $s->fetchColumn();
})();
$pages = (int)ceil($total / $perPage);

$stmt = db()->prepare("SELECT * FROM products WHERE $where ORDER BY updated_at DESC LIMIT $perPage OFFSET $offset");
$stmt->execute($params);
$products = $stmt->fetchAll();

$adminTitle = 'Products';
$adminNav = 'products';
include __DIR__ . '/_header.php';
?>
<div class="toolbar">
    <form class="search-inline" method="get">
        <input name="q" value="<?= e($q) ?>" placeholder="Search name, SKU or brand…">
        <button class="btn btn-sm">Search</button>
        <?php if ($q): ?><a class="btn btn-sm btn-ghost" href="<?= e(admin_url('products.php')) ?>">Clear</a><?php endif; ?>
    </form>
    <span class="muted"><?= number_format($total) ?> products</span>
</div>

<table class="grid">
    <thead><tr><th></th><th>Product</th><th>Brand</th><th>Cost</th><th>Sell</th><th>Stock</th><th>Flags</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($products as $p): ?>
        <tr>
            <td><img class="thumb-sm" src="<?= e(product_image($p['image_url'])) ?>" onerror="this.src='<?= e(asset('img/placeholder.svg')) ?>'" alt=""></td>
            <td style="max-width:320px">
                <a href="<?= e(product_url($p)) ?>" target="_blank" style="font-weight:600;color:var(--ink)"><?= e($p['name']) ?></a>
                <div class="muted" style="font-size:.76rem">SKU: <?= e($p['sku']) ?></div>
            </td>
            <td><?= e($p['brand'] ?: '—') ?></td>
            <td><?= money((float)$p['cost_price']) ?></td>
            <td style="font-weight:700"><?= money((float)$p['price']) ?></td>
            <td>
                <span class="badge <?= (int)$p['stock_qty']>3?'b-green':((int)$p['stock_qty']>0?'b-amber':'b-red') ?>"><?= (int)$p['stock_qty'] ?></span>
                <?php if ($p['warehouse']): ?><div class="muted" style="font-size:.72rem"><?= e($p['warehouse']) ?></div><?php endif; ?>
            </td>
            <td>
                <?php if (!$p['active']): ?><span class="badge b-grey">hidden</span><?php endif; ?>
                <?php if ($p['featured']): ?><span class="badge b-blue">featured</span><?php endif; ?>
            </td>
            <td style="white-space:nowrap">
                <form method="post" style="display:inline">
                    <?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$p['id'] ?>"><input type="hidden" name="q" value="<?= e($q) ?>">
                    <button class="btn btn-sm btn-ghost" name="toggle" value="featured" title="Toggle featured">★</button>
                    <button class="btn btn-sm btn-ghost" name="toggle" value="active" title="Show/hide"><?= $p['active'] ? '🙈' : '👁' ?></button>
                </form>
            </td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>

<?php if ($pages > 1): ?>
<div class="pagination">
    <?php for ($i = max(1,$page-3); $i <= min($pages,$page+3); $i++): ?>
        <a class="<?= $i===$page?'active':'' ?>" href="<?= e(admin_url('products.php?'.http_build_query(array_filter(['q'=>$q,'page'=>$i])))) ?>"><?= $i ?></a>
    <?php endfor; ?>
</div>
<?php endif; ?>
<?php include __DIR__ . '/_footer.php'; ?>
