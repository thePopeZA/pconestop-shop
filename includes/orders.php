<?php
/**
 * Order creation & status helpers.
 */

declare(strict_types=1);

function generate_order_number(): string
{
    return 'PCOS-' . date('Ymd') . '-' . strtoupper(bin2hex(random_bytes(3)));
}

/**
 * Create a pending order from resolved cart items + totals + customer data.
 * Returns the created order row (with id + order_number).
 */
function create_order(array $customer, array $items, array $totals): array
{
    $pdo = db();
    $pdo->beginTransaction();
    try {
        $orderNumber = generate_order_number();
        $stmt = $pdo->prepare(
            'INSERT INTO orders
             (order_number, customer_name, email, phone, address_line1, address_line2,
              city, province, postal_code, subtotal, shipping, vat_amount, total,
              payment_status, status)
             VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,"pending","new")'
        );
        $stmt->execute([
            $orderNumber,
            $customer['name'], $customer['email'], $customer['phone'] ?? null,
            $customer['address_line1'] ?? null, $customer['address_line2'] ?? null,
            $customer['city'] ?? null, $customer['province'] ?? null, $customer['postal_code'] ?? null,
            $totals['subtotal'], $totals['shipping'], $totals['vat'], $totals['total'],
        ]);
        $orderId = (int)$pdo->lastInsertId();

        $itemStmt = $pdo->prepare(
            'INSERT INTO order_items (order_id, product_id, sku, name, unit_price, cost_price, quantity, line_total)
             VALUES (?,?,?,?,?,?,?,?)'
        );
        foreach ($items as $it) {
            $p = $it['product'];
            $itemStmt->execute([
                $orderId, (int)$p['id'], $p['sku'], $p['name'],
                (float)$p['price'], (float)$p['cost_price'], (int)$it['qty'], (float)$it['line_total'],
            ]);
        }
        $pdo->commit();
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }

    return order_by_id($orderId);
}

function order_by_id(int $id): ?array
{
    $stmt = db()->prepare('SELECT * FROM orders WHERE id = ? LIMIT 1');
    $stmt->execute([$id]);
    return $stmt->fetch() ?: null;
}

function order_by_number(string $number): ?array
{
    $stmt = db()->prepare('SELECT * FROM orders WHERE order_number = ? LIMIT 1');
    $stmt->execute([$number]);
    return $stmt->fetch() ?: null;
}

function order_items_for(int $orderId): array
{
    $stmt = db()->prepare('SELECT * FROM order_items WHERE order_id = ?');
    $stmt->execute([$orderId]);
    return $stmt->fetchAll();
}

function set_order_checkout(int $orderId, string $checkoutId): void
{
    $stmt = db()->prepare('UPDATE orders SET yoco_checkout_id = ? WHERE id = ?');
    $stmt->execute([$checkoutId, $orderId]);
}

/** Mark an order paid (idempotent). Returns true if it transitioned to paid. */
function mark_order_paid(int $orderId, ?string $paymentId = null): bool
{
    $order = order_by_id($orderId);
    if (!$order) {
        return false;
    }
    if ($order['payment_status'] === 'paid') {
        return false; // already handled
    }
    $stmt = db()->prepare(
        'UPDATE orders SET payment_status = "paid", status = "processing", yoco_payment_id = ?
         WHERE id = ? AND payment_status <> "paid"'
    );
    $stmt->execute([$paymentId, $orderId]);
    $changed = $stmt->rowCount() > 0;
    if ($changed) {
        decrement_stock_for_order($orderId);
        // Email hook (respects MAIL_ENABLED flag)
        require_once BASE_PATH . '/includes/mailer.php';
        send_order_emails($orderId);
    }
    return $changed;
}

function mark_order_failed(int $orderId, string $status = 'failed'): void
{
    $allowed = ['failed', 'cancelled'];
    $status = in_array($status, $allowed, true) ? $status : 'failed';
    $stmt = db()->prepare(
        'UPDATE orders SET payment_status = ? WHERE id = ? AND payment_status = "pending"'
    );
    $stmt->execute([$status, $orderId]);
}

/**
 * Permanently delete an order (and its items via FK cascade). If the order was
 * paid, the stock it consumed is returned to inventory. Intended for removing
 * test orders; real fulfilled orders should be cancelled/refunded, not deleted.
 */
function delete_order(int $orderId): bool
{
    $pdo = db();
    $order = order_by_id($orderId);
    if (!$order) {
        return false;
    }
    $pdo->beginTransaction();
    try {
        if ($order['payment_status'] === 'paid') {
            $items = order_items_for($orderId);
            $upd = $pdo->prepare(
                'UPDATE products SET stock_qty = stock_qty + ?,
                 stock_status = CASE WHEN stock_qty + ? <= 0 THEN "out_of_stock"
                                     WHEN stock_qty + ? <= 3 THEN "low_stock"
                                     ELSE "in_stock" END
                 WHERE id = ?'
            );
            foreach ($items as $it) {
                if ($it['product_id']) {
                    $q = (int)$it['quantity'];
                    $upd->execute([$q, $q, $q, (int)$it['product_id']]);
                }
            }
        }
        $pdo->prepare('DELETE FROM orders WHERE id = ?')->execute([$orderId]);
        $pdo->commit();
        return true;
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }
}

/** Reduce product stock by ordered quantities (best-effort). */
function decrement_stock_for_order(int $orderId): void
{
    $items = order_items_for($orderId);
    $upd = db()->prepare(
        'UPDATE products SET stock_qty = GREATEST(0, stock_qty - ?),
         stock_status = CASE WHEN GREATEST(0, stock_qty - ?) <= 0 THEN "out_of_stock"
                             WHEN GREATEST(0, stock_qty - ?) <= 3 THEN "low_stock"
                             ELSE "in_stock" END
         WHERE id = ?'
    );
    foreach ($items as $it) {
        if ($it['product_id']) {
            $q = (int)$it['quantity'];
            $upd->execute([$q, $q, $q, (int)$it['product_id']]);
        }
    }
}
