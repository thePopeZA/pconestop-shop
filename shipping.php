<?php
require_once __DIR__ . '/config/config.php';
$pageTitle = 'Shipping & Delivery';
include BASE_PATH . '/includes/header.php';
?>
<div style="max-width:720px;margin:0 auto">
    <h1>Shipping &amp; Delivery</h1>
    <div class="panel">
        <h2>Delivery fees</h2>
        <p>We deliver nationwide by courier at a flat fee of <strong><?= money(SHIPPING_FEE_INCL) ?></strong> per order (incl. VAT) — passed on at cost. <strong>Larger orders qualify for free delivery</strong>; your cart will show it automatically before you pay.</p>
        <h2>Delivery time</h2>
        <p>Orders are dispatched once payment is confirmed. Delivery typically takes 2–5 business days nationwide, depending on your location and stock availability across our warehouses.</p>
        <h2>Stock &amp; availability</h2>
        <p>Live stock levels are shown on each product. Items marked "In stock" are available for immediate dispatch. Where an item shows an expected date, it is on backorder from our supplier.</p>
    </div>
    <a class="btn" href="<?= e(url('shop.php')) ?>">Continue shopping</a>
</div>
<?php include BASE_PATH . '/includes/footer.php'; ?>
