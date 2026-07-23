<?php
/**
 * Secret promo gallery. Lists the published PNGs in img/ with copy/share/save
 * and a "posted" memory in localStorage. Not linked from anywhere; noindex.
 *
 * Captions are FROZEN at publish time: they are read ONLY from captions.json
 * (filename => caption), which is committed alongside the PNGs by the refresh
 * step. There is ZERO live DB dependency here — a deal expiring or drifting
 * never changes a published pack. If captions.json is missing, the gallery
 * shows the empty state rather than broken cards.
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/config/config.php'; // for e(); never touches the DB here

// Load the frozen caption snapshot: { "pcos-status-01-<slug>.png": "caption" }.
$captions = [];
$capFile = __DIR__ . '/captions.json';
if (is_file($capFile)) {
    $decoded = json_decode((string)file_get_contents($capFile), true);
    if (is_array($decoded)) {
        $captions = $decoded;
    }
}

// Cards = each captioned PNG that actually exists on disk, in filename order.
$cards = [];
if ($captions) {
    $names = array_keys($captions);
    sort($names, SORT_NATURAL | SORT_FLAG_CASE);
    foreach ($names as $file) {
        $file = (string)$file;
        if (!preg_match('/\.png$/i', $file)) {
            continue;
        }
        if (!is_file(__DIR__ . '/img/' . basename($file))) {
            continue;
        }
        $cards[] = ['file' => basename($file), 'caption' => (string)$captions[$file]];
    }
}

$total = count($cards);
// Signature of the current pack (its exact set of images). When it changes,
// the gallery treats it as a NEW pack and clears the posted ticks, so stable
// top products don't carry a "posted ✓" over from last week's pack.
$packSig = md5(implode('|', array_column($cards, 'file')));
foreach ($cards as $i => &$c) {
    $c['num']   = str_pad((string)($i + 1), 2, '0', STR_PAD_LEFT);
    $c['total'] = str_pad((string)$total, 2, '0', STR_PAD_LEFT);
}
unset($c);
?><!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex,nofollow">
<title>PCOS Promo Gallery</title>
<style>
    :root { --navy:#0B1F33; --blue:#0E63D8; --green:#12A15E; --amber:#E8850C; --panel:#12283f; --line:#22496f; }
    * { box-sizing: border-box; }
    body { margin:0; background:var(--navy); color:#eaf1fb; font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,sans-serif; -webkit-text-size-adjust:100%; }
    header { padding:20px 16px 8px; text-align:center; }
    header h1 { margin:0; font-size:1.15rem; letter-spacing:.02em; }
    header p { margin:6px 0 0; font-size:.82rem; color:#93a7c2; }
    .wrap { max-width:520px; margin:0 auto; padding:12px 12px 60px; }
    .card { background:var(--panel); border:1px solid var(--line); border-radius:16px; padding:14px; margin:16px 0; }
    .card.done { opacity:.5; }
    .badge { display:inline-block; font-family:ui-monospace,SFMono-Regular,Menlo,Consolas,monospace; font-size:.8rem; color:#93a7c2; letter-spacing:.1em; margin-bottom:10px; }
    .shot { width:100%; border-radius:12px; display:block; background:#000; }
    .cap { margin:12px 0; padding:12px; border:1px dashed var(--blue); border-radius:10px;
           white-space:pre-wrap; word-break:break-word; font-size:.9rem; line-height:1.4; color:#dbe7f6;
           -webkit-user-select:all; user-select:all; cursor:text; }
    .row { display:flex; gap:8px; }
    button { flex:1; border:0; border-radius:10px; padding:12px 8px; font-size:.92rem; font-weight:600;
             color:#fff; cursor:pointer; font-family:inherit; }
    .b-copy { background:var(--blue); }
    .b-share { background:var(--green); }
    .b-save { background:#243b57; }
    .done-tick { margin-top:10px; text-align:center; font-size:.85rem; color:var(--green); min-height:1.1em; }
    .empty { text-align:center; color:#93a7c2; padding:48px 16px; }
    .toolbar { text-align:center; margin:4px 0 0; }
    .toolbar button { display:inline-block; flex:none; width:auto; background:#243b57; font-weight:500; padding:8px 14px; font-size:.8rem; }
</style>
</head>
<body>
<header>
    <h1>🔥 PCOS Promo Gallery</h1>
    <p><?= $total ?> card<?= $total === 1 ? '' : 's' ?> ready to post · tap a caption to select it</p>
    <div class="toolbar"><button type="button" id="reset-done">Clear posted marks</button></div>
</header>
<div class="wrap">
<?php if (!$cards): ?>
    <div class="empty">No promo images published yet.<br>Run the status maker, then <code>refresh-promo</code>.</div>
<?php else: ?>
    <?php foreach ($cards as $c): ?>
    <div class="card" data-file="<?= e($c['file']) ?>">
        <span class="badge"><?= e($c['num']) ?> / <?= e($c['total']) ?></span>
        <img class="shot" src="img/<?= e(rawurlencode($c['file'])) ?>" alt="Promo <?= e($c['num']) ?>" loading="lazy">
        <div class="cap"><?= e($c['caption']) ?></div>
        <div class="row">
            <button type="button" class="b-copy">⧉ Copy</button>
            <button type="button" class="b-share">📲 Share</button>
            <button type="button" class="b-save">⬇ Save</button>
        </div>
        <div class="done-tick"></div>
    </div>
    <?php endforeach; ?>
<?php endif; ?>
</div>
<script>
(function () {
    'use strict';
    var LSKEY = 'pcos-promo-posted';
    var LSKEY_SIG = 'pcos-promo-pack';
    var PACK_SIG = <?= json_encode($packSig) ?>;

    // New pack? (its set of images changed) -> start with a clean slate so
    // ticks are remembered per image within a pack, never inherited across packs.
    try {
        if (localStorage.getItem(LSKEY_SIG) !== PACK_SIG) {
            localStorage.removeItem(LSKEY);
            localStorage.setItem(LSKEY_SIG, PACK_SIG);
        }
    } catch (e) {}

    function posted() { try { return JSON.parse(localStorage.getItem(LSKEY) || '{}'); } catch (e) { return {}; } }
    function savePosted(o) { try { localStorage.setItem(LSKEY, JSON.stringify(o)); } catch (e) {} }

    function markDone(card, on) {
        var file = card.getAttribute('data-file');
        var tick = card.querySelector('.done-tick');
        var store = posted();
        if (on) { store[file] = 1; card.classList.add('done'); tick.textContent = 'posted ✓'; }
        else { delete store[file]; card.classList.remove('done'); tick.textContent = ''; }
        savePosted(store);
    }

    // Restore posted state
    var store = posted();
    document.querySelectorAll('.card').forEach(function (card) {
        if (store[card.getAttribute('data-file')]) markDone(card, true);
    });

    document.getElementById('reset-done') && document.getElementById('reset-done').addEventListener('click', function () {
        savePosted({});
        document.querySelectorAll('.card').forEach(function (c) { markDone(c, false); });
    });

    function caption(card) { return card.querySelector('.cap').textContent; }
    function imgUrl(card) { return card.querySelector('.shot').getAttribute('src'); }

    document.querySelectorAll('.card').forEach(function (card) {
        var cap = card.querySelector('.cap');

        // Tap caption selects all of it
        cap.addEventListener('click', function () {
            var r = document.createRange();
            r.selectNodeContents(cap);
            var sel = window.getSelection();
            sel.removeAllRanges();
            sel.addRange(r);
        });

        card.querySelector('.b-copy').addEventListener('click', function () {
            var text = caption(card);
            var done = function () { markDone(card, true); flash(this, '✓ Copied'); }.bind(this);
            if (navigator.clipboard && navigator.clipboard.writeText) {
                navigator.clipboard.writeText(text).then(done, function () { legacyCopy(text); done(); });
            } else { legacyCopy(text); done(); }
        });

        card.querySelector('.b-save').addEventListener('click', function () {
            var a = document.createElement('a');
            a.href = imgUrl(card);
            a.download = card.getAttribute('data-file');
            document.body.appendChild(a); a.click(); a.remove();
            markDone(card, true);
        });

        card.querySelector('.b-share').addEventListener('click', function () {
            var btn = this;
            var text = caption(card);
            var url = imgUrl(card);
            var fname = card.getAttribute('data-file');
            fetch(url).then(function (r) { return r.blob(); }).then(function (blob) {
                var file = new File([blob], fname, { type: blob.type || 'image/png' });
                if (navigator.canShare && navigator.canShare({ files: [file] })) {
                    return navigator.share({ files: [file], text: text }).then(function () { markDone(card, true); });
                }
                throw new Error('no file share');
            }).catch(function () {
                // Fallback: copy caption, tell the user, open the image for long-press save.
                if (navigator.clipboard && navigator.clipboard.writeText) navigator.clipboard.writeText(text);
                alert('Caption copied. Opening the image — long-press to save, then paste the caption in WhatsApp.');
                window.open(url, '_blank');
                markDone(card, true);
            });
        });
    });

    function legacyCopy(text) {
        var t = document.createElement('textarea');
        t.value = text; document.body.appendChild(t); t.select();
        try { document.execCommand('copy'); } catch (e) {}
        t.remove();
    }
    function flash(btn, msg) {
        var old = btn.textContent; btn.textContent = msg;
        setTimeout(function () { btn.textContent = old; }, 1200);
    }
})();
</script>
</body>
</html>
