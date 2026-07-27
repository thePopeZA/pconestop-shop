<?php
/**
 * Picker publish endpoint. Receives the 10 browser-rendered cards + captions
 * and writes them ATOMICALLY into promo-82f02098/ (stage in a temp dir, then
 * swap so the gallery never shows a half-built pack), then fires the pack-ready
 * email. Auth is a signed token embedded in the picker page (no login) — every
 * request is rejected without a valid, unexpired token.
 *
 *   POST action=begin  token=…                 -> fresh staging dir
 *   POST action=image  token=… name=… image=…  -> stage one PNG (multipart)
 *   POST action=finish token=… captions=…      -> validate, swap live, email
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/config/config.php';
require_once BASE_PATH . '/includes/promo.php';

header('Content-Type: application/json; charset=utf-8');

function pub_fail(string $msg, int $code = 400): never
{
    http_response_code($code);
    echo json_encode(['ok' => false, 'error' => $msg]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    pub_fail('POST only', 405);
}
if (!promo_check_token((string)($_POST['token'] ?? ''))) {
    pub_fail('Invalid or expired token — reload the picker page.', 403);
}

$galleryDir = BASE_PATH . '/promo-82f02098';
$imgDir     = $galleryDir . '/img';
$stageDir   = $galleryDir . '/.staging';

function pub_valid_name(string $name): bool
{
    return (bool)preg_match('/^pcos-status-\d{2,3}-[a-z0-9-]+\.png$/', $name);
}

$action = $_POST['action'] ?? '';

if ($action === 'begin') {
    // Fresh staging dir.
    if (is_dir($stageDir)) {
        foreach (glob($stageDir . '/*') ?: [] as $f) { @unlink($f); }
        @rmdir($stageDir);
    }
    if (!@mkdir($stageDir, 0775, true) && !is_dir($stageDir)) {
        pub_fail('Cannot create staging dir (permissions?).', 500);
    }
    echo json_encode(['ok' => true]);
    exit;
}

if ($action === 'image') {
    if (!is_dir($stageDir)) {
        pub_fail('No staging dir — call begin first.');
    }
    $name = basename((string)($_POST['name'] ?? ''));
    if (!pub_valid_name($name)) {
        pub_fail('Rejected filename: ' . $name);
    }
    if (!isset($_FILES['image']) || !is_uploaded_file($_FILES['image']['tmp_name'] ?? '')) {
        pub_fail('No image for ' . $name);
    }
    $size = (int)($_FILES['image']['size'] ?? 0);
    if ($size < 100 || $size > 4_000_000) {
        pub_fail('Image size out of range for ' . $name);
    }
    if (strncmp((string)file_get_contents($_FILES['image']['tmp_name'], false, null, 0, 8), "\x89PNG\r\n\x1a\n", 8) !== 0) {
        pub_fail('Not a PNG: ' . $name);
    }
    if (!move_uploaded_file($_FILES['image']['tmp_name'], $stageDir . '/' . $name)) {
        pub_fail('Could not stage ' . $name, 500);
    }
    echo json_encode(['ok' => true, 'staged' => $name]);
    exit;
}

if ($action === 'finish') {
    if (!is_dir($stageDir)) {
        pub_fail('No staging dir — nothing to publish.');
    }
    $staged = array_map('basename', glob($stageDir . '/*.png') ?: []);
    if (!$staged) {
        pub_fail('No staged images to publish.');
    }
    // Captions, keyed by staged filename.
    $map = json_decode((string)($_POST['captions'] ?? ''), true);
    if (!is_array($map)) {
        pub_fail('Invalid captions payload.');
    }
    $captions = [];
    foreach ($staged as $file) {
        if (isset($map[$file])) {
            $captions[$file] = (string)$map[$file];
        }
    }
    if (count($captions) !== count($staged)) {
        pub_fail('Captions do not match staged images.');
    }

    // ---- Atomic swap: replace img/ wholesale, then write captions.json last ----
    $oldDir = $galleryDir . '/.img-old-' . bin2hex(random_bytes(4));
    if (is_dir($imgDir) && !@rename($imgDir, $oldDir)) {
        pub_fail('Swap failed (could not move current img).', 500);
    }
    if (!@rename($stageDir, $imgDir)) {
        // try to roll back
        if (isset($oldDir) && is_dir($oldDir)) { @rename($oldDir, $imgDir); }
        pub_fail('Swap failed (could not move staged pack).', 500);
    }
    // Keep .gitkeep so the tracked folder marker survives.
    @file_put_contents($imgDir . '/.gitkeep', '');

    // Write captions.json atomically (this is the visible switch for the gallery).
    $json = json_encode($captions, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    $tmp = $galleryDir . '/captions.json.tmp';
    if (@file_put_contents($tmp, $json) === false || !@rename($tmp, $galleryDir . '/captions.json')) {
        pub_fail('Could not write captions.json', 500);
    }

    // Clean up the previous pack (scandir so dotfiles like .gitkeep are removed
    // too, otherwise the old dir can't be rmdir'd and leaks one dir per publish).
    if (isset($oldDir) && is_dir($oldDir)) {
        foreach (scandir($oldDir) ?: [] as $f) {
            if ($f !== '.' && $f !== '..') { @unlink($oldDir . '/' . $f); }
        }
        @rmdir($oldDir);
    }

    // Fire the pack-ready email (real on prod, dry-run elsewhere).
    $mail = promo_send_pack_ready(count($captions));

    echo json_encode([
        'ok'      => true,
        'count'   => count($captions),
        'gallery' => PROMO_PUBLIC_BASE . '/promo-82f02098/',
        'mail'    => $mail,
    ]);
    exit;
}

pub_fail('Unknown action.');
