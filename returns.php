<?php
require_once __DIR__ . '/config/config.php';
$pageTitle = 'Returns & Warranty';
$metaDesc  = 'PC One Stop returns, refunds and warranty policy — your rights under the South African Consumer Protection Act.';
include BASE_PATH . '/includes/header.php';
?>
<div style="max-width:720px;margin:0 auto">
    <h1>Returns &amp; Warranty</h1>
    <div class="panel">
        <p>We want you to be happy with your purchase. If something isn't right, email <a href="mailto:orders@pconestop.co.za">orders@pconestop.co.za</a> with your order number and we'll help you sort it out.</p>

        <h2>Change-of-mind returns (7 days)</h2>
        <p>You may return an unwanted item within <strong>7 days</strong> of delivery for a refund or exchange, provided it is <strong>unused, complete and in its original, undamaged packaging</strong> with all accessories. Start the return by emailing us — please don't send anything back before we've confirmed the return.</p>
        <p>For change-of-mind returns, you arrange and pay the return delivery. Once we receive the item and check it's in resalable condition, we'll process your refund or exchange.</p>

        <h2>Faulty, damaged or incorrect items</h2>
        <p>If an item arrives faulty, damaged, or isn't what you ordered, tell us within <strong>7 days</strong> of delivery and <strong>we cover the return delivery cost</strong>. In line with the <strong>Consumer Protection Act</strong>, goods that fail, are defective or unsafe within <strong>6 months</strong> of delivery will be repaired, replaced or refunded — your choice — at no charge to you.</p>

        <h2>How refunds work</h2>
        <p>Approved refunds are made to your <strong>original payment method</strong>. Once we've received and inspected the returned item, refunds are processed within <strong>7–10 business days</strong>. Original delivery fees are refunded where the return is due to our error or a faulty item.</p>

        <h2>Warranty</h2>
        <p>All products carry the <strong>manufacturer's warranty</strong>. Warranty periods vary by product and, where the supplier provides them, are shown on the product page. To claim under warranty, email us with your order number and a description of the fault and we'll guide you through it — we handle the supplier side for you.</p>

        <h2>What can't be returned</h2>
        <p>For change-of-mind returns we can't accept items that have been used or installed, that are missing parts or packaging, or software and digital licences once they've been activated. This doesn't affect your rights for faulty goods under the Consumer Protection Act.</p>

        <p style="margin-top:22px;color:var(--ink-soft);font-size:.9rem">See also our <a href="<?= e(url('shipping.php')) ?>">Shipping &amp; Delivery</a> policy, or <a href="https://pconestop.co.za/contact-us/">contact us</a>.</p>
    </div>
    <a class="btn" href="<?= e(url('shop.php')) ?>">Continue shopping</a>
</div>
<?php include BASE_PATH . '/includes/footer.php'; ?>
