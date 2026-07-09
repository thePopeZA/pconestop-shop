<?php
/**
 * Syntech product feed importer.
 *
 * Handles XML (full/update), CSV and JSON feeds. Field mapping is
 * alias-based and case-insensitive, so it adapts to minor variations
 * in the feed's tag/column names.
 *
 * Usage (CLI):
 *   php cron/fetch_feed.php full
 *   php cron/fetch_feed.php update
 */

declare(strict_types=1);

class FeedImporter
{
    private PDO $db;
    private int $logId = 0;
    private array $stats = [
        'seen' => 0, 'added' => 0, 'updated' => 0, 'deactivated' => 0,
    ];
    /** SKUs seen this run (for full-import deactivation) */
    private array $seenSkus = [];
    private array $categoryCache = [];

    /* Field alias maps — first match wins (lower-cased keys). */
    private const ALIASES = [
        'sku'         => ['sku', 'code', 'productcode', 'product_code', 'itemcode', 'stockcode', 'partnumber', 'part_number'],
        'name'        => ['name', 'title', 'productname', 'product_name'],
        'description' => ['description', 'longdescription', 'long_description', 'fulldescription', 'body', 'details'],
        'shortdesc'   => ['shortdesc', 'short_desc', 'shortdescription', 'description_short', 'summary'],
        'price'       => ['price', 'dealerprice', 'dealer_price', 'cost', 'costprice', 'cost_price', 'baseprice', 'priceexcl', 'price_excl'],
        'rrp'         => ['rrp_incl', 'rrp', 'recommended_retail', 'retail', 'rrp_excl', 'srp'],
        'promo'       => ['promo_price', 'promoprice', 'sale_price', 'special'],
        'brand'       => ['brand', 'make', 'vendor'],
        'manufacturer'=> ['manufacturer', 'mfr'],
        'category'    => ['categorytree', 'category_tree', 'categories', 'category', 'categorypath', 'category_path', 'cat', 'producttype', 'product_type', 'department'],
        'barcode'     => ['ean', 'barcode', 'gtin', 'upc'],
        'weight'      => ['weight', 'weightkg', 'weight_kg', 'shippingweight'],
        'cptstock'    => ['cptstock', 'cpt_stock', 'ctstock', 'capetown', 'cape_town', 'cptqty', 'stockcpt'],
        'jhbstock'    => ['jhbstock', 'jhb_stock', 'johannesburg', 'jhbqty', 'stockjhb'],
        'dbnstock'    => ['dbnstock', 'dbn_stock', 'durban', 'dbnqty', 'stockdbn'],
        'stock'       => ['stock', 'qty', 'quantity', 'instock', 'stockqty', 'stock_qty', 'availablestock', 'totalstock'],
        'image'       => ['featured_image', 'image', 'imageurl', 'image_url', 'img', 'picture', 'thumbnail', 'mainimage'],
        'images'      => ['all_images', 'images', 'gallery', 'additionalimages', 'additional_images', 'extraimages'],
        'url'         => ['url', 'producturl', 'product_url', 'link', 'permalink'],
        'eta'         => ['nextshipmenteta', 'eta', 'next_shipment', 'shipmenteta'],
    ];

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    /**
     * Run an import.
     *
     * @param string $url        Feed download URL
     * @param string $type       'xml'|'csv'|'json'
     * @param bool   $isFull      Whether this is a FULL feed (enables deactivation of unseen products)
     */
    public function run(string $url, string $type = 'xml', bool $isFull = true): array
    {
        $this->startLog($isFull ? 'full' : 'update');
        try {
            $file = $this->download($url, $type);
            switch (strtolower($type)) {
                case 'csv':  $this->importCsv($file); break;
                case 'json': $this->importJson($file); break;
                default:     $this->importXml($file); break;
            }
            if ($isFull) {
                $this->deactivateUnseen();
            }
            $this->recalcCategoryCounts();
            $this->finishLog('success', sprintf(
                'Seen %d, added %d, updated %d, deactivated %d',
                $this->stats['seen'], $this->stats['added'], $this->stats['updated'], $this->stats['deactivated']
            ));
        } catch (Throwable $e) {
            $this->finishLog('failed', $e->getMessage());
            throw $e;
        }
        return $this->stats;
    }

