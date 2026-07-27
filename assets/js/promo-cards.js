/* Shared promo-card renderer — single source used by the picker (pick-*) and
   the legacy status maker. Requires html2canvas to be loaded first for
   PromoCards.capture(). Cards are built from promo feed item objects
   (name, brand, category, price_now, price_was, save_amount, save_pct, stock,
   image, type). */
window.PromoCards = (function () {
    'use strict';

    function zar(n) {
        var s = (Math.round(n * 100) / 100).toFixed(2).split('.');
        s[0] = s[0].replace(/\B(?=(\d{3})+(?!\d))/g, ' ');
        return 'R' + s.join('.');
    }
    function zarWhole(n) {
        return 'R' + String(Math.round(n)).replace(/\B(?=(\d{3})+(?!\d))/g, ' ');
    }
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

    /** Filename for a card at a given 0-based index: pcos-status-01-<slug>.png */
    function filenameFor(index, item) {
        return 'pcos-status-' + String(index + 1).padStart(2, '0') + '-' + item.slug + '.png';
    }

    /** Render a card element to a 1080x1920 canvas via an offscreen clone in `stage`. */
    function capture(card, stage) {
        var clone = card.cloneNode(true);
        stage.innerHTML = '';
        stage.appendChild(clone);
        return (document.fonts && document.fonts.ready ? document.fonts.ready : Promise.resolve())
            .then(function () {
                return html2canvas(clone, {
                    width: 1080, height: 1920, windowWidth: 1080, windowHeight: 1920,
                    scale: 1, backgroundColor: null, useCORS: true, logging: false
                });
            })
            .then(function (canvas) { stage.innerHTML = ''; return canvas; });
    }

    function applyRobot(show) {
        document.querySelectorAll('.pc-robot').forEach(function (r) { r.style.display = show ? '' : 'none'; });
    }

    return {
        zar: zar, zarWhole: zarWhole, proxy: proxy, esc: esc,
        buildCard: buildCard, filenameFor: filenameFor, capture: capture, applyRobot: applyRobot
    };
})();
