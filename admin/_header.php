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
// Profit split, commission & user management are partner-only (build owner).
if (is_partner()) {
    $nav['profit'] = ['profit.php', '💰 Profit split'];
    $nav['users']  = ['users.php', '👤 Admin users'];
}
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
            <li class="sep">Shop</li>
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
