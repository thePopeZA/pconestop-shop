    </div><!-- /.container -->
</main>
<footer class="site-footer">
    <div class="container">
        <div class="footer-cols">
            <div>
                <a class="logo" href="<?= e(url('/')) ?>" style="margin-bottom:14px;display:inline-block">
                    <img src="<?= e(asset('img/logo.png')) ?>" alt="PC One Stop" style="height:40px;width:auto;filter:brightness(0) invert(1)">
                </a>
                <p style="max-width:320px">Your Trusted IT Solutions. PC hardware, components, peripherals and tech — sourced fresh and shipped across South Africa.</p>
            </div>
            <div>
                <h4>Shop</h4>
                <ul>
                    <li><a href="<?= e(url('shop.php')) ?>">All Products</a></li>
                    <?php foreach (nav_categories(5) as $c): ?>
                        <li><a href="<?= e(category_url($c)) ?>"><?= e($c['name']) ?></a></li>
                    <?php endforeach; ?>
                </ul>
            </div>
            <div>
                <h4>Help</h4>
                <ul>
                    <li><a href="<?= e(url('track.php')) ?>">Track Order</a></li>
                    <li><a href="https://pconestop.co.za/contact-us/">Contact Us</a></li>
                    <li><a href="<?= e(url('shipping.php')) ?>">Shipping</a></li>
                    <li><a href="<?= e(url('returns.php')) ?>">Returns</a></li>
                </ul>
            </div>
            <div>
                <h4>Pay securely</h4>
                <p style="font-size:.82rem">We accept all major cards via <strong style="color:#fff">Yoco</strong>. Payments are encrypted and secure.</p>
            </div>
        </div>
        <div class="footer-bottom">
            <span>&copy; <?= date('Y') ?> PC One Stop. All rights reserved.</span>
            <span>Prices incl. VAT · E&amp;OE</span>
        </div>
    </div>
</footer>
<script src="<?= e(asset('js/main.js')) ?>"></script>
</body>
</html>
