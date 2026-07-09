<?php
/**
 * Order emails. Sending is gated by MAIL_ENABLED — when off, emails are
 * written to storage/logs/mail.log so everything is "ready to go" but silent.
 */

declare(strict_types=1);

require_once BASE_PATH . '/includes/orders.php';

function send_order_emails(int $orderId): void
{
    $order = order_by_id($orderId);
    if (!$order) {
        return;
    }
    $items = order_items_for($orderId);

    // Customer confirmation
    $customerSubject = 'Order confirmation — ' . $order['order_number'];
    $customerBody = render_order_email($order, $items, false);
    dispatch_mail($order['email'], $customerSubject, $customerBody);

    // Shop notification
    $notify = (string)env('ORDER_NOTIFY_EMAIL', 'shop@pconestop.co.za');
    $adminSubject = 'New paid order ' . $order['order_number'] . ' — ' . money((float)$order['total']);
    $adminBody = render_order_email($order, $items, true);
    dispatch_mail($notify, $adminSubject, $adminBody);
}

function dispatch_mail(string $to, string $subject, string $htmlBody): void
{
    $from     = (string)env('MAIL_FROM', 'shop@pconestop.co.za');
    $fromName = (string)env('MAIL_FROM_NAME', APP_NAME);
    $enabled  = (bool)env('MAIL_ENABLED', false);

    if (!$enabled) {
        $line = '[' . date('Y-m-d H:i:s') . "] (SENDING DISABLED) To: $to | Subject: $subject\n";
        @file_put_contents(BASE_PATH . '/storage/logs/mail.log', $line, FILE_APPEND);
        return;
    }

    $headers = [
        'MIME-Version: 1.0',
        'Content-Type: text/html; charset=UTF-8',
        'From: ' . sprintf('%s <%s>', $fromName, $from),
        'Reply-To: ' . $from,
    ];
    // On shared hosting PHP mail() routes via the server MTA.
    @mail($to, $subject, $htmlBody, implode("\r\n", $headers));
}

function render_order_email(array $order, array $items, bool $forAdmin): string
{
    $rows = '';
    foreach ($items as $it) {
        $rows .= '<tr>'
            . '<td style="padding:6px 8px;border-bottom:1px solid #eee">' . e($it['name']) . ' <span style="color:#888">(' . e($it['sku']) . ')</span></td>'
            . '<td style="padding:6px 8px;border-bottom:1px solid #eee;text-align:center">' . (int)$it['quantity'] . '</td>'
            . '<td style="padding:6px 8px;border-bottom:1px solid #eee;text-align:right">' . money((float)$it['line_total']) . '</td>'
            . '</tr>';
    }
    $addr = trim(implode('<br>', array_filter([
        e($order['address_line1'] ?? ''),
        e($order['address_line2'] ?? ''),
        trim(($order['city'] ?? '') . ', ' . ($order['province'] ?? '') . ' ' . ($order['postal_code'] ?? '')),
    ])));

    $intro = $forAdmin
        ? '<p>A new order has been paid and is ready to process.</p>'
        : '<p>Thanks for your order! We\'ve received your payment and are getting it ready.</p>';

    return '<div style="font-family:Arial,sans-serif;max-width:600px;margin:auto;color:#1a2233">'
        . '<h2 style="color:#1e4fd8">PC One Stop Shop</h2>'
        . '<h3>Order ' . e($order['order_number']) . '</h3>'
        . $intro
        . '<table style="width:100%;border-collapse:collapse;margin:16px 0">'
        . '<thead><tr><th style="text-align:left;padding:6px 8px;border-bottom:2px solid #ddd">Item</th>'
        . '<th style="padding:6px 8px;border-bottom:2px solid #ddd">Qty</th>'
        . '<th style="text-align:right;padding:6px 8px;border-bottom:2px solid #ddd">Total</th></tr></thead>'
        . '<tbody>' . $rows . '</tbody></table>'
        . '<p style="text-align:right">Subtotal: ' . money((float)$order['subtotal']) . '<br>'
        . 'Shipping: ' . money((float)$order['shipping']) . '<br>'
        . '<strong style="font-size:1.1em">Total: ' . money((float)$order['total']) . '</strong> '
        . '<span style="color:#888">(incl. ' . money((float)$order['vat_amount']) . ' VAT)</span></p>'
        . '<hr style="border:none;border-top:1px solid #eee">'
        . '<p><strong>' . e($order['customer_name']) . '</strong><br>'
        . e($order['email']) . '<br>' . e($order['phone'] ?? '') . '<br>' . $addr . '</p>'
        . ($forAdmin ? '' : '<p style="color:#888;font-size:13px">We\'ll email you again when your order ships. Questions? Reply to this email.</p>')
        . '</div>';
}
