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
 * Output is cached to storage/cache for a few minutes so repeated fetches don't
 * hammer the DB; the feed data itself refreshes with every Syntech import.
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/config/config.php';
require_once BASE_PATH . '/includes/shop.php';

const GOOGLE_FEED_TTL = 1800; // 30 min

header('Content-Type: application/xml; charset=utf-8');
header('X-Robots-Tag: noindex'); // the feed itself shouldn't be indexed

$cacheFile = BASE_PATH . '/storage/cache/google-feed.xml';
if (is_file($cacheFile) && (time() - filemtime($cacheFile)) < GOOGLE_FEED_TTL) {
    readfile($cacheFile);
    exit;
}

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

$rows = db()->query(
    "SELECT sku, name, slug, brand, category_path, short_desc, description,
            price, stock_qty, image_url, barcode
     FROM products
     WHERE active = 1 AND stock_qty > 0 AND price > 0
       AND image_url IS NOT NULL AND image_url <> ''
     ORDER BY id"
)->fetchAll(PDO::FETCH_ASSOC);

$ship = number_format(SHIPPING_FEE_INCL, 2, '.', '');

ob_start();
echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
echo '<rss version="2.0" xmlns:g="http://base.google.com/ns/1.0">' . "\n";
echo '<channel>' . "\n";
echo '  <title>' . gx(APP_NAME) . '</title>' . "\n";
echo '  <link>' . gx($base . '/') . '</link>' . "\n";
echo '  <description>Product feed for Google Merchant Center</description>' . "\n";

foreach ($rows as $p) {
    // Image must be an absolute raster URL Google can fetch; skip anything else.
    $image = (string)$p['image_url'];
    if (!preg_match('#^https?://#i', $image)) {
        continue;
    }

    $title = trim((string)$p['name']);
    if (mb_strlen($title) > 150) {
        $title = mb_substr($title, 0, 149) . '…';
    }

    // Description: prefer the short blurb, else strip the HTML description.
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

    $link  = $base . '/product.php?slug=' . rawurlencode((string)$p['slug']);
    $price = number_format((float)$p['price'], 2, '.', '') . ' ZAR';
    $brand = trim((string)($p['brand'] ?? ''));
    $barcode = trim((string)($p['barcode'] ?? ''));
    $hasGtin = gtin_ok($barcode);

    echo "  <item>\n";
    echo '    <g:id>' . gx((string)$p['sku']) . "</g:id>\n";
    echo '    <title>' . gx($title) . "</title>\n";
    echo '    <description>' . gx($desc) . "</description>\n";
    echo '    <link>' . gx($link) . "</link>\n";
    echo '    <g:image_link>' . gx($image) . "</g:image_link>\n";
    echo "    <g:availability>in_stock</g:availability>\n";
    echo "    <g:condition>new</g:condition>\n";
    echo '    <g:price>' . gx($price) . "</g:price>\n";
    if ($brand !== '') {
        echo '    <g:brand>' . gx($brand) . "</g:brand>\n";
    }
    if ($p['category_path']) {
        echo '    <g:product_type>' . gx((string)$p['category_path']) . "</g:product_type>\n";
    }
    // Identifiers: prefer a real GTIN; always give an MPN (our SKU). If we have
    // neither a GTIN nor a brand, tell Google no unique identifier exists.
    if ($hasGtin) {
        echo '    <g:gtin>' . gx($barcode) . "</g:gtin>\n";
    }
    if ($hasGtin || $brand !== '') {
        echo '    <g:mpn>' . gx((string)$p['sku']) . "</g:mpn>\n";
    } else {
        echo "    <g:identifier_exists>no</g:identifier_exists>\n";
    }
    echo "    <g:shipping>\n";
    echo "      <g:country>ZA</g:country>\n";
    echo "      <g:service>Courier</g:service>\n";
    echo '      <g:price>' . $ship . " ZAR</g:price>\n";
    echo "    </g:shipping>\n";
    echo "  </item>\n";
}

echo "</channel>\n</rss>\n";
$xml = (string)ob_get_clean();

// Best-effort cache write (never fatal if storage isn't writable).
@file_put_contents($cacheFile, $xml, LOCK_EX);

echo $xml;
