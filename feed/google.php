<?php
/**
 * Google Merchant Center product feed (RSS 2.0 + g: namespace).
 *
 *   https://shop.pconestop.co.za/feed/google.php
 *
 * Lists every active, in-stock product that has an image and a price, in the
 * format Google Merchant Center ingests for free Shopping listings (and, if the
 * owner ever turns them on, Shopping ads). Prices are the live selling price
 * incl VAT — so Syntech promo pricing flows through automatically and always
 * matches the product page (Google rejects feed/landing-page price mismatches).
 *
 * The whole catalogue is large (~1,900 products with long descriptions), so the
 * feed is STREAMED: rows are read unbuffered from the DB and written straight to
 * the client (and to a temp cache file) one item at a time, keeping memory flat.
 * The temp file is atomically renamed to the cache only after the closing tag,
 * so a killed run can never leave a truncated cache. Served from cache for a few
 * minutes; the data refreshes with every Syntech import.
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/config/config.php';
require_once BASE_PATH . '/includes/shop.php';

const GOOGLE_FEED_TTL = 1800; // 30 min

header('Content-Type: application/xml; charset=utf-8');
header('X-Robots-Tag: noindex'); // the feed itself shouldn't be indexed

$cacheFile = BASE_PATH . '/storage/cache/google-feed.xml';

// Serve a fresh cache only if it is COMPLETE (ends with </rss>) — guards against
// ever serving a partially written file.
if (is_file($cacheFile) && (time() - filemtime($cacheFile)) < GOOGLE_FEED_TTL) {
    $tail = '';
    if ($fh = @fopen($cacheFile, 'rb')) {
        if (filesize($cacheFile) > 16) { fseek($fh, -16, SEEK_END); }
        $tail = (string)fread($fh, 16);
        fclose($fh);
    }
    if (strpos($tail, '</rss>') !== false) {
        readfile($cacheFile);
        exit;
    }
}

// Generation can take a while and must finish even if the client goes away (so
// the cache still gets built for the next fetch).
@set_time_limit(0);
ignore_user_abort(true);
while (ob_get_level() > 0) { ob_end_flush(); }

/** XML-escape a value for text/attribute content. */
function gx(string $s): string
{
    return htmlspecialchars($s, ENT_XML1 | ENT_QUOTES, 'UTF-8');
}

/** A GTIN is valid for Google only as 8/12/13/14 digits. */
function gtin_ok(string $barcode): bool
{
    return ctype_digit($barcode) && in_array(strlen($barcode), [8, 12, 13, 14], true);
}

$base = rtrim(APP_URL, '/');
$ship = number_format(SHIPPING_FEE_INCL, 2, '.', '');

// Stream to a temp file as we stream to the client; atomically swap in at the end.
$tmp = $cacheFile . '.' . getmypid() . '.tmp';
$out = @fopen($tmp, 'wb');
register_shutdown_function(function () use (&$out, $tmp) {
    if (is_resource($out)) { fclose($out); }
    if (is_file($tmp)) { @unlink($tmp); } // only survives if rename below didn't run
});
$emit = function (string $s) use ($out) {
    echo $s;
    if (is_resource($out)) { fwrite($out, $s); }
};

$emit('<?xml version="1.0" encoding="UTF-8"?>' . "\n");
$emit('<rss version="2.0" xmlns:g="http://base.google.com/ns/1.0">' . "\n<channel>\n");
$emit('  <title>' . gx(APP_NAME) . "</title>\n");
$emit('  <link>' . gx($base . '/') . "</link>\n");
$emit("  <description>Product feed for Google Merchant Center</description>\n");

$pdo = db();
// Unbuffered so we don't load all ~1,900 rows (with long descriptions) into
// memory at once; LEFT() caps the MEDIUMTEXT transfer per row.
$pdo->setAttribute(PDO::MYSQL_ATTR_USE_BUFFERED_QUERY, false);
$stmt = $pdo->query(
    "SELECT sku, name, slug, brand, category_path, short_desc,
            LEFT(description, 5000) AS description,
            price, image_url, barcode
     FROM products
     WHERE active = 1 AND stock_qty > 0 AND price > 0
       AND image_url IS NOT NULL AND image_url <> ''
     ORDER BY id"
);

$n = 0;
while ($p = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $image = (string)$p['image_url'];
    if (!preg_match('#^https?://#i', $image)) {
        continue; // Google needs an absolute raster URL
    }

    $title = trim((string)$p['name']);
    if (mb_strlen($title) > 150) {
        $title = mb_substr($title, 0, 149) . '…';
    }

    $desc = trim((string)$p['short_desc']);
    if ($desc === '') {
        $desc = trim(preg_replace('/\s+/', ' ', strip_tags(html_entity_decode((string)$p['description'], ENT_QUOTES | ENT_HTML5, 'UTF-8'))) ?? '');
    }
    if ($desc === '') {
        $desc = $title; // Google requires a non-empty description
    }
    if (mb_strlen($desc) > 4900) {
        $desc = mb_substr($desc, 0, 4900);
    }

    $link    = $base . '/product.php?slug=' . rawurlencode((string)$p['slug']);
    $price   = number_format((float)$p['price'], 2, '.', '') . ' ZAR';
    $brand   = trim((string)($p['brand'] ?? ''));
    $barcode = trim((string)($p['barcode'] ?? ''));
    $hasGtin = gtin_ok($barcode);

    $item  = "  <item>\n";
    $item .= '    <g:id>' . gx((string)$p['sku']) . "</g:id>\n";
    $item .= '    <title>' . gx($title) . "</title>\n";
    $item .= '    <description>' . gx($desc) . "</description>\n";
    $item .= '    <link>' . gx($link) . "</link>\n";
    $item .= '    <g:image_link>' . gx($image) . "</g:image_link>\n";
    $item .= "    <g:availability>in_stock</g:availability>\n";
    $item .= "    <g:condition>new</g:condition>\n";
    $item .= '    <g:price>' . gx($price) . "</g:price>\n";
    if ($brand !== '') {
        $item .= '    <g:brand>' . gx($brand) . "</g:brand>\n";
    }
    if ($p['category_path']) {
        $item .= '    <g:product_type>' . gx((string)$p['category_path']) . "</g:product_type>\n";
    }
    if ($hasGtin) {
        $item .= '    <g:gtin>' . gx($barcode) . "</g:gtin>\n";
    }
    if ($hasGtin || $brand !== '') {
        $item .= '    <g:mpn>' . gx((string)$p['sku']) . "</g:mpn>\n";
    } else {
        $item .= "    <g:identifier_exists>no</g:identifier_exists>\n";
    }
    $item .= "    <g:shipping>\n      <g:country>ZA</g:country>\n      <g:service>Courier</g:service>\n"
           . '      <g:price>' . $ship . " ZAR</g:price>\n    </g:shipping>\n";
    $item .= "  </item>\n";

    $emit($item);
    if ((++$n % 200) === 0) { flush(); }
}
$stmt->closeCursor();

$emit("</channel>\n</rss>\n");

// Atomically publish the cache. Clearing $out first stops the shutdown handler
// from deleting the file we just renamed into place.
if (is_resource($out)) {
    fclose($out);
    $out = null;
    @rename($tmp, $cacheFile);
}
