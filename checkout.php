<?php
require_once __DIR__ . '/config/config.php';
require_once BASE_PATH . '/includes/shop.php';
require_once BASE_PATH . '/includes/cart_lib.php';
require_once BASE_PATH . '/includes/orders.php';
require_once BASE_PATH . '/includes/Yoco.php';

$data = cart_items();
$items = $data['items'];
$totals = cart_totals($items);

// Empty cart -> back to cart
if (!$items) {
    flash('Your cart is empty.', 'info');
    redirect('cart.php');
}

$errors = [];
$old = [
    'name' => '', 'email' => $_SESSION['checkout_email'] ?? '', 'phone' => '',
    'address_line1' => '', 'address_line2' => '', 'city' => '', 'province' => '', 'postal_code' => '',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_check()) {
        flash('Session expired, please try again.', 'error');
        redirect('checkout.php');
    }
    foreach ($old as $k => $_) {
        $old[$k] = trim((string)($_POST[$k] ?? ''));
    }

    if ($old['name'] === '')  $errors['name'] = 'Please enter your full name.';
    if (!filter_var($old['email'], FILTER_VALIDATE_EMAIL)) $errors['email'] = 'Please enter a valid email address.';
    if ($old['phone'] === '') $errors['phone'] = 'Please enter a contact number.';
    if ($old['address_line1'] === '') $errors['address_line1'] = 'Please enter your delivery address.';
    if ($old['city'] === '') $errors['city'] = 'Please enter your city/town.';
    if ($old['province'] === '') $errors['province'] = 'Please select your province.';
    if ($old['postal_code'] === '') $errors['postal_code'] = 'Please enter a postal code.';

    if (!$errors) {
        $_SESSION['checkout_email'] = $old['email'];
        try {
            // Recompute totals fresh right before creating the order.
            $data = cart_items();
            $items = $data['items'];
            if (!$items) {
                flash('Your cart is empty.', 'info');
                redirect('cart.php');
            }
            $totals = cart_totals($items);

            $order = create_order($old, $items, $totals);

            $yoco = new Yoco();
            $checkout = $yoco->createCheckout(
                (int)round($totals['total'] * 100),
                [
                    'success' => url('checkout-success.php?order=' . urlencode($order['order_number'])),
                    'cancel'  => url('checkout-cancel.php?order=' . urlencode($order['order_number'])),
                    'failure' => url('checkout-cancel.php?order=' . urlencode($order['order_number']) . '&failed=1'),
                ],
                [
                    'order_number' => $order['order_number'],
                    'order_id'     => (string)$order['id'],
                ]
            );

            if (empty($checkout['redirectUrl']) || empty($checkout['id'])) {
                throw new RuntimeException('Yoco did not return a redirect URL.');
            }
            set_order_checkout((int)$order['id'], (string)$checkout['id']);
            redirect($checkout['redirectUrl']);
        } catch (Throwable $ex) {
            error_log('Checkout error: ' . $ex->getMessage());
            $errors['general'] = APP_DEBUG
                ? 'Payment error: ' . $ex->getMessage()
                : 'We could not start the payment. Please try again in a moment.';
        }
    }
}

$provinces = ['Eastern Cape','Free State','Gauteng','KwaZulu-Natal','Limpopo','Mpumalanga','North West','Northern Cape','Western Cape'];

$pageTitle = 'Checkout';
include BASE_PATH . '/includes/header.php';
?>
<div class="breadcrumb"><a href="<?= e(url('cart.php')) ?>">Cart</a> › Checkout</div>
<h1 style="margin-bottom:18px">Checkout</h1>

<?php if (!empty($errors['general'])): ?>
    <div class="flash flash-error"><?= e($errors['general']) ?></div>
<?php endif; ?>

