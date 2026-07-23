<?php
/**
 * Promo publish endpoint (owner + partner only).
 * Receives the browser-rendered promo pack and writes it into the live gallery
 * (promo-82f02098/) — no git, no cPanel pull. Called in three steps by
 * admin/promos.php:
 *   POST action=begin              -> wipe the old pack
 *   POST action=image  (xN)        -> save one PNG (multipart file 'image')
 *   POST action=finish captions=…  -> write captions.json, return the count
 *
 * Every request is CSRF-checked and role-gated. Filenames are whitelisted and
 * uploads must be real PNGs, so nothing arbitrary can be written to the docroot.
 */

declare(strict_types=1);

require_once __DIR__ . '/_bootstrap.php';
require_promo_access();

header('Content-Type: application/json; charset=utf-8');

function pp_fail(string $msg, int $code = 400): never
{
    http_response_code($code);
    echo json_encode(['ok' => false, 'error' => $msg]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !csrf_check()) {
    pp_fail('Bad request (session expired?). Reload and try again.', 403);
}

$galleryDir = BASE_PATH . '/promo-82f02098';
$imgDir     = $galleryDir . '/img';
if (!is_dir($imgDir)) {
    @mkdir($imgDir, 0775, true);
}
if (!is_writable($imgDir)) {
    pp_fail('The gallery folder is not writable on the server (promo-82f02098/img).', 500);
}

$action = $_POST['action'] ?? '';

/** Valid published filename: pcos-status-01-<slug>.png */
function pp_valid_name(string $name): bool
{
    return (bool)preg_match('/^pcos-status-\d{2,3}-[a-z0-9-]+\.png$/', $name);
}

if ($action === 'begin') {
    foreach (glob($imgDir . '/*.png') ?: [] as $f) {
        @unlink($f);
    }
    @unlink($galleryDir . '/captions.json');
    echo json_encode(['ok' => true]);
    exit;
}

if ($action === 'image') {
    $name = basename((string)($_POST['name'] ?? ''));
    if (!pp_valid_name($name)) {
        pp_fail('Rejected filename: ' . $name);
    }
    if (!isset($_FILES['image']) || !is_uploaded_file($_FILES['image']['tmp_name'] ?? '')) {
        pp_fail('No image received for ' . $name);
    }
    $tmp  = $_FILES['image']['tmp_name'];
    $size = (int)($_FILES['image']['size'] ?? 0);
    if ($size < 100 || $size > 4_000_000) {
        pp_fail('Image size out of range for ' . $name);
    }
    // Must be a real PNG (magic bytes).
    $head = (string)file_get_contents($tmp, false, null, 0, 8);
    if (strncmp($head, "\x89PNG\r\n\x1a\n", 8) !== 0) {
        pp_fail('Not a PNG: ' . $name);
    }
    if (!move_uploaded_file($tmp, $imgDir . '/' . $name)) {
        pp_fail('Could not save ' . $name, 500);
    }
    echo json_encode(['ok' => true, 'saved' => $name]);
    exit;
}

if ($action === 'finish') {
    $raw = (string)($_POST['captions'] ?? '');
    $map = json_decode($raw, true);
    if (!is_array($map)) {
        pp_fail('Invalid captions payload.');
    }
    $clean = [];
    foreach ($map as $file => $caption) {
        $file = basename((string)$file);
        if (pp_valid_name($file) && is_file($imgDir . '/' . $file)) {
            $clean[$file] = (string)$caption;
        }
    }
    if (!$clean) {
        pp_fail('No captions matched published images.');
    }
    $json = json_encode($clean, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    if (@file_put_contents($galleryDir . '/captions.json', $json) === false) {
        pp_fail('Could not write captions.json', 500);
    }
    echo json_encode(['ok' => true, 'count' => count($clean)]);
    exit;
}

pp_fail('Unknown action.');
