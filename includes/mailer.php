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
    $notify = (string)env('ORDER_NOTIFY_EMAIL', 'orders@pconestop.co.za');
    $adminSubject = 'New paid order ' . $order['order_number'] . ' — ' . money((float)$order['total']);
    $adminBody = render_order_email($order, $items, true);
    dispatch_mail($notify, $adminSubject, $adminBody);

    // Supplier purchase order (drop-ship): dealer prices only, never our selling prices.
    send_supplier_po($order, $items);
}

/**
 * Email the paid order to the Syntech sales rep as a purchase order, with a
 * verification copy to the shop address. Contains ONLY dealer (Syntech) prices
 * from the order_items cost snapshot — customer pricing never leaves the shop.
 */
function send_supplier_po(array $order, array $items): void
{
    $repEmail = trim((string)setting('syntech_rep_email', ''));
    $repName  = trim((string)setting('syntech_rep_name', ''));
    $notify   = (string)env('ORDER_NOTIFY_EMAIL', 'orders@pconestop.co.za');

    $body = render_supplier_po($order, $items, $repName);
    $subject = 'Purchase order ' . $order['order_number'] . ' — PC One Stop (drop-ship)';

    if ($repEmail !== '') {
        dispatch_mail($repEmail, $subject, $body);
        dispatch_mail($notify, '[COPY sent to ' . $repEmail . '] ' . $subject, $body);
    } else {
        dispatch_mail($notify, '[NO REP CONFIGURED — forward manually] ' . $subject, $body);
    }
}

function render_supplier_po(array $order, array $items, string $repName): string
{
    $rows = '';
    $dealerTotal = 0.0;
    foreach ($items as $it) {
        $unitCost  = (float)($it['cost_price'] ?? 0);
        $qty       = (int)$it['quantity'];
        $lineCost  = round($unitCost * $qty, 2);
        $dealerTotal += $lineCost;
        $rows .= '<tr>'
            . '<td style="padding:7px 9px;border-bottom:1px solid #eee;font-family:monospace">' . e($it['sku']) . '</td>'
            . '<td style="padding:7px 9px;border-bottom:1px solid #eee">' . e($it['name']) . '</td>'
            . '<td style="padding:7px 9px;border-bottom:1px solid #eee;text-align:center;font-weight:700">' . $qty . '</td>'
            . '<td style="padding:7px 9px;border-bottom:1px solid #eee;text-align:right">' . money($unitCost) . '</td>'
            . '<td style="padding:7px 9px;border-bottom:1px solid #eee;text-align:right">' . money($lineCost) . '</td>'
            . '</tr>';
    }
    $addr = implode('<br>', array_filter([
        e($order['address_line1'] ?? ''),
        e($order['address_line2'] ?? ''),
        e(trim(($order['city'] ?? '') . ', ' . ($order['province'] ?? ''))),
        e($order['postal_code'] ?? ''),
    ]));
    $greeting = $repName !== '' ? 'Hi ' . e($repName) . ',' : 'Hi,';

    return '<div style="font-family:Arial,sans-serif;max-width:640px;margin:auto;color:#1a2233">'
        . '<h2 style="color:#0f8fbf;margin-bottom:4px">PC One Stop — Purchase Order</h2>'
        . '<h3 style="margin-top:0">' . e($order['order_number']) . ' · ' . e(date('d M Y H:i')) . '</h3>'
        . '<p>' . $greeting . '<br>Please process and ship the following order directly to our customer.</p>'

        . '<div style="background:#c0392b;color:#ffffff;padding:16px 18px;border-radius:8px;'
        . 'font-size:17px;font-weight:800;line-height:1.5;margin:16px 0">'
        . '⚠ DO NOT SEND ANY INVOICE OR PC ONE STOP PAPERWORK TO THE CUSTOMER.<br>'
        . 'THIS IS A DROP-SHIP ORDER — INVOICE PC ONE STOP ONLY, BY EMAIL TO '
        . '<span style="text-decoration:underline">orders@pconestop.co.za</span>.'
        . '</div>'

        . '<div style="border:2px solid #1a2233;border-radius:8px;padding:14px 18px;margin:16px 0">'
        . '<div style="font-size:12px;text-transform:uppercase;letter-spacing:1px;color:#666">Deliver to</div>'
        . '<div style="font-size:18px;font-weight:800">' . e($order['customer_name']) . '</div>'
        . '<div style="font-size:16px;line-height:1.5">' . $addr . '</div>'
        . ($order['phone'] ? '<div style="margin-top:6px">📞 ' . e($order['phone']) . '</div>' : '')
        . '</div>'

        . '<table style="width:100%;border-collapse:collapse;margin:16px 0">'
        . '<thead><tr>'
        . '<th style="text-align:left;padding:7px 9px;border-bottom:2px solid #ddd">SKU</th>'
        . '<th style="text-align:left;padding:7px 9px;border-bottom:2px solid #ddd">Product</th>'
        . '<th style="padding:7px 9px;border-bottom:2px solid #ddd">Qty</th>'
        . '<th style="text-align:right;padding:7px 9px;border-bottom:2px solid #ddd">Dealer (ex VAT)</th>'
        . '<th style="text-align:right;padding:7px 9px;border-bottom:2px solid #ddd">Line total</th>'
        . '</tr></thead>'
        . '<tbody>' . $rows . '</tbody></table>'
        . '<p style="text-align:right;font-size:1.05em"><strong>Order total (dealer, ex VAT): ' . money($dealerTotal) . '</strong></p>'

        . '<p style="color:#888;font-size:13px">Reference <strong>' . e($order['order_number'])
        . '</strong> on all paperwork. Questions: reply to this email or contact orders@pconestop.co.za.</p>'
        . '</div>';
}

