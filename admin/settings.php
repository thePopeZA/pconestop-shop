<?php
require_once __DIR__ . '/_bootstrap.php';
require_admin();

$keys = ['markup_multiplier','vat_multiplier','shipping_flat','shipping_free_over','store_tagline'];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && csrf_check()) {
    $stmt = db()->prepare('INSERT INTO settings (skey, svalue) VALUES (?,?) ON DUPLICATE KEY UPDATE svalue = VALUES(svalue)');
    foreach ($keys as $k) {
        if (isset($_POST[$k])) {
            $stmt->execute([$k, trim((string)$_POST[$k])]);
        }
    }
    flash('Settings saved. Note: pricing multipliers also live in .env and apply on the next feed import.', 'success');
    redirect('admin/settings.php');
}

$vals = [];
foreach (db()->query('SELECT skey, svalue FROM settings') as $r) {
    $vals[$r['skey']] = $r['svalue'];
}

$adminTitle = 'Settings';
$adminNav = 'settings';
include __DIR__ . '/_header.php';
?>
<div class="panel" style="max-width:620px">
    <h2>Store settings</h2>
    <form method="post">
        <?= csrf_field() ?>
        <div class="field">
            <label>Markup multiplier</label>
            <input name="markup_multiplier" value="<?= e($vals['markup_multiplier'] ?? '1.25') ?>">
            <small class="muted">Sell price = feed cost × markup × VAT. Currently: cost × <?= e($vals['markup_multiplier'] ?? '1.25') ?> × <?= e($vals['vat_multiplier'] ?? '1.15') ?></small>
        </div>
        <div class="field">
            <label>VAT multiplier</label>
            <input name="vat_multiplier" value="<?= e($vals['vat_multiplier'] ?? '1.15') ?>">
        </div>
        <div class="field">
            <label>Flat shipping (R)</label>
            <input name="shipping_flat" value="<?= e($vals['shipping_flat'] ?? '99.00') ?>">
        </div>
        <div class="field">
            <label>Free shipping over (R)</label>
            <input name="shipping_free_over" value="<?= e($vals['shipping_free_over'] ?? '1000.00') ?>">
        </div>
        <div class="field">
            <label>Store tagline</label>
            <input name="store_tagline" value="<?= e($vals['store_tagline'] ?? '') ?>">
        </div>
        <button class="btn" type="submit">Save settings</button>
    </form>
    <div class="flash flash-info" style="margin-top:18px">
        <strong>Important:</strong> Pricing and shipping are read from <code>.env</code> at runtime. To change them live, update the values in your <code>.env</code> file. These DB settings are a convenience mirror. After changing markup/VAT, run a <a href="<?= e(admin_url('feeds.php')) ?>">full feed sync</a> to recalculate all product prices.
    </div>
</div>
<?php include __DIR__ . '/_footer.php'; ?>
