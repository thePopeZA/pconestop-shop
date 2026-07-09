<?php
/**
 * One-time web installer / setup.
 * Visit:  https://shop.pconestop.co.za/install.php?key=YOUR_ADMIN_SETUP_KEY
 *
 * - Creates all database tables (idempotent) from database/schema.sql
 * - Optionally runs the first full product import
 *
 * DELETE THIS FILE once setup is complete.
 */

declare(strict_types=1);
require_once __DIR__ . '/config/config.php';

$key = (string)($_GET['key'] ?? '');
$setupKey = (string)env('ADMIN_SETUP_KEY', '');
if ($setupKey === '' || !hash_equals($setupKey, $key)) {
    http_response_code(403);
    exit('Forbidden. Append ?key=YOUR_ADMIN_SETUP_KEY (from .env) to the URL.');
}

$action = $_GET['action'] ?? '';
$log = [];
$err = null;

function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES); }

try {
    // Basic DB connectivity
    $pdo = db();

    if ($action === 'schema' || $action === 'all') {
        $sql = file_get_contents(BASE_PATH . '/database/schema.sql');
        if ($sql === false) throw new RuntimeException('Cannot read database/schema.sql');
        $pdo->exec($sql);
        $log[] = 'Schema imported (tables created).';
    }

    if ($action === 'import' || $action === 'all') {
        require_once BASE_PATH . '/lib/FeedImporter.php';
        @set_time_limit(0);
        $url = (string)env('SYNTECH_FEED_XML_FULL', '');
        if ($url === '') throw new RuntimeException('SYNTECH_FEED_XML_FULL not set in .env');
        $importer = new FeedImporter($pdo);
        $stats = $importer->run($url, 'xml', true);
        $log[] = "Feed imported: {$stats['added']} added, {$stats['updated']} updated.";
    }

    // Current status
    $tables = [];
    foreach (['products','categories','orders','admin_users','settings'] as $t) {
        try { $tables[$t] = (int)$pdo->query("SELECT COUNT(*) FROM $t")->fetchColumn(); }
        catch (Throwable $e) { $tables[$t] = 'missing'; }
    }
} catch (Throwable $e) {
    $err = $e->getMessage();
}
?><!doctype html>
<html><head><meta charset="utf-8"><title>PC One Stop — Installer</title>
<style>body{font-family:system-ui,Arial,sans-serif;max-width:680px;margin:40px auto;padding:0 20px;color:#222}
.box{border:1px solid #ddd;border-radius:10px;padding:20px;margin:14px 0}
.ok{color:#17935b}.bad{color:#c1362f}
a.btn{display:inline-block;background:#0f8fbf;color:#fff;padding:10px 16px;border-radius:8px;text-decoration:none;margin:4px 6px 4px 0}
code{background:#f2f2f2;padding:2px 5px;border-radius:4px}</style></head><body>
<h1>PC One Stop — Installer</h1>
<?php if ($err): ?>
    <div class="box"><strong class="bad">Error:</strong> <?= h($err) ?></div>
<?php endif; ?>
<?php if ($log): ?>
    <div class="box"><?php foreach ($log as $l): ?><div class="ok">✓ <?= h($l) ?></div><?php endforeach; ?></div>
<?php endif; ?>

<div class="box">
    <h3>Database status</h3>
    <?php if (!empty($tables)): foreach ($tables as $t => $c): ?>
        <div><code><?= h($t) ?></code>: <?= is_int($c) ? h($c) . ' rows' : '<span class="bad">'.h($c).'</span>' ?></div>
    <?php endforeach; endif; ?>
</div>

<div class="box">
    <h3>Steps</h3>
    <p>1. Create the database tables:</p>
    <a class="btn" href="?key=<?= h($key) ?>&action=schema">Create tables</a>
    <p>2. Import the product catalogue (takes ~30–60s):</p>
    <a class="btn" href="?key=<?= h($key) ?>&action=import">Import products</a>
    <p>Or do both at once:</p>
    <a class="btn" href="?key=<?= h($key) ?>&action=all">Run full setup</a>
</div>

<div class="box">
    <p><strong>When products show above:</strong></p>
    <ol>
        <li>Create your admin at <code>/admin/login.php</code> (uses the setup key).</li>
        <li>Add the cron jobs (see <code>cron/crontab.txt</code>).</li>
        <li><strong class="bad">Delete this install.php file.</strong></li>
    </ol>
</div>
</body></html>
