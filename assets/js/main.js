/* PC One Stop Shop — front-end interactions */
(function () {
    'use strict';

    // Product gallery thumbnail switching
    document.querySelectorAll('.gallery').forEach(function (g) {
        var main = g.querySelector('.main-img img');
        g.querySelectorAll('.thumbs img').forEach(function (t) {
            t.addEventListener('click', function () {
                if (!main) return;
                main.src = t.dataset.full || t.src;
                g.querySelectorAll('.thumbs img').forEach(function (x) { x.classList.remove('active'); });
                t.classList.add('active');
            });
        });
    });

    // Quantity steppers
    document.querySelectorAll('.qty').forEach(function (q) {
        var input = q.querySelector('input');
        var dec = q.querySelector('[data-step="-1"]');
        var inc = q.querySelector('[data-step="1"]');
        function clamp() {
            var v = parseInt(input.value, 10);
            if (isNaN(v) || v < 1) v = 1;
            var max = parseInt(input.max, 10);
            if (!isNaN(max) && max > 0 && v > max) v = max;
            input.value = v;
        }
        if (dec) dec.addEventListener('click', function () { input.value = (parseInt(input.value, 10) || 1) - 1; clamp(); });
        if (inc) inc.addEventListener('click', function () { input.value = (parseInt(input.value, 10) || 0) + 1; clamp(); });
        if (input) input.addEventListener('change', clamp);
    });

    // Auto-submit sort dropdown
    var sortSel = document.getElementById('sort-select');
    if (sortSel) sortSel.addEventListener('change', function () { sortSel.form.submit(); });

    // Collapsible categories (mobile): tap the "Categories" heading to toggle.
    var catToggle = document.querySelector('.cat-toggle');
    if (catToggle) {
        var sidebar = catToggle.closest('.sidebar');
        function toggleCats() {
            var open = sidebar.classList.toggle('cats-open');
            catToggle.setAttribute('aria-expanded', open ? 'true' : 'false');
        }
        catToggle.addEventListener('click', toggleCats);
        catToggle.addEventListener('keydown', function (e) {
            if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); toggleCats(); }
        });
    }
})();
