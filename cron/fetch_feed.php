<?php
/**
 * CLI feed fetcher — run from cron.
 *
 *   php cron/fetch_feed.php full     # full catalog (XML full)
 *   php cron/fetch_feed.php update   # stock/price update (XML update)
 *   php cron/fetch_feed.php full csv # force a format
 *   php cron/fetch_feed.php full file:/path/to/local.xml   # import a local file
 *
 * Cron example (every 3 hours full sync, hourly update) — see cron/crontab.txt
 */

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    // allow web trigger only from admin (handled elsewhere); block direct web access
    http_response_code(403);
    exit('CLI only');
}

require_once dirname(__DIR__) . '/config/config.php';
require_once BASE_PATH . '/lib/FeedImporter.php';

$mode = $argv[1] ?? 'full';
$isFull = ($mode !== 'update');

// Optional override of format or source
$override = $argv[2] ?? null;

$type = 'xml';
if ($isFull) {
    $url = (string)env('SYNTECH_FEED_XML_FULL', '');
} else {
    $url = (string)env('SYNTECH_FEED_XML_UPDATE', '');
}

// Argument overrides
if ($override) {
    if (str_starts_with($override, 'file:')) {
        // local file import for testing
        $local = substr($override, 5);
        $ext = strtolower(pathinfo($local, PATHINFO_EXTENSION));
        $type = in_array($ext, ['csv', 'json'], true) ? $ext : 'xml';
        run_local($local, $type, $isFull);
        exit;
    }
    if (in_array(strtolower($override), ['xml', 'csv', 'json'], true)) {
        $type = strtolower($override);
        // switch URL to matching format if configured
        if ($type === 'csv') {
            $url = (string)env('SYNTECH_FEED_XML_FULL', $url); // CSV full uses same account page; user sets specific var if needed
        }
    }
}

$start = microtime(true);
echo "[" . date('Y-m-d H:i:s') . "] Feed import '$mode' ($type) starting...\n";

if ($url === '') {
    fwrite(STDERR, "ERROR: No feed URL configured. Set SYNTECH_FEED_XML_FULL / SYNTECH_FEED_XML_UPDATE in .env\n");
    exit(2);
}

try {
    $importer = new FeedImporter(db());
    $stats = $importer->run($url, $type, $isFull);
    $secs = round(microtime(true) - $start, 1);
    echo "[" . date('Y-m-d H:i:s') . "] Done in {$secs}s — "
        . "seen {$stats['seen']}, added {$stats['added']}, updated {$stats['updated']}, deactivated {$stats['deactivated']}\n";
    exit(0);
} catch (Throwable $e) {
    fwrite(STDERR, "[" . date('Y-m-d H:i:s') . "] FAILED: " . $e->getMessage() . "\n");
    exit(1);
}

function run_local(string $file, string $type, bool $isFull): void
{
    if (!is_readable($file)) {
        fwrite(STDERR, "ERROR: local file not readable: $file\n");
        exit(2);
    }
    echo "Importing local $type file: $file\n";
    try {
        $importer = new FeedImporter(db());
        // For local file, call run() with a file:// URL so curl reads it.
        $stats = $importer->run('file://' . str_replace('\\', '/', realpath($file)), $type, $isFull);
        echo "Done — seen {$stats['seen']}, added {$stats['added']}, updated {$stats['updated']}, deactivated {$stats['deactivated']}\n";
    } catch (Throwable $e) {
        fwrite(STDERR, "FAILED: " . $e->getMessage() . "\n");
        exit(1);
    }
}
