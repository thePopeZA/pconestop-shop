<?php
/** Admin layout header. Set $adminTitle and $adminNav before including. */
require_admin();
$adminTitle = $adminTitle ?? 'Admin';
$adminNav = $adminNav ?? '';
$nav = [
    'dashboard' => ['index.php', '📊 Dashboard'],
    'products'  => ['products.php', '📦 Products'],
    'orders'    => ['orders.php', '🧾 Orders'],
    'feeds'     => ['feeds.php', '🔄 Feed sync'],
    'settings'  => ['settings.php', '⚙️ Settings'],
];
// Promo studio — owner + partner (not staff).
if (can_publish_promos()) {
    $nav['promos'] = ['promos.php', '📣 Promos'];
}
// Profit split & commission are partner-only (build owner).
if (is_partner()) {
    $nav['profit'] = ['profit.php', '💰 Profit split'];
}
// User management is visible to all — each role manages only what it may.
$nav['users'] = ['users.php', '👤 Admin users'];
?><!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($adminTitle) ?> — PCOS Admin</title>
<link rel="stylesheet" href="<?= e(asset('css/admin.css')) ?>">
<link rel="icon" href="<?= e(asset('img/favicon.png')) ?>" type="image/png">
</head>
<body>
<div class="admin-shell">
    <aside class="admin-side">
        <div class="brand"><span class="mark">PC</span> <span>PCOS Admin</span></div>
        <ul class="admin-nav">
            <?php foreach ($nav as $key => [$href, $label]): ?>
                <li><a href="<?= e(admin_url($href)) ?>" class="<?= $adminNav === $key ? 'active' : '' ?>"><?= $label ?></a></li>
            <?php endforeach; ?>
            <li class="sep">Account</li>
            <li><a href="<?= e(admin_url('change_password.php')) ?>" class="<?= $adminNav === 'change_password' ? 'active' : '' ?>">🔑 Change password</a></li>
            <li><a href="<?= e(url('/')) ?>" target="_blank">🌐 View store</a></li>
            <li><a href="<?= e(admin_url('logout.php')) ?>">🚪 Log out</a></li>
        </ul>
    </aside>
    <div class="admin-main">
        <div class="admin-top">
            <h1 style="font-size:1.2rem;margin:0"><?= e($adminTitle) ?></h1>
            <span class="who">Signed in as <strong><?= e(admin_user()['username']) ?></strong></span>
        </div>
        <div class="admin-content">
            <?php foreach (get_flashes() as $f): ?>
                <div class="flash flash-<?= e($f['type']) ?>"><?= e($f['msg']) ?></div>
            <?php endforeach; ?>