function dispatch_mail(string $to, string $subject, string $htmlBody, ?string $fromOverride = null, ?string $fromNameOverride = null): void
{
    $from     = $fromOverride ?? (string)env('MAIL_FROM', 'orders@pconestop.co.za');
    $fromName = $fromNameOverride ?? (string)env('MAIL_FROM_NAME', APP_NAME);
    $enabled  = (bool)env('MAIL_ENABLED', false);

    if (!$enabled) {
        $line = '[' . date('Y-m-d H:i:s') . "] (SENDING DISABLED) To: $to | Subject: $subject\n";
        @file_put_contents(BASE_PATH . '/storage/logs/mail.log', $line, FILE_APPEND);
        // Save the full body so emails can be previewed before go-live.
        $dir = BASE_PATH . '/storage/logs/mail';
        if (!is_dir($dir)) { @mkdir($dir, 0775, true); }
        $slug = preg_replace('/[^a-z0-9]+/i', '-', substr($subject, 0, 60));
        @file_put_contents($dir . '/' . date('Ymd-His') . '-' . $slug . '.html',
            "<!-- To: $to | Subject: $subject -->\n" . $htmlBody);
        return;
    }

    // Authenticated SMTP when MAIL_HOST is configured; otherwise fall back to
    // PHP mail() (works on shared hosting via the server MTA).
    $host = (string)env('MAIL_HOST', '');
    if ($host !== '') {
        $ok = smtp_send($to, $subject, $htmlBody, $from, $fromName);
        $line = '[' . date('Y-m-d H:i:s') . '] ' . ($ok ? 'SMTP sent' : 'SMTP FAILED')
              . " To: $to | Subject: $subject\n";
        @file_put_contents(BASE_PATH . '/storage/logs/mail.log', $line, FILE_APPEND);
        return;
    }

    $headers = [
        'MIME-Version: 1.0',
        'Content-Type: text/html; charset=UTF-8',
        'From: ' . sprintf('%s <%s>', $fromName, $from),
        'Reply-To: ' . $from,
    ];
    @mail($to, $subject, $htmlBody, implode("\r\n", $headers));
}

