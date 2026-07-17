<?php
/**
 * Commission report: per-product profit split for a month, and the monthly
 * invoice email to the partner. Shared by admin/profit.php and
 * cron/commission_report.php so the numbers are computed one way only.
 *
 * Profit is EX VAT: sell ex VAT (line_total / VAT_MULTIPLIER) minus the Syntech
 * feed cost snapshotted at sale (order_items.cost_price). Only PAID orders.
 * Commission = profit * commission_rate_pct; the shop owner keeps the rest.
 */

declare(strict_types=1);

require_once BASE_PATH . '/includes/orders.php';
require_once BASE_PATH . '/includes/mailer.php';

function commission_rate(): float
{
    return (float)setting('commission_rate_pct', '40') / 100;
}

/** Per-product rows for a given month ("YYYY-MM"), highest profit first. */
function commission_month_rows(string $ym): array
{
    $vat = VAT_MULTIPLIER;
    $stmt = db()->prepare(
        "SELECT oi.sku, oi.name,
                SUM(oi.quantity)                 AS units,
                SUM(oi.line_total)               AS revenue_incl,
                SUM(oi.line_total) / ?           AS revenue_ex,
                SUM(oi.cost_price * oi.quantity) AS cost_ex
         FROM orders o
         JOIN order_items oi ON oi.order_id = o.id
         WHERE o.payment_status = 'paid' AND DATE_FORMAT(o.created_at, '%Y-%m') = ?
         GROUP BY oi.sku, oi.name
         ORDER BY (SUM(oi.line_total) / ? - SUM(oi.cost_price * oi.quantity)) DESC"
    );
    $stmt->execute([$vat, $ym, $vat]);
    return $stmt->fetchAll();
}

/** Totals for a set of commission rows. */
function commission_totals(array $rows): array
{
    $revEx = 0.0; $costEx = 0.0; $units = 0;
    foreach ($rows as $r) {
        $revEx  += (float)$r['revenue_ex'];
        $costEx += (float)$r['cost_ex'];
        $units  += (int)$r['units'];
    }
    return [
        'units'      => $units,
        'revenue_ex' => round($revEx, 2),
        'cost_ex'    => round($costEx, 2),
        'profit'     => round($revEx - $costEx, 2),
    ];
}

/** HTML invoice-style commission report for one month. */
function render_commission_email(string $ym, array $rows, float $rate, string $repName = ''): string
{
    $label = date('F Y', strtotime($ym . '-01'));
    $t = commission_totals($rows);
    $commission = round($t['profit'] * $rate, 2);

    $body = '';
    foreach ($rows as $r) {
        $profit = (float)$r['revenue_ex'] - (float)$r['cost_ex'];
        $body .= '<tr>'
            . '<td style="padding:6px 8px;border-bottom:1px solid #eee;font-family:monospace">' . e($r['sku']) . '</td>'
            . '<td style="padding:6px 8px;border-bottom:1px solid #eee">' . e($r['name']) . '</td>'
            . '<td style="padding:6px 8px;border-bottom:1px solid #eee;text-align:center">' . (int)$r['units'] . '</td>'
            . '<td style="padding:6px 8px;border-bottom:1px solid #eee;text-align:right">' . money((float)$r['revenue_ex']) . '</td>'
            . '<td style="padding:6px 8px;border-bottom:1px solid #eee;text-align:right">' . money((float)$r['cost_ex']) . '</td>'
            . '<td style="padding:6px 8px;border-bottom:1px solid #eee;text-align:right">' . money($profit) . '</td>'
            . '<td style="padding:6px 8px;border-bottom:1px solid #eee;text-align:right;font-weight:700">' . money($profit * $rate) . '</td>'
            . '</tr>';
    }
    if (!$rows) {
        $body = '<tr><td colspan="7" style="padding:12px;text-align:center;color:#888">No paid sales in ' . e($label) . '.</td></tr>';
    }

    $greeting = $repName !== '' ? 'Hi ' . e($repName) . ',' : 'Hi,';

    return '<div style="font-family:Arial,sans-serif;max-width:680px;margin:auto;color:#1a2233">'
        . '<h2 style="color:#0f8fbf;margin-bottom:2px">PC One Stop — Commission Report</h2>'
        . '<h3 style="margin-top:0">' . e($label) . '</h3>'
        . '<p>' . $greeting . '<br>Here is your commission for ' . e($label) . ' (paid orders only, all figures ex VAT).</p>'
        . '<table style="width:100%;border-collapse:collapse;margin:16px 0;font-size:14px">'
        . '<thead><tr>'
        . '<th style="text-align:left;padding:6px 8px;border-bottom:2px solid #ddd">SKU</th>'
        . '<th style="text-align:left;padding:6px 8px;border-bottom:2px solid #ddd">Product</th>'
        . '<th style="padding:6px 8px;border-bottom:2px solid #ddd">Qty</th>'
        . '<th style="text-align:right;padding:6px 8px;border-bottom:2px solid #ddd">Revenue</th>'
        . '<th style="text-align:right;padding:6px 8px;border-bottom:2px solid #ddd">Cost</th>'
        . '<th style="text-align:right;padding:6px 8px;border-bottom:2px solid #ddd">Profit</th>'
        . '<th style="text-align:right;padding:6px 8px;border-bottom:2px solid #ddd">Commission ' . round($rate * 100) . '%</th>'
        . '</tr></thead><tbody>' . $body . '</tbody></table>'
        . '<table style="margin-left:auto;font-size:15px">'
        . '<tr><td style="padding:3px 10px;text-align:right">Total revenue (ex VAT):</td><td style="padding:3px 10px;text-align:right">' . money($t['revenue_ex']) . '</td></tr>'
        . '<tr><td style="padding:3px 10px;text-align:right">Total cost (ex VAT):</td><td style="padding:3px 10px;text-align:right">' . money($t['cost_ex']) . '</td></tr>'
        . '<tr><td style="padding:3px 10px;text-align:right">Profit (ex VAT):</td><td style="padding:3px 10px;text-align:right">' . money($t['profit']) . '</td></tr>'
        . '<tr style="font-size:1.15em;font-weight:800;color:#0a7d3b">'
        . '<td style="padding:6px 10px;text-align:right;border-top:2px solid #1a2233">Your commission (' . round($rate * 100) . '%):</td>'
        . '<td style="padding:6px 10px;text-align:right;border-top:2px solid #1a2233">' . money($commission) . '</td></tr>'
        . '</table>'
        . '<p style="color:#888;font-size:12px;margin-top:22px">Automated report from shop.pconestop.co.za. '
        . 'Commission rate ' . round($rate * 100) . '%. Sent on the 1st of each month for the month just ended.</p>'
        . '</div>';
}

