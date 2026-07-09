<?php
require_once __DIR__ . '/config/config.php';
require_once BASE_PATH . '/includes/shop.php';
require_once BASE_PATH . '/includes/cart_lib.php';
require_once BASE_PATH . '/includes/orders.php';
require_once BASE_PATH . '/includes/Yoco.php';

$number = trim((string)($_GET['order'] ?? ''));
$order = $number !== '' ? order_by_number($number) : null;

if (!$order) {
    http_response_code(404);
    $pageTitle = 'Order not found';
    include BASE_PATH . '/includes/header.php';
    echo '<div class="empty"><h3>Order not found</h3><a class="btn" href="' . e(url('/')) . '">Home</a></div>';
    include BASE_PATH . '/includes/footer.php';
    exit;
}

// Confirm payment server-side (webhook is authoritative, but confirm here too
// so the customer sees an accurate status immediately).
if ($order['payment_status'] === 'pending' && $order['yoco_checkout_id']) {
    try {
        $yoco = new Yoco();
        $co = $yoco->getCheckout($order['yoco_checkout_id']);
        $status = strtolower((string)($co['status'] ?? ''));
        $paymentId = $co['paymentId'] ?? ($co['payment']['id'] ?? null);
        if (in_array($status, ['completed', 'succeeded', 'successful'], true)) {
            mark_order_paid((int)$order['id'], $paymentId);
            $order = order_by_number($number);
        }
    } catch (Throwable $e) {
        error_log('Success verify error: ' . $e->getMessage());
    }
}

// Clear the cart on a successful landing.
if ($order['payment_status'] === 'paid') {
    cart_clear();
}

$items = order_items_for((int)$order['id']);
$paid = $order['payment_status'] === 'paid';

$pageTitle = $paid ? 'Order confirmed' : 'Order received';
include BASE_PATH . '/includes/header.php';
?>
<div style="max-width:640px;margin:0 auto">
    <div class="panel" style="text-align:center">
        <?php if ($paid): ?>
            <div style="font-size:3rem">✅</div>
            <h1>Thank you! Your order is confirmed.</h1>
            <p class="muted">Order <strong><?= e($order['order_number']) ?></strong> — a confirmation has been prepared for <?= e($order['email']) ?>.</p>
        <?php else: ?>
            <div style="font-size:3rem">⏳</div>
            <h1>We've received your order</h1>
            <p class="muted">Order <strong><?= e($order['order_number']) ?></strong>. Payment is still being confirmed — we'll email you as soon as it clears.</p>
        <?php endif; ?>
    </div>

    <div class="panel">
        <h2>Order summary</h2>
        <table class="cart-table" style="border:none">
            <?php foreach ($items as $it): ?>
                <tr>
                    <td><?= e($it['name']) ?> <span class="muted">× <?= (int)$it['quantity'] ?></span></td>
                    <td style="text-align:right"><?= money((float)$it['line_total']) ?></td>
                </tr>
            <?php endforeach; ?>
        </table>
        <div class="cart-summary" style="border:none;padding:12px 0 0">
            <div class="row"><span>Subtotal</span><span><?= money((float)$order['subtotal']) ?></span></div>
            <div class="row"><span>Shipping</span><span><?= (float)$order['shipping']==0?'FREE':money((float)$order['shipping']) ?></span></div>
            <div class="row total"><span>Total paid</span><span><?= money((float)$order['total']) ?></span></div>
        </div>
    </div>

    <div style="text-align:center;margin-top:16px">
        <a class="btn btn-lg" href="<?= e(url('shop.php')) ?>">Continue shopping</a>
    </div>
</div>
<?php include BASE_PATH . '/includes/footer.php'; ?>
