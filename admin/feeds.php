<?php
require_once __DIR__ . '/_bootstrap.php';
require_admin();
require_once BASE_PATH . '/lib/FeedImporter.php';

$result = null;
$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && csrf_check()) {
    $mode = $_POST['mode'] ?? 'update';
    $isFull = $mode === 'full';
    $url = $isFull ? (string)env('SYNTECH_FEED_XML_FULL', '') : (string)env('SYNTECH_FEED_XML_UPDATE', '');
    if ($url === '') {
        $error = 'Feed URL not configured in .env.';
    } else {
        @set_time_limit(0);
        ignore_user_abort(true);
        try {
            $importer = new FeedImporter(db());
            $result = $importer->run($url, 'xml', $isFull);
            flash("Feed '{$mode}' completed: {$result['added']} added, {$result['updated']} updated, {$result['deactivated']} deactivated.", 'success');
        } catch (Throwable $e) {
            $error = $e->getMessage();
            flash('Feed import failed: ' . $e->getMessage(), 'error');
        }
        redirect('admin/feeds.php');
    }
}

$logs = db()->query('SELECT * FROM feed_log ORDER BY id DESC LIMIT 20')->fetchAll();

$adminTitle = 'Feed sync';
$adminNav = 'feeds';
include __DIR__ . '/_header.php';
?>
<?php if ($error): ?><div class="flash flash-error"><?= e($error) ?></div><?php endif; ?>

<div class="panel">
    <h2>Sync products from Syntech</h2>
    <p class="muted">A <strong>Full sync</strong> imports the entire catalogue and deactivates products no longer in the feed (~30s for 2 500+ items). An <strong>Update sync</strong> is faster and refreshes stock &amp; prices only.</p>
    <form method="post" style="display:flex;gap:10px;flex-wrap:wrap;align-items:center">
        <?= csrf_field() ?>
        <button class="btn" name="mode" value="full" onclick="return confirm('Run a FULL catalogue sync now? This can take ~30 seconds.')">🔄 Run full sync</button>
        <button class="btn btn-ghost" name="mode" value="update">⚡ Run update sync</button>
    </form>
    <p class="muted" style="font-size:.8rem;margin-top:12px">
        Tip: set up cron so this runs automatically — see <code>cron/crontab.txt</code>. Recommended: full sync every 3 hours, update sync hourly.
    </p>
</div>

<div class="panel">
    <h2>Recent sync history</h2>
    <table class="grid">
        <thead><tr><th>Started</th><th>Type</th><th>Status</th><th>Seen</th><th>Added</th><th>Updated</th><th>Deactivated</th><th>Duration</th></tr></thead>
        <tbody>
        <?php foreach ($logs as $l):
            $sc = $l['status']==='success'?'b-green':($l['status']==='failed'?'b-red':'b-amber');
            $dur = $l['finished_at'] ? (strtotime($l['finished_at']) - strtotime($l['started_at'])) . 's' : '—'; ?>
            <tr>
                <td><?= e(date('d M H:i', strtotime($l['started_at']))) ?></td>
                <td><?= e(ucfirst($l['feed_type'])) ?></td>
                <td><span class="badge <?= $sc ?>"><?= e(ucfirst($l['status'])) ?></span></td>
                <td><?= (int)$l['products_seen'] ?></td>
                <td><?= (int)$l['products_added'] ?></td>
                <td><?= (int)$l['products_updated'] ?></td>
                <td><?= (int)$l['products_deactivated'] ?></td>
                <td class="muted"><?= $dur ?></td>
            </tr>
        <?php endforeach; ?>
        <?php if (!$logs): ?><tr><td colspan="8" class="muted">No sync history yet.</td></tr><?php endif; ?>
        </tbody>
    </table>
</div>
<?php include __DIR__ . '/_footer.php'; ?>
