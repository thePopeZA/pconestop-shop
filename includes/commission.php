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
 * Send the commission report for a month to the partner's chosen address.
 * Returns a small status array for logging.
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