<form method="post" action="<?= e(url('checkout.php')) ?>">
    <?= csrf_field() ?>
    <div class="cart-grid">
        <div>
            <div class="panel">
                <h2>Contact details</h2>
                <div class="form-grid">
                    <div class="field">
                        <label>Full name *</label>
                        <input name="name" value="<?= e($old['name']) ?>" required>
                        <?php if (!empty($errors['name'])): ?><small style="color:var(--red)"><?= e($errors['name']) ?></small><?php endif; ?>
                    </div>
                    <div class="field">
                        <label>Email *</label>
                        <input type="email" name="email" value="<?= e($old['email']) ?>" required>
                        <?php if (!empty($errors['email'])): ?><small style="color:var(--red)"><?= e($errors['email']) ?></small><?php endif; ?>
                    </div>
                    <div class="field full">
                        <label>Phone *</label>
                        <input name="phone" value="<?= e($old['phone']) ?>" required>
                        <?php if (!empty($errors['phone'])): ?><small style="color:var(--red)"><?= e($errors['phone']) ?></small><?php endif; ?>
                    </div>
                </div>
            </div>

            <div class="panel">
                <h2>Delivery address</h2>
                <div class="form-grid">
                    <div class="field full">
                        <label>Address line 1 *</label>
                        <input name="address_line1" value="<?= e($old['address_line1']) ?>" required>
                        <?php if (!empty($errors['address_line1'])): ?><small style="color:var(--red)"><?= e($errors['address_line1']) ?></small><?php endif; ?>
                    </div>
                    <div class="field full">
                        <label>Address line 2</label>
                        <input name="address_line2" value="<?= e($old['address_line2']) ?>">
                    </div>
                    <div class="field">
                        <label>City / Town *</label>
                        <input name="city" value="<?= e($old['city']) ?>" required>
                        <?php if (!empty($errors['city'])): ?><small style="color:var(--red)"><?= e($errors['city']) ?></small><?php endif; ?>
                    </div>
                    <div class="field">
                        <label>Province *</label>
                        <select name="province" required>
                            <option value="">Select…</option>
                            <?php foreach ($provinces as $pr): ?>
                                <option value="<?= e($pr) ?>" <?= $old['province']===$pr?'selected':'' ?>><?= e($pr) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <?php if (!empty($errors['province'])): ?><small style="color:var(--red)"><?= e($errors['province']) ?></small><?php endif; ?>
                    </div>
                    <div class="field">
                        <label>Postal code *</label>
                        <input name="postal_code" value="<?= e($old['postal_code']) ?>" required>
                        <?php if (!empty($errors['postal_code'])): ?><small style="color:var(--red)"><?= e($errors['postal_code']) ?></small><?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <aside class="cart-summary">
            <h2 style="margin-top:0">Your order</h2>
            <?php foreach ($items as $it): $p = $it['product']; ?>
                <div class="row" style="font-size:.88rem">
                    <span><?= e($p['name']) ?> <span class="muted">× <?= (int)$it['qty'] ?></span></span>
                    <span><?= money($it['line_total']) ?></span>
                </div>
            <?php endforeach; ?>
            <div class="row" style="border-top:1px solid var(--line);margin-top:8px;padding-top:8px"><span>Subtotal</span><span><?= money($totals['subtotal']) ?></span></div>
            <div class="row"><span>Shipping</span><span><?= $totals['shipping']==0?'<strong style="color:var(--green)">FREE</strong>':money($totals['shipping']) ?></span></div>
            <div class="row total"><span>Total</span><span><?= money($totals['total']) ?></span></div>
            <div class="muted" style="font-size:.8rem;margin-bottom:14px">Incl. <?= money($totals['vat']) ?> VAT</div>
            <button type="submit" class="btn btn-lg btn-accent btn-block">Pay <?= money($totals['total']) ?> with Yoco →</button>
            <p class="muted" style="font-size:.78rem;text-align:center;margin-top:10px">🔒 You'll be redirected to Yoco's secure page to complete payment.</p>
        </aside>
    </div>
</form>
<?php include BASE_PATH . '/includes/footer.php'; ?>
