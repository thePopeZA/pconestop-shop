<?php
require_once __DIR__ . '/config/config.php';
require_once BASE_PATH . '/includes/shop.php';
require_once BASE_PATH . '/includes/orders.php';

$order = null;
$notFound = false;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $number = trim((string)($_POST['order_number'] ?? ''));
    $email  = trim((string)($_POST['email'] ?? ''));
    $o = $number !== '' ? order_by_number($number) : null;
    if ($o && strcasecmp($o['email'], $email) === 0) {
        $order = $o;
    } else {
        $notFound = true;
    }
}

$pageTitle = 'Track your order';
include BASE_PATH . '/includes/header.php';
?>
<div style="max-width:560px;margin:0 auto">
    <h1>Track your order</h1>
    <div class="panel">
        <form method="post">
            <div class="field"><label>Order number</label><input name="order_number" value="<?= e($_POST['order_number'] ?? '') ?>" placeholder="PCOS-XXXXXXXX-XXXXXX" required></div>
            <div class="field"><label>Email used at checkout</label><input type="email" name="email" value="<?= e($_POST['email'] ?? '') ?>" required></div>
            <button class="btn btn-lg" type="submit">Track order</button>
        </form>
    </div>
    <?php if ($notFound): ?>
        <div class="flash flash-error">We couldn't find an order matching those details.</div>
    <?php endif; ?>
    <?php if ($order):
        $labels = ['new'=>'Order received','processing'=>'Being prepared','shipped'=>'Shipped','completed'=>'Completed','cancelled'=>'Cancelled'];
        $payLabels = ['pending'=>'Awaiting payment','paid'=>'Paid','failed'=>'Payment failed','cancelled'=>'Cancelled']; ?>
        <div class="panel">
            <h2><?= e($order['order_number']) ?></h2>
            <p>Placed <?= e(date('d M Y', strtotime($order['created_at']))) ?></p>
            <p><span class="stock <?= $order['payment_status']==='paid'?'stock-in':'stock-low' ?>"><?= e($payLabels[$order['payment_status']] ?? $order['payment_status']) ?></span></p>
            <p><strong>Status:</strong> <?= e($labels[$order['status']] ?? $order['status']) ?></p>
            <p><strong>Total:</strong> <?= money((float)$order['total']) ?></p>
        </div>
    <?php endif; ?>
</div>
<?php include BASE_PATH . '/includes/footer.php'; ?>