/**
 * Send the PARTNER commission report (build owner's income) for a month to the
 * partner's chosen address. Returns a small status array for logging.
 */
function send_commission_report(string $ym): array
{
    $to   = trim((string)setting('commission_report_email', ''));
    $name = trim((string)setting('commission_report_name', ''));
    if ($to === '') {
        return ['sent' => false, 'reason' => 'no commission_report_email configured'];
    }
    $rows = commission_month_rows($ym);
    $body = render_commission_email($ym, $rows, commission_rate(), $name);
    $label = date('F Y', strtotime($ym . '-01'));
    dispatch_mail($to, 'Commission report — ' . $label . ' — PC One Stop', $body);
    return ['sent' => true, 'to' => $to, 'month' => $ym, 'rows' => count($rows)];
}

/**
 * OWNER (shop owner) monthly statement: what sold, the profit, and the
 * commission the owner owes the partner — framed so the owner understands the
 * invoice they will receive. Shows their side (60%), not the partner's income.
 */
function render_owner_statement_email(string $ym, array $rows, float $rate, string $ownerName = ''): string
{
    $label = date('F Y', strtotime($ym . '-01'));
    $t = commission_totals($rows);
    $commission = round($t['profit'] * $rate, 2);
    $ownerShare = round($t['profit'] - $commission, 2);
    $salesIncl  = 0.0;
    foreach ($rows as $r) { $salesIncl += (float)$r['revenue_incl']; }

    $body = '';
    foreach ($rows as $r) {
        $profit = (float)$r['revenue_ex'] - (float)$r['cost_ex'];
        $body .= '<tr>'
            . '<td style="padding:6px 8px;border-bottom:1px solid #eee">' . e($r['name']) . '<br><span style="color:#999;font-family:monospace;font-size:12px">' . e($r['sku']) . '</span></td>'
            . '<td style="padding:6px 8px;border-bottom:1px solid #eee;text-align:center">' . (int)$r['units'] . '</td>'
            . '<td style="padding:6px 8px;border-bottom:1px solid #eee;text-align:right">' . money((float)$r['revenue_incl']) . '</td>'
            . '<td style="padding:6px 8px;border-bottom:1px solid #eee;text-align:right">' . money((float)$r['revenue_ex']) . '</td>'
            . '<td style="padding:6px 8px;border-bottom:1px solid #eee;text-align:right">' . money((float)$r['cost_ex']) . '</td>'
            . '<td style="padding:6px 8px;border-bottom:1px solid #eee;text-align:right">' . money($profit) . '</td>'
            . '<td style="padding:6px 8px;border-bottom:1px solid #eee;text-align:right;font-weight:700;color:#c0392b">' . money($profit * $rate) . '</td>'
            . '</tr>';
    }
    if (!$rows) {
        $body = '<tr><td colspan="7" style="padding:12px;text-align:center;color:#888">No paid sales in ' . e($label) . '.</td></tr>';
    }

    $greeting = $ownerName !== '' ? 'Hi ' . e($ownerName) . ',' : 'Hi,';

    return '<div style="font-family:Arial,sans-serif;max-width:720px;margin:auto;color:#1a2233">'
        . '<h2 style="color:#0f8fbf;margin-bottom:2px">PC One Stop — Monthly Sales &amp; Commission Statement</h2>'
        . '<h3 style="margin-top:0">' . e($label) . '</h3>'
        . '<p>' . $greeting . '<br>Here is your sales breakdown for ' . e($label)
        . ' and the commission due, so it all lines up with the invoice you receive from me.</p>'

        . '<div style="display:flex;gap:12px;flex-wrap:wrap;margin:16px 0">'
        . '<div style="flex:1;min-width:150px;background:#f4f7fb;border-radius:8px;padding:12px 14px">'
        . '<div style="font-size:12px;text-transform:uppercase;letter-spacing:.5px;color:#666">Total sales (incl VAT)</div>'
        . '<div style="font-size:20px;font-weight:800">' . money($salesIncl) . '</div></div>'
        . '<div style="flex:1;min-width:150px;background:#f4f7fb;border-radius:8px;padding:12px 14px">'
        . '<div style="font-size:12px;text-transform:uppercase;letter-spacing:.5px;color:#666">Gross profit (ex VAT)</div>'
        . '<div style="font-size:20px;font-weight:800">' . money($t['profit']) . '</div></div>'
        . '<div style="flex:1;min-width:150px;background:#fdecea;border-radius:8px;padding:12px 14px">'
        . '<div style="font-size:12px;text-transform:uppercase;letter-spacing:.5px;color:#c0392b">Commission due (' . round($rate * 100) . '%)</div>'
        . '<div style="font-size:20px;font-weight:800;color:#c0392b">' . money($commission) . '</div></div>'
        . '</div>'

        . '<table style="width:100%;border-collapse:collapse;margin:8px 0;font-size:14px">'
        . '<thead><tr>'
        . '<th style="text-align:left;padding:6px 8px;border-bottom:2px solid #ddd">Product</th>'
        . '<th style="padding:6px 8px;border-bottom:2px solid #ddd">Qty</th>'
        . '<th style="text-align:right;padding:6px 8px;border-bottom:2px solid #ddd">Sold (incl VAT)</th>'
        . '<th style="text-align:right;padding:6px 8px;border-bottom:2px solid #ddd">Net (ex VAT)</th>'
        . '<th style="text-align:right;padding:6px 8px;border-bottom:2px solid #ddd">Cost (ex VAT)</th>'
        . '<th style="text-align:right;padding:6px 8px;border-bottom:2px solid #ddd">Profit</th>'
        . '<th style="text-align:right;padding:6px 8px;border-bottom:2px solid #ddd">Commission ' . round($rate * 100) . '%</th>'
        . '</tr></thead><tbody>' . $body . '</tbody></table>'

        . '<table style="margin-left:auto;font-size:15px;margin-top:8px">'
        . '<tr><td style="padding:3px 10px;text-align:right">Total net sales (ex VAT):</td><td style="padding:3px 10px;text-align:right">' . money($t['revenue_ex']) . '</td></tr>'
        . '<tr><td style="padding:3px 10px;text-align:right">Total supplier cost (ex VAT):</td><td style="padding:3px 10px;text-align:right">' . money($t['cost_ex']) . '</td></tr>'
        . '<tr><td style="padding:3px 10px;text-align:right">Gross profit (ex VAT):</td><td style="padding:3px 10px;text-align:right">' . money($t['profit']) . '</td></tr>'
        . '<tr style="color:#c0392b;font-weight:800"><td style="padding:4px 10px;text-align:right">Commission due (' . round($rate * 100) . '%):</td><td style="padding:4px 10px;text-align:right">' . money($commission) . '</td></tr>'
        . '<tr style="font-size:1.1em;font-weight:800;color:#0a7d3b"><td style="padding:6px 10px;text-align:right;border-top:2px solid #1a2233">Your share after commission:</td><td style="padding:6px 10px;text-align:right;border-top:2px solid #1a2233">' . money($ownerShare) . '</td></tr>'
        . '</table>'

        . '<p style="color:#666;font-size:13px;margin-top:20px">Commission is <strong>' . round($rate * 100) . '% of gross profit</strong> — your selling price excl VAT, less the supplier cost excl VAT. '
        . 'Courier/delivery fees are excluded (they are passed through at cost). Only paid orders are counted. '
        . 'This statement matches the invoice you will receive.</p>'
        . '<p style="color:#999;font-size:12px">Automated statement from shop.pconestop.co.za, sent on the 1st of each month for the month just ended.</p>'
        . '</div>';
}

/** Send the OWNER statement for a month to the owner's chosen address. */
function send_owner_statement(string $ym): array
{
    $to   = trim((string)setting('owner_report_email', ''));
    $name = trim((string)setting('owner_report_name', ''));
    if ($to === '') {
        return ['sent' => false, 'reason' => 'no owner_report_email configured'];
    }
    $rows = commission_month_rows($ym);
    $body = render_owner_statement_email($ym, $rows, commission_rate(), $name);
    $label = date('F Y', strtotime($ym . '-01'));
    dispatch_mail($to, 'Your PC One Stop sales & commission statement — ' . $label, $body);
    return ['sent' => true, 'to' => $to, 'month' => $ym, 'rows' => count($rows)];
}

/** Send both monthly reports (partner income + owner statement). For the cron. */
function send_monthly_commission_reports(string $ym): array
{
    return [
        'partner' => send_commission_report($ym),
        'owner'   => send_owner_statement($ym),
    ];
}
