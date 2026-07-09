<?php
require_once __DIR__ . '/config/config.php';
require_once BASE_PATH . '/includes/shop.php';
require_once BASE_PATH . '/includes/cart_lib.php';

// Handle POST actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_check()) {
        flash('Your session expired. Please try again.', 'error');
        redirect('cart.php');
    }
    $action = $_POST['action'] ?? '';
    switch ($action) {
        case 'add':
            $id  = (int)($_POST['id'] ?? 0);
            $qty = max(1, (int)($_POST['qty'] ?? 1));
            $p = $id ? product_by_id($id) : null;
            if ($p && $p['active'] && (int)$p['stock_qty'] > 0) {
                cart_add($id, $qty);
                flash('“' . $p['name'] . '” added to your cart.', 'success');
            } else {
                flash('Sorry, that product is not available.', 'error');
            }
            // If added from a listing, go back there; else show cart
            $back = $_POST['return'] ?? 'cart.php';
            redirect($action === 'add' && !empty($_POST['stay']) ? $back : 'cart.php');
            break;

        case 'update':
            $qtys = $_POST['qty'] ?? [];
            foreach ($qtys as $id => $q) {
                cart_set((int)$id, (int)$q);
            }
            flash('Cart updated.', 'success');
            redirect('cart.php');
            break;

        case 'remove':
            cart_remove((int)($_POST['id'] ?? 0));
            flash('Item removed.', 'info');
            redirect('cart.php');
            break;

        case 'clear':
            cart_clear();
            flash('Cart cleared.', 'info');
            redirect('cart.php');
            break;

        default:
            redirect('cart.php');
    }
}

$data = cart_items();
$items = $data['items'];
if ($data['adjusted']) {
    flash('Some items were updated due to stock or availability changes.', 'info');
}
$totals = cart_totals($items);

$pageTitle = 'Your Cart';
include BASE_PATH . '/includes/header.php';
?>
<div class="breadcrumb"><a href="<?= e(url('/')) ?>">Home</a> › Cart</div>
<h1 style="margin-bottom:18px">Your Cart</h1>

<?php if (!$items): ?>
    <div class="empty">
        <h3>Your cart is empty</h3>
        <p class="muted">Browse our range and add something you like.</p>
        <a class="btn btn-lg" href="<?= e(url('shop.php')) ?>">Start shopping</a>
    </div>
<?php else: ?>
<form method="post" action="<?= e(url('cart.php')) ?>">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="update">
    <div class="cart-grid">
        <div>
            <table class="cart-table">
                <thead>
                    <tr><th>Product</th><th>Price</th><th>Qty</th><th>Total</th><th></th></tr>
                </thead>
                <tbody>
                    <?php foreach ($items as $it): $p = $it['product']; ?>
                    <tr>
                        <td>
                            <div class="cart-prod">
                                <img src="<?= e(product_image($p['image_url'])) ?>" alt=""
                                     onerror="this.onerror=null;this.src='<?= e(asset('img/placeholder.svg')) ?>'">
                                <div>
                                    <a href="<?= e(product_url($p)) ?>" style="font-weight:600;color:var(--ink)"><?= e($p['name']) ?></a>
                                    <div class="muted" style="font-size:.8rem">SKU: <?= e($p['sku']) ?></div>
                                    <?php if ($it['capped']): ?><div style="color:var(--amber);font-size:.78rem">Only <?= (int)$p['stock_qty'] ?> in stock</div><?php endif; ?>
                                </div>
                            </div>
                        </td>
                        <td><?= money((float)$p['price']) ?></td>
                        <td>
                            <input type="number" name="qty[<?= (int)$p['id'] ?>]" value="<?= (int)$it['qty'] ?>"
                                   min="0" max="<?= (int)$p['stock_qty'] ?>" style="width:70px;padding:.5em;border:1px solid var(--line);border-radius:6px">
                        </td>
                        <td style="font-weight:700"><?= money($it['line_total']) ?></td>
                        <td>
                            <button type="submit" form="remove-<?= (int)$p['id'] ?>" class="btn btn-sm btn-ghost" title="Remove">✕</button>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <div style="display:flex;justify-content:space-between;margin-top:14px;gap:10px;flex-wrap:wrap">
                <a class="btn btn-ghost" href="<?= e(url('shop.php')) ?>">← Continue shopping</a>
                <button type="submit" class="btn btn-ghost">Update cart</button>
            </div>
        </div>

        <aside class="cart-summary">
            <h2 style="margin-top:0">Order summary</h2>
            <div class="row"><span>Subtotal (<?= $totals['count'] ?> items)</span><span><?= money($totals['subtotal']) ?></span></div>
            <div class="row">
                <span>Shipping</span>
                <span><?= $totals['shipping'] == 0 ? '<strong style="color:var(--green)">FREE</strong>' : money($totals['shipping']) ?></span>
            </div>
            <?php if ($totals['shipping'] > 0): ?>
                <div class="muted" style="font-size:.8rem">Add <?= money(SHIPPING_FREE_OVER - $totals['subtotal']) ?> for free delivery</div>
            <?php endif; ?>
            <div class="row total"><span>Total</span><span><?= money($totals['total']) ?></span></div>
            <div class="muted" style="font-size:.8rem;margin-bottom:14px">Incl. <?= money($totals['vat']) ?> VAT</div>
            <a class="btn btn-lg btn-accent btn-block" href="<?= e(url('checkout.php')) ?>">Proceed to checkout →</a>
            <p class="muted" style="font-size:.78rem;text-align:center;margin-top:10px">🔒 Secure payment via Yoco</p>
        </aside>
    </div>
</form>

<?php // separate remove forms (can't nest forms)
foreach ($items as $it): $p = $it['product']; ?>
    <form id="remove-<?= (int)$p['id'] ?>" method="post" action="<?= e(url('cart.php')) ?>" style="display:none">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="remove">
        <input type="hidden" name="id" value="<?= (int)$p['id'] ?>">
    </form>
<?php endforeach; ?>
<?php endif; ?>

<?php include BASE_PATH . '/includes/footer.php'; ?>
