<?php
require_once __DIR__ . '/_bootstrap.php';
require_partner(); // build owner only — commission & profit are private to the partner

/*
 * Profit split report.
 * Profit is calculated EX VAT per line item:
 *   sell ex VAT  = line_total / VAT_MULTIPLIER
 *   cost ex VAT  = cost_price (feed dealer price, snapshotted at sale) * qty
 *   profit       = sell ex VAT - cost ex VAT
 * Commission = profit * commission_rate_pct; owner keeps the rest.
 * Only PAID orders count. Shipping fees are excluded (cost recovery, not product profit).
 */

/* One-time migration: snapshot column on order_items (safe on repeat visits). */
$hasCol = db()->query("SHOW COLUMNS FROM order_items LIKE 'cost_price'")->fetch();
if (!$hasCol) {
    db()->exec("ALTER TABLE order_items ADD COLUMN cost_price DECIMAL(12,2) NOT NULL DEFAULT 0.00 AFTER unit_price");
    // Backfill older rows from current product costs (best available estimate)
    db()->exec("UPDATE order_items oi JOIN products p ON p.id = oi.product_id
                SET oi.cost_price = p.cost_price WHERE oi.cost_price = 0");
    flash('Added cost snapshot column to order_items and backfilled existing rows.', 'info');
}

/* Partner updates their own commission rate here (owner never sees this control). */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'set_rate' && csrf_check()) {
    $newRate = (float)($_POST['commission_rate_pct'] ?? 40);
    $newRate = max(0, min(100, $newRate));
    db()->prepare('INSERT INTO settings (skey, svalue) VALUES ("commission_rate_pct", ?)
                   ON DUPLICATE KEY UPDATE svalue = VALUES(svalue)')
        ->execute([(string)$newRate]);
    flash('Commission rate updated to ' . rtrim(rtrim(number_format($newRate, 2), '0'), '.') . '%.', 'success');
    redirect('admin/profit.php' . (!empty($_POST['m']) ? '?m=' . urlencode((string)$_POST['m']) : ''));
}

$rate = (float)setting('commission_rate_pct', '40') / 100;
$vat  = VAT_MULTIPLIER;

/* ---- Month-by-month summary (paid orders only) ---- */
$summaryStmt = db()->prepare(
    "SELECT DATE_FORMAT(o.created_at, '%Y-%m') AS ym,
            COUNT(DISTINCT o.id)               AS orders,
            SUM(oi.quantity)                   AS units,
            SUM(oi.line_total)                 AS revenue_incl,
            SUM(oi.line_total) / ?             AS revenue_ex,
            SUM(oi.cost_price * oi.quantity)   AS cost_ex
     FROM orders o
     JOIN order_items oi ON oi.order_id = o.id
     WHERE o.payment_status = 'paid'
     GROUP BY ym
     ORDER BY ym DESC"
);
$summaryStmt->execute([$vat]);
$months = $summaryStmt->fetchAll();

/* ---- Selected month detail, grouped by product ---- */
$month = (string)($_GET['m'] ?? '');
if (!preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $month)) {
    $month = $months[0]['ym'] ?? date('Y-m');
}

$detailStmt = db()->prepare(
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
$detailStmt->execute([$vat, $month, $vat]);
$rows = $detailStmt->fetchAll();

/* ---- CSV export of the selected month ---- */
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="profit-' . $month . '.csv"');
    $out = fopen('php://output', 'w');
    fputcsv($out, ['SKU', 'Product', 'Units', 'Revenue ex VAT', 'Cost ex VAT', 'Profit ex VAT',
                   'Commission ' . round($rate * 100) . '%', 'Owner ' . round((1 - $rate) * 100) . '%']);
    foreach ($rows as $r) {
        $profit = (float)$r['revenue_ex'] - (float)$r['cost_ex'];
        fputcsv($out, [
            $r['sku'], $r['name'], $r['units'],
            number_format((float)$r['revenue_ex'], 2, '.', ''),
            number_format((float)$r['cost_ex'], 2, '.', ''),
            number_format($profit, 2, '.', ''),
            number_format($profit * $rate, 2, '.', ''),
            number_format($profit * (1 - $rate), 2, '.', ''),
        ]);
    }
    fclose($out);
    exit;
}

$monthLabel = date('F Y', strtotime($month . '-01'));
$adminTitle = 'Profit split';
$adminNav = 'profit';
include __DIR__ . '/_header.php';
?>

<div class="panel" style="margin-bottom:20px;border-left:4px solid var(--green,#0a7d3b)">
    <h2 style="margin-top:0">Your commission rate</h2>
    <p class="muted" style="margin-top:0;font-size:.88rem">Private to this partner login — the shop admin can't see or change it. Applies to the figures below and to CSV exports.</p>
    <form method="post" style="display:flex;align-items:flex-end;gap:12px;flex-wrap:wrap">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="set_rate">
        <input type="hidden" name="m" value="<?= e($month) ?>">
        <div class="field" style="margin:0">
            <label>Commission (%)</label>
            <input name="commission_rate_pct" value="<?= e(rtrim(rtrim(number_format($rate * 100, 2), '0'), '.')) ?>" style="max-width:120px">
        </div>
        <button class="btn" type="submit">Save rate</button>
        <span class="muted" style="font-size:.85rem">You take <strong><?= round($rate * 100) ?>%</strong> · owner keeps <strong><?= round((1 - $rate) * 100) ?>%</strong></span>
    </form>
