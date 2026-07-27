<?php
/**
 * Morning promo picker invite — "Today's deals are in, pick 10 to post".
 * Emails the picker link to PROMO_RECIPIENTS at 07:00 Africa/Johannesburg,
 * regardless of the server's timezone.
 *
 * How the 07:00 SAST guarantee works without knowing the server TZ:
 *   - config.php forces date_default_timezone_set('Africa/Johannesburg'), so all
 *     time checks below are in SAST no matter where the server clock sits.
 *   - Run this HOURLY from cron; it only sends when SAST time >= 07:00 and it
 *     hasn't already sent today (a lock file in storage/cache). So it fires once,
 *     right after 07:00 SAST, whatever the server timezone.
 *
 * Manual trigger (for testing) — respects the dry-run guard off prod:
 *   https://shop.pconestop.co.za/cron/daily-promo-email.php?key=a58e6ecf54427aac
 *
 * cPanel cron (hourly): see cron/crontab.txt.
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/config/config.php'; // forces TZ = Africa/Johannesburg
require_once BASE_PATH . '/includes/promo.php';

const MORNING_KEY = 'a58e6ecf54427aac';

$isCli  = PHP_SAPI === 'cli';
$manual = false;

if (!$isCli) {
    header('Content-Type: text/plain; charset=utf-8');
    if (!hash_equals(MORNING_KEY, (string)($_GET['key'] ?? ''))) {
        http_response_code(403);
        echo "403 Forbidden\n";
        exit;
    }
    $manual = true; // web + key = manual test send (ignores time + lock)
}

$sastDate = date('Y-m-d');
$sastHour = (int)date('G');
$lockFile = BASE_PATH . '/storage/cache/promo-picker-email-sent.txt';
$lastSent = is_file($lockFile) ? trim((string)file_get_contents($lockFile)) : '';

$out = static function (string $m): void { echo $m . "\n"; };

$out('Effective TZ:  ' . date_default_timezone_get());
$out('SAST now:      ' . date('Y-m-d H:i'));
$out('Last sent:     ' . ($lastSent !== '' ? $lastSent : '(never)'));
$out('Mode:          ' . ($manual ? 'manual (key)' : 'cron'));

if (!$manual) {
    if ($sastHour < 7) {
        $out('Result:        skipped — before 07:00 SAST');
        exit;
    }
    if ($lastSent === $sastDate) {
        $out('Result:        skipped — already sent today');
        exit;
    }
}

$res = promo_send_picker_invite();

// In cron mode, record today's date so it can't double-send (even on dry-run).
if (!$manual) {
    @file_put_contents($lockFile, $sastDate);
}

if (!empty($res['dry_run'])) {
    $out('Result:        DRY-RUN (not prod) — no mail sent');
    $out('Would email:   ' . implode(', ', $res['recipients']));
    $out('Summary:       ' . $res['summary']);
} else {
    $out('Result:        SENT (' . ($res['dispatched'] ?? 0) . ' dispatched)');
    $out('Recipients:    ' . implode(', ', $res['recipients']));
}
