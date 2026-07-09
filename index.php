<?php
require_once __DIR__ . '/config/config.php';
require_once BASE_PATH . '/includes/shop.php';

$pageTitle = APP_NAME;
$activeCat = '';
$newArrivals = homepage_products(10);
$deals = promo_products(5);
$cats = nav_categories(8);

include BASE_PATH . '/includes/header.php';
?>
<section class="hero">
    <h1>Everything for your PC build &amp; setup — in one place.</h1>
    <p>Thousands of genuine components, peripherals and tech accessories with live stock and fast nationwide delivery.</p>
    <a class="btn btn-accent btn-lg" href="<?= e(url('shop.php')) ?>">Shop all products →</a>
    <div class="trust-row">
        <span class="item">✓ Live stock levels</span>
        <span class="item">✓ Secure Yoco checkout</span>
        <span class="item">✓ Free delivery over <?= money(SHIPPING_FREE_OVER) ?></span>
    </div>
</section>

<!-- Category tiles -->
<div class="section-head"><h2>Shop by category</h2><a href="<?= e(url('shop.php')) ?>">View all</a></div>
<div class="product-grid" style="grid-template-columns:repeat(auto-fill,minmax(160px,1fr))">
    <?php foreach ($cats as $c): ?>
        <a class="card" href="<?= e(category_url($c)) ?>" style="text-decoration:none">
            <div class="body" style="text-align:center;padding:22px 14px">
                <div style="font-size:1.6rem;margin-bottom:6px">🖥️</div>
                <h3 class="title" style="min-height:auto;justify-content:center"><?= e($c['name']) ?></h3>
                <span class="muted" style="font-size:.8rem"><?= (int)$c['product_count'] ?> products</span>
            </div>
        </a>
    <?php endforeach; ?>
</div>

<?php if ($deals): ?>
<div class="section-head"><h2>🔥 Hot deals</h2></div>
<div class="product-grid">
    <?php foreach ($deals as $p) echo render_card($p); ?>
</div>
<?php endif; ?>

<div class="section-head"><h2>Just arrived</h2><a href="<?= e(url('shop.php?sort=newest')) ?>">More →</a></div>
<div class="product-grid">
    <?php foreach ($newArrivals as $p) echo render_card($p); ?>
</div>

<?php include BASE_PATH . '/includes/footer.php'; ?>
