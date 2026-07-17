<?php
/**
 * Monthly commission report — run on the 1st of each month.
 *
 *   php cron/commission_report.php            # previous month (default)
 *   php cron/commission_report.php 2026-06    # a specific month
 *
 * Emails the partner's commission for the month to the address configured on
 * the admin Profit split page (commission_report_email). Silent no-op if none
 * is set. See cron/crontab.txt.
 */

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('CLI only');
}

require_once dirname(__DIR__) . '/config/config.php';
require_once BASE_PATH . '/includes/commission.php';

$ym = $argv[1] ?? date('Y-m', strtotime('first day of last month'));
if (!preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $ym)) {
    fwrite(STDERR, "Invalid month '$ym' (expected YYYY-MM)\n");
    exit(2);
}

$res = send_commission_report($ym);
echo '[' . date('Y-m-d H:i:s') . "] commission report $ym: " . json_encode($res) . "\n";
exit($res['sent'] ? 0 : 0); // no-op (no recipient) is not a failure
