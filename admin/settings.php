<?php
require_once __DIR__ . '/_bootstrap.php';
require_admin();

$keys = ['markup_multiplier','vat_multiplier','shipping_flat','shipping_free_over','store_tagline','commission_rate_pct',
         'price_floor_margin_pct','price_cap_margin_pct','price_rrp_nudge_pct'];

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
        <h3 style="margin:0 0 4px">Pricing model (RRP-anchored)</h3>
        <p class="muted" style="font-size:.85rem;margin-top:0">Sell price targets Syntech's RRP, clamped so the ex-VAT margin over feed cost stays between the floor and cap. Run a <a href="<?= e(admin_url('feeds.php')) ?>">full feed sync</a> after changing these to reprice the catalogue.</p>
        <div class="field">
            <label>Minimum margin — floor (%)</label>
            <input name="price_floor_margin_pct" value="<?= e($vals['price_floor_margin_pct'] ?? '15') ?>">
            <small class="muted">We always make at least this % over feed cost (ex VAT), even if that means pricing above RRP.</small>
        </div>
        <div class="field">
            <label>Maximum margin — cap (%)</label>
            <input name="price_cap_margin_pct" value="<?= e($vals['price_cap_margin_pct'] ?? '35') ?>">
            <small class="muted">Never take more than this %, even when RRP allows it — those products end up cheaper than RRP.</small>
        </div>
        <div class="field">
            <label>RRP nudge (%)</label>
            <input name="price_rrp_nudge_pct" value="<?= e($vals['price_rrp_nudge_pct'] ?? '100') ?>">
            <small class="muted">Target price as % of RRP. 100 = price exactly at RRP, 98 = always 2% under RRP.</small>
        </div>
        <div class="field">
            <label>Fallback markup multiplier (products without an RRP)</label>
            <input name="markup_multiplier" value="<?= e($vals['markup_multiplier'] ?? '1.25') ?>">
            <small class="muted">Only used when the feed has no RRP for a product: sell = cost × markup × VAT.</small>
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
        <div class="field">
            <label>Commission rate (%)</label>
            <input name="commission_rate_pct" value="<?= e($vals['commission_rate_pct'] ?? '40') ?>">
            <small class="muted">Your share of profit (ex VAT) on each sale — used by the Profit split report. Owner keeps the rest.</small>
        </div>
        <button class="btn" type="submit">Save settings</button>
    </form>
    <div class="flash flash-info" style="margin-top:18px">
        <strong>Important:</strong> The pricing-model knobs (floor / cap / nudge) are read live from these settings —
        run a <a href="<?= e(admin_url('feeds.php')) ?>">full feed sync</a> after changing them to reprice the whole catalogue.
        VAT, the fallback markup, and shipping are read from <code>.env</code> at runtime; the DB copies here are a convenience mirror.
    </div>
</div>
<?php include __DIR__ . '/_footer.php'; ?>
