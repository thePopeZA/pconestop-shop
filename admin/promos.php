<?php
/**
 * Admin promo studio (owner + partner). Renders the 10 auto-selected promo
 * cards from live data, then PUBLISHES them straight to the live gallery
 * (server-side, no git/deploy) and offers a QR + email notify. This is the
 * owner's one-stop, browser-only workflow.
 *
 * NOTE: the 1080x1920 card markup/CSS mirrors tools/status-maker/ — if the card
 * design changes, update both.
 */

require_once __DIR__ . '/_bootstrap.php';
require_promo_access();

$galleryUrl = url('promo-82f02098/');
$notifyUrl  = url('promo-82f02098/notify.php?key=2ce5b7f83a48a976');

$adminTitle = 'Promos';
$adminNav = 'promos';
include __DIR__ . '/_header.php';
?>
<link href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@500;700&display=swap" rel="stylesheet">
<style>
    .promo-toolbar { display:flex; gap:12px; align-items:center; flex-wrap:wrap; margin-bottom:16px; }
    .promo-toolbar .switch { display:flex; align-items:center; gap:7px; color:var(--ink-soft,#667); cursor:pointer; }
    .promo-toolbar .status { color:var(--ink-soft,#667); font-size:.85rem; margin-left:auto; }
    .promo-grid { display:grid; grid-template-columns:repeat(auto-fill, 172px); gap:18px; }
    .promo-grid .cell { width:172px; }
    .promo-grid .frame { width:172px; height:306px; border-radius:9px; overflow:hidden; background:#000; box-shadow:0 4px 14px rgba(0,0,0,.25); }
    .promo-grid .frame .promo-card { transform:scale(.159259); transform-origin:top left; }
    .promo-grid .lbl { font-size:.72rem; color:var(--ink-soft,#667); margin-top:6px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
    #promo-stage { position:fixed; left:-99999px; top:0; }
    #published-panel #qr { display:inline-block; padding:12px; background:#fff; border-radius:10px; }
    #notify-result { white-space:pre-wrap; background:#0d1b2a; color:#cfe; padding:10px 12px; border-radius:8px; font-size:.82rem; margin-top:10px; display:none; }

    /* ---------- 1080x1920 CARD (literal colours; mirrors the status maker) ---------- */
    .promo-card {
        width:1080px; height:1920px; position:relative; overflow:hidden;
        background:
            radial-gradient(1100px 700px at 88% -6%, rgba(14,99,216,.42), rgba(14,99,216,0) 60%),
            repeating-linear-gradient(0deg, rgba(255,255,255,.045) 0 1px, rgba(0,0,0,0) 1px 96px),
            repeating-linear-gradient(90deg, rgba(255,255,255,.045) 0 1px, rgba(0,0,0,0) 1px 96px),
            #0B1F33;
        color:#eaf2ff;
        font-family:"Space Grotesk",-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,sans-serif;
        display:flex; flex-direction:column; padding:64px;
    }
    .pc-head { display:flex; align-items:center; justify-content:space-between; }
    .pc-logo-pill { background:#fff; border-radius:999px; padding:16px 26px; display:flex; align-items:center; }
    .pc-logo-pill img { height:56px; width:auto; display:block; }
    .pc-tag { font-family:ui-monospace,"SFMono-Regular",Menlo,Consolas,monospace; font-size:30px; font-weight:700;
              letter-spacing:.06em; text-transform:uppercase; padding:16px 26px; border-radius:999px; background:rgba(11,31,51,.4); }
    .pc-tag.deal { color:#E8850C; border:3px solid #E8850C; }
    .pc-tag.arrival { color:#5b9bff; border:3px solid #0E63D8; }
    .pc-panel { margin-top:40px; height:730px; background:#fff; border-radius:36px; position:relative;
                display:flex; align-items:center; justify-content:center; padding:44px; }
    .pc-panel img.photo { max-width:100%; max-height:100%; object-fit:contain; }
    .pc-burst { position:absolute; top:-46px; right:-30px; width:250px; height:250px; border-radius:50%;
                background:#E8850C; color:#fff; display:flex; flex-direction:column; align-items:center; justify-content:center;
                transform:rotate(8deg); box-shadow:0 14px 30px rgba(0,0,0,.28); text-align:center; }
    .pc-burst .sml { font-size:34px; font-weight:700; letter-spacing:.16em; }
    .pc-burst .big { font-size:60px; font-weight:700; line-height:.92; letter-spacing:-.02em; margin-top:2px; }
    .pc-brand { margin-top:44px; font-family:ui-monospace,"SFMono-Regular",Menlo,Consolas,monospace;
                font-size:30px; letter-spacing:.16em; text-transform:uppercase; color:#7f9bbd; min-height:30px; }
    .pc-name { margin-top:14px; font-size:64px; font-weight:700; line-height:1.06; letter-spacing:-.01em;
               display:-webkit-box; -webkit-line-clamp:3; -webkit-box-orient:vertical; overflow:hidden; }
    .pc-price { margin-top:36px; display:flex; align-items:flex-end; gap:26px; flex-wrap:wrap; }
    .pc-now { font-size:124px; font-weight:700; line-height:.9; letter-spacing:-.02em; }
    .pc-was { font-size:52px; font-weight:500; color:#7f9bbd; text-decoration:line-through; padding-bottom:14px; }
    .pc-save { margin-top:26px; display:inline-block; background:#12A15E; color:#fff; font-weight:700;
               font-size:34px; padding:14px 30px; border-radius:999px; }
    .pc-stock { margin-top:32px; font-family:ui-monospace,"SFMono-Regular",Menlo,Consolas,monospace;
                font-size:36px; font-weight:700; letter-spacing:.04em; }
    .pc-stock.in { color:#12A15E; }
    .pc-stock.low { color:#E8850C; }
    .pc-spacer { flex:1; }
    .pc-robot { text-align:center; }
    .pc-robot img { height:150px; width:auto; }
    .pc-foot { margin-top:22px; display:flex; flex-direction:column; align-items:center; gap:14px; }
    .pc-site { background:#fff; color:#0B1F33; font-weight:700; font-size:38px; padding:16px 34px; border-radius:999px; }
    .pc-foot-sub { font-family:ui-monospace,"SFMono-Regular",Menlo,Consolas,monospace; font-size:27px; color:#9fb6d4; }
    .pc-fine { font-family:ui-monospace,"SFMono-Regular",Menlo,Consolas,monospace; font-size:22px; color:#5f7a9c; letter-spacing:.04em; }
</style>

<div class="panel">
    <h2 style="margin-top:0">This week's promo pack</h2>
    <p class="muted" style="margin-top:0;font-size:.9rem">10 cards auto-picked from live deals (biggest savings, spread across categories). Click <strong>Publish</strong> to push them to the gallery — no downloads, no git. Then share the QR or email the team.</p>
    <div class="promo-toolbar">
        <button class="btn" id="publish-btn" disabled>📣 Publish to gallery</button>
        <button class="btn btn-ghost" id="reload-btn">↻ Reload</button>
        <label class="switch"><input type="checkbox" id="robot-toggle" checked> Robot mascot</label>
        <span class="status" id="status">Loading…</span>
    </div>
    <div class="promo-grid" id="grid"></div>
    <div id="promo-stage"></div>
</div>

<div class="panel" id="published-panel" style="display:none;border-left:4px solid var(--green,#12A15E)">
    <h2 style="margin-top:0">✅ Published to the gallery</h2>
    <p>Staff open this to post — scan the code with a phone camera, or tap the link:</p>
    <p><a id="gallery-link" href="<?= e($galleryUrl) ?>" target="_blank" style="font-size:1.05rem"><?= e($galleryUrl) ?></a></p>
    <div id="qr"></div>
    <div style="margin-top:16px">
        <button class="btn" id="notify-btn">✉ Email the team the link</button>
        <div id="notify-result"></div>
    </div>
</div>

<script src="<?= e(asset('js/lib/html2canvas.min.js')) ?>"></script>
<script src="<?= e(asset('js/lib/qrcode.min.js')) ?>"></script>
<script>
(function () {
    'use strict';
    var CSRF = <?= json_encode(csrf_token()) ?>;
    var GALLERY_URL = <?= json_encode($galleryUrl) ?>;
    var NOTIFY_URL = <?= json_encode($notifyUrl) ?>;
    var CARD_COUNT = 10;

    var grid = document.getElementById('grid');
    var stage = document.getElementById('promo-stage');
    var statusEl = document.getElementById('status');
    var publishBtn = document.getElementById('publish-btn');
    var robotToggle = document.getElementById('robot-toggle');
    var cards = [];

    function setStatus(t) { statusEl.textContent = t; }
    function zar(n) { var s = (Math.round(n * 100) / 100).toFixed(2).split('.'); s[0] = s[0].replace(/\B(?=(\d{3})+(?!\d))/g, ' '); return 'R' + s.join('.'); }
    function zarWhole(n) { return 'R' + String(Math.round(n)).replace(/\B(?=(\d{3})+(?!\d))/g, ' '); }
    function proxy(url) { return '/api/img-proxy.php?url=' + encodeURIComponent(url); }
    function esc(s) { var d = document.createElement('div'); d.textContent = s == null ? '' : s; return d.innerHTML; }

    function buildCard(item) {
        var isDeal = item.type === 'deal';
        var nowStr = zar(item.price_now);
        var nowSize = nowStr.length <= 9 ? 124 : (nowStr.length <= 12 ? 100 : 82);
        var cat = (item.category || '').toUpperCase();
        if (cat.length > 15) cat = cat.slice(0, 14).replace(/[\s&]+$/, '') + '…';
        var tagText = (isDeal ? '🔥 HOT DEAL' : '⚡ JUST LANDED') + (cat ? ' · ' + cat : '');

        var card = document.createElement('div');
        card.className = 'promo-card';
        card.innerHTML =
            '<div class="pc-head">' +
                '<div class="pc-logo-pill"><img src="/assets/img/logo.png" alt="PC One Stop"></div>' +
                '<div class="pc-tag ' + (isDeal ? 'deal' : 'arrival') + '">' + esc(tagText) + '</div>' +
            '</div>' +
            '<div class="pc-panel">' +
                '<img class="photo" crossorigin="anonymous" src="' + proxy(item.image) + '" alt="">' +
                (isDeal ? '<div class="pc-burst"><span class="sml">SAVE</span><span class="big" style="font-size:' +
                    (zarWhole(item.save_amount).length > 7 ? 52 : 64) + 'px">' + esc(zarWhole(item.save_amount)) + '</span></div>' : '') +
            '</div>' +
            '<div class="pc-brand">' + esc(item.brand || '') + '</div>' +
            '<div class="pc-name">' + esc(item.name) + '</div>' +
            '<div class="pc-price">' +
                '<span class="pc-now" style="font-size:' + nowSize + 'px">' + esc(nowStr) + '</span>' +
                (item.price_was ? '<span class="pc-was">RRP ' + esc(zar(item.price_was)) + '</span>' : '') +
            '</div>' +
            (isDeal ? '<div><span class="pc-save">' + item.save_pct + '% below RRP</span></div>' : '') +
            '<div class="pc-stock ' + (item.stock === 'Low stock' ? 'low' : 'in') + '">' +
                (item.stock === 'Low stock' ? '● LOW STOCK — BE QUICK' : '● IN STOCK') + '</div>' +
            '<div class="pc-spacer"></div>' +
            '<div class="pc-robot"><img src="/assets/img/robot.png" alt=""></div>' +
            '<div class="pc-foot">' +
                '<div class="pc-site">shop.pconestop.co.za</div>' +
                '<div class="pc-foot-sub">🚚 nationwide courier · yoco secure checkout</div>' +
                '<div class="pc-fine">Prices incl. VAT · E&amp;OE · while stocks last</div>' +
            '</div>';
        return card;
    }

    function filenameFor(index, item) { return 'pcos-status-' + String(index + 1).padStart(2, '0') + '-' + item.slug + '.png'; }

    function applyRobot() {
        var show = robotToggle.checked;
        document.querySelectorAll('.pc-robot').forEach(function (r) { r.style.display = show ? '' : 'none'; });
    }

    function capture(card) {
        var clone = card.cloneNode(true);
        stage.innerHTML = '';
        stage.appendChild(clone);
        return (document.fonts && document.fonts.ready ? document.fonts.ready : Promise.resolve())
            .then(function () {
                return html2canvas(clone, { width: 1080, height: 1920, windowWidth: 1080, windowHeight: 1920, scale: 1, backgroundColor: null, useCORS: true, logging: false });
            })
            .then(function (canvas) { stage.innerHTML = ''; return canvas; });
    }
    function toBlob(canvas) { return new Promise(function (r) { canvas.toBlob(r, 'image/png'); }); }

    function render(list) {
        grid.innerHTML = '';
        cards = [];
        list.forEach(function (item, index) {
            var card = buildCard(item);
            var name = filenameFor(index, item);
            var cell = document.createElement('div'); cell.className = 'cell';
            var frame = document.createElement('div'); frame.className = 'frame';
            frame.appendChild(card);
            var lbl = document.createElement('div'); lbl.className = 'lbl';
            lbl.textContent = String(index + 1).padStart(2, '0') + '  ' + (item.type === 'deal' ? 'save ' + zarWhole(item.save_amount) : 'new') + ' · ' + item.name;
            cell.appendChild(frame); cell.appendChild(lbl);
            grid.appendChild(cell);
            cards.push({ card: card, name: name, caption: item.caption || '' });
        });
        applyRobot();
        publishBtn.disabled = list.length === 0;
        setStatus(list.length + ' cards ready.');
    }

    function load() {
        setStatus('Loading deals…'); publishBtn.disabled = true;
        fetch('/api/deals.json.php', { cache: 'no-store' })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                var list = (data.cards || []).slice(0, CARD_COUNT);
                if (!list.length) { setStatus('No deals found.'); grid.innerHTML = '<p class="muted" style="padding:20px">Nothing to show.</p>'; return; }
                render(list);
            })
            .catch(function (e) { setStatus('Failed to load deals — ' + e.message); });
    }

    function post(fields) {
        var fd = new FormData();
        fd.append('csrf', CSRF);
        Object.keys(fields).forEach(function (k) { fd.append(k, fields[k]); });
        return fetch('promo-publish.php', { method: 'POST', body: fd }).then(function (r) { return r.json(); });
    }

    function publish() {
        publishBtn.disabled = true;
        setStatus('Publishing… clearing old pack');
        var captions = {};
        cards.forEach(function (e) { captions[e.name] = e.caption; });

        post({ action: 'begin' }).then(function (res) {
            if (!res.ok) throw new Error(res.error || 'begin failed');
            var i = 0;
            function nextImg() {
                if (i >= cards.length) {
                    return post({ action: 'finish', captions: JSON.stringify(captions) });
                }
                setStatus('Publishing… card ' + (i + 1) + ' / ' + cards.length);
                var e = cards[i];
                return capture(e.card).then(toBlob).then(function (blob) {
                    var fd = new FormData();
                    fd.append('csrf', CSRF); fd.append('action', 'image');
                    fd.append('name', e.name); fd.append('image', blob, e.name);
                    return fetch('promo-publish.php', { method: 'POST', body: fd }).then(function (r) { return r.json(); });
                }).then(function (res) {
                    if (!res.ok) throw new Error(res.error || ('image ' + e.name + ' failed'));
                    i++; return nextImg();
                });
            }
            return nextImg();
        }).then(function (res) {
            if (!res || !res.ok) throw new Error((res && res.error) || 'finish failed');
            setStatus('Published ' + res.count + ' cards ✓');
            showPublished();
        }).catch(function (err) {
            setStatus('Publish failed: ' + err.message);
            publishBtn.disabled = false;
        });
    }

    function showPublished() {
        var panel = document.getElementById('published-panel');
        panel.style.display = '';
        var qr = document.getElementById('qr');
        qr.innerHTML = '';
        new QRCode(qr, { text: GALLERY_URL, width: 200, height: 200, correctLevel: QRCode.CorrectLevel.M });
        panel.scrollIntoView({ behavior: 'smooth' });
        publishBtn.disabled = false;
    }

    document.getElementById('notify-btn').addEventListener('click', function () {
        var btn = this, out = document.getElementById('notify-result');
        btn.disabled = true; out.style.display = 'block'; out.textContent = 'Sending…';
        fetch(NOTIFY_URL, { cache: 'no-store' }).then(function (r) { return r.text(); })
            .then(function (t) { out.textContent = t; btn.disabled = false; })
            .catch(function (e) { out.textContent = 'Failed: ' + e.message; btn.disabled = false; });
    });

    robotToggle.addEventListener('change', applyRobot);
    publishBtn.addEventListener('click', publish);
    document.getElementById('reload-btn').addEventListener('click', load);
    load();
})();
</script>
<?php include __DIR__ . '/_footer.php'; ?>
