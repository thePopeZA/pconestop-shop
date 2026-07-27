<?php
/**
 * Manual pack-ready notifier (re-send the gallery link).
 *   /promo-82f02098/notify.php?key=2ce5b7f83a48a976
 * The daily flow emails automatically on publish; this is a manual re-trigger.
 * Uses the shared PROMO_RECIPIENTS + dry-run guard (only prod sends). 403 without key.
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/config/config.php';
require_once BASE_PATH . '/includes/promo.php';

const NOTIFY_KEY = '2ce5b7f83a48a976';

header('Content-Type: text/plain; charset=utf-8');

if (!hash_equals(NOTIFY_KEY, (string)($_GET['key'] ?? ''))) {
    http_response_code(403);
    echo "403 Forbidden\n";
    exit;
}

// Count published PNGs.
$count = 0;
foreach (glob(__DIR__ . '/img/*.png') ?: [] as $_) {
    $count++;
}

$res = promo_send_pack_ready($count);

echo "Images:     {$count}\n";
echo "Recipients: " . implode(', ', $res['recipients']) . "\n";
if (!empty($res['dry_run'])) {
    echo "Result:     DRY-RUN (not prod) — no mail sent\n";
    echo "Summary:    " . $res['summary'] . "\n";
} else {
    echo "Result:     SENT (" . ($res['dispatched'] ?? 0) . " dispatched)\n";
}
