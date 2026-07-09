<?php
require_once __DIR__ . '/config/config.php';
require_once BASE_PATH . '/includes/shop.php';
require_once BASE_PATH . '/includes/orders.php';

$number = trim((string)($_GET['order'] ?? ''));
$failed = !empty($_GET['failed']);
$order = $number !== '' ? order_by_number($number) : null;

if ($order && $order['payment_status'] === 'pending') {
    mark_order_failed((int)$order['id'], 'cancelled');
}

$pageTitle = $failed ? 'Payment failed' : 'Payment cancelled';
include BASE_PATH . '/includes/header.php';
?>
<div style="max-width:560px;margin:0 auto">
    <div class="panel" style="text-align:center">
        <div style="font-size:3rem"><?= $failed ? '⚠️' : '🛒' ?></div>
        <h1><?= $failed ? 'Payment didn\'t go through' : 'Payment cancelled' ?></h1>
        <p class="muted">
            <?php if ($order): ?>Your order <strong><?= e($order['order_number']) ?></strong> was not completed.<?php endif; ?>
            Your cart is still saved — you can try again whenever you're ready.
        </p>
        <div style="margin-top:16px;display:flex;gap:10px;justify-content:center;flex-wrap:wrap">
            <a class="btn btn-accent" href="<?= e(url('checkout.php')) ?>">Try again</a>
            <a class="btn btn-ghost" href="<?= e(url('cart.php')) ?>">View cart</a>
        </div>
    </div>
</div>
<?php include BASE_PATH . '/includes/footer.php'; ?>
