<?php
/**
 * Secret daily promo picker. Lists ALL qualifying deals (no price floor / no
 * min-saving — the human curates), lets the person pick exactly 10, then renders
 * those cards in-browser (shared PromoCards) and publishes them to the gallery
 * via publish.php (atomic, token-authed). Not linked anywhere; noindex.
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/config/config.php';
require_once BASE_PATH . '/includes/promo.php';

$deals = promo_all_deals();
$token = promo_make_token();
$galleryUrl = PROMO_PUBLIC_BASE . '/promo-82f02098/';

$cats = [];
foreach ($deals as $d) { $cats[$d['category']] = true; }
$cats = array_keys($cats);
sort($cats, SORT_NATURAL | SORT_FLAG_CASE);
?><!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex,nofollow">
<title>PCOS Deal Picker</title>
<link href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@500;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="/assets/css/promo-card.css">
<style>
    :root { --navy:#0B1F33; --blue:#0E63D8; --green:#12A15E; --amber:#E8850C; --panel:#12283f; --line:#22496f; }
    * { box-sizing:border-box; }
    body { margin:0; background:var(--navy); color:#eaf1fb; font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,sans-serif; -webkit-text-size-adjust:100%; padding-bottom:96px; }
    header { padding:16px 14px 8px; }
    header h1 { margin:0; font-size:1.1rem; }
    header p { margin:4px 0 0; font-size:.82rem; color:#93a7c2; }
    .controls { position:sticky; top:0; z-index:5; background:var(--navy); padding:10px 14px; border-bottom:1px solid var(--line); }
    .controls input[type=search] { width:100%; padding:11px 12px; border-radius:10px; border:1px solid var(--line); background:var(--panel); color:#eaf1fb; font-size:1rem; }
    .sorts { display:flex; gap:8px; margin-top:8px; }
    .sorts button { flex:1; padding:8px; border-radius:8px; border:1px solid var(--line); background:var(--panel); color:#cfe; font-size:.82rem; font-weight:600; cursor:pointer; }
    .sorts button.on { background:var(--blue); color:#fff; border-color:var(--blue); }
    .chips { display:flex; gap:7px; overflow-x:auto; padding:9px 14px 2px; -webkit-overflow-scrolling:touch; }
    .chips button { white-space:nowrap; padding:6px 12px; border-radius:999px; border:1px solid var(--line); background:var(--panel); color:#cfe; font-size:.78rem; cursor:pointer; }
    .chips button.on { background:var(--green); color:#fff; border-color:var(--green); }
    .promo-toggle { width:100%; margin-top:8px; padding:9px; border-radius:8px; border:1px solid #d97a2a; background:var(--panel); color:#f2b47a; font-size:.85rem; font-weight:700; cursor:pointer; }
    .promo-toggle.on { background:#e07b28; color:#fff; border-color:#e07b28; }
    .row .tag-promo { display:inline-block; background:#e07b28; color:#fff; font-size:.66rem; font-weight:700; padding:1px 6px; border-radius:5px; margin-left:6px; vertical-align:middle; }
    .list { padding:8px 12px; }
    .row { display:flex; gap:12px; align-items:center; background:var(--panel); border:2px solid transparent; border-radius:12px; padding:10px; margin-bottom:10px; cursor:pointer; }
    .row.sel { border-color:var(--amber); }
    .list.maxed .row:not(.sel) { opacity:.45; }
    .row img { width:56px; height:56px; object-fit:contain; background:#fff; border-radius:8px; flex:none; padding:3px; }
    .row .info { flex:1; min-width:0; }
    .row .brand { font-size:.68rem; text-transform:uppercase; letter-spacing:.05em; color:#7f9bbd; }
    .row .name { font-size:.9rem; font-weight:600; line-height:1.25; display:-webkit-box; -webkit-line-clamp:2; -webkit-box-orient:vertical; overflow:hidden; }
    .row .meta { font-size:.78rem; color:#9fb6d4; margin-top:3px; }
    .row .meta .save { color:var(--green); font-weight:700; }
    .row .meta .low { color:var(--amber); }
    .row .tick { flex:none; width:26px; height:26px; border-radius:50%; border:2px solid var(--line); display:grid; place-items:center; font-size:.9rem; color:#fff; }
    .row.sel .tick { background:var(--amber); border-color:var(--amber); }
    .bar { position:fixed; left:0; right:0; bottom:0; background:#0a1826; border-top:1px solid var(--line); padding:12px 14px; display:flex; align-items:center; gap:12px; }
    .bar .count { font-weight:700; }
    .bar .count small { color:#93a7c2; font-weight:400; }
    .bar button { margin-left:auto; padding:13px 22px; border:0; border-radius:10px; background:var(--green); color:#fff; font-weight:700; font-size:1rem; cursor:pointer; }
    .bar button:disabled { opacity:.4; cursor:default; }
    .overlay { position:fixed; inset:0; background:rgba(6,15,25,.94); z-index:20; display:none; flex-direction:column; align-items:center; justify-content:center; text-align:center; padding:24px; }
    .overlay.show { display:flex; }
    .overlay .spin { width:46px; height:46px; border:4px solid var(--line); border-top-color:var(--blue); border-radius:50%; animation:spin 1s linear infinite; margin-bottom:16px; }
    @keyframes spin { to { transform:rotate(360deg); } }
    .overlay .msg { font-size:1.05rem; }
    .overlay .err { color:#ff8f8f; }
    .overlay .retry { margin-top:16px; padding:11px 20px; border:0; border-radius:10px; background:var(--blue); color:#fff; font-weight:600; cursor:pointer; display:none; }
    #stage { position:fixed; left:-99999px; top:0; }
    .empty { text-align:center; color:#93a7c2; padding:40px 16px; }
</style>
</head>
<body>
<header>
    <h1>🛒 Pick today's deals</h1>
    <p>Choose exactly <strong>10</strong> products, then tap Generate. They publish to the gallery automatically.</p>
</header>
<div class="controls">
    <input type="search" id="search" placeholder="Search product or brand…" autocomplete="off">
    <div class="sorts">
        <button data-sort="save" class="on">Biggest saving</button>
        <button data-sort="pct">Biggest %</button>
        <button data-sort="price">Price</button>
    </div>
    <button type="button" id="promoToggle" class="promo-toggle">🏷️ On promo only <span id="promoCount"></span></button>
</div>
<div class="chips" id="chips"></div>
<div class="list" id="list"></div>

<div class="bar">
    <div class="count"><span id="count">0</span> <small>/ 10 selected</small></div>
    <button id="generate" disabled>Generate pack</button>
</div>

<div class="overlay" id="overlay">
    <div class="spin" id="spin"></div>
    <div class="msg" id="ov-msg">Working…</div>
    <button class="retry" id="ov-retry">Close</button>
</div>
<div id="stage"></div>

<script src="/assets/js/lib/html2canvas.min.js"></script>
<script src="/assets/js/promo-cards.js"></script>
<script>
(function () {
    'use strict';
    var PC = window.PromoCards;
    var DEALS = <?= json_encode($deals, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?>;
    var CATS = <?= json_encode($cats, JSON_UNESCAPED_UNICODE) ?>;
    var TOKEN = <?= json_encode($token) ?>;
    var GALLERY_URL = <?= json_encode($galleryUrl) ?>;
    var MAX = 10;

    var listEl = document.getElementById('list');
    var chipsEl = document.getElementById('chips');
    var searchEl = document.getElementById('search');
    var countEl = document.getElementById('count');
    var genBtn = document.getElementById('generate');
    var stage = document.getElementById('stage');

    var bySlug = {}; DEALS.forEach(function (d) { bySlug[d.slug] = d; });
    var selected = [];            // ordered slugs
    var sort = 'save', cat = '', q = '', promoOnly = false;

    // Show how many live Syntech promos are in the pool on the toggle.
    var promoToggle = document.getElementById('promoToggle');
    var promoTotal = DEALS.filter(function (d) { return d.on_promo; }).length;
    document.getElementById('promoCount').textContent = '(' + promoTotal + ')';

    function zar(n) { return PC.zar(n); }
    function zarWhole(n) { return PC.zarWhole(n); }
    function esc(s) { return PC.esc(s); }

    // Category chips
    chipsEl.innerHTML = '<button data-cat="" class="on">All</button>' +
        CATS.map(function (c) { return '<button data-cat="' + esc(c) + '">' + esc(c) + '</button>'; }).join('');

    function filtered() {
        var out = DEALS.slice();
        if (promoOnly) out = out.filter(function (d) { return d.on_promo; });
        if (cat) out = out.filter(function (d) { return d.category === cat; });
        if (q) {
            var t = q.toLowerCase();
            out = out.filter(function (d) { return (d.name + ' ' + d.brand).toLowerCase().indexOf(t) !== -1; });
        }
        out.sort(function (a, b) {
            if (sort === 'pct') return b.save_pct - a.save_pct;
            if (sort === 'price') return a.price_now - b.price_now;
            return b.save_amount - a.save_amount;
        });
        return out;
    }

    function render() {
        var rows = filtered();
        if (!rows.length) { listEl.innerHTML = '<div class="empty">No deals match.</div>'; return; }
        listEl.innerHTML = rows.map(function (d) {
            var isSel = selected.indexOf(d.slug) !== -1;
            return '<div class="row' + (isSel ? ' sel' : '') + '" data-slug="' + esc(d.slug) + '">' +
                '<img loading="lazy" src="' + esc(d.image) + '" alt="">' +
                '<div class="info">' +
                    '<div class="brand">' + esc(d.brand || '') + ' · ' + esc(d.category) + '</div>' +
                    '<div class="name">' + esc(d.name) + (d.on_promo ? '<span class="tag-promo">🏷️ PROMO</span>' : '') + '</div>' +
                    '<div class="meta"><span class="save">save ' + zarWhole(d.save_amount) + '</span> · ' +
                        d.save_pct + '% · now ' + zar(d.price_now) + ' <span style="text-decoration:line-through">RRP ' + zar(d.price_was) + '</span> · ' +
                        '<span class="' + (d.stock === 'Low stock' ? 'low' : '') + '">' + esc(d.stock) + '</span></div>' +
                '</div>' +
                '<div class="tick">' + (isSel ? '✓' : '') + '</div>' +
            '</div>';
        }).join('');
    }

    function updateBar() {
        countEl.textContent = selected.length;
        genBtn.disabled = selected.length !== MAX;
        listEl.classList.toggle('maxed', selected.length >= MAX);
    }

    // Toggle in place — never re-render the whole list on select (keeps scroll).
    listEl.addEventListener('click', function (e) {
        var row = e.target.closest('.row'); if (!row) return;
        var slug = row.getAttribute('data-slug');
        var i = selected.indexOf(slug);
        if (i !== -1) {
            selected.splice(i, 1);
            row.classList.remove('sel');
            row.querySelector('.tick').textContent = '';
        } else {
            if (selected.length >= MAX) return;
            selected.push(slug);
            row.classList.add('sel');
            row.querySelector('.tick').textContent = '✓';
        }
        updateBar();
    });

    chipsEl.addEventListener('click', function (e) {
        var b = e.target.closest('button'); if (!b) return;
        cat = b.getAttribute('data-cat');
        [].forEach.call(chipsEl.children, function (c) { c.classList.toggle('on', c === b); });
        render();
    });
    document.querySelector('.sorts').addEventListener('click', function (e) {
        var b = e.target.closest('button'); if (!b) return;
        sort = b.getAttribute('data-sort');
        [].forEach.call(this.children, function (c) { c.classList.toggle('on', c === b); });
        render();
    });
    searchEl.addEventListener('input', function () { q = searchEl.value.trim(); render(); });
    promoToggle.addEventListener('click', function () {
        promoOnly = !promoOnly;
        promoToggle.classList.toggle('on', promoOnly);
        render();
    });

    // ---- Generate & publish ----
    var overlay = document.getElementById('overlay');
    var ovMsg = document.getElementById('ov-msg');
    var ovSpin = document.getElementById('spin');
    var ovRetry = document.getElementById('ov-retry');

    function showOverlay(msg, isErr) {
        overlay.classList.add('show');
        ovMsg.innerHTML = msg; ovMsg.className = 'msg' + (isErr ? ' err' : '');
        ovSpin.style.display = isErr ? 'none' : '';
        ovRetry.style.display = isErr ? '' : 'none';
    }
    ovRetry.addEventListener('click', function () { overlay.classList.remove('show'); genBtn.disabled = false; });

    function postForm(fields, file) {
        var fd = new FormData();
        fd.append('token', TOKEN);
        Object.keys(fields).forEach(function (k) { fd.append(k, fields[k]); });
        if (file) fd.append('image', file.blob, file.name);
        return fetch('publish.php', { method: 'POST', body: fd }).then(function (r) { return r.json(); });
    }
    function toBlob(canvas) { return new Promise(function (r) { canvas.toBlob(r, 'image/png'); }); }

    function generate() {
        if (selected.length !== MAX) return;
        genBtn.disabled = true;
        var items = selected.map(function (s) { return bySlug[s]; });
        var captions = {};
        showOverlay('Preparing pack…');

        postForm({ action: 'begin' }).then(function (res) {
            if (!res.ok) throw new Error(res.error || 'begin failed');
            var i = 0;
            function nextImg() {
                if (i >= items.length) return postForm({ action: 'finish', captions: JSON.stringify(captions) });
                showOverlay('Rendering card ' + (i + 1) + ' / ' + items.length + '…');
                var item = items[i];
                var name = PC.filenameFor(i, item);
                captions[name] = item.caption || '';
                var card = PC.buildCard(item);
                return PC.capture(card, stage).then(toBlob).then(function (blob) {
                    if (!blob) throw new Error('render failed for card ' + (i + 1));
                    return postForm({ action: 'image', name: name }, { blob: blob, name: name });
                }).then(function (res) {
                    if (!res.ok) throw new Error(res.error || ('upload failed: card ' + (i + 1)));
                    i++; return nextImg();
                });
            }
            return nextImg();
        }).then(function (res) {
            if (!res || !res.ok) throw new Error((res && res.error) || 'publish failed');
            showOverlay('✅ Published ' + res.count + ' cards — opening the gallery…');
            setTimeout(function () { window.location.href = GALLERY_URL; }, 1200);
        }).catch(function (err) {
            showOverlay('Publish failed — nothing was changed live.<br><br>' + esc(err.message), true);
        });
    }
    genBtn.addEventListener('click', generate);

    render(); updateBar();
})();
</script>
</body>
</html>
