<?php
require_once __DIR__ . '/config/config.php';
require_once BASE_PATH . '/includes/shop.php';

$slug = trim((string)($_GET['slug'] ?? ''));
$id   = (int)($_GET['id'] ?? 0);
$product = $slug !== '' ? product_by_slug($slug) : ($id ? product_by_id($id) : null);

if (!$product || !$product['active']) {
    http_response_code(404);
    $pageTitle = 'Product not found';
    include BASE_PATH . '/includes/header.php';
    echo '<div class="empty"><h3>Product not found</h3><p class="muted">This product may no longer be available.</p><a class="btn" href="' . e(url('shop.php')) . '">Continue shopping</a></div>';
    include BASE_PATH . '/includes/footer.php';
    exit;
}

$pageTitle = $product['name'];
$metaDesc  = $product['short_desc'] ?: mb_substr(strip_tags($product['description'] ?? ''), 0, 155);
$gallery   = $product['image_gallery'] ? json_decode($product['image_gallery'], true) : [];
$mainImg   = product_image($product['image_url']);
$hasStock  = (int)$product['stock_qty'] > 0;
$save      = ($product['rrp'] && $product['rrp'] > $product['price']) ? ((float)$product['rrp'] - (float)$product['price']) : 0;
$related   = related_products($product, 5);

include BASE_PATH . '/includes/header.php';
?>
<div class="breadcrumb">
    <a href="<?= e(url('/')) ?>">Home</a> ›
    <a href="<?= e(url('shop.php')) ?>">Shop</a>
    <?php if ($product['category_path']): ?> › <span><?= e($product['category_path']) ?></span><?php endif; ?>
</div>

<div class="product-detail">
    <div class="gallery">
        <div class="main-img">
            <img src="<?= e($mainImg) ?>" alt="<?= e($product['name']) ?>"
                 onerror="this.onerror=null;this.src='<?= e(asset('img/placeholder.svg')) ?>'">
        </div>
        <?php if ($gallery): ?>
            <div class="thumbs">
                <img class="active" src="<?= e($mainImg) ?>" data-full="<?= e($mainImg) ?>" alt="">
                <?php foreach (array_slice($gallery, 0, 6) as $g): ?>
                    <img src="<?= e($g) ?>" data-full="<?= e($g) ?>" alt="" onerror="this.remove()">
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <div class="info">
        <?php if ($product['brand']): ?><div class="pd-brand"><?= e($product['brand']) ?></div><?php endif; ?>
        <h1 class="pd-title"><?= e($product['name']) ?></h1>
        <div class="pd-sku">SKU: <?= e($product['sku']) ?><?php if ($product['barcode']): ?> · EAN: <?= e($product['barcode']) ?><?php endif; ?></div>

        <div>
            <span class="pd-price"><?= money((float)$product['price']) ?></span>
            <?php if ($save > 0): ?>
                <span class="pd-rrp"><?= money((float)$product['rrp']) ?></span>
                <span class="pd-save">You save <?= money($save) ?></span>
            <?php endif; ?>
            <div class="pd-vat">Price incl. 15% VAT</div>
        </div>

        <div style="margin:16px 0">
            <span class="stock <?= e(stock_class($product['stock_status'])) ?>"><?= e(stock_label($product['stock_status'])) ?></span>
            <?php if ($product['warehouse']): ?>
                <span class="muted" style="font-size:.82rem;margin-left:8px">Available: <?= e($product['warehouse']) ?></span>
            <?php endif; ?>
            <?php if (!$hasStock && $product['supplier_eta']): ?>
                <div class="notice" style="margin-top:8px">Expected back in stock: <?= e($product['supplier_eta']) ?></div>
            <?php endif; ?>
        </div>

        <?php if ($hasStock): ?>
        <form method="post" action="<?= e(url('cart.php')) ?>" class="pd-buy">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="add">
            <input type="hidden" name="id" value="<?= (int)$product['id'] ?>">
            <input type="hidden" name="stay" value="1">
            <input type="hidden" name="return" value="<?= e($_SERVER['REQUEST_URI'] ?? '') ?>">
            <div class="qty">
                <button type="button" data-step="-1">−</button>
                <input type="number" name="qty" value="1" min="1" max="<?= (int)$product['stock_qty'] ?>">
                <button type="button" data-step="1">+</button>
            </div>
            <button class="btn btn-lg btn-accent" type="submit">Add to cart</button>
        </form>
        <?php else: ?>
            <div class="pd-buy"><button class="btn btn-lg disabled" disabled>Out of stock</button></div>
        <?php endif; ?>

        <?php if ($product['short_desc']): ?>
            <p style="color:var(--ink-soft)"><?= e($product['short_desc']) ?></p>
        <?php endif; ?>

        <div class="pd-meta">
            <?php if ($product['brand']): ?><div><span class="lbl">Brand</span><span><?= e($product['brand']) ?></span></div><?php endif; ?>
            <?php if ($product['category_path']): ?><div><span class="lbl">Category</span><span><?= e($product['category_path']) ?></span></div><?php endif; ?>
            <?php if ($product['weight_kg'] > 0): ?><div><span class="lbl">Weight</span><span><?= e(rtrim(rtrim(number_format((float)$product['weight_kg'],3),'0'),'.')) ?> kg</span></div><?php endif; ?>
            <div><span class="lbl">Delivery</span><span>Nationwide · Free over <?= money(SHIPPING_FREE_OVER) ?></span></div>
        </div>
    </div>
</div>

<?php if (!empty($product['description'])): ?>
<div class="pd-desc">
    <h2>Product details</h2>
    <?= clean_html($product['description']) ?>
</div>
<?php endif; ?>

<?php if ($related): ?>
<div class="section-head"><h2>Related products</h2></div>
<div class="product-grid">
    <?php foreach ($related as $p) echo render_card($p); ?>
</div>
<?php endif; ?>

<?php include BASE_PATH . '/includes/footer.php'; ?>