/**
 * Minimal SMTP client (no dependencies): implicit TLS on port 465, STARTTLS
 * otherwise. Credentials from .env MAIL_HOST/MAIL_PORT/MAIL_USER/MAIL_PASS.
 */
function smtp_send(string $to, string $subject, string $htmlBody, string $from, string $fromName): bool
{
    $host = (string)env('MAIL_HOST', '');
    $port = (int)env('MAIL_PORT', 587);
    $user = (string)env('MAIL_USER', '');
    $pass = (string)env('MAIL_PASS', '');

    $errno = 0; $errstr = '';
    $remote = ($port === 465 ? 'ssl://' : '') . $host;
    $fp = @stream_socket_client("$remote:$port", $errno, $errstr, 15);
    if (!$fp) {
        return smtp_log_fail("connect: $errstr ($errno)");
    }
    stream_set_timeout($fp, 15);

    $read = function () use ($fp): string {
        $out = '';
        while (($line = fgets($fp, 1024)) !== false) {
            $out .= $line;
            if (isset($line[3]) && $line[3] === ' ') break; // last line of reply
        }
        return $out;
    };
    $cmd = function (string $c, array $expect) use ($fp, $read): bool {
        fwrite($fp, $c . "\r\n");
        $resp = $read();
        return in_array((int)substr($resp, 0, 3), $expect, true);
    };

    try {
        if ((int)substr($read(), 0, 3) !== 220) return smtp_log_fail('no greeting');
        if (!$cmd('EHLO ' . parse_url(APP_URL, PHP_URL_HOST), [250])) return smtp_log_fail('EHLO');
        if ($port !== 465) {
            if (!$cmd('STARTTLS', [220])) return smtp_log_fail('STARTTLS refused');
            if (!stream_socket_enable_crypto($fp, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
                return smtp_log_fail('TLS negotiation');
            }
            if (!$cmd('EHLO ' . parse_url(APP_URL, PHP_URL_HOST), [250])) return smtp_log_fail('EHLO/tls');
        }
        if ($user !== '') {
            if (!$cmd('AUTH LOGIN', [334])) return smtp_log_fail('AUTH LOGIN');
            if (!$cmd(base64_encode($user), [334])) return smtp_log_fail('auth user');
            if (!$cmd(base64_encode($pass), [235])) return smtp_log_fail('auth pass (check MAIL_USER/MAIL_PASS)');
        }
        if (!$cmd("MAIL FROM:<$from>", [250])) return smtp_log_fail('MAIL FROM');
        if (!$cmd("RCPT TO:<$to>", [250, 251])) return smtp_log_fail("RCPT $to");
        if (!$cmd('DATA', [354])) return smtp_log_fail('DATA');

        $headers = 'From: ' . sprintf('%s <%s>', $fromName, $from) . "\r\n"
            . "To: $to\r\n"
            . 'Subject: =?UTF-8?B?' . base64_encode($subject) . "?=\r\n"
            . "Reply-To: $from\r\n"
            . 'Date: ' . date('r') . "\r\n"
            . 'Message-ID: <' . bin2hex(random_bytes(12)) . '@' . parse_url(APP_URL, PHP_URL_HOST) . ">\r\n"
            . "MIME-Version: 1.0\r\n"
            . "Content-Type: text/html; charset=UTF-8\r\n"
            . "Content-Transfer-Encoding: base64\r\n";
        $body = chunk_split(base64_encode($htmlBody), 76, "\r\n");
        if (!$cmd($headers . "\r\n" . $body . "\r\n.", [250])) return smtp_log_fail('message rejected');
        $cmd('QUIT', [221]);
        return true;
    } finally {
        fclose($fp);
    }
}

function smtp_log_fail(string $why): bool
{
    @file_put_contents(BASE_PATH . '/storage/logs/mail.log',
        '[' . date('Y-m-d H:i:s') . "] SMTP error: $why\n", FILE_APPEND);
    return false;
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
        . '<h2 style="color:#0f8fbf">PC One Stop</h2>'
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
