<?php
/**
 * Deployment version check. Open https://shop.pconestop.co.za/version.php to see
 * exactly which commit is live on this server (reads the checked-out .git), so
 * you can confirm a cPanel "Update from Remote" actually landed the latest push.
 * Reveals only the commit SHA + time — no source. (The .git dir itself is blocked
 * over HTTP by .htaccess; this reads it from the filesystem.)
 */

declare(strict_types=1);

require_once __DIR__ . '/config/config.php';

header('Content-Type: text/plain; charset=utf-8');
header('X-Robots-Tag: noindex');

$gitDir = BASE_PATH . '/.git';

/** Resolve a ref (e.g. refs/heads/main) to its SHA, loose or packed. */
function git_ref_sha(string $gitDir, string $ref): string
{
    $loose = $gitDir . '/' . $ref;
    if (is_file($loose)) {
        return trim((string)file_get_contents($loose));
    }
    foreach (@file($gitDir . '/packed-refs', FILE_IGNORE_NEW_LINES) ?: [] as $line) {
        if ($line === '' || $line[0] === '#' || $line[0] === '^') {
            continue;
        }
        $parts = explode(' ', $line, 2);
        if (count($parts) === 2 && $parts[1] === $ref) {
            return $parts[0];
        }
    }
    return '';
}

if (!is_dir($gitDir)) {
    echo "No .git directory on this server — deployed without Git Version Control?\n";
    exit;
}

$head   = trim((string)@file_get_contents($gitDir . '/HEAD'));
$branch = '(detached)';
$sha    = $head;
if (strpos($head, 'ref:') === 0) {
    $ref    = trim(substr($head, 4));
    $branch = basename($ref);
    $sha    = git_ref_sha($gitDir, $ref);
}

$refFile = $gitDir . '/refs/heads/' . $branch;
$mtime   = is_file($refFile) ? filemtime($refFile)
         : (is_file($gitDir . '/HEAD') ? filemtime($gitDir . '/HEAD') : null);
$when    = $mtime ? date('Y-m-d H:i:s', $mtime) : 'unknown';

echo "PC One Stop — deployed version\n";
echo "------------------------------\n";
echo 'branch:       ' . $branch . "\n";
echo 'commit:       ' . ($sha !== '' ? $sha : 'unknown') . "\n";
echo 'short:        ' . ($sha !== '' ? substr($sha, 0, 7) : 'unknown') . "\n";
echo 'last updated: ' . $when . " (server time)\n";
