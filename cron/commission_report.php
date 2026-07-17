<?php
/**
 * Monthly commission report — run on the 1st of each month.
 *
 *   php cron/commission_report.php            # previous month (default)
 *   php cron/commission_report.php 2026-06    # a specific month
 *
 * Emails two monthly reports for the month just ended:
 *   1. Partner income report  -> commission_report_email
 *   2. Owner sales & commission statement -> owner_report_email
 * Both recipients are configured on the admin Profit split page. Each is a
 * silent no-op if its address is unset. See cron/crontab.txt.
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

$res = send_monthly_commission_reports($ym);
echo '[' . date('Y-m-d H:i:s') . "] commission reports $ym: " . json_encode($res) . "\n";
exit(0); // a missing recipient is a no-op, not a failure
