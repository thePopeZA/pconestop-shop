<?php
require_once __DIR__ . '/config/config.php';
$pageTitle = 'Shipping & Delivery';
$metaDesc  = 'How PC One Stop ships across South Africa: courier fees, delivery times and free delivery on larger orders.';
include BASE_PATH . '/includes/header.php';
?>
<div style="max-width:720px;margin:0 auto">
    <h1>Shipping &amp; Delivery</h1>
    <div class="panel">
        <h2>Where we deliver</h2>
        <p>We deliver <strong>anywhere in South Africa</strong> by courier. We do not currently ship outside South Africa.</p>

        <h2>Delivery fees</h2>
        <p>Nationwide courier delivery is a flat <strong><?= money(SHIPPING_FEE_INCL) ?></strong> per order (incl. VAT), passed on at cost. <strong>Larger orders qualify for free delivery</strong> — if yours does, your cart will show it automatically before you pay.</p>

        <h2>Handling &amp; delivery time</h2>
        <p>Orders are processed and dispatched within <strong>1–2 business days</strong> of payment being confirmed. Once collected by the courier, delivery typically takes a further <strong>2–5 business days</strong>, depending on your location. Outlying and remote areas may take a little longer.</p>
        <p>Business days are Monday to Friday, excluding public holidays. Orders placed over a weekend or public holiday are processed the next business day.</p>

        <h2>Tracking your order</h2>
        <p>You'll receive an email confirmation when your order is placed and again when it's dispatched. You can check progress any time on our <a href="<?= e(url('track.php')) ?>">Track Order</a> page.</p>

        <h2>Stock &amp; availability</h2>
        <p>Live stock levels are shown on every product. Items marked "In stock" are available for immediate dispatch. Where an item shows an expected date, it is on backorder from our supplier and will ship as soon as it arrives — we'll keep you posted.</p>

        <p style="margin-top:22px;color:var(--ink-soft);font-size:.9rem">Questions about a delivery? Email us at <a href="mailto:orders@pconestop.co.za">orders@pconestop.co.za</a>. See also our <a href="<?= e(url('returns.php')) ?>">Returns &amp; Warranty</a> policy.</p>
    </div>
    <a class="btn" href="<?= e(url('shop.php')) ?>">Continue shopping</a>
</div>
<?php include BASE_PATH . '/includes/footer.php'; ?>