    /* ---------------- Download ---------------- */

    private function download(string $url, string $type): string
    {
        if ($url === '') {
            throw new RuntimeException('Feed URL is empty. Set SYNTECH_FEED_* in .env');
        }
        $dest = BASE_PATH . '/storage/feeds/syntech_' . date('Ymd_His') . '.' . $type;
        $fp = fopen($dest, 'wb');
        if (!$fp) {
            throw new RuntimeException("Cannot open $dest for writing");
        }
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_FILE           => $fp,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_TIMEOUT        => 600,
            CURLOPT_CONNECTTIMEOUT => 30,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_USERAGENT      => 'PCOneStopShop-FeedBot/1.0',
            CURLOPT_ENCODING       => '', // accept gzip
        ]);
        $ok   = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err  = curl_error($ch);
        curl_close($ch);
        fclose($fp);

        if (!$ok || $code >= 400) {
            @unlink($dest);
            throw new RuntimeException("Feed download failed (HTTP $code) $err");
        }
        if (filesize($dest) === 0) {
            @unlink($dest);
            throw new RuntimeException('Feed download was empty');
        }
        $this->pruneOldFeeds();
        return $dest;
    }

    /** Keep only the 3 most recent downloaded feed files. */
    private function pruneOldFeeds(): void
    {
        $files = glob(BASE_PATH . '/storage/feeds/syntech_*') ?: [];
        if (count($files) <= 3) {
            return;
        }
        usort($files, fn($a, $b) => filemtime($b) <=> filemtime($a));
        foreach (array_slice($files, 3) as $old) {
            @unlink($old);
        }
    }

    /* ---------------- XML ---------------- */

    private function importXml(string $file): void
    {
        $itemTag = $this->detectItemTag($file);
        $reader = new XMLReader();
        if (!$reader->open($file)) {
            throw new RuntimeException("Cannot open XML feed $file");
        }
        // Advance to the first item element.
        while ($reader->read() && $reader->localName !== $itemTag) {
            // skip
        }
        // expand() needs an owner document to attach the node to.
        $doc = new DOMDocument();
        while ($reader->nodeType === XMLReader::ELEMENT && $reader->localName === $itemTag) {
            $node = $reader->expand($doc);
            if ($node instanceof DOMNode) {
                $xml = simplexml_import_dom($node);
                if ($xml instanceof SimpleXMLElement) {
                    $this->processRecord($this->xmlToArray($xml));
                }
            }
            if (!$reader->next($itemTag)) {
                break;
            }
        }
        $reader->close();
    }

    /**
     * Detect the repeating item element name by frequency.
     * The Syntech feed wraps <product> nodes inside <syntechstock><stock>…,
     * so a naive "first child of root" detector would wrongly pick <data>.
     */
    private function detectItemTag(string $file): string
    {
        $candidates = ['product', 'item', 'row', 'entry', 'record'];
        $reader = new XMLReader();
        if (!$reader->open($file)) {
            throw new RuntimeException("Cannot open XML feed $file");
        }
        $counts = array_fill_keys($candidates, 0);
        $scanned = 0;
        while ($reader->read() && $scanned < 5000) {
            if ($reader->nodeType === XMLReader::ELEMENT) {
                $name = strtolower($reader->localName);
                if (isset($counts[$name])) {
                    $counts[$name]++;
                }
                $scanned++;
            }
        }
        $reader->close();
        arsort($counts);
        $best = array_key_first($counts);
        return ($counts[$best] ?? 0) > 0 ? $best : 'product';
    }

    private function xmlToArray(SimpleXMLElement $xml): array
    {
        $out = [];
        foreach ($xml->children() as $child) {
            $key = strtolower($child->getName());
            if ($child->count() > 0) {
                // Distinguish uniform lists (<additional_images><additional_image>…)
                // from mixed containers (<attributes><brand/><colour/>…).
                $names = [];
                foreach ($child->children() as $gc) {
                    $names[strtolower($gc->getName())] = true;
                }
                if (count($names) === 1) {
                    $vals = [];
                    foreach ($child->children() as $gc) {
                        $vals[] = trim((string)$gc);
                    }
                    $out[$key] = $vals;
                } else {
                    // Flatten grandchildren to the top level so brand/colour/model
                    // stored inside <attributes> become mappable fields.
                    foreach ($child->children() as $gc) {
                        $this->addVal($out, strtolower($gc->getName()), trim((string)$gc));
                    }
                }
            } else {
                $this->addVal($out, $key, trim((string)$child));
            }
        }
        foreach ($xml->attributes() as $k => $v) {
            $this->addVal($out, strtolower($k), trim((string)$v));
        }
        return $out;
    }

    /** Add a value to $out[$key], collecting repeats into an array. */
    private function addVal(array &$out, string $key, string $val): void
    {
        if (!array_key_exists($key, $out)) {
            $out[$key] = $val;
            return;
        }
        if (!is_array($out[$key])) {
            $out[$key] = [$out[$key]];
        }
        $out[$key][] = $val;
    }

    /* ---------------- CSV ---------------- */

    private function importCsv(string $file): void
    {
        $fp = fopen($file, 'r');
        if (!$fp) {
            throw new RuntimeException("Cannot open CSV feed $file");
        }
        // Detect delimiter from first line
        $firstLine = fgets($fp);
        rewind($fp);
        $delim = (substr_count($firstLine, ';') > substr_count($firstLine, ',')) ? ';' : ',';

        $header = fgetcsv($fp, 0, $delim);
        if (!$header) {
            fclose($fp);
            throw new RuntimeException('CSV feed has no header row');
        }
        $header = array_map(fn($h) => strtolower(trim((string)$h)), $header);

        while (($row = fgetcsv($fp, 0, $delim)) !== false) {
            if (count($row) === 1 && trim((string)$row[0]) === '') {
                continue;
            }
            $rec = [];
            foreach ($header as $i => $col) {
                $rec[$col] = isset($row[$i]) ? trim((string)$row[$i]) : '';
            }
            $this->processRecord($rec);
        }
        fclose($fp);
    }

    /* ---------------- JSON ---------------- */

    private function importJson(string $file): void
    {
        $data = json_decode(file_get_contents($file) ?: '', true);
        if (!is_array($data)) {
            throw new RuntimeException('JSON feed could not be decoded');
        }
        // find the list of products
        $list = $data;
        if (isset($data['products']) && is_array($data['products'])) {
            $list = $data['products'];
        } elseif (isset($data['data']) && is_array($data['data'])) {
            $list = $data['data'];
        }
        foreach ($list as $rec) {
            if (is_array($rec)) {
                $lc = [];
                foreach ($rec as $k => $v) {
                    $lc[strtolower((string)$k)] = $v;
                }
                $this->processRecord($lc);
            }
        }
    }

    /* ---------------- Field mapping ---------------- */

    /** Pick a single scalar value for $field (collapses arrays to first non-empty). */
    private function pick(array $rec, string $field, $default = null)
    {
        foreach (self::ALIASES[$field] ?? [] as $alias) {
            if (!array_key_exists($alias, $rec)) {
                continue;
            }
            $v = $rec[$alias];
            if (is_array($v)) {
                foreach ($v as $vv) {
                    if (trim((string)$vv) !== '') {
                        return $vv;
                    }
                }
                continue;
            }
            if ($v !== '' && $v !== null) {
                return $v;
            }
        }
        return $default;
    }

    /** Gather ALL values for $field across aliases, flattened into a list. */
    private function pickAll(array $rec, string $field): array
    {
        $out = [];
        foreach (self::ALIASES[$field] ?? [] as $alias) {
            if (!array_key_exists($alias, $rec)) {
                continue;
            }
            $v = $rec[$alias];
            if (is_array($v)) {
                foreach ($v as $vv) {
                    $out[] = (string)$vv;
                }
            } elseif ($v !== '' && $v !== null) {
                $out[] = (string)$v;
            }
        }
        return $out;
    }

    /* ---------------- Record processing ---------------- */

    private function processRecord(array $rec): void
    {
        $sku = trim((string)$this->pick($rec, 'sku', ''));
        if ($sku === '') {
            return; // cannot import without a SKU
        }
        $this->stats['seen']++;
        $this->seenSkus[$sku] = true;

        $name  = trim((string)$this->pick($rec, 'name', $sku));
        $cost  = $this->parsePrice((string)$this->pick($rec, 'price', '0'));
        $sell  = calc_sell_price($cost);
        $rrp   = $this->parsePrice((string)$this->pick($rec, 'rrp', '0'));
        // Brand may appear multiple times (e.g. "Port" and "Port Designs") — keep the fullest
        // brand value, but never let the manufacturer's legal name override a real brand.
        $brand = '';
        foreach ($this->pickAll($rec, 'brand') as $b) {
            $b = trim($b);
            if (mb_strlen($b) > mb_strlen($brand)) {
                $brand = $b;
            }
        }
        if ($brand === '') {
            $brand = trim((string)$this->pick($rec, 'manufacturer', ''));
        }
        $desc  = (string)$this->pick($rec, 'description', '');
        $short = trim(strip_tags((string)$this->pick($rec, 'shortdesc', '')));
        if (mb_strlen($short) > 480) {
            $short = mb_substr($short, 0, 477) . '…';
        }
        $barcode = trim((string)$this->pick($rec, 'barcode', ''));
        $weight  = (float)$this->parsePrice((string)$this->pick($rec, 'weight', '0'));
        $productUrl = trim((string)$this->pick($rec, 'url', ''));
        $eta = trim((string)$this->pick($rec, 'eta', ''));

        // Stock: CPT + JHB + DBN warehouses; fall back to a generic stock field.
        $cpt = (int)$this->parsePrice((string)$this->pick($rec, 'cptstock', '0'));
        $jhb = (int)$this->parsePrice((string)$this->pick($rec, 'jhbstock', '0'));
        $dbn = (int)$this->parsePrice((string)$this->pick($rec, 'dbnstock', '0'));
        $qty = $cpt + $jhb + $dbn;
        $generic = $this->pick($rec, 'stock', null);
        if ($qty === 0 && $generic !== null) {
            $qty = (int)$this->parsePrice((string)$generic);
        }
        $wh = [];
        if ($cpt > 0) $wh[] = "CPT:$cpt";
        if ($jhb > 0) $wh[] = "JHB:$jhb";
        if ($dbn > 0) $wh[] = "DBN:$dbn";
        $warehouse = implode(' ', $wh);
        $stockStatus = $this->stockStatus($qty);

        // Category — Syntech's categorytree looks like "A > B|C > D" where '|'
        // separates multiple category memberships. Use the first as the primary.
        $catRaw = (string)$this->pick($rec, 'category', '');
        if (str_contains($catRaw, '|')) {
            $catRaw = explode('|', $catRaw)[0];
        }
        $catPath = trim($catRaw);
        $categoryId = $catPath !== '' ? $this->resolveCategory($catPath) : null;

        // Images
        [$image, $gallery] = $this->extractImages($rec);

        $slug = slugify($name) . '-' . strtolower($sku);

        $existing = $this->db->prepare('SELECT id FROM products WHERE sku = ? LIMIT 1');
        $existing->execute([$sku]);
        $id = $existing->fetchColumn();

        if ($id) {
            $stmt = $this->db->prepare(
                'UPDATE products SET name=?, slug=?, brand=?, category_id=?, category_path=?,
                 description=?, short_desc=?, cost_price=?, price=?, rrp=?, stock_qty=?, stock_status=?,
                 warehouse=?, supplier_eta=?, image_url=COALESCE(NULLIF(?, ""), image_url),
                 image_gallery=?, product_url=?, barcode=?, weight_kg=?,
                 active=1, source="syntech", last_feed_seen=NOW()
                 WHERE id=?'
            );
            $stmt->execute([
                $name, $slug, $brand ?: null, $categoryId, $catPath ?: null,
                $desc, $short ?: null, $cost, $sell, $rrp ?: null, $qty, $stockStatus,
                $warehouse ?: null, $eta ?: null, $image,
                $gallery ? json_encode($gallery) : null, $productUrl ?: null, $barcode ?: null, $weight ?: null,
                $id,
            ]);
            $this->stats['updated']++;
        } else {
            $stmt = $this->db->prepare(
                'INSERT INTO products
                 (sku, name, slug, brand, category_id, category_path, description, short_desc,
                  cost_price, price, rrp, stock_qty, stock_status, warehouse, supplier_eta, image_url,
                  image_gallery, product_url, barcode, weight_kg, active, source, last_feed_seen)
                 VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,1,"syntech",NOW())'
            );
            $stmt->execute([
                $sku, $name, $slug, $brand ?: null, $categoryId, $catPath ?: null, $desc, $short ?: null,
                $cost, $sell, $rrp ?: null, $qty, $stockStatus, $warehouse ?: null, $eta ?: null, $image,
                $gallery ? json_encode($gallery) : null, $productUrl ?: null, $barcode ?: null, $weight ?: null,
            ]);
            $this->stats['added']++;
        }
    }

    private function extractImages(array $rec): array
    {
        $images = [];
        // featured first so it becomes the main image
        foreach ($this->pickAll($rec, 'image') as $v) {
            $images[] = $v;
        }
        // all_images / additional_images — may be pipe-separated strings
        foreach ($this->pickAll($rec, 'images') as $v) {
            if (str_contains($v, '|')) {
                foreach (explode('|', $v) as $part) {
                    $images[] = $part;
                }
            } else {
                $images[] = $v;
            }
        }
        // normalise, validate, dedupe (preserve order)
        $clean = [];
        foreach ($images as $u) {
            $u = trim($u);
            if ($u !== '' && filter_var($u, FILTER_VALIDATE_URL) && !in_array($u, $clean, true)) {
                $clean[] = $u;
            }
        }
        $main = $clean[0] ?? '';
        $gallery = array_slice($clean, 1);
        return [$main, $gallery];
    }

    private function parsePrice(string $v): float
    {
        // strip currency symbols/spaces, handle comma decimals
        $v = trim($v);
        $v = preg_replace('/[^0-9,.\-]/', '', $v) ?? '0';
        // if both , and . present, assume , is thousands
        if (str_contains($v, ',') && str_contains($v, '.')) {
            $v = str_replace(',', '', $v);
        } elseif (str_contains($v, ',')) {
            $v = str_replace(',', '.', $v);
        }
        return (float)$v;
    }

    private function stockStatus(int $qty): string
    {
        if ($qty <= 0)  return 'out_of_stock';
        if ($qty <= 3)  return 'low_stock';
        return 'in_stock';
    }

    /* ---------------- Categories ---------------- */

    private function resolveCategory(string $path): ?int
    {
        // Support hierarchical paths "A > B > C" — return leaf id, create ancestors.
        $parts = preg_split('/\s*(>|\/|\|)\s*/', $path) ?: [$path];
        $parts = array_values(array_filter(array_map('trim', $parts)));
        if (!$parts) {
            return null;
        }
        $parentId = null;
        $leafId = null;
        $accSlug = '';
        foreach ($parts as $part) {
            $accSlug = trim($accSlug . '-' . slugify($part), '-');
            $cacheKey = $accSlug;
            if (isset($this->categoryCache[$cacheKey])) {
                $leafId = $this->categoryCache[$cacheKey];
                $parentId = $leafId;
                continue;
            }
            $sel = $this->db->prepare('SELECT id FROM categories WHERE slug = ? LIMIT 1');
            $sel->execute([$accSlug]);
            $cid = $sel->fetchColumn();
            if (!$cid) {
                $ins = $this->db->prepare('INSERT INTO categories (name, slug, parent_id) VALUES (?,?,?)');
                $ins->execute([$part, $accSlug, $parentId]);
                $cid = (int)$this->db->lastInsertId();
            }
            $this->categoryCache[$cacheKey] = (int)$cid;
            $leafId = (int)$cid;
            $parentId = (int)$cid;
        }
        return $leafId;
    }

    private function recalcCategoryCounts(): void
    {
        // Direct counts first.
        $direct = [];
        foreach ($this->db->query(
            'SELECT category_id, COUNT(*) c FROM products
             WHERE active = 1 AND category_id IS NOT NULL GROUP BY category_id'
        ) as $row) {
            $direct[(int)$row['category_id']] = (int)$row['c'];
        }
        // Build parent map.
        $parent = [];
        foreach ($this->db->query('SELECT id, parent_id FROM categories') as $row) {
            $parent[(int)$row['id']] = $row['parent_id'] !== null ? (int)$row['parent_id'] : null;
        }
        // Roll each category's direct count up through its ancestors.
        $total = [];
        foreach ($parent as $id => $_) {
            $total[$id] = 0;
        }
        foreach ($direct as $catId => $count) {
            $cur = $catId;
            $guard = 0;
            while ($cur !== null && isset($total[$cur]) && $guard++ < 20) {
                $total[$cur] += $count;
                $cur = $parent[$cur] ?? null;
            }
        }
        $upd = $this->db->prepare('UPDATE categories SET product_count = ? WHERE id = ?');
        foreach ($total as $id => $count) {
            $upd->execute([$count, $id]);
        }
    }

    private function deactivateUnseen(): void
    {
        // Deactivate active syntech products not seen in this full run.
        $stmt = $this->db->query(
            "SELECT id FROM products WHERE active = 1 AND source = 'syntech'
             AND (last_feed_seen IS NULL OR last_feed_seen < (NOW() - INTERVAL 1 MINUTE))"
        );
        // Safer approach: mark inactive any active product whose SKU wasn't seen.
        $all = $this->db->query("SELECT id, sku FROM products WHERE active = 1 AND source='syntech'");
        $toDeactivate = [];
        foreach ($all as $row) {
            if (!isset($this->seenSkus[$row['sku']])) {
                $toDeactivate[] = (int)$row['id'];
            }
        }
        if ($toDeactivate) {
            $in = implode(',', array_fill(0, count($toDeactivate), '?'));
            $upd = $this->db->prepare("UPDATE products SET active = 0 WHERE id IN ($in)");
            $upd->execute($toDeactivate);
            $this->stats['deactivated'] = count($toDeactivate);
        }
    }

    /* ---------------- Logging ---------------- */

    private function startLog(string $type): void
    {
        $stmt = $this->db->prepare("INSERT INTO feed_log (feed_type, status) VALUES (?, 'running')");
        $stmt->execute([$type]);
        $this->logId = (int)$this->db->lastInsertId();
    }

    private function finishLog(string $status, string $message): void
    {
        if (!$this->logId) {
            return;
        }
        $stmt = $this->db->prepare(
            'UPDATE feed_log SET finished_at=NOW(), status=?, message=?,
             products_seen=?, products_added=?, products_updated=?, products_deactivated=?
             WHERE id=?'
        );
        $stmt->execute([
            $status, $message,
            $this->stats['seen'], $this->stats['added'], $this->stats['updated'], $this->stats['deactivated'],
            $this->logId,
        ]);
    }
}