</div>

<div class="panel" style="margin-bottom:20px">
    <h2 style="margin-top:0">Month by month</h2>
    <?php if (!$months): ?>
        <p class="muted" style="margin:0">No paid orders yet. Once sales come in, each month shows up here automatically.</p>
    <?php else: ?>
    <table class="grid">
        <thead><tr>
            <th>Month</th><th>Orders</th><th>Units</th><th>Revenue ex VAT</th><th>Cost ex VAT</th>
            <th>Profit ex VAT</th><th>Commission (<?= round($rate * 100) ?>%)</th><th>Owner (<?= round((1 - $rate) * 100) ?>%)</th><th></th>
        </tr></thead>
        <tbody>
        <?php foreach ($months as $m):
            $profit = (float)$m['revenue_ex'] - (float)$m['cost_ex']; ?>
            <tr <?= $m['ym'] === $month ? 'style="background:var(--primary-tint, #eef4ff)"' : '' ?>>
                <td><strong><?= e(date('F Y', strtotime($m['ym'] . '-01'))) ?></strong></td>
                <td><?= (int)$m['orders'] ?></td>
                <td><?= (int)$m['units'] ?></td>
                <td><?= money((float)$m['revenue_ex']) ?></td>
                <td><?= money((float)$m['cost_ex']) ?></td>
                <td style="font-weight:700"><?= money($profit) ?></td>
                <td style="font-weight:700;color:var(--green, #0a7d3b)"><?= money($profit * $rate) ?></td>
                <td><?= money($profit * (1 - $rate)) ?></td>
                <td><a class="btn btn-sm btn-ghost" href="<?= e(admin_url('profit.php?m=' . $m['ym'])) ?>">View</a></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <?php endif; ?>
</div>

<div class="toolbar">
    <h2 style="margin:0;font-size:1.05rem">Products sold — <?= e($monthLabel) ?></h2>
    <?php if ($rows): ?>
        <a class="btn btn-sm btn-ghost" href="<?= e(admin_url('profit.php?m=' . $month . '&export=csv')) ?>">⬇ Export CSV</a>
    <?php endif; ?>
</div>

<?php if (!$rows): ?>
    <div class="panel"><p class="muted" style="margin:0">No paid sales in <?= e($monthLabel) ?>.</p></div>
<?php else: ?>
<?php
    $tRev = $tCost = 0.0;
    foreach ($rows as $r) { $tRev += (float)$r['revenue_ex']; $tCost += (float)$r['cost_ex']; }
    $tProfit = $tRev - $tCost;
?>
<table class="grid">
    <thead><tr>
        <th>SKU</th><th>Product</th><th>Units</th><th>Revenue ex VAT</th><th>Cost ex VAT</th>
        <th>Profit ex VAT</th><th>Commission (<?= round($rate * 100) ?>%)</th><th>Owner (<?= round((1 - $rate) * 100) ?>%)</th>
    </tr></thead>
    <tbody>
    <?php foreach ($rows as $r):
        $profit = (float)$r['revenue_ex'] - (float)$r['cost_ex']; ?>
        <tr>
            <td class="muted" style="font-size:.8rem"><?= e($r['sku']) ?></td>
            <td><?= e($r['name']) ?></td>
            <td><?= (int)$r['units'] ?></td>
            <td><?= money((float)$r['revenue_ex']) ?></td>
            <td><?= money((float)$r['cost_ex']) ?></td>
            <td style="font-weight:700"><?= money($profit) ?></td>
            <td style="font-weight:700;color:var(--green, #0a7d3b)"><?= money($profit * $rate) ?></td>
            <td><?= money($profit * (1 - $rate)) ?></td>
        </tr>
    <?php endforeach; ?>
    </tbody>
    <tfoot>
        <tr style="border-top:2px solid var(--line,#ddd);font-weight:700">
            <td colspan="3">Totals</td>
            <td><?= money($tRev) ?></td>
            <td><?= money($tCost) ?></td>
            <td><?= money($tProfit) ?></td>
            <td style="color:var(--green, #0a7d3b)"><?= money($tProfit * $rate) ?></td>
            <td><?= money($tProfit * (1 - $rate)) ?></td>
        </tr>
    </tfoot>
</table>
<?php endif; ?>

<div class="flash flash-info" style="margin-top:18px">
    <strong>How this is calculated:</strong> only <em>paid</em> orders count. Per item:
    sell price ex VAT (sold price ÷ <?= e((string)$vat) ?>) minus the Syntech feed cost at the moment of sale
    (snapshotted, so later feed price changes never affect old months). Shipping fees are excluded.
    Commission rate is set in <a href="<?= e(admin_url('settings.php')) ?>">Settings</a> (currently <?= round($rate * 100) ?>%).
</div>

<?php include __DIR__ . '/_footer.php'; ?>
