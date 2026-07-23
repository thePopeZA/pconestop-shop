<?php
/**
 * Whitelisted image proxy with disk cache.
 * Serves remote product images SAME-ORIGIN so html2canvas can export cards
 * without tainting the canvas. Only images on syntech.co.za and
 * pconestop.co.za (and subdomains) are allowed — nothing else.
 *
 *   /api/img-proxy.php?url=<urlencoded absolute image URL>
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/config/config.php';

const IMG_PROXY_ALLOWED_HOSTS = ['syntech.co.za', 'pconestop.co.za'];
const IMG_PROXY_TTL = 604800; // 7 days

$raw = (string)($_GET['url'] ?? '');
if ($raw === '') {
    http_response_code(400);
    exit('Missing url');
}

$parts = parse_url($raw);
$scheme = strtolower($parts['scheme'] ?? '');
$host   = strtolower($parts['host'] ?? '');

if (!in_array($scheme, ['http', 'https'], true) || $host === '') {
    http_response_code(400);
    exit('Bad url');
}

// Host must be one of the allowed domains, or a subdomain of one.
$allowed = false;
foreach (IMG_PROXY_ALLOWED_HOSTS as $ok) {
    if ($host === $ok || str_ends_with($host, '.' . $ok)) {
        $allowed = true;
        break;
    }
}
if (!$allowed) {
    http_response_code(403);
    exit('Host not allowed');
}

$cacheDir = BASE_PATH . '/storage/cache/img-proxy';
if (!is_dir($cacheDir)) {
    @mkdir($cacheDir, 0775, true);
}
$ext = strtolower(pathinfo((string)($parts['path'] ?? ''), PATHINFO_EXTENSION));
$ext = preg_match('/^(jpe?g|png|gif|webp|svg)$/', $ext) ? $ext : 'img';
$cacheFile = $cacheDir . '/' . sha1($raw) . '.' . $ext;

$mimeFor = static function (string $ext): string {
    return [
        'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png',
        'gif' => 'image/gif', 'webp' => 'image/webp', 'svg' => 'image/svg+xml',
    ][$ext] ?? 'application/octet-stream';
};

/** Serve a cached file and stop. */
$serve = static function (string $file, string $mime): void {
    header('Content-Type: ' . $mime);
    header('Cache-Control: public, max-age=' . IMG_PROXY_TTL);
    header('Content-Length: ' . (string)filesize($file));
    readfile($file);
    exit;
};

if (is_file($cacheFile) && (time() - filemtime($cacheFile)) < IMG_PROXY_TTL) {
    $serve($cacheFile, $mimeFor($ext));
}

// Fetch the remote image.
$data = null;
$contentType = $mimeFor($ext);
if (function_exists('curl_init')) {
    $ch = curl_init($raw);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_MAXREDIRS      => 3,
        CURLOPT_TIMEOUT        => 15,
        CURLOPT_USERAGENT      => 'PCOneStopPromo/1.0',
    ]);
    $body = curl_exec($ch);
    $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $ctype = (string)curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
    curl_close($ch);
    if ($body !== false && $code >= 200 && $code < 300) {
        $data = $body;
        if ($ctype !== '' && str_starts_with($ctype, 'image/')) {
            $contentType = explode(';', $ctype)[0];
        }
    }
} else {
    $body = @file_get_contents($raw);
    if ($body !== false) {
        $data = $body;
    }
}

if ($data === null || $data === '') {
    // Serve a stale cache if we have one, else 502.
    if (is_file($cacheFile)) {
        $serve($cacheFile, $mimeFor($ext));
    }
    http_response_code(502);
    exit('Upstream fetch failed');
}

$tmp = $cacheFile . '.' . getmypid() . '.tmp';
if (@file_put_contents($tmp, $data) !== false) {
    @rename($tmp, $cacheFile);
}

header('Content-Type: ' . $contentType);
header('Cache-Control: public, max-age=' . IMG_PROXY_TTL);
header('Content-Length: ' . (string)strlen($data));
echo $data;
