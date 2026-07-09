<?php
require_once __DIR__ . '/config/config.php';
$pageTitle = 'Returns & Warranty';
include BASE_PATH . '/includes/header.php';
?>
<div style="max-width:720px;margin:0 auto">
    <h1>Returns &amp; Warranty</h1>
    <div class="panel">
        <h2>Returns</h2>
        <p>If something isn't right, contact us at <a href="mailto:shop@pconestop.co.za">shop@pconestop.co.za</a> within 7 days of delivery. Products must be unused and in their original packaging.</p>
        <h2>Warranty</h2>
        <p>All products carry the manufacturer's warranty. Warranty periods vary by product and are listed on the product page where provided by the supplier.</p>
        <h2>Faulty items</h2>
        <p>Dead-on-arrival or faulty items will be replaced or refunded in line with the Consumer Protection Act. Please reach out and we'll sort it out quickly.</p>
    </div>
    <a class="btn" href="<?= e(url('shop.php')) ?>">Continue shopping</a>
</div>
<?php include BASE_PATH . '/includes/footer.php'; ?>
