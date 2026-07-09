<?php
require_once __DIR__ . '/config/config.php';
http_response_code(404);
$pageTitle = 'Page not found';
include BASE_PATH . '/includes/header.php';
?>
<div class="empty" style="max-width:560px;margin:0 auto">
    <h1 style="font-size:3rem;margin:0">404</h1>
    <h3>Page not found</h3>
    <p class="muted">The page you're looking for doesn't exist or has moved.</p>
    <a class="btn btn-lg" href="<?= e(url('/')) ?>">Back to home</a>
</div>
<?php include BASE_PATH . '/includes/footer.php'; ?>
