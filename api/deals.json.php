<?php
/**
 * Read-only public deals/arrivals feed for the WhatsApp promo system.
 * Output is cached ~15 minutes to storage/cache/. Exposes only fields the
 * public shop pages already show.
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/config/config.php';
require_once BASE_PATH . '/includes/promo.php';

const DEALS_CACHE_TTL = 900; // 15 minutes

$cacheFile = BASE_PATH . '/storage/cache/deals-feed.json';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: public, max-age=' . DEALS_CACHE_TTL);

// Serve fresh cache if within TTL.
if (is_file($cacheFile) && (time() - filemtime($cacheFile)) < DEALS_CACHE_TTL) {
    header('X-Deals-Cache: hit');
    readfile($cacheFile);
    exit;
}

try {
    $feed = promo_feed();
    $payload = [
        'generated_at' => date('c'),
        'currency'     => 'ZAR',
        'counts'       => [
            'deals'    => count($feed['deals']),
            'arrivals' => count($feed['arrivals']),
        ],
        'deals'    => $feed['deals'],
        'arrivals' => $feed['arrivals'],
    ];
    $json = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

    // Best-effort cache write (atomic).
    $dir = dirname($cacheFile);
    if (!is_dir($dir)) {
        @mkdir($dir, 0775, true);
    }
    $tmp = $cacheFile . '.' . getmypid() . '.tmp';
    if (@file_put_contents($tmp, $json) !== false) {
        @rename($tmp, $cacheFile);
    }

    header('X-Deals-Cache: miss');
    echo $json;
} catch (Throwable $e) {
    // Fall back to stale cache if the DB hiccups; otherwise report a clean error.
    if (is_file($cacheFile)) {
        header('X-Deals-Cache: stale');
        readfile($cacheFile);
        exit;
    }
    http_response_code(500);
    error_log('deals.json.php: ' . $e->getMessage());
    echo json_encode(['error' => 'Unable to build the deals feed right now.']);
}
