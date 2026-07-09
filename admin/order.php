<?php
require_once __DIR__ . '/_bootstrap.php';
require_admin();

$id = (int)($_GET['id'] ?? 0);
$order = $id ? order_by_id($id) : null;
if (!$order) {
    http_response_code(404);
    $adminTitle = 'Order not found'; $adminNav = 'orders';
    include __DIR__ . '/_header.php';
    echo '<div class="panel">Order not found. <a href="'.e(admin_url('orders.php')).'">Back to orders</a></div>';
    include __DIR__ . '/_footer.php';
    exit;
}

// Update fulfilment status
if ($_SERVER['REQUEST_METHOD'] === 'POST' && csrf_check()) {
    $new = $_POST['status'] ?? '';
    if (in_array($new, ['new','processing','shipped','completed','cancelled'], true)) {
        db()->prepare('UPDATE orders SET status = ? WHERE id = ?')->execute([$new, $id]);
        flash('Order status updated to ' . $new . '.', 'success');
    }
    if (!empty($_POST['mark_paid']) && $order['payment_status'] !== 'paid') {
        mark_order_paid($id, 'manual');
        flash('Order marked as paid.', 'success');
    }
    redirect('admin/order.php?id=' . $id);
}

$items = order_items_for($id);
$pc = ['paid'=>'b-green','pending'=>'b-amber','failed'=>'b-red','cancelled'=>'b-grey','refunded'=>'b-blue'][$order['payment_status']] ?? 'b-grey';

$adminTitle = 'Order ' . $order['order_number'];
$adminNav = 'orders';
include __DIR__ . '/_header.php';
?>
<a href="<?= e(admin_url('orders.php')) ?>" class="muted">← Back to orders</a>
<div style="display:grid;grid-template-columns:1.5fr 1fr;gap:22px;margin-top:14px">
    <div>
        <div class="panel">
            <div class="toolbar" style="margin-bottom:10px">
                <h2 style="margin:0"><?= e($order['order_number']) ?></h2>
                <span class="badge <?= $pc ?>"><?= e(ucfirst($order['payment_status'])) ?></span>
            </div>
            <p class="muted" style="margin:0"><?= e(date('d M Y, H:i', strtotime($order['created_at']))) ?></p>
            <table class="grid" style="margin-top:14px">
                <thead><tr><th>Item</th><th>SKU</th><th>Price</th><th>Qty</th><th>Total</th></tr></thead>
                <tbody>
                <?php foreach ($items as $it): ?>
                    <tr>
                        <td><?= e($it['name']) ?></td>
                        <td class="muted"><?= e($it['sku']) ?></td>
                        <td><?= money((float)$it['unit_price']) ?></td>
                        <td><?= (int)$it['quantity'] ?></td>
                        <td style="font-weight:700"><?= money((float)$it['line_total']) ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            <div style="text-align:right;margin-top:14px">
                <div>Subtotal: <?= money((float)$order['subtotal']) ?></div>
                <div>Shipping: <?= money((float)$order['shipping']) ?></div>
                <div style="font-size:1.2rem;font-weight:800;margin-top:4px">Total: <?= money((float)$order['total']) ?></div>
                <div class="muted" style="font-size:.8rem">incl. <?= money((float)$order['vat_amount']) ?> VAT</div>
            </div>
        </div>
    </div>

    <div>
        <div class="panel">
            <h2>Customer</h2>
            <p style="margin:0"><strong><?= e($order['customer_name']) ?></strong><br>
            <?= e($order['email']) ?><br><?= e($order['phone']) ?></p>
            <h3 style="margin-top:16px">Delivery</h3>
            <p class="muted" style="margin:0">
                <?= e($order['address_line1']) ?><br>
                <?php if ($order['address_line2']): ?><?= e($order['address_line2']) ?><br><?php endif; ?>
                <?= e($order['city']) ?>, <?= e($order['province']) ?> <?= e($order['postal_code']) ?>
            </p>
        </div>

        <div class="panel">
            <h2>Manage</h2>
            <form method="post">
                <?= csrf_field() ?>
                <div class="field">
                    <label>Fulfilment status</label>
                    <select name="status">
                        <?php foreach (['new','processing','shipped','completed','cancelled'] as $s): ?>
                            <option value="<?= $s ?>" <?= $order['status']===$s?'selected':'' ?>><?= ucfirst($s) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <button class="btn" type="submit">Update status</button>
                <?php if ($order['payment_status'] !== 'paid'): ?>
                    <button class="btn btn-ghost" type="submit" name="mark_paid" value="1" onclick="return confirm('Manually mark this order as paid?')">Mark paid</button>
                <?php endif; ?>
            </form>
            <?php if ($order['yoco_checkout_id']): ?>
                <p class="muted" style="font-size:.78rem;margin-top:12px">Yoco checkout: <?= e($order['yoco_checkout_id']) ?><?php if ($order['yoco_payment_id']): ?><br>Payment: <?= e($order['yoco_payment_id']) ?><?php endif; ?></p>
            <?php endif; ?>
        </div>
    </div>
</div>
<?php include __DIR__ . '/_footer.php'; ?>
